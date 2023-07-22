<?php

namespace app\modules\jira\notify\models\strategy\interfaces;

use app\models\User;

interface StrategyInterface
{
    public function getTelegramChats(): array;
    public function setWatchers(): void;
    public function getIssueUrl(): string;
    public function findUser(string $jiraUsername): ?User;
    public function excludeWhoInitAction(string $telegramChatId, array $chats): array;
    public function getCurrentEventName(): ?string;
    public function getAllMembers(): array;
    public function getReporter(): ?string;
    public function getTester(): ?string;
    public function getReview(): ?string;
    public function getAssignee(): ?string;
    public function getWatchers(): array;
}