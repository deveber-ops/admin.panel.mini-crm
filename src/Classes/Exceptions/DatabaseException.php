<?php
namespace App\Classes\Exceptions;

class DatabaseException extends \RuntimeException
{
    private int $errorCode;
    private string $sqlState;

    public function __construct(string $message, int $errorCode = 0, string $sqlState = '')
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->sqlState = $sqlState;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getSqlState(): string
    {
        return $this->sqlState;
    }
}