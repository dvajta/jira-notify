<?php

namespace app\modules\jira\notify\models\strategy;

use app\components\jira\objects\models\Update;
use app\models\traits\Loggable;
use app\modules\jira\notify\models\strategy\comment\DeleteCommentStrategy;
use app\modules\jira\notify\models\strategy\exceptions\UnsupportedStrategyException;
use app\modules\jira\notify\models\strategy\interfaces\CommentStrategyInterface;
use app\modules\jira\notify\models\strategy\interfaces\MessageStrategyInterface;
use app\modules\jira\notify\models\strategy\interfaces\StrategyInterface;
use app\modules\jira\notify\models\strategy\message\StatusStrategy;
use app\modules\jira\notify\models\strategy\message\WorklogStrategy;

class MessageManagerJiraNotify
{
    use Loggable;

    private Update $update;

    /** @var string Название класса для стратегии по комментариям. */
    private const COMMENT_STRATEGY = 'CommentStrategy';

    /** @var string Название класса для стратегии по удалению комментария. */
    private const DELETE_COMMENT_STRATEGY = 'DeleteCommentStrategy';

    /** @var string Название класса для ворклог стратегии. */
    private const WORKLOG_STRATEGY = 'WorklogStrategy';

    /** @var string Название класса для статуса стратегии. */
    private const STATUS_STRATEGY = 'StatusStrategy';

    /**
     * @param Update $update
     */
    public function __construct(Update $update)
    {
        $this->update = $update;
    }

    /**
     * @throws UnsupportedStrategyException
     * @return void
     */
    public function handle(): void
    {
        $currentStrategy = $this->getCurrentStrategy();
        if ($currentStrategy === null) {
            return;
        }
        switch (true) {
            case $currentStrategy instanceof MessageStrategyInterface && $currentStrategy->initiator === null:
            case $currentStrategy instanceof CommentStrategyInterface && $currentStrategy->author === null:
                return;
        }

        $currentStrategy->send();
    }


    /**
     * @throws UnsupportedStrategyException
     * @return StrategyInterface|null
     */
    private function getCurrentStrategy(): ?StrategyInterface
    {
        $comment = $this->update->comment;
        $changelog = $this->update->changelog;
        $webhook = $this->update->webhookEvent;
        $issueEventTypeName = $this->update->issue_event_type_name;

        switch (true) {
            case $webhook === WorklogStrategy::WORKLOG_CREATED
                || $webhook === WorklogStrategy::WORKLOG_DELETED
                || $webhook === WorklogStrategy::WORKLOG_UPDATE:
            case $issueEventTypeName === WorklogStrategy::ISSUE_WORKLOG_DELETED:
            case $comment !== null && $issueEventTypeName === null:
                if ($webhook === DeleteCommentStrategy::ISSUE_WEBHOOK_EVENT_COMMENT_DELETED) {
                    $strategyClass = __NAMESPACE__ . '\\comment\\' . self::DELETE_COMMENT_STRATEGY;
                    break;
                }
                return null;
            case $comment !== null:
                $strategyClass = __NAMESPACE__ . '\\comment\\' . self::COMMENT_STRATEGY;
                break;
            case $issueEventTypeName === WorklogStrategy::ISSUE_WORK_LOGGED
                || $issueEventTypeName === WorklogStrategy::ISSUE_WORKLOG_UPDATE:
                $strategyClass = __NAMESPACE__ . '\\message\\' . self::WORKLOG_STRATEGY;
                break;
            case $changelog !== null:
                $entityName = $changelog->asArray()['items'][0]['field'];
                $strategyName = ucfirst($entityName) . 'Strategy';

                /** Отсекаем создание журнала(оценка) и обновление оценки времени, так как изменился процесс. */
                if ($entityName === WorklogStrategy::WORKLOG_TIME_ESTIMATE
                    || $entityName === WorklogStrategy::WORKLOG_TIME_ORIGINAL_ESTIMATE) {
                    return null;
                }

                /** Отсекаем новый вебхук, который появился после установки расширения ворклог по ролям. */
                if ($entityName === WorklogStrategy::WORKLOG_UPDATE_TIME_TRACKING_BY_ROLES
                    && $issueEventTypeName !== StatusStrategy::ISSUE_EVENT_TYPE_NAME) {
                    return null;
                }

                /** Ситуация когда тип события не совпадает с содержимым свойства changelog (видимо баг Jira) */
                if ($issueEventTypeName === StatusStrategy::ISSUE_EVENT_TYPE_NAME && $entityName !== StatusStrategy::STATUS) {
                    $strategyName = self::STATUS_STRATEGY;
                }
                $strategyClass = __NAMESPACE__ . '\\message\\' . $strategyName;
                break;

            default:
                if ($issueEventTypeName === DeleteCommentStrategy::ISSUE_EVENT_TYPE_NAME_COMMENT_DELETED) {
                    return null;
                }
                $strategyName = str_replace(" ", "", ucwords(str_replace(['jira:', '_'], " ", $webhook))) . 'Strategy';
                $strategyClass = __NAMESPACE__ . '\\message\\' . $strategyName;
        }

        if (!class_exists($strategyClass)) {
            throw new UnsupportedStrategyException(
                sprintf(
                    'Получен неподдерживаемый тип стратегии: %s. JSON: %s',
                    $strategyClass,
                    $this->update->asJson()
                )
            );
        }

        return new $strategyClass($this->update);
    }
}
