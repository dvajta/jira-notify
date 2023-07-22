<?php

namespace app\modules\jira\notify\models\strategy\interfaces;

interface CommentStrategyInterface extends BaseCommentStrategyInterface
{
    public function send(): void;
}