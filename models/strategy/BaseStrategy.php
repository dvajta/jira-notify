<?php

namespace app\modules\jira\notify\models\strategy;

use app\components\jira\bots\JiraNotifyBot;
use app\components\jira\clients\ApiClient;
use app\components\telegram\bots\TelegramNotifyBot;
use app\components\telegram\clients\ApiClient as TelegramApiClient;
use app\components\jira\exceptions\ApiException as JiraApiException;
use app\components\jira\objects\models\Update;
use app\models\config\jira\JiraNotifySettingsConfig;
use app\models\JiraNotifyUserSettings;
use app\models\traits\Loggable;
use app\models\traits\ReflectionTrait;
use app\models\User;
use app\modules\jira\notify\models\strategy\interfaces\StrategyInterface;
use yii\base\UnknownPropertyException;
use yii\base\UserException;

abstract class BaseStrategy implements StrategyInterface
{
    use Loggable,ReflectionTrait;

    private ApiClient $client;

    protected TelegramApiClient $telegramClient;

    protected Update $update;

    protected ?string $_logCategory = null;

    /** @var int 20 чатов в секунду - безопасный предел */
    protected const MAILING_LIMIT = 20;

    /** @var string Пользовательское поле с данными о ревьюере в issue. */
    public const JIRA_REVIEW_CUSTOM_FIELD = 'customfield_10401';

    /** @var string Пользовательское поле с данными о тестере в issue. */
    public const JIRA_TESTER_CUSTOM_FIELD = 'customfield_10400';

    /** @var string Пользовательское поле с данными о тестере 2 в issue. */
    public const JIRA_TESTER_2_CUSTOM_FIELD = 'customfield_10829';

    /** @var User|null Инициатор события. */
    public ?User $initiator;

    /** @var User|null Автор комментария. */
    public ?User $author;

    /** @var string|null Текст комментария. */
    public ?string $commentText;

    /** @var int Внешний ID комментария. */
    public int $commentId;

    /** @var string Дата обновления комментария. */
    protected string $commentUpdated;

    /** @var string Полное имя автора комментария. */
    protected string $authorDisplayName;

    /** @var string|null Ключ задачи. */
    private ?string $issueKey;

    /** @var string|null Время события. */
    protected ?string $eventTime;

    /** @var string|null Название задачи. */
    protected ?string $issueName;

    /** @var string|null Никнейм исполнителя задачи. */
    private ?string $assignee;

    /** @var string|null Автор задачи. */
    private ?string $reporter;

    /** @var string|null Никней ревьюера, если существует. */
    private ?string $review;

    /** @var string|null Никнейм тестера, если существует. */
    private ?string $tester;

    /** @var string|null Никнейм тестера 2, если существует. */
    private ?string $tester2;

    /** @var array Массив наблюдателей в задаче. */
    private array $watchers = [];

    /** @var bool Значение в настройках конфига по умолчанию. */
    protected bool $defaultEventSetting;

    /** @var string Наименование текущего события (для сверки со св-м в конфиге) */
    protected string $currentEventName;

    /** @var string Наименование текущего статуса задачи. */
    protected string $issueStatusName;

    public function __construct(Update $update)
    {
        $this->update = $update;
        $this->client = JiraNotifyBot::getClient();
        $this->telegramClient = TelegramNotifyBot::getClient();

        if (isset($update->issue)) {
            $this->issueKey = $update->issue->key;
            $this->issueStatusName = $update->issue->fields->status->name;
            $this->eventTime = date('d.m.Y H:i', strtotime($update->issue->fields->updated));
            $this->issueName = $update->issue->fields->summary;
            $this->assignee = isset($update->issue->fields->assignee) ? $update->issue->fields->assignee->name : null;
            $this->reporter = isset($update->issue->fields->reporter) ? $update->issue->fields->reporter->name : null;
            $this->review = isset($update->issue->fields->asArray()[self::JIRA_REVIEW_CUSTOM_FIELD])
            && !empty($update->issue->fields->asArray()[self::JIRA_REVIEW_CUSTOM_FIELD])
                ? $update->issue->fields->asArray()[self::JIRA_REVIEW_CUSTOM_FIELD]['name']
                : null;
            $this->tester = isset($update->issue->fields->asArray()[self::JIRA_TESTER_CUSTOM_FIELD])
            && !empty($update->issue->fields->asArray()[self::JIRA_TESTER_CUSTOM_FIELD])
                ? $update->issue->fields->asArray()[self::JIRA_TESTER_CUSTOM_FIELD]['name']
                : null;
            $this->tester2 = isset($update->issue->fields->asArray()[self::JIRA_TESTER_2_CUSTOM_FIELD])
            && !empty($update->issue->fields->asArray()[self::JIRA_TESTER_2_CUSTOM_FIELD])
                ? $update->issue->fields->asArray()[self::JIRA_TESTER_2_CUSTOM_FIELD]['name']
                : null;
            $this->setWatchers();
        }
    }

    /**
     * Получает данные по наблюдателям и устанавливает
     * в св-во watchers.
     */
    public function setWatchers(): void
    {
        try {
            $watchers = $this->client->issue->getWatchers($this->update->issue->key)->getDecodedBody();
            $watchersName = [];
            foreach ($watchers['watchers'] as $watcher) {
                $watchersName[] = $watcher['name'];
            }
            $this->watchers = $watchersName;
        } catch (JiraApiException $e) {
            $this->logWarn($e->getMessage());
            return;
        } catch (\Throwable $e) {
            $this->logWarn($e);
            return;
        }
    }

    /**
     * Получает наблюдателей в задаче.
     * @return array
     */
    public function getWatchers(): array
    {
        return $this->watchers;
    }

    /**
     * Получает исполнителя задачи.
     * @return string|null
     */
    public function getAssignee(): ?string
    {
        return $this->assignee;
    }

    /**
     * Получает ревьюера задачи.
     * @return string|null
     */
    public function getReview(): ?string
    {
        return $this->review;
    }

    /**
     * Получает тестера задачи.
     * @return string|null
     */
    public function getTester(): ?string
    {
        return $this->tester;
    }

    /**
     * Получает тестера 2 задачи.
     * @return string|null
     */
    public function getTester2(): ?string
    {
        return $this->tester2;
    }

    /**
     * Получает автора задачи.
     * @return string|null
     */
    public function getReporter(): ?string
    {
        return $this->reporter;
    }

    /**
     * Получаем всех доступных участников
     * задачи.
     * @return array
     */
    public function getAllMembers(): array
    {
        $members = [$this->assignee, $this->reporter, $this->review, $this->tester, $this->tester2];
        foreach ($members as $member) {
            if ($member !== null) {
                $this->watchers[] = $member;
            }
        }
        return $this->watchers;
    }

    /**
     * Получает данные журнала изменений
     * (вся информация).
     * @return array
     */
    protected function getChangelog(): array
    {
        return $this->update->changelog->asArray()['items'];
    }

    /**
     * Получает данные журнала изменений
     * (новое значение).
     * @return string|null
     */
    protected function changeToString(): ?string
    {
        return $this->getChangelog()[0]['toString'];
    }

    /**
     * Получает данные журнала изменений
     * (предыдущее значение).
     * @return string|null
     */
    protected function changeFromString(): ?string
    {
        return $this->getChangelog()[0]['fromString'];
    }

    /**
     * Получает внешний тип события для issue.
     * @return string|null
     */
    public function getCurrentEventName(): ?string
    {
        return $this->update->issue_event_type_name;
    }

    /**
     * Получение чатов у существующих пользователей.
     * @param array $members
     * @return array
     */
    public function getTelegramChats(): array
    {
        $chats = [];
        $members = $this->getAllMembers();
        /** @var string $member  */
        foreach ($members as $member) {
            $user = $this->findUser($member);
            if ($user === null) {
                continue;
            }

            $chats[] = $user->telegram_chat_id;
        }

        return array_unique($chats);
    }

    /**
     * Исключает из чатов того, кто инициировал то или
     * иное действие.
     * @param string $telegramChatId
     * @param array $chats
     */
    public function excludeWhoInitAction(string $telegramChatId, array $chats): array
    {
        if (in_array($telegramChatId, $chats, true)) {
            $chats = array_diff($chats, [$telegramChatId]);
        }
        return $chats;
    }

    /**
     * Проверка наличия пользователя по его jiraUsername.
     * @param string $jiraUsername
     * @return User|null
     */
    public function findUser(string $jiraUsername): ?User
    {
        try {
            $user = User::findByJiraUsername($jiraUsername);
        } catch (UserException $e) {
            $this->logWarn($e->getMessage());
            return null;
        }

        if ($this->isSettingsBlock($user)) {
            return null;
        }

        if ($user->isNotifyBlock()) {
            $this->logInfo("Пользователь `{$user->username}` заблокировал уведомления, пропускаем его.");
            return null;
        }

        if ($user->telegram_chat_id === null) {
            $this->logWarn("Не могу начать диалог с пользователем `{$user->username}`, telegram_chat_id равен null.");
            return null;
        }

        return $user;
    }

    /**
     * Проверяет есть ли блокировка на уровне
     * глобальных или кастомных настроек.
     * @param User $user
     * @return bool
     * @throws UserException
     */
    private function isSettingsBlock(User $user): bool
    {
        $userSettings = JiraNotifyUserSettings::getSettings($user->id);
        $eventName = $this->currentEventName;
        $customUserSetting = (bool) $userSettings->$eventName;

        switch (true) {
            case $this->defaultEventSetting === false && $customUserSetting === false:
            case $this->defaultEventSetting === true && $customUserSetting === false:
                return true;
            case $this->defaultEventSetting === false && $customUserSetting === true:
            case $this->defaultEventSetting === true && $customUserSetting === true:
            default:
                return false;
        }
    }

    /**
     * Получение ссылки на задачу.
     * @return string
     */
    public function getIssueUrl(): string
    {
        return 'https://atlassian.i2crm.ru/jira/browse/' . $this->issueKey;
    }

    /**
     * Устанавливает значение св-ва в отношении текущего события, которое
     * получает с конфига настроек оповещений Jira.
     * @param string $eventName
     * @throws UserException
     */
    protected function setDefaultSetting(string $eventName): void
    {
        $this->currentEventName = $eventName;
        try {
            $this->defaultEventSetting = JiraNotifySettingsConfig::getConfig()->$eventName;
        } catch (UnknownPropertyException $e) {
            $this->logInfo("Свойство `{$eventName}` не найдено в конфиге настроек оповещений Jira.");
            $this->defaultEventSetting = true;
        }
    }

    /**
     * Получение категории логирования.
     *
     * @param string $type
     * @return string
     */
    public function getLogCategory(string $type): string
    {
        if ($this->_logCategory === null) {
            $this->_logCategory = sprintf('telegram\notify\jira\%s', $type);
        }

        return $this->_logCategory;
    }
}
