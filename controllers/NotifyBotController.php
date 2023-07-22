<?php

namespace app\modules\jira\notify\controllers;

use app\components\jira\bots\JiraNotifyBot;
use app\components\jira\objects\models\Update;
use app\helpers\filters\JiraDuplicateRequestFilter;
use app\modules\jira\notify\models\strategy\exceptions\UnsupportedStrategyException;
use app\modules\jira\notify\models\strategy\MessageManagerJiraNotify;
use Throwable;
use Yii;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\web\Controller;

class NotifyBotController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors(): array
    {
        return ArrayHelper::merge(parent::behaviors(), [
            [
                'class' => JiraDuplicateRequestFilter::class,
                'bot' => JiraNotifyBot::CLASS_NAME,
                'only' => ['get-updates']
            ],
            [
                'class' => VerbFilter::class,
                'actions' => ['get-updates' => ['POST']],
            ],
        ]);
    }

    /**
     * Метод работы с вебхуками Jira.
     * @throws Throwable
     */
    public function actionGetUpdates(): string
    {
        try {
            $update = Yii::$app->request->post();
            if (!isset($update['webhookEvent'])) {
                return 'ok';
            }

            $updateObject = new Update($update);
            (new MessageManagerJiraNotify($updateObject))->handle();
        } catch (UnsupportedStrategyException $e) {
            Yii::warning($e->getMessage(), 'jira\notify\unsupported');
        } catch (Throwable $e) {
            Yii::error($e, 'jira\notify\error');
        }

        return 'ok';
    }
}