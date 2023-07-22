<?php

namespace app\modules\jira\notify\models\strategy\comment;

use app\modules\jira\notify\models\strategy\interfaces\CommentStrategyInterface;

class CommentStrategy extends BaseCommentStrategy implements CommentStrategyInterface
{
    /**
     * Запускает процесс отправки комментария.
     */
    public function send(): void
    {
        $this->setDefaultSetting($this->getInternalEventName());
        $this->process();
    }

    /**
     * Получает внутреннее наименование события,
     * исходя из типа действия в отношении комментария.
     * @return string
     */
    public function getInternalEventName(): string
    {
        return lcfirst(str_replace(" ", "", ucwords(str_replace("_", " ", $this->getCurrentEventName()))));
    }
}