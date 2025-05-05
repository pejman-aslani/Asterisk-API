<?php

namespace app\commands;

use yii\console\Controller;
use yii\console\ExitCode;
use app\modules\telephony\components\AsteriskServiceAMI;

class AsteriskController extends Controller
{
    protected $asteriskService;

    public function __construct($id, $module, AsteriskServiceAMI $asteriskService, $config = [])
    {
        $this->asteriskService = $asteriskService;
        parent::__construct($id, $module, $config);
    }

    public function actionListenCdr()
    {
        try {
            // Get AMI configuration from environment
            $host = $_ENV['ASTERISK_AMI_HOST'] ?? die("Missing ASTERISK_AMI_HOST in .env.asterisk\n");
            $port = $_ENV['ASTERISK_AMI_PORT'] ?? die("Missing ASTERISK_AMI_PORT in .env.asterisk\n");
            $username = $_ENV['ASTERISK_AMI_USERNAME'] ?? die("Missing ASTERISK_AMI_USERNAME in .env.asterisk\n");
            $password = $_ENV['ASTERISK_AMI_PASSWORD'] ?? die("Missing ASTERISK_AMI_PASSWORD in .env.asterisk\n");
            // Validate configuration

            if (empty($host) || empty($port) || empty($username) || empty($password)) {
                throw new \RuntimeException('Missing one or more required Asterisk AMI configuration parameters');
            }

            if ($this->asteriskService->connect($host, $port, $username, $password)) {
                $this->stdout("Successfully connected to AMI...\n");
                $this->asteriskService->listenToCdrEvents();
            } else {
                $this->stderr("Failed to connect to AMI. Check your credentials and connection settings.\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }

            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Error: " . $e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}