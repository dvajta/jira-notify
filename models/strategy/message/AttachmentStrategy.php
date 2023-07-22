<?php

namespace app\modules\jira\notify\models\strategy\message;

use app\modules\jira\notify\models\strategy\interfaces\MessageStrategyInterface;

class AttachmentStrategy extends BaseMessageStrategy implements MessageStrategyInterface
{
    /** @var string Внешний тип вебхук-события при действиях с вложениями. */
    private const ISSUE_WEBHOOK_EVENT = 'jira:issue_updated';

    /** @var string Внешний тип события для issue "Добавление и удаление вложения" */
    public const ISSUE_EVENT_TYPE_NAME = 'issue_updated';

    /** @var string Внутренний тип события для issue "Добавление вложения" */
    public const ADD_ATTACHMENT = 'addAttachment';

    /** @var string Внутренний тип события для issue "Удаление вложения" */
    public const DELETE_ATTACHMENT = 'deleteAttachment';

    /** @var string Базовый текст типа события при добавлении вложения. */
    private const BASE_EVENT_TYPE_NAME_TEXT_TO = 'Добавлено вложение - ';

    /** @var string Базовый текст типа события при удалении вложения. */
    private const BASE_EVENT_TYPE_NAME_TEXT_FROM = 'Удалёно вложение - ';


    /**
     * @inheritDoc
     */
    public function getInternalEventName(): string
    {
        switch (true) {
            case $this->changeFromString() === null && $this->changeToString() !== null:
                return self::ADD_ATTACHMENT;

            default:
                return self::DELETE_ATTACHMENT;
        }
    }

    /**
     * @inheritDoc
     */
    public function getEventTypeNameText(): string
    {
        return $this->getExtendEventName(
            null,
            self::BASE_EVENT_TYPE_NAME_TEXT_FROM,
            self::BASE_EVENT_TYPE_NAME_TEXT_TO
        );
    }
}