<?php

namespace app\modules\telephony\components;

use Yii;

class AsteriskServiceAMI
{
    private $socket;
    private $timeout = 10; // تایم‌اوت مثل کد قدیمی

    /**
     * Connect to Asterisk AMI.
     *
     * @param string $host
     * @param int $port
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function connect($host, $port, $username, $password)
    {
        try {
            $this->socket = @fsockopen($host, $port, $errno, $errstr, $this->timeout);
            if (!$this->socket) {
                Yii::error("خطا در اتصال به AMI: $errstr ($errno)", 'live-calls');
                return false;
            }

            // ارسال دستور Login
            $login = "Action: Login\r\nUsername: $username\r\nSecret: $password\r\n\r\n";
            fwrite($this->socket, $login);

            // خواندن فقط یک خط از پاسخ (مثل کد قدیمی)
            $response = fgets($this->socket);
            Yii::info("Login Response: $response", 'live-calls');

            // برای دیباگ، پاسخ کامل رو هم بخونیم
            $fullResponse = $response;
            while (!feof($this->socket)) {
                $line = fgets($this->socket);
                $fullResponse .= $line;
                if (trim($line) === '') {
                    break;
                }
            }
            Yii::info("Full Response: $fullResponse", 'live-calls');

            if (strpos($response, 'Authentication accepted') !== false) {
                return true;
            } else {
                Yii::error("اطلاعات ورود اشتباه است: $response", 'live-calls');
                return false;
            }
        } catch (\Exception $e) {
            Yii::error('خطا در اتصال به AMI: ' . $e->getMessage(), 'live-calls');
            return false;
        }
    }

    /**
     * Send a command to AMI.
     *
     * @param string $cmd
     * @return bool
     */
    public function send($cmd)
    {
        if (!$this->isSocketActive()) {
            Yii::error('Socket is not active', 'live-calls');
            return false;
        }
        fwrite($this->socket, $cmd);
        return true;
    }

    /**
     * Read response from AMI.
     *
     * @return string
     */
    public function read()
    {
        if (!$this->isSocketActive()) {
            Yii::error('Socket is not active', 'live-calls');
            return '';
        }

        $response = '';
        $timeout = time() + $this->timeout;
        while (!feof($this->socket) && time() < $timeout) {
            $line = fgets($this->socket);
            if ($line === false) {
                break;
            }
            $response .= $line;
            if (trim($line) === '') {
                break;
            }
        }
        return $response;
    }

    /**
     * Execute an AMI command.
     *
     * @param string $action
     * @param array $params
     * @return string
     */
    public function command($action, $params = [])
    {
        $actionId = uniqid('ami_');
        $cmd = "Action: $action\r\nActionID: $actionId\r\n";
        foreach ($params as $key => $val) {
            $cmd .= "$key: $val\r\n";
        }
        $cmd .= "\r\n";

        if ($this->send($cmd)) {
            $response = $this->read();
            if (strpos($response, "ActionID: $actionId") === false) {
                Yii::warning("Response ActionID mismatch for action: $action", 'live-calls');
            }
            return $response;
        }
        return '';
    }

    /**
     * Close the AMI connection.
     */
    public function close()
    {
        if ($this->isSocketActive()) {
            fclose($this->socket);
        }
    }

    /**
     * Destructor to ensure socket is closed.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Check if socket is active.
     *
     * @return bool
     */
    private function isSocketActive()
    {
        return is_resource($this->socket) && !feof($this->socket);
    }

    /**
     * Parse AMI event data.
     *
     * @param string $raw
     * @return array
     */
    private function parseAmiEvent($raw)
    {
        $lines = explode("\n", trim($raw));
        $data = [];

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $data[trim($key)] = trim($value);
            }
        }

        return $data;
    }

    /**
     * Save CDR to database.
     *
     * @param array $cdr
     */
    private function saveCdrToDatabase($cdr)
    {
        try {
            Yii::$app->db->createCommand()->insert('tbl_call_logs', [
                'call_id' => $cdr['UniqueID'] ?? '',
                'direction' => (strpos(strtolower($cdr['Channel'] ?? ''), 'sip') !== false ? 'outbound' : 'inbound'),
                'source' => $cdr['Source'] ?? '',
                'destination' => $cdr['Destination'] ?? '',
                'started_at' => $cdr['StartTime'] ?? date('Y-m-d H:i:s'),
                'answered_at' => $cdr['AnswerTime'] ?? null,
                'ended_at' => $cdr['EndTime'] ?? date('Y-m-d H:i:s'),
                'duration' => $cdr['Duration'] ?? 0,
                'billsec' => $cdr['BillableSeconds'] ?? 0,
                'status' => strtolower($cdr['Disposition'] ?? 'no-answer'),
                'channel' => $cdr['Channel'] ?? '',
                'recording_url' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ])->execute();

            Yii::info("CDR saved for call_id: " . ($cdr['UniqueID'] ?? '-'), 'cdr');
        } catch (\Throwable $e) {
            Yii::error("خطا در ذخیره CDR: " . $e->getMessage(), 'cdr');
        }
    }

    /**
     * Listen to CDR events and save them to database.
     */
    public function listenToCdrEvents()
    {
        if (!$this->isSocketActive()) {
            Yii::error('AMI connection not established.', 'cdr');
            return;
        }

        stream_set_timeout($this->socket, 0, 300000); // Non-blocking

        while (!feof($this->socket)) {
            $eventData = '';
            while (($line = fgets($this->socket)) !== false) {
                if (trim($line) === '') {
                    break;
                }
                $eventData .= $line;
            }

            if (stripos($eventData, "Event: Cdr") !== false) {
                $parsed = $this->parseAmiEvent($eventData);
                $this->saveCdrToDatabase($parsed);
            }
        }
    }

    /**
     * Get active calls.
     *
     * @return array
     */
    public function getActiveCalls()
    {
        $response = $this->command('Core Show Channels concise');
        if (empty($response)) {
            Yii::info('هیچ تماسی در حال حاضر وجود ندارد.', 'live-calls');
            return [];
        }

        $lines = explode("\n", trim($response));
        $calls = [];

        foreach ($lines as $line) {
            $callDetails = explode('!', $line);
            if (count($callDetails) >= 10) {
                $calls[] = [
                    'channel' => $callDetails[0],
                    'route' => $callDetails[1],
                    'extension' => $callDetails[2],
                    'status' => $callDetails[4],
                    'callType' => $callDetails[5],
                    'callID' => $callDetails[9],
                ];
            }
        }

        return $calls;
    }

    /**
     * Get SIP peers status.
     *
     * @return array
     */
    public function getPeers()
    {
        $response = $this->command('SIP show peers');
        $lines = explode("\n", trim($response));
        $peers = [];

        foreach ($lines as $line) {
            if (preg_match('/^(\S+)\s+(\d+\.\d+\.\d+\.\d+)?\s+.*\s+(OK|UNREACHABLE|UNKNOWN|Lagged)/', $line, $matches)) {
                $peers[] = [
                    'peer' => $matches[1],
                    'ip' => $matches[2] ?? '',
                    'status' => $matches[3],
                ];
            }
        }

        return $peers;
    }

    /**
     * Originate a call.
     *
     * @param string $channel
     * @param string $exten
     * @param string $context
     * @param int $priority
     * @param string $callerId
     * @param int $timeout
     * @param array $variables
     * @return string
     */
    public function originateCall($channel, $exten, $context = 'default', $priority = 1, $callerId = 'ServerCall', $timeout = 30000, $variables = [])
    {
        $params = [
            'Channel' => $channel,
            'Exten' => $exten,
            'Context' => $context,
            'Priority' => $priority,
            'CallerID' => $callerId,
            'Timeout' => $timeout,
        ];

        if (!empty($variables)) {
            $vars = [];
            foreach ($variables as $key => $val) {
                $vars[] = "$key=$val";
            }
            $params['Variable'] = implode('|', $vars);
        }

        return $this->command('Originate', $params);
    }

    /**
     * Hang up a channel.
     *
     * @param string $channel
     * @return string
     */
    public function hangupChannel($channel)
    {
        return $this->command('Hangup', ['Channel' => $channel]);
    }

    /**
     * Get channel information.
     *
     * @param string $channel
     * @return string
     */
    public function getChannelInfo($channel)
    {
        return $this->command('Status', ['Channel' => $channel]);
    }

    /**
     * Get queue status.
     *
     * @param string|null $queue
     * @return string
     */
    public function getQueueStatus($queue = null)
    {
        $params = $queue ? ['Queue' => $queue] : [];
        return $this->command('QueueStatus', $params);
    }

    /**
     * Get Asterisk core status.
     *
     * @return string
     */
    public function getAsteriskInfo()
    {
        return $this->command('CoreStatus');
    }

    /**
     * Get list of conference bridge rooms.
     *
     * @return string
     */
    public function getConfBridgeList()
    {
        return $this->command('ConfbridgeListRooms');
    }

    /**
     * Play DTMF to a channel.
     *
     * @param string $channel
     * @param string $digit
     * @return string
     */
    public function sendDTMF($channel, $digit)
    {
        return $this->command('PlayDTMF', [
            'Channel' => $channel,
            'Digit' => $digit,
        ]);
    }

    /**
     * Start monitoring a channel.
     *
     * @param string $channel
     * @param string $file
     * @param string $format
     * @return string
     */
    public function startMonitor($channel, $file, $format = 'wav')
    {
        return $this->command('Monitor', [
            'Channel' => $channel,
            'File' => $file,
            'Format' => $format,
            'Mix' => '1',
        ]);
    }

    /**
     * Stop monitoring a channel.
     *
     * @param string $channel
     * @return string
     */
    public function stopMonitor($channel)
    {
        return $this->command('StopMonitor', ['Channel' => $channel]);
    }

    /**
     * Send a SIP message.
     *
     * @param string $to
     * @param string $from
     * @param string $body
     * @return string
     */
    public function sendMessage($to, $from, $body)
    {
        return $this->command('MessageSend', [
            'To' => "sip:$to",
            'From' => "sip:$from",
            'Body' => $body,
        ]);
    }

    /**
     * Get trunks status.
     *
     * @return array
     */
    public function getTrunksStatus()
    {
        $sipTrunks = $this->command('SIP show registry');
        $iaxTrunks = $this->command('IAX2 show registry');
        return [
            'sip' => $sipTrunks,
            'iax' => $iaxTrunks,
        ];
    }

    /**
     * Hang up a channel with a specific cause.
     *
     * @param string $channel
     * @param int $cause
     * @return string
     */
    public function hangupWithCause($channel, $cause)
    {
        return $this->command('Hangup', [
            'Channel' => $channel,
            'Cause' => $cause,
        ]);
    }

    /**
     * Redirect a call.
     *
     * @param string $channel
     * @param string $context
     * @param string $exten
     * @param int $priority
     * @return string
     */
    public function redirectCall($channel, $context, $exten, $priority = 1)
    {
        return $this->command('Redirect', [
            'Channel' => $channel,
            'Context' => $context,
            'Exten' => $exten,
            'Priority' => $priority,
        ]);
    }

    /**
     * Get CDR status.
     *
     * @return string
     */
    public function getCDRList()
    {
        return $this->command('Command', ['Command' => 'cdr show status']);
    }

    /**
     * Log a message to Asterisk console.
     *
     * @param string $level
     * @param string $message
     * @return string
     */
    public function logToAsteriskConsole($level, $message)
    {
        return $this->command('Command', [
            'Command' => "logger log $level \"$message\"",
        ]);
    }

    /**
     * Get recent call history.
     *
     * @param int $limit
     * @return string
     */
    public function getCallHistory($limit = 50)
    {
        return $this->command('Command', ['Command' => "cdr show last $limit"]);
    }

    /**
     * Find an active call by phone number.
     *
     * @param string $phoneNumber
     * @return array|null
     */
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

    /**
     * Get agent status.
     *
     * @param string $agentChannel
     * @return string
     */
    public function getAgentStatus($agentChannel)
    {
        return $this->command('QueueMemberStatus', ['Interface' => $agentChannel]);
    }

    /**
     * Count active calls.
     *
     * @return int
     */
    public function countActiveCalls()
    {
        return count($this->getActiveCalls());
    }

    /**
     * Direct a call to an agent.
     *
     * @param string $customerNumber
     * @param string $agentExtension
     * @return string
     */
    public function directCallToAgent($customerNumber, $agentExtension)
    {
        $channel = "SIP/$agentExtension";
        return $this->originateCall($channel, $customerNumber, 'from-internal', 1, 'AgentCall');
    }

    /**
     * Notify an agent with a message.
     *
     * @param string $agentExtension
     * @param string $message
     * @return string
     */
    public function notifyAgent($agentExtension, $message)
    {
        return $this->sendMessage($agentExtension, 'CRM', $message);
    }

    /**
     * Get queue summary.
     *
     * @return string
     */
    public function getQueueSummary()
    {
        return $this->command('QueueSummary');
    }

    /**
     * Get calls in a specific queue.
     *
     * @param string $queue
     * @return string
     */
    public function getCallsInQueue($queue)
    {
        return $this->command('QueueStatus', ['Queue' => $queue]);
    }

    /**
     * Check if a customer is in a call.
     *
     * @param string $phoneNumber
     * @return bool
     */
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

    /**
     * Get active calls for a customer.
     *
     * @param string $phoneNumber
     * @return array
     */
    public function getCustomerActiveCalls($phoneNumber)
    {
        $calls = $this->getActiveCalls();
        return array_filter($calls, function ($call) use ($phoneNumber) {
            return strpos($call['extension'], $phoneNumber) !== false;
        });
    }

    /**
     * Get waiting calls in a queue.
     *
     * @param string $queue
     * @return string
     */
    public function getWaitingCallsInQueue($queue)
    {
        return $this->getQueueStatus($queue);
    }

    /**
     * Get queue members.
     *
     * @param string $queue
     * @return string
     */
    public function getQueueMembers($queue)
    {
        return $this->command('Command', ['Command' => "queue show $queue"]);
    }

    /**
     * Add a member to a queue.
     *
     * @param string $queue
     * @param string $interface
     * @param int $penalty
     * @return string
     */
    public function addMemberToQueue($queue, $interface, $penalty = 0)
    {
        return $this->command('QueueAdd', [
            'Queue' => $queue,
            'Interface' => $interface,
            'Penalty' => $penalty,
        ]);
    }

    /**
     * Remove a member from a queue.
     *
     * @param string $queue
     * @param string $interface
     * @return string
     */
    public function removeMemberFromQueue($queue, $interface)
    {
        return $this->command('QueueRemove', [
            'Queue' => $queue,
            'Interface' => $interface,
        ]);
    }

    /**
     * Set penalty for a queue member.
     *
     * @param string $queue
     * @param string $interface
     * @param int $penalty
     * @return string
     */
    public function setPenaltyForMember($queue, $interface, $penalty)
    {
        return $this->command('QueuePenalty', [
            'Queue' => $queue,
            'Interface' => $interface,
            'Penalty' => $penalty,
        ]);
    }

    /**
     * Pause a queue member.
     *
     * @param string $interface
     * @param string $reason
     * @return string
     */
    public function pauseQueueMember($interface, $reason = 'Break')
    {
        return $this->command('QueuePause', [
            'Interface' => $interface,
            'Paused' => 'true',
            'Reason' => $reason,
        ]);
    }

    /**
     * Unpause a queue member.
     *
     * @param string $interface
     * @return string
     */
    public function unpauseQueueMember($interface)
    {
        return $this->command('QueuePause', [
            'Interface' => $interface,
            'Paused' => 'false',
        ]);
    }

    /**
     * Check if a queue member is paused.
     *
     * @param string $queue
     * @param string $interface
     * @return bool
     */
    public function isQueueMemberPaused($queue, $interface)
    {
        $output = $this->getQueueMembers($queue);
        return strpos($output, "$interface (paused)") !== false;
    }

    /**
     * Get queues a member is part of.
     *
     * @param string $interface
     * @return array
     */
    public function getQueuesOfMember($interface)
    {
        $queues = ['sales', 'support', 'billing']; // TODO: Make dynamic
        $result = [];

        foreach ($queues as $queue) {
            $output = $this->getQueueMembers($queue);
            if (strpos($output, $interface) !== false) {
                $result[] = $queue;
            }
        }

        return $result;
    }

    /**
     * List all queues.
     *
     * @return string
     */
    public function listQueues()
    {
        return $this->command('Command', ['Command' => 'queue show']);
    }
}