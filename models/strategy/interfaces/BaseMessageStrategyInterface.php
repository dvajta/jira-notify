<?php

namespace app\modules\jira\notify\models\strategy\interfaces;

use app\models\User;

interface BaseMessageStrategyInterface extends StrategyInterface
{
    public function send(): void;
    public function process(string $text): void;
    public function getInitiator(string $initiator): ?User;
    public function getBaseText(): string;
    public function getInitiatorUrl(): string;
    public function getExtendEventName(?string $fromTo, ?string $from = null, ?string $to = null): string;
}