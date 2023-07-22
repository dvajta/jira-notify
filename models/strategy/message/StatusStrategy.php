<?php

namespace app\modules\jira\notify\models\strategy\message;

use app\modules\jira\notify\models\strategy\interfaces\MessageStrategyInterface;

/**
 * Класс описывает стратегию при типе события - "Изменение статуса (перемещение) задачи".
 */
final class StatusStrategy extends BaseMessageStrategy implements MessageStrategyInterface
{
    /** @var string Внешний тип вебхук-события при изменении статуса задачи. */
    private const ISSUE_WEBHOOK_EVENT = 'jira:issue_updated';

    /** @var string Внешний тип события для issue "Изменение статуса." */
    public const ISSUE_EVENT_TYPE_NAME = 'issue_generic';

    /** @var string Текст типа события при изменении статуса задачи. */
    private const BASE_EVENT_TYPE_NAME_TEXT_TO = 'Перемещение! Статус задачи - ';

    /** @var string Внутренний тип события для issue "Изменение статуса" */
    public const CHANGE_STATUS = 'changeStatus';

    /** @var string Внешний тип события для issue "Изменение статуса", который отражен в changelog */
    public const STATUS = 'status';


    /**
     * @inheritDoc
     */
    public function getInternalEventName(): string
    {
        return self::CHANGE_STATUS;
    }

    /**
     * @inheritDoc
     */
    public function getEventTypeNameText(): string
    {
        return $this->getExtendEventName(
            null,
            null,
            self::BASE_EVENT_TYPE_NAME_TEXT_TO
        );
    }
}