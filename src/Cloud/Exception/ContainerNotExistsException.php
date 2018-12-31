<?php

namespace Atlex\Cloud\Exception;


class ContainerNotExistsException extends CloudException
{
    protected $message;

    public function __construct($name)
    {
        $this->message = "Container " . $name . " not exists";
    }

}