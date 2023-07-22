<?php

namespace app\modules\jira\notify\models\strategy\message;

use app\modules\jira\notify\models\strategy\interfaces\MessageStrategyInterface;

/**
 * Класс описывает стратегию при типе события - "Создание, обновление, удаление ворклог".
 */
final class WorklogStrategy extends BaseMessageStrategy implements MessageStrategyInterface
{
    /** @var string Внешний тип вебхук-события не информиативный и не нужный (отсекаем). */
    public const WORKLOG_DELETED = 'worklog_deleted';

    /** @var string Внешний тип вебхук-события не инфоримативный (отсекаем), есть 2-й на эту тему. */
    public const WORKLOG_CREATED = 'worklog_created';

    /** @var string Внешний тип вебхук-события при обновлении записи ворклог - не инфоримативный (отсекаем). */
    public const WORKLOG_UPDATE = 'worklog_updated';

    /** @var string Внешний тип нового вебхук-события обновление ворклога по ролям - не инфоримативный (отсекаем). */
    public const WORKLOG_UPDATE_TIME_TRACKING_BY_ROLES = 'Time Tracking (By Roles)';

    /** @var string Внешний тип события для issue "Создание записи ворклог" */
    public const ISSUE_WORK_LOGGED = 'issue_work_logged';

    /** @var string Внутреннее наименование события для issue "Создание записи ворклог" */
    private const INTERNAL_ISSUE_WORK_LOGGED = 'issueWorkLogged';

    /** @var string Внешний тип события для issue "Обновление записи ворклог" */
    public const ISSUE_WORKLOG_UPDATE = 'issue_worklog_updated';

    /** @var string Внешний тип события для issue "Удаление записи ворклог" */
    public const ISSUE_WORKLOG_DELETED = 'issue_worklog_deleted';

    /** @var string Внутреннее наименование события для issue "Обновление записи ворклог" */
    private const INTERNAL_ISSUE_WORKLOG_UPDATE = 'issueWorklogUpdated';

    /** @var string Внешний тип события для issue "Создание ворклог журнала для задачи" */
    public const WORKLOG_TIME_ESTIMATE = 'timeestimate';

    /** @var string Внешний тип события для issue "Обновление первоначальной оценки" */
    public const WORKLOG_TIME_ORIGINAL_ESTIMATE = 'timeoriginalestimate';

    /** @var string Базовый текст типа события при добавлении записи в журнале. */
    private const BASE_EVENT_TYPE_NAME_TEXT_WORK_LOGGED = 'Добавлена запись в журнал - ';

    /** @var string Базовый текст типа события при обновлении записи в журнале (Увеличение продолжительности). */
    private const BASE_EVENT_TYPE_NAME_TEXT_WORKLOG_UPDATED_PLUS = 'Обновлена запись в журнале, увеличина продолжительность работы на ';

    /** @var string Базовый текст типа события при обновлении записи в журнале (Уменьшении продолжительности). */
    private const BASE_EVENT_TYPE_NAME_TEXT_WORKLOG_UPDATED_MINUS = 'Обновлена запись в журнале, уменьшена продолжительность работы на ';

    /** @var int Один час в секундах. */
    private const ONE_HOUR_IN_SECONDS = 60 * 60;

    /** @var int Одна минута в секундах. */
    private const ONE_MINUTE_IN_SECONDS = 60;

    /** @var int Один час */
    private const ONE_HOUR = 1;

    /** @var int Номер элемента, относящийся к изменению текушей записи пользователем в журнале. */
    private const WORKLOG_ITEM_UPDATE_ENTITY = 1;

    /** @var int Номер элемента, относящийся к добавлению записи пользователем в журнале. */
    private const WORKLOG_ITEM_LOGGED_ENTITY = 2;


    /**
     * Получает внутренний тип события,
     * исходя из типа действия в отношении журнала работ.
     * @return string
     */
    public function getInternalEventName(): string
    {
        switch (true) {
            case $this->getCurrentEventName() === self::ISSUE_WORK_LOGGED:
                return self::INTERNAL_ISSUE_WORK_LOGGED;

            default:
                return self::INTERNAL_ISSUE_WORKLOG_UPDATE;
        }
    }

    /**
     * Получает расширенный вариант названия события,
     * исходя из типа действия в отношении журнала работ.
     * @return string
     */
    public function getEventTypeNameText(): string
    {
        switch (true) {
            /** Тип действия связан с добавлением пользователем новой записи учета в журнал работ. */
            case $this->getCurrentEventName() === self::ISSUE_WORK_LOGGED:
                return self::BASE_EVENT_TYPE_NAME_TEXT_WORK_LOGGED
                    . $this->calculateTime($this->getWorklogTimeUpdate(self::WORKLOG_ITEM_LOGGED_ENTITY));

            /** Тип действия связан с обновлением пользователем существующей записи учета в журнале работ. */
            case $this->getCurrentEventName() === self::ISSUE_WORKLOG_UPDATE:
                if ($this->getWorklogTimeUpdate(self::WORKLOG_ITEM_UPDATE_ENTITY) < 0) {
                    return self::BASE_EVENT_TYPE_NAME_TEXT_WORKLOG_UPDATED_MINUS
                        . $this->calculateTime(abs($this->getWorklogTimeUpdate(self::WORKLOG_ITEM_UPDATE_ENTITY)));
                }
                return self::BASE_EVENT_TYPE_NAME_TEXT_WORKLOG_UPDATED_PLUS
                    . $this->calculateTime($this->getWorklogTimeUpdate(self::WORKLOG_ITEM_UPDATE_ENTITY));

            default:
                return '';
        }
    }

    /**
     * Переводит время журнала работ исходя из типа события
     * и количества в удобный формат.
     * @param int $time
     * @return string
     */
    private function calculateTime(int $time): string
    {
        switch (true) {
            case $time < self::ONE_HOUR_IN_SECONDS:
                return $time / self::ONE_MINUTE_IN_SECONDS . ' m';
            case $time === self::ONE_HOUR_IN_SECONDS:
                return self::ONE_HOUR . ' h';

            default:
                return $time / self::ONE_HOUR_IN_SECONDS . ' h';
        }
    }

    /**
     * Получает разницу между значением времени
     * при обновлении первоначальной оценки либо
     * обновлении текущей записи в журнале работ.
     * @return int
     */
    private function getWorklogTimeUpdate(int $entity): int
    {
        $changeLogEntity = isset ($this->getChangelog()[$entity])
            ? $this->getChangelog()[$entity]
            : $this->getChangelog()[self::WORKLOG_ITEM_UPDATE_ENTITY];
        return (int) $changeLogEntity['to'] - (int) $changeLogEntity['from'];
    }
}