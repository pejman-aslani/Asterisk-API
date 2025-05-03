<?php

namespace app\components;

use Yii;
class AsteriskServiceAMI
{
    private $socket;
    public function connect($host, $port, $username, $password)
    {
        try {
            // تلاش برای اتصال به AMI
            $this->socket = fsockopen($host, $port, $errno, $errstr, 10);
            
            if (!$this->socket) {
                // در صورت عدم اتصال
                Yii::error("خطا در اتصال به AMI: $errstr ($errno)", 'live-calls');
                return false;
            }
            // ارسال اطلاعات ورود به AMI
            $login = "Action: Login\r\nUsername: $username\r\nSecret: $password\r\n\r\n";
            fwrite($this->socket, $login);
            // خواندن جواب از AMI
            $response = fgets($this->socket);
            Yii::info('Response from AMI: ' . $response, 'live-calls');
            if (strpos($response, 'Authentication accepted') !== false) {
                return true;  // اتصال برقرار است
            } else {
                Yii::error("اطلاعات ورود اشتباه است: " . $response, 'live-calls');
                return false;
            }
        } catch (\Exception $e) {
            Yii::error('خطا در اتصال به AMI: ' . $e->getMessage(), 'live-calls');
            return false;
        }
    }
    
    

    public function send($cmd)
    {
        fwrite($this->socket, $cmd);
    }

    public function read()
    {
        $response = '';
        while (!feof($this->socket)) {
            $line = fgets($this->socket);
            $response .= $line;
            if (trim($line) === '') break;
        }
        return $response;
    }

    public function command($action, $params = [])
    {
        $cmd = "Action: $action\r\n";
        foreach ($params as $key => $val) {
            $cmd .= "$key: $val\r\n";
        }
        $cmd .= "\r\n";
        $this->send($cmd);
        return $this->read();
    }

    public function close()
    {
        fclose($this->socket);
    }
    
    

    public function getActiveCalls()
    {
        // ارسال دستور به Asterisk
        $response = $this->command('Core Show Channels concise');
        
        // اگر خروجی خالی است، به معنی عدم وجود تماس‌هاست
        if (empty($response)) {
            Yii::info('هیچ تماسی در حال حاضر وجود ندارد.', 'live-calls');
            return [];
        }
    
        // تفکیک خطوط برای دریافت هر تماس
        $lines = explode("\n", trim($response));
        $calls = [];
    
        // پردازش هر خط
        foreach ($lines as $line) {
            // جدا کردن اطلاعات هر تماس با استفاده از '!'
            $callDetails = explode('!', $line);
    
            // در صورتی که اطلاعات به اندازه کافی باشند، آنها را پردازش می‌کنیم
            if (count($callDetails) >= 10) {
                $call = [
                    'channel' => $callDetails[0],  // کانال تماس
                    'route' => $callDetails[1],    // مسیر تماس
                    'extension' => $callDetails[2], // شماره تماس
                    'status' => $callDetails[4],   // وضعیت تماس
                    'callType' => $callDetails[5], // نوع تماس (Dial)
                    'callID' => $callDetails[9]    // شناسه تماس
                ];
    
                // اضافه کردن تماس به آرایه
                $calls[] = $call;
            }
        }
    
        // برگرداندن اطلاعات تماس‌ها
        return $calls;
    }
    
    
}