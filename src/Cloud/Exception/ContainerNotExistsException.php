<?php
/**
 * Created by PhpStorm.
 * User: nix
 * Date: 12/13/18
 * Time: 1:14 PM
 */

namespace Atlex\Cloud\Exception;


class ContainerNotExistsException extends CloudException
{
    protected $message = "Incorrect object name, use only lower case characters";

    public function __construct($name)
    {
        $this->message = "Container " . $name . " not exists";
    }
}