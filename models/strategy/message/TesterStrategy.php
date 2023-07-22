<?php

namespace app\modules\jira\notify\models\strategy\message;

use app\modules\jira\notify\models\strategy\interfaces\MessageStrategyInterface;

/**
 * Класс описывает стратегию при типе события - "Назначение, смена и удаление тестера в задаче".
 */
final class TesterStrategy extends BaseMessageStrategy implements MessageStrategyInterface
{
    /** @var string Внешний тип вебхук-события при действиях с тестером. */
    private const ISSUE_WEBHOOK_EVENT = 'jira:issue_updated';

    /** @var string Внешний тип события для issue "Назначение тестера" */
    public const ISSUE_EVENT_TYPE_NAME = 'issue_updated';

    /** @var string Внутренний тип события для issue "Обновление тестера" */
    private const UPDATE_TESTER = 'updateTester';

    /** @var string Внутренний тип события для issue "Назначение тестера" */
    public const ADD_TESTER = 'addTester';

    /** @var string Внутренний тип события для issue "Удаление тестера" */
    public const DELETE_TESTER = 'deleteTester';

    /** @var string Базовый текст типа события при добавлении тестера. */
    private const BASE_EVENT_TYPE_NAME_TEXT_TO = 'Добавлен тестер - ';

    /** @var string Базовый текст типа события при обновлении тестера. */
    private const BASE_EVENT_TYPE_NAME_TEXT_FROM_TO = 'Смена тестера - ';

    /** @var string Базовый текст типа события при удалении тестера. */
    private const BASE_EVENT_TYPE_NAME_TEXT_FROM = 'Удалён тестер - ';


    /**
     * Получает внутренний тип события,
     * исходя из типа действия в отношении ревьюера.
     * @return string
     */
    public function getInternalEventName(): string
    {
        switch (true) {
            case $this->changeFromString() === null && $this->changeToString() !== null:
                return self::ADD_TESTER;
            case empty($this->changeToString()) || $this->changeToString() === null:
                return self::DELETE_TESTER;

            default:
                return self::UPDATE_TESTER;
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