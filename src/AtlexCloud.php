<?php

namespace Atlex;

use Atlex\Adapter\CloudAdapter;



class AtlexCloud extends CloudAdapter
{
    private $adapter;


    /**
     * {@inheritdoc}
     */
    public function __construct($adapter)
    {
        $this->adapter = $adapter;
    }



    /**
     * {@inheritdoc}
     */
    public function createContainer($name)
    {
        $this->adapter->createContainer($name);
    }

    /**
     * {@inheritdoc}
     */
    public function setObject($path, $content, $createContainer = true)
    {
        $this->adapter->setObject($path, $content, $createContainer);
    }

    /**
     * {@inheritdoc}
     */
    public function getObject($path, $handle = null)
    {
        return $this->adapter->getObject($path, $handle);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteObject($path)
    {
        $this->adapter->deleteObject($path);
    }

    /**
     * {@inheritdoc}
     */
    public function get($path = "")
    {
        return $this->adapter->get($path);
    }


    /**
     * {@inheritdoc}
     */
    public function download($object, $localDir)
    {
        return $this->adapter->download($object, $localDir);
    }

    /**
     * {@inheritdoc}
     */
    public function upload($localPath, $remotePath)
    {
        return $this->adapter->upload($localPath, $remotePath);
    }


}