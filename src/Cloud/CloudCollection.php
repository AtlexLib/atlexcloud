<?php

namespace Atlex\Cloud;


class CloudCollection implements \Iterator
{
    private $container = [];

    /** @var CloudAdapter */
    private $adapter;

    public function __construct($adapter)
    {
        $this->adapter = $adapter;
    }

    public function rewind()
    {
        reset($this->container);
    }

    public function current()
    {
        return current($this->container);
    }

    public function key()
    {
        return key($this->container);
    }

    public function next()
    {
        return next($this->container);
    }

    public function valid()
    {
        $key = key($this->container);
        return ($key !== null && $key !== false);
    }

    public function add($object)
    {
        $this->container[] = $object;
    }

    public function exists($name, $type)
    {
        foreach ($this->container as $object){
            if($object->getName() == $name && $object->getType() == $type)
                return true;
        }
        return false;
    }

    public function downloadTo($path)
    {
        foreach ($this->container as $object){
            $object->downloadTo($path);
        }
    }

    public function delete()
    {
        foreach ($this->container as $object){
            echo "delete " . $object->getPath()."<hr>";
            $object->delete();
        }
        $this->container = [];
    }

}