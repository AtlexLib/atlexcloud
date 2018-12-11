<?php

namespace Atlex;

use Atlex\Adapter\CloudAdapter;


class AtlexCloud extends CloudAdapter
{
    private $adapter;
    public function __construct($adapter)
    {
        $this->adapter = $adapter;
    }

    public function loadContainers()
    {
        return $this->adapter->loadContainers();
    }

    public function loadObjects($path)
    {
        return $this->adapter->loadObjects($path);
    }

    public function createContainer($name)
    {
        $this->adapter->createContainer($name);
    }

    public function createContainerSub($name)
    {
        $this->adapter->createContainerSub($name);
    }

    public function setObject($path, $content)
    {
        $this->adapter->setObject($path, $content);
    }

    public function getObject($path)
    {
        $this->adapter->getObject($path);
    }

    public function list($path = "")
    {
        return $this->adapter->list($path);
    }

    public function listAll($path)
    {
        return $this->adapter->listAll($path);
    }

    public function download($object, $localDir)
    {
        return $this->adapter->download($object, $localDir);
    }


}