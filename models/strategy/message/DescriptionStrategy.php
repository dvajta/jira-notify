<?php

namespace app\modules\jira\notify\models\strategy\message;

use app\modules\jira\notify\models\strategy\interfaces\MessageStrategyInterface;

/**
 * Класс описывает стратегию при типе события - "Обновление описания в задаче".
 */
final class DescriptionStrategy extends BaseMessageStrategy implements MessageStrategyInterface
{
    /** @var string Внешний тип вебхук-события при обновлении описания. */
    private const ISSUE_WEBHOOK_EVENT = 'jira:issue_updated';

    /** @var string Внешний тип события для issue "Обновление описания." */
    private const ISSUE_EVENT_TYPE_NAME = 'issue_updated';

    /** @var string Текст типа события при обновлении описания. */
    private const EVENT_TYPE_NAME_TEXT = 'Изменение описания!';

    /** @var string Внутренний тип события для issue "Обновление описания" */
    private const CHANGE_DESCRIPTION = 'changeDescription';

    /**
     * @inheritDoc
     */
    public function getInternalEventName(): string
    {
        return self::CHANGE_DESCRIPTION;
    }

    /**
     * @inheritDoc
     */
    public function getEventTypeNameText(): string
    {
        return self::EVENT_TYPE_NAME_TEXT;
    }
}