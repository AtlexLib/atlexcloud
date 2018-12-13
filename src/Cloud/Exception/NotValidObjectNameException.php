<?php

namespace Atlex\Cloud\Exception;


class NotValidObjectNameException extends CloudException
{
    protected $message = "Incorrect object name, use only lower case characters";

    public function __construct($message = null, $code = 0, Exception $previous = null)
    {

    }
}