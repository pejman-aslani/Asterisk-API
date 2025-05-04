<?php

namespace app\modules\telephony\components;

use yii\base\Component;

class AsteriskServiceARI extends Component
{
    // متغیرها برای اتصال به ARI
    public $host;
    public $port;
    public $username;
    public $password;

    // کانکشن ARI
    private $connection;

    public function init()
    {
        parent::init();
        // اتصال به ARI
        $this->connection = new ($this->host, $this->port, $this->username, $this->password);
    }

    // عملیات‌هایی مانند برقراری تماس، قطع تماس و...
    public function createChannel($channelParams)
    {
        return $this->connection->channel->create($channelParams);
    }
}
