<?php

namespace app\modules\jira\notify\models\strategy\comment;

use app\components\telegram\exceptions\ApiException;
use app\components\telegram\exceptions\message\MessageCantBeDeletedException;
use app\models\JiraNotifyMessage;
use app\modules\jira\notify\models\strategy\interfaces\CommentStrategyInterface;

class DeleteCommentStrategy extends BaseCommentStrategy implements CommentStrategyInterface
{
    /** @var string Внешний тип вебхук-события при удалении комментария (урезанный вариант хука). */
    public const ISSUE_WEBHOOK_EVENT_COMMENT_DELETED = 'comment_deleted';

    /** @var string Внешний тип события для issue "Удаление комментария" (полный вариант хука). */
    public const ISSUE_EVENT_TYPE_NAME_COMMENT_DELETED = 'issue_comment_deleted';

    /**
     * Получает все сообщения по комментарию для дальнейшего удаления.
     * @throws \Telegram\Bot\Exceptions\TelegramSDKException
     * @throws \yii\db\Exception
     * @throws \yii\db\StaleObjectException
     */
    public function send(): void
    {
        $messages = JiraNotifyMessage::find()->byOnlyCommentId($this->commentId)->all();
        if ($messages) {
            foreach ($messages as $message) {
                $this->deleteMessage($message);
            }
        }
    }

    /**
     * Удаляем соообщения в Телеграм и в БД.
     * @param JiraNotifyMessage $message
     * @throws \Telegram\Bot\Exceptions\TelegramSDKException
     * @throws \yii\db\Exception
     * @throws \yii\db\StaleObjectException
     */
    private function deleteMessage(JiraNotifyMessage $message): void
    {
        try {
            $this->telegramClient->deleteMessage([
                'chat_id' => $message->telegram_chat_id,
                'message_id' => $message->telegram_message_id
            ]);
            $message->delete();
        } catch (MessageCantBeDeletedException $e) {
            $this->telegramClient->logWarn($e);
        } catch (ApiException $e) {
            $this->telegramClient->logError($e);
        }
    }
}