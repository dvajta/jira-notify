<?php

namespace app\modules\jira\notify\models\strategy\message;

use app\modules\jira\notify\models\strategy\interfaces\MessageStrategyInterface;

/**
 * Класс описывает стратегию при типе события - "Обновление названия задачи".
 */
final class SummaryStrategy extends BaseMessageStrategy implements MessageStrategyInterface
{
    /** @var string Внешний тип вебхук-события при обновлении названия. */
    private const ISSUE_WEBHOOK_EVENT = 'jira:issue_updated';

    /** @var string Внешний тип события для issue "Обновление названия." */
    private const ISSUE_EVENT_TYPE_NAME = 'issue_updated';

    /** @var string Текст типа события при обновлении названия. */
    private const EVENT_TYPE_NAME_TEXT = 'Изменение названия!';

    /** @var string Внутренний тип события для issue "Обновление названия" */
    private const CHANGE_SUMMARY = 'changeSummary';


    /**
     * @inheritDoc
     */
    public function getInternalEventName(): string
    {
        return self::CHANGE_SUMMARY;
    }

    /**
     * @inheritDoc
     */
    public function getEventTypeNameText(): string
    {
        return self::EVENT_TYPE_NAME_TEXT;
    }
}