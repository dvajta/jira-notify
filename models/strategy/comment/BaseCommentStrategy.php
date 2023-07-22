<?php

namespace app\modules\jira\notify\models\strategy\comment;

use app\components\jira\objects\models\Update;
use app\components\telegram\exceptions\ApiException;
use app\jobs\telegram\bots\notify\message\MessageUpdateCommentFromJira;
use app\jobs\telegram\bots\notify\message\messageUpdateCommentFromJira\StateMessageUpdateCommentFromJira;
use app\models\User;
use app\modules\jira\notify\models\strategy\BaseStrategy;
use app\modules\jira\notify\models\strategy\interfaces\BaseCommentStrategyInterface;
use Yii;
use yii\base\UserException;
use yii\di\Instance;
use yii\helpers\Json;
use yii\helpers\StringHelper;
use yii\queue\Queue;

class BaseCommentStrategy extends BaseStrategy implements BaseCommentStrategyInterface
{
    /** @var string Шаблон username в комментарии markup-разметки Jira */
    private const JIRA_NICK_TEMPLATE = '!\[~([\S]*)\]!m';

    /** @var int Допустимое количество символов в комментарии */
    private const MAX_COMMENT_LENGTH = 3550;

    /**
     * @param Update $update
     * @throws \yii\base\UserException
     */
    public function __construct(Update $update)
    {
        parent::__construct($update);
        $this->author = $this->getAuthor($update->comment->author->name);
        $this->commentText = $update->comment->body;
        $this->commentId = $update->comment->id;
        $this->authorDisplayName = $update->comment->author->displayName;
        $this->commentUpdated = $update->comment->updated;
    }

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function process(): void
    {
        $chats = $this->getTelegramChats();

        $shouldSleep = false;
        if (count($chats) > self::MAILING_LIMIT) {
            $shouldSleep = true;
        }

        foreach ($chats as $chat) {
            try {
                $job = new MessageUpdateCommentFromJira(
                    $this->telegramClient,
                    StateMessageUpdateCommentFromJira::createDefault(),
                    $this->getCommentText(),
                    $chat,
                    $this->prepareCommentUrl(),
                    $this->commentId
                );
            } catch (ApiException $e) {
                $this->telegramClient->logError($e);
                continue;
            }

            $this->pushJob($job);

            if ($shouldSleep) {
                sleep(1);
            }
        }
    }

    /**
     * Отправка комментария в очередь.
     * @param $job
     * @throws \yii\base\InvalidConfigException
     */
    public function pushJob($job): void
    {
        /** @var Queue $queue */
        $queue = Instance::ensure(Yii::$app->get('TelegramNotifyQueue'), Queue::class);
        $queueId = $queue->push($job);
        if ($queueId === null) {
            throw new \InvalidArgumentException("Не удалось отправить задание `{$this->getShortClassName()}`. Полученные данные:  ".Json::encode($job)."");
        }
    }

    /**
     * Проверяет наличие автора сообщения в БД.
     * @param string $author
     * @return User|null
     */
    public function getAuthor(string $author): ?User
    {
        try {
            $user = User::findByJiraUsername($author);
        } catch (UserException $e) {
            $this->logWarn($e->getMessage());
            return null;
        }
        return $user;
    }

    /**
     * Получает чаты для отправки комментария.
     * @param string $eventName
     * @return array
     */
    public function getTelegramChats(): array
    {
        $memberChats = $this->getChatsFromIssueMembers($this->commentText);
        [$pinnedChats, $fullNames] = $this->getChatsFromIssuePinned($this->commentText);

        /** Если были упомянуты сотрудники, подготавливаем текст сообщения с заменой markup разметки Jira. */
        if ($pinnedChats) {
            $this->commentText = $this->prepareCommentText($fullNames, $this->commentText);
        }

        $chats = array_unique(array_merge($memberChats, $pinnedChats));

        /** Исключаем из чатов автора комментария */
        return $this->excludeWhoInitAction($this->author->telegram_chat_id, $chats);
    }

    /**
     * Подготовка сообщения для комментария.
     * @return string
     */
    public function getCommentText(): string
    {
        $commentText = $this->checkCommentLength();
        $issueUrl = $this->getIssueUrl();
        $authorUrl = 'https://t.me/' . $this->author->username . '/';
        $time = date('d.m.Y H:i', strtotime($this->commentUpdated));
        return '<b>Задача</b>: <a href="' . $issueUrl . '">' . $this->issueName . '</a>' . PHP_EOL
            . '<b>Автор</b>: <a href="' . $authorUrl . '">' . $this->authorDisplayName . '</a>' . PHP_EOL
            . "<b>Время</b>: {$time}" . PHP_EOL
            . "<b>Комментарий</b>: {$commentText}" . PHP_EOL . PHP_EOL;
    }

    /**
     * Получаем id чатов всех участников задачи.
     * @param string $commentText
     * @return array
     */
    private function getChatsFromIssueMembers(string $commentText): array
    {
        $match = preg_match("/@card/", $commentText);
        if ($match === 0 || $match === false) {
            return [];
        }

        /** @var  array Возвращаем чаты всех участников */
        return parent::getTelegramChats();
    }

    /**
     * Получение чатов отмеченных пользователей.
     * @param string $commentText
     * @return array
     */
    private function getChatsFromIssuePinned(string $commentText): array
    {
        $chats = [];
        $fullNames = [];
        preg_match_all(self::JIRA_NICK_TEMPLATE, trim($commentText), $jiraUsernames);
        $jiraUsernames = $this->checkStickTags($jiraUsernames);
        foreach ($jiraUsernames as $jiraUsername) {
            if ($jiraUsername === 'card') {
                continue;
            }
            $user = $this->findUser($jiraUsername);
            if ($user === null) {
                continue;
            }

            $chats[] = $user->telegram_chat_id;

            /** Формируем массив с данными о user, для замены markup разметки в комментарии */
            $fullNames[] = [
                'jira_nick' => $jiraUsername,
                'telegram_nick' => $user->username,
                'full_name' => $user->fullname
            ];
        }

        return [$chats, $fullNames];
    }

    /**
     * Проверяет слепленные теги username в markup разметке Jira
     * при отправке комментария (случается в режиме Текст).
     * @param array $jiraUsernames
     * @return array
     */
    private function checkStickTags(array $jiraUsernames): array
    {
        $separateJiraUsernames = [];
        foreach ($jiraUsernames[1] as $name) {
            if (strpos($name,'][~')) {
                $result = explode(' ', preg_replace("!\]\[~!", ' ', $name));
                foreach ($result as $one) {
                    $separateJiraUsernames[] = $one;
                }
                continue;
            }
            $separateJiraUsernames[] = $name;
        }

        return $separateJiraUsernames;
    }

    /**
     * Подготовка текста сообщения комментариев, где тегают
     * участников с подстановкой имён.
     * @param array $fullNames
     * @param string $commentText
     * @return string
     */
    private function prepareCommentText(array $fullNames, string $commentText): string
    {
        $newCommentText = $commentText;
        preg_match_all(self::JIRA_NICK_TEMPLATE, trim($newCommentText), $jiraUsernames);
        $jiraUsernames = $this->checkStickTags($jiraUsernames);
        foreach ($jiraUsernames as $jiraUsername) {
            $replacement = $this->getReplacementText($jiraUsername, $fullNames);

            /** @var string Замена текста markup разметки пользователей Jira */
            $newCommentText = preg_replace("!\[~$jiraUsername\]!m", $replacement, $newCommentText);
        }

        return $newCommentText;
    }

    /**
     * Получение текста с заменой markup разметки на имена пользователей Jira.
     * @param string $jiraUsername
     * @param array $fullNames
     * @return string
     */
    private function getReplacementText(string $jiraUsername, array $fullNames): string
    {
        foreach ($fullNames as $fullName) {
            if ($jiraUsername === $fullName['jira_nick']) {
                return $this->getRecipientLink($fullName);
            }
        }

        return '';
    }

    /**
     * Получение ссылки на телеграм пользователя,
     * упомянутого в комментарии.
     * @param array $fullName
     * @return string
     */
    private function getRecipientLink(array $fullName): string
    {
        $recipientTelegramUrl =  'https://t.me/' . $fullName['telegram_nick'] . '/';
        return '<a href="' . $recipientTelegramUrl . '">' . $fullName['full_name'] . '</a> ';
    }

    /**
     * Проверка длины комментария на максимально
     * допустимое значение
     * @return mixed|string
     */
    private function checkCommentLength()
    {
        return (strlen(trim($this->commentText)) > self::MAX_COMMENT_LENGTH) ?
            StringHelper::truncate($this->commentText, self::MAX_COMMENT_LENGTH, '...') :
            $this->commentText;
    }

    /**
     * Подготовка ссылки на комментарий.
     * @return string
     */
    public function prepareCommentUrl(): string
    {
        return $this->getIssueUrl() . '#comment-' . $this->commentId;
    }
}