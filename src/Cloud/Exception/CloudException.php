<?php

namespace Atlex\Cloud\Exception;


class CloudException extends \Exception
{
    public function __construct($message, $code = 0)
    {
        $this->code = $code;
        $this->message = $message;
    }
}