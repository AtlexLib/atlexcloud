<?php

namespace Atlex\Cloud;


use Atlex\Adapter\CloudAdapter;

/**
 * Class CloudObject
 * @package Atlex\Cloud
 */
class CloudObject
{
    /** @var CloudAdapter */
    private $adapter;

    private $parent;
    private $name;
    private $path;
    private $size;
    private $type;

    public function __construct($name, $type, $parent, $size, $adapter)
    {
        $this->name = $name;
        $this->type = $type;
        $this->parent = $parent;
        if($parent == "/")
        {
            $this->path = $name;
        }else {
            $this->path = $parent . "/" . $name;
        }
        $this->size = $size;
        $this->adapter = $adapter;
    }

    public function downloadTo($path)
    {
        $this->adapter->download($this, $path);
    }

    public function delete()
    {
        $this->adapter->deleteObject($this->path);
    }

    /**
     * {@inheritdoc}
     */
    public function isContainer()
    {
        return $this->type == CloudObjectType::CONTAINER;
    }

    public function isObject()
    {
        return $this->type == CloudObjectType::OBJECT;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getSize()
    {
        return $this->size;
    }

    public function getType()
    {
        return $this->type;
    }



}