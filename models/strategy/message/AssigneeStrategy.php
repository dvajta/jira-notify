<?php

namespace app\modules\jira\notify\models\strategy\message;

use app\modules\jira\notify\models\strategy\interfaces\MessageStrategyInterface;

/**
 * Класс описывает стратегию при типе события - "Назначение, смена и удаление исполнителя в задаче".
 */
final class AssigneeStrategy extends BaseMessageStrategy implements MessageStrategyInterface
{
    /** @var string Внешний тип вебхук-события при действиях с исполнителем. */
    private const ISSUE_WEBHOOK_EVENT = 'jira:issue_updated';

    /** @var string Внешний тип события для issue "Назначение исполнителя" */
    public const ISSUE_EVENT_TYPE_NAME = 'issue_assigned';

    /** @var string Внутренний тип события для issue "Обновление исполнителя" */
    private const UPDATE_ASSIGNEE = 'updateAssignee';

    /** @var string Внутренний тип события для issue "Назначение исполнителя" */
    public const ADD_ASSIGNEE = 'addAssignee';

    /** @var string Внутренний тип события для issue "Удаление исполнителя" */
    public const DELETE_ASSIGNEE = 'deleteAssignee';

    /** @var string Базовый текст типа события при добавлении исполнителя. */
    private const BASE_EVENT_TYPE_NAME_TEXT_TO = 'Добавлен исполнитель - ';

    /** @var string Базовый текст типа события при обновлении исполнителя. */
    private const BASE_EVENT_TYPE_NAME_TEXT_FROM_TO = 'Смена исполнителя - ';

    /** @var string Базовый текст типа события при удалении исполнителя. */
    private const BASE_EVENT_TYPE_NAME_TEXT_FROM = 'Удалён исполнитель - ';


    /**
     * @inheritDoc
     */
    public function getInternalEventName(): string
    {
        switch (true) {
            case $this->getCurrentEventName() === self::ISSUE_EVENT_TYPE_NAME;
            case empty($this->changeFromString()) && $this->changeToString() !== null:
                return self::ADD_ASSIGNEE;
            case empty($this->changeToString()) || $this->changeToString() === null:
                return self::DELETE_ASSIGNEE;

            default:
                return self::UPDATE_ASSIGNEE;
        }
    }

    /**
     * @inheritDoc
     */
    public function getEventTypeNameText(): string
    {
        return $this->getExtendEventName(
                self::BASE_EVENT_TYPE_NAME_TEXT_FROM_TO,
                self::BASE_EVENT_TYPE_NAME_TEXT_FROM,
                self::BASE_EVENT_TYPE_NAME_TEXT_TO
            );
    }
}