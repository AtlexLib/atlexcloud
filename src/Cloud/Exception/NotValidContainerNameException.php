<?php

namespace Atlex\Cloud\Exception;


class NotValidContainerNameException extends CloudException
{

    protected $message = "Incorrect container name, use only alphanumeric lower case characters, available symbols \"-\" and  \".\" minimum length 3 symbols";

    public function __construct($message = null, $code = 0, Exception $previous = null)
    {

    }
}