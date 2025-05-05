<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';
$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__), '.env.asterisk');
$dotenv->safeLoad();
$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'modules' => [
        'telephony' => [
            'class' => 'app\modules\telephony\Telephony',
        ],
    ],
    'components' => [
        'asteriskService' => [
            'class' => 'app\modules\telephony\components\AsteriskServiceAMI',
            'host' => '127.0.0.1', // Your Asterisk server IP
            'port' => 5038,        // AMI port
            'username' => 'admin', // AMI username
            'secret' => 'your_password', // AMI password
            'timeout' => 5,
        ],
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'w4PFyJ1JMbaTA6ojuqzO-q-CZvomCPPm',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning', 'info'],
                    'categories' => ['ami', 'cdr', 'live-calls', 'telephony'],
                    'logFile' => '@runtime/logs/asterisk.log', // فایل لاگ جداگانه
                    'maxFileSize' => 10240, // حداکثر سایز فایل 10MB
                    'maxLogFiles' => 5, // حداکثر 5 فایل چرخشی
                    'logVars' => [], // غیرفعال کردن متغیرهای اضافی
                ],
            ],
        ],
        'db' => $db,

        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],

    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
