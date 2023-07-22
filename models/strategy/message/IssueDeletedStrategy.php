<?php

namespace app\modules\jira\notify\models\strategy\message;

use app\modules\jira\notify\models\strategy\interfaces\MessageStrategyInterface;

final class IssueDeletedStrategy extends BaseMessageStrategy implements MessageStrategyInterface
{
    /** @var string Внешний тип вебхук-события при удалении задачи. */
    private const ISSUE_WEBHOOK_EVENT = 'jira:issue_deleted';

    /** @var string Текст типа события при удалении задачи. */
    private const EVENT_TYPE_NAME_TEXT = 'Удаление задачи!';

    /** @var string Внутреннее наименование события для issue "Удаление задачи" */
    private const DELETE_ISSUE = 'deleteIssue';


    /**
     * @inheritDoc
     */
    public function getInternalEventName(): string
    {
        return self::DELETE_ISSUE;
    }

    /**
     * @inheritDoc
     */
    public function getEventTypeNameText(): string
    {
        return self::EVENT_TYPE_NAME_TEXT;
    }
}