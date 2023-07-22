<?php

namespace app\modules\jira\notify\models\strategy\interfaces;

use app\models\User;

interface BaseCommentStrategyInterface extends StrategyInterface
{
    public function process(): void;
    public function getCommentText(): string;
    public function prepareCommentUrl(): string;
    public function getAuthor(string $author): ?User;
}