<?php

namespace app\modules\jira\notify\models\strategy\interfaces;

interface MessageStrategyInterface extends BaseMessageStrategyInterface
{
    /** Подготовка к процессу отправки сообщения. */
    public function send(): void;

    /** @return string Получает текст сообщения. */
    public function getText(): string;

    /** @return string Получает внутренний тип события для issue. */
    public function getInternalEventName(): string;

    /** @return string Получает текст сообщения по типу события для пользователя. */
    public function getEventTypeNameText(): string;
}