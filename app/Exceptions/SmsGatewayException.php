<?php

// app/Exceptions/SmsGatewayException.php
namespace App\Exceptions;

use Illuminate\Http\Client\Response;

class SmsGatewayException extends \Exception
{
    public ?Response $response;

    public function __construct(string $message = "", int $code = 0, ?Response $response = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }
}
