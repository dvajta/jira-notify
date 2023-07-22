<?php

namespace app\modules\jira\notify\models\strategy\message;

use app\components\jira\objects\models\Update;
use app\models\User;
use app\modules\jira\notify\models\strategy\BaseStrategy;
use app\modules\jira\notify\models\strategy\interfaces\BaseMessageStrategyInterface;
use app\modules\telegram\bots\notify\models\helpers\Menu;
use app\modules\telegram\bots\notify\models\helpers\notifyEntity\NotifyApi;
use app\modules\telegram\models\MassSendingMessages;
use yii\base\UserException;

class BaseMessageStrategy extends BaseStrategy implements BaseMessageStrategyInterface
{
    /**
     * @param Update $update
     * @throws \yii\base\UserException
     */
    public function __construct(Update $update)
    {
        parent::__construct($update);
        $this->initiator = $this->getInitiator($update->user->name);
    }

    /**
     * Запускает процесс отправки сообщения.
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\base\UserException
     */
    public function send(): void
    {
        $this->setDefaultSetting($this->getInternalEventName());
        $this->process($this->getText());
    }

    /**
     * Осуществляет процесс отправки по средством очереди.
     * @param string $text
     * @throws \JsonException
     */
    public function process(string $text): void
    {
        $api = new NotifyApi($this->telegramClient);
        $massSend = new MassSendingMessages($api, $this->getTelegramChats(), $text, Menu::getBasePassKeyboard());
        $massSend->execute();
    }

    /**
     * Получает чаты для отправки сообщений.
     * @return array
     */
    public function getTelegramChats(): array
    {
        $chats = parent::getTelegramChats();
        return $this->excludeWhoInitAction($this->initiator->telegram_chat_id, $chats);
    }

    /**
     * Инициатор события.
     * @param string $initiator
     * @return User|null
     */
    public function getInitiator(string $initiator): ?User
    {
        try {
            $user = User::findByJiraUsername($initiator);
        } catch (UserException $e) {
            $this->logWarn($e->getMessage());
            return null;
        }
        return $user;
    }

    /**
     * Получает полный текст с типом события.
     */
    public function getText(): string
    {
        return $this->getBaseText()
            . "<b>Тип события</b>: " . $this->getEventTypeNameText() . "" . PHP_EOL . PHP_EOL;
    }

    /**
     * Получает базовый текст сообщения.
     * @return string
     */
    public function getBaseText(): string
    {
        return '<b>Инициатор события</b>: <a href="' . $this->getInitiatorUrl() . '">' . $this->initiator->fullname . '</a>' . PHP_EOL
            .'<b>Задача</b>: <a href="' . $this->getIssueUrl() . '">' . $this->issueName . '</a>' . PHP_EOL
            ."<b>Время</b>: {$this->eventTime}" . PHP_EOL;
    }

    /**
     * Получение ссылки на профиль телеграм
     * инициатора события.
     * @return string
     */
    public function getInitiatorUrl(): string
    {
        return 'https://t.me/' . $this->initiator->username . '/';
    }

    /**
     * Получает расширенный вариант названия события,
     * исходя из типа действия в отношении участников.
     * @return string
     */
    public function getExtendEventName(?string $fromTo, ?string $from = null, ?string $to = null): string
    {
        switch ($this->currentEventName) {
            case StatusStrategy::CHANGE_STATUS:
                return $to . $this->issueStatusName;
            case AssigneeStrategy::ADD_ASSIGNEE;
            case ReviewStrategy::ADD_REVIEW;
            case TesterStrategy::ADD_TESTER;
            case AttachmentStrategy::ADD_ATTACHMENT:
                return $to . $this->changeToString();

            case AssigneeStrategy::DELETE_ASSIGNEE;
            case ReviewStrategy::DELETE_REVIEW;
            case TesterStrategy::DELETE_TESTER;
            case AttachmentStrategy::DELETE_ATTACHMENT:
                return $from . $this->changeFromString();

            default:
                return $fromTo . 'c ' . $this->changeFromString() . ' на ' . $this->changeToString();
        }
    }
}