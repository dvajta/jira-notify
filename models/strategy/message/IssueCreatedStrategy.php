<?php

namespace app\modules\jira\notify\models\strategy\message;

use app\modules\jira\notify\models\strategy\interfaces\MessageStrategyInterface;

/**
 * Класс описывает стратегию при типе события - "Создание задачи".
 */
final class IssueCreatedStrategy extends BaseMessageStrategy implements MessageStrategyInterface
{
    /** @var string Внешний тип вебхук-события при создании задачи. */
    private const ISSUE_WEBHOOK_EVENT = 'jira:issue_created';

    /** @var string Текст типа события при создании задачи. */
    private const EVENT_TYPE_NAME_TEXT = 'Создание задачи!';

    /** @var string Внутреннее наименование события для issue "Создание задачи" */
    private const CREATE_ISSUE = 'createIssue';

    /**
     * @inheritDoc
     */
    public function getInternalEventName(): string
    {
        return self::CREATE_ISSUE;
    }

    /**
     * @inheritDoc
     */
    public function getEventTypeNameText(): string
    {
        return self::EVENT_TYPE_NAME_TEXT;
    }
}