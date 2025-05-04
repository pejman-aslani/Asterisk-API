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

    public function getPeers()
    {
        $response = $this->command('SIP show peers');
        $lines = explode("\n", $response);
        $peers = [];

        foreach ($lines as $line) {
            if (preg_match('/^(\S+)\s+(\d+\.\d+\.\d+\.\d+)?\s+.*\s+(OK|UNREACHABLE|UNKNOWN|Lagged)/', $line, $matches)) {
                $peers[] = [
                    'peer' => $matches[1],
                    'ip' => $matches[2] ?? '',
                    'status' => $matches[3]
                ];
            }
        }

        return $peers;
    }
    public function originateCall($channel, $exten, $context = 'default', $priority = 1, $callerId = 'ServerCall', $timeout = 30000, $variables = [])
    {
        $params = [
            'Channel' => $channel,          // مثال: SIP/101
            'Exten' => $exten,              // شماره مقصد (مثلاً 102 یا شماره موبایل)
            'Context' => $context,
            'Priority' => $priority,
            'CallerID' => $callerId,
            'Timeout' => $timeout,
        ];

        // اگر متغیر خاصی بخواهیم ارسال کنیم
        if (!empty($variables)) {
            $vars = [];
            foreach ($variables as $key => $val) {
                $vars[] = "$key=$val";
            }
            $params['Variable'] = implode('|', $vars);
        }

        $response = $this->command('Originate', $params);
        return $response;
    }
    public function hangupChannel($channel)
    {
        $params = [
            'Channel' => $channel
        ];

        $response = $this->command('Hangup', $params);
        return $response;
    }
    public function getChannelInfo($channel)
    {
        $params = ['Channel' => $channel];
        $response = $this->command('ChannelStatus', $params);
        return $response;
    }

    public function getQueueStatus($queue = null)
    {
        $params = [];
        if ($queue) {
            $params['Queue'] = $queue;
        }

        $response = $this->command('QueueStatus', $params);
        return $response;
    }
    public function getAsteriskInfo()
    {
        return $this->command('CoreStatus');
    }
    public function getConfBridgeList()
    {
        return $this->command('ConfbridgeListRooms');
    }
    public function playbackToChannel($channel, $soundFile)
    {
        $params = [
            'Channel' => $channel,
            'File' => $soundFile // مثل: "demo-congrats"
        ];

        return $this->command('PlayDTMF', $params);
    }
    public function sendDTMF($channel, $digit)
    {
        $params = [
            'Channel' => $channel,
            'Digit' => $digit
        ];

        return $this->command('PlayDTMF', $params);
    }
    public function startMonitor($channel, $file, $format = 'wav')
    {
        $params = [
            'Channel' => $channel,
            'File' => $file,
            'Format' => $format,
            'Mix' => '1' // میکس ورودی و خروجی
        ];

        return $this->command('Monitor', $params);
    }
    public function stopMonitor($channel)
    {
        $params = ['Channel' => $channel];
        return $this->command('StopMonitor', $params);
    }
    public function sendMessage($to, $from, $body)
    {
        $params = [
            'To' => "sip:$to",
            'From' => "sip:$from",
            'Body' => $body
        ];

        return $this->command('MessageSend', $params);
    }
    public function getTrunksStatus()
    {
        $sipTrunks = $this->command('SIP show registry');
        $iaxTrunks = $this->command('IAX2 show registry');

        return [
            'sip' => $sipTrunks,
            'iax' => $iaxTrunks
        ];
    }
    public function hangupWithCause($channel, $cause)
    {
        $params = [
            'Channel' => $channel,
            'Cause' => $cause // مثلاً 16 برای “normal clearing”
        ];

        return $this->command('Hangup', $params);
    }
    public function redirectCall($channel, $context, $exten, $priority = 1)
    {
        $params = [
            'Channel' => $channel,
            'Context' => $context,
            'Exten' => $exten,
            'Priority' => $priority
        ];

        return $this->command('Redirect', $params);
    }
    public function getCDRList()
    {
        return $this->command('Command', ['Command' => 'cdr show status']);
    }
    public function logToAsteriskConsole($level, $message)
    {
        return $this->command('Command', [
            'Command' => "logger log $level \"$message\""
        ]);
    }
    public function getCallHistory($limit = 50)
    {
        $cmd = "Command: cdr show last $limit\r\n";
        $this->send($cmd);
        return $this->read();
    }
    public function findActiveCallByNumber($phoneNumber)
    {
        $calls = $this->getActiveCalls();
        foreach ($calls as $call) {
            if (strpos($call['extension'], $phoneNumber) !== false) {
                return $call;
            }
        }
        return null;
    }
    public function getAgentStatus($agentChannel)
    {
        return $this->command('QueueMemberStatus', ['Interface' => $agentChannel]);
    }
    public function countActiveCalls()
    {
        $calls = $this->getActiveCalls();
        return count($calls);
    }
    public function directCallToAgent($customerNumber, $agentExtension)
    {
        $channel = "SIP/$agentExtension";
        return $this->originateCall($channel, $customerNumber, 'from-internal', 1, "AgentCall");
    }
    public function notifyAgent($agentExtension, $message)
    {
        return $this->sendMessage($agentExtension, 'CRM', $message);
    }
    public function getQueueSummary()
    {
        return $this->command('QueueSummary');
    }
    public function getCallsInQueue($queue)
    {
        return $this->command('QueueStatus', ['Queue' => $queue]);
    }
    public function isCustomerInCall($phoneNumber)
    {
        $calls = $this->getActiveCalls();
        foreach ($calls as $call) {
            if (strpos($call['extension'], $phoneNumber) !== false) {
                return true;
            }
        }
        return false;
    }
    public function getCustomerActiveCalls($phoneNumber)
    {
        $calls = $this->getActiveCalls();
        return array_filter($calls, function ($call) use ($phoneNumber) {
            return strpos($call['extension'], $phoneNumber) !== false;
        });
    }
    public function getWaitingCallsInQueue($queue)
    {
        $status = $this->getQueueStatus($queue);
        // پردازش خروجی با توجه به فرمت CLI
        return $status; // در صورت نیاز می‌تونیم پردازش کنیم
    }
    public function getQueueMembers($queue)
    {
        return $this->command('Command', ['Command' => "queue show $queue"]);
    }
    public function addMemberToQueue($queue, $interface, $penalty = 0)
    {
        return $this->command('QueueAdd', [
            'Queue' => $queue,
            'Interface' => $interface,
            'Penalty' => $penalty
        ]);
    }
    public function removeMemberFromQueue($queue, $interface)
    {
        return $this->command('QueueRemove', [
            'Queue' => $queue,
            'Interface' => $interface
        ]);
    }
    public function setPenaltyForMember($queue, $interface, $penalty)
    {
        return $this->command('QueuePenalty', [
            'Queue' => $queue,
            'Interface' => $interface,
            'Penalty' => $penalty
        ]);
    }
    public function pauseQueueMember($interface, $reason = 'Break')
    {
        return $this->command('QueuePause', [
            'Interface' => $interface,
            'Paused' => 'true',
            'Reason' => $reason
        ]);
    }
    public function unpauseQueueMember($interface)
    {
        return $this->command('QueuePause', [
            'Interface' => $interface,
            'Paused' => 'false'
        ]);
    }
    public function isQueueMemberPaused($queue, $interface)
    {
        $output = $this->getQueueMembers($queue);
        return (strpos($output, "$interface (paused)") !== false);
    }
    public function getQueuesOfMember($interface)
    {
        $queues = ['sales', 'support', 'billing']; // در صورت نیاز داینامیک کن
        $result = [];

        foreach ($queues as $queue) {
            $output = $this->getQueueMembers($queue);
            if (strpos($output, $interface) !== false) {
                $result[] = $queue;
            }
        }

        return $result;
    }
    public function listQueues()
    {
        $output = $this->command('Command', ['Command' => 'queue show']);
        // می‌تونی خروجی رو با regex پردازش کنی و نام صف‌ها رو استخراج کنی
        return $output;
    }
}
