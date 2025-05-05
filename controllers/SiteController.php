<?php

namespace app\controllers;

use app\components\AsteriskServiceAMI;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;

class SiteController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

    /**
     * Login action.
     *
     * @return Response|string
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }

        $model->password = '';
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Displays contact page.
     *
     * @return Response|string
     */
    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');

            return $this->refresh();
        }
        return $this->render('contact', [
            'model' => $model,
        ]);
    }

    /**
     * Displays about page.
     *
     * @return string
     */
    public function actionAbout()
    {
        return $this->render('about');
    }
    public function actionAmiPing()
    {
        $ami = new \app\modules\telephony\components\AsteriskServiceAMI();
        $ami->connect('192.168.180.7', 2342, 'admin', '123');
    
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
    
    public function actionParseCalls()
    {
        $ami = new AsteriskServiceAMI();
        $ami->connect('192.168.180.7', 2342, 'pejman', '1234');
    
        // دریافت داده‌های تماس
        $response = $ami->command('Core Show Channels concise');
        
        // تجزیه داده‌های AMI
        $calls = $this->parseAMIResponse($response);
    
        $ami->close();
    
        return $this->asJson([
            'calls' => $calls
        ]);
    }
    
    private function parseAMIResponse($response)
    {
        // جدا کردن رویدادها (برای این که تماس‌ها رو از هم جدا کنیم)
        $lines = explode("\n", trim($response));
        $parsedCalls = [];
    
        foreach ($lines as $line) {
            // تجزیه هر خط بر اساس '!'
            $callDetails = explode('!', $line);
    
            if (count($callDetails) >= 10) {
                // آماده‌سازی داده‌ها برای نمایش تمیزتر
                $parsedCalls[] = [
                    'channel'     => $callDetails[0],  // کانال تماس
                    'route'       => $callDetails[1],  // مسیر تماس
                    'extension'   => $callDetails[2],  // شماره تماس
                    'status'      => $callDetails[4],  // وضعیت تماس
                    'callType'    => $callDetails[5],  // نوع تماس
                    'callID'      => $callDetails[9],  // شناسه تماس
                ];
            }
        }
    
        // در صورتی که داده‌ای پیدا نشد
        if (empty($parsedCalls)) {
            return ['message' => 'هیچ تماسی یافت نشد.'];
        }
    
        return $parsedCalls;
    }
    
    public function actionActiveUsers()
{
    try {
        $ami = new AsteriskServiceAMI();
        $connected = $ami->connect('192.168.180.7', 2342, 'pejman', '1234');
        
        if (!$connected) {
            Yii::error('اتصال به AMI برقرار نشد', 'active-users');
            throw new \Exception('اتصال به AMI برقرار نشد');
        }

        // ارسال دستور برای دریافت لیست کاربران فعال
        $response = $ami->command('Core Show Users');

        // پردازش پاسخ
        $lines = explode("\n", trim($response));
        $activeUsers = [];
        
        foreach ($lines as $line) {
            // در اینجا می‌توانید اطلاعات کاربران را بر اساس فرمت دستور پردازش کنید
            $userDetails = explode('!', $line);
            
            if (count($userDetails) >= 4) {
                $user = [
                    'user' => $userDetails[0],   // نام کاربر
                    'status' => $userDetails[1], // وضعیت کاربر
                    'device' => $userDetails[2], // دستگاه
                    'extension' => $userDetails[3] // شماره داخلی
                ];
                $activeUsers[] = $user;
            }
        }

        // بستن اتصال
        $ami->close();

        return $this->asJson([
            'active_users' => $activeUsers
        ]);
    } catch (\Exception $e) {
        Yii::error('خطا در دریافت کاربران فعال: ' . $e->getMessage(), 'active-users');
        return $this->asJson([
            'error' => 'خطا در دریافت کاربران فعال: ' . $e->getMessage()
        ]);
    }
}


}
