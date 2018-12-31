<?php

namespace Atlex\Cloud\Exception;


class IncorrectPathException extends CloudException
{
    protected $message;

    public function __construct($path)
    {
        $this->message = "Path not found: " . $path;
    }
}