<?php

namespace app\modules\jira\notify\models\strategy\message;

use app\modules\jira\notify\models\strategy\interfaces\MessageStrategyInterface;

/**
 * Класс описывает стратегию при типе события - "Смена автора в задаче".
 */
class ReporterStrategy extends BaseMessageStrategy implements MessageStrategyInterface
{
    /** @var string Внешний тип вебхук-события при действиях с ревьюером. */
    private const ISSUE_WEBHOOK_EVENT = 'jira:issue_updated';

    /** @var string Внешний тип события для issue "Смена автора" */
    public const ISSUE_EVENT_TYPE_NAME = 'issue_updated';

    /** @var string Внутренний тип события для issue "Обновление автора" */
    private const UPDATE_REPORTER = 'updateReporter';

    /** @var string Базовый текст типа события при обновлении автора. */
    private const BASE_EVENT_TYPE_NAME_TEXT_FROM_TO = 'Смена автора - ';


    /**
     * @inheritDoc
     */
    public function getInternalEventName(): string
    {
        return self::UPDATE_REPORTER;
    }

    /**
     * @inheritDoc
     */
    public function getEventTypeNameText(): string
    {
        return $this->getExtendEventName(
            self::BASE_EVENT_TYPE_NAME_TEXT_FROM_TO
        );
    }
}