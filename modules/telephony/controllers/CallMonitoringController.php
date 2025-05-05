<?php

namespace app\modules\telephony\controllers;

use Yii;
use yii\rest\Controller;
use yii\web\Response;
use app\modules\telephony\components\AsteriskServiceAMI;

class CallMonitoringController extends Controller
{
    public $enableCsrfValidation = false;

    private AsteriskServiceAMI $ami;

    public function __construct($id, $module, AsteriskServiceAMI $ami, $config = [])
    {
        $this->ami = $ami;
        parent::__construct($id, $module, $config);
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // خروجی JSON
        $behaviors['contentNegotiator']['formats']['application/json'] = Response::FORMAT_JSON;

        return $behaviors;
    }

    public function actionIndex()
    {
        try {
            // Connect using .env settings or fallback to configured defaults
            $this->ami->connect(
                $_ENV['ASTERISK_AMI_HOST'] ?? null,
                $_ENV['ASTERISK_AMI_PORT'] ? (int)$_ENV['ASTERISK_AMI_PORT'] : null,
                $_ENV['ASTERISK_AMI_USERNAME'] ?? null,
                $_ENV['ASTERISK_AMI_PASSWORD'] ?? null
            );
            // Fetch live calls
            $calls = $this->ami->getActiveCalls();
            \Yii::info('Retrieved ' . count($calls) . ' active calls', 'ami');

            return [
                'success' => true,
                'calls' => $calls
            ];
        } catch (\Throwable $e) {
            \Yii::error("Failed to retrieve live calls: {$e->getMessage()}", 'ami');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        } finally {
            $this->ami->disconnect();
        }
    }
    public function actionAmiPing()
    {
        $ami = new AsteriskServiceAMI();
        $ami->connect('192.168.180.7', 2342, 'pejman', '7799');

        $ping = $ami->command('Ping');
        $queues = $ami->command('QueueSummary');

        // دریافت تماس‌های فعال
        $calls = $ami->command('Core Show Channels concise');
        Yii::info('Active Calls: ' . $calls, 'live-calls');

        $ami->close();

        return $this->asJson([
            'ping' => $ping,
            'queues' => $queues,
            'calls' => $calls  // اضافه کردن تماس‌ها
        ]);
    }
}
