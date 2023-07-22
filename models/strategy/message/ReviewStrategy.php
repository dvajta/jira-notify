<?php

namespace app\modules\jira\notify\models\strategy\message;

use app\modules\jira\notify\models\strategy\interfaces\MessageStrategyInterface;

/**
 * Класс описывает стратегию при типе события - "Назначение, смена и удаление ревьюера в задаче".
 */
final class ReviewStrategy extends BaseMessageStrategy implements MessageStrategyInterface
{
    /** @var string Внешний тип вебхук-события при действиях с ревьюером. */
    private const ISSUE_WEBHOOK_EVENT = 'jira:issue_updated';

    /** @var string Внешний тип события для issue "Назначение ревьюера" */
    public const ISSUE_EVENT_TYPE_NAME = 'issue_updated';

    /** @var string Внутренний тип события для issue "Обновление ревьюера" */
    private const UPDATE_REVIEW = 'updateReview';

    /** @var string Внутренний тип события для issue "Назначение ревьюера" */
    public const ADD_REVIEW = 'addReview';

    /** @var string Внутренний тип события для issue "Удаление ревьюера" */
    public const DELETE_REVIEW = 'deleteReview';

    /** @var string Базовый текст типа события при добавлении ревьюера. */
    private const BASE_EVENT_TYPE_NAME_TEXT_TO = 'Добавлен ревьюер - ';

    /** @var string Базовый текст типа события при обновлении ревьюера. */
    private const BASE_EVENT_TYPE_NAME_TEXT_FROM_TO = 'Смена ревьюера - ';

    /** @var string Базовый текст типа события при удалении ревьюера. */
    private const BASE_EVENT_TYPE_NAME_TEXT_FROM = 'Удалён ревьюер - ';


    /**
     * @inheritDoc
     */
    public function getInternalEventName(): string
    {
        switch (true) {
            case $this->changeFromString() === null && $this->changeToString() !== null:
                return self::ADD_REVIEW;
            case empty($this->changeToString()) || $this->changeToString() === null:
                return self::DELETE_REVIEW;

            default:
                return self::UPDATE_REVIEW;
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