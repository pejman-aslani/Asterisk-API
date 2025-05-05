<?php
namespace app\modules\telephony\components;



use RuntimeException;

/**
 * Custom exception for AMI-related errors.
 */
class AmiException extends RuntimeException
{
    public static function connectionFailed(string $message): self
    {
        return new self("Connection failed: $message");
    }

    public static function authenticationFailed(string $message): self
    {
        return new self("Authentication failed: $message");
    }

    public static function actionFailed(string $action, string $message): self
    {
        return new self("Action '$action' failed: $message");
    }
    public static function invalidResponse(string $message): self
    {
        return new self("Invalid response: $message");
    }
}
