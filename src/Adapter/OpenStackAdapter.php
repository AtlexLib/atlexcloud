<?php

namespace Atlex\Adapter;

use OpenStack;
use Atlex;

use Atlex\Cloud\CloudObjectType;
use Atlex\Cloud\Exception\CloudException;
use Atlex\Cloud\Exception\NotValidContainerNameException;


class OpenStackAdapter extends CloudUtils
{
    private $openstack = null;
    public function __construct($url, $user, $password, $project,
                                $region = 'RegionOne',
                                $domainId = 'default')
    {
        $this->openstack = new OpenStack\OpenStack([
                'authUrl' => $url,
                'region'  => $region,
                'user'    => [
                    'name'       => $user,
                    'password' => $password,
                    'domain'   => [
                        'id' => $domainId
                    ]
                ],
                'scope'   => [
                    'project' => [
                        'name' => $project,
                        'domain'   => [
                            'id' => $domainId
                        ]
                    ]
                ]
            ]
        );

    }

    /**
     * {@inheritdoc}
     */
    public function createContainer($name)
    {
        $name = trim($name);
        if($this->checkContainerName($name)) {
            try {
                $service = $this->openstack->objectStoreV1();
                $service->createContainer(['name' => $name]);
            } catch (OpenStack\Common\Error\BadResponseError $e) {
                throw new CloudException($e->getResponse()->getReasonPhrase());
            }
        }else{
            throw new NotValidContainerNameException();
        }

    }



    /**
     * {@inheritdoc}
     */
    public function get($path = "")
    {
        $collection = new Atlex\Cloud\CloudCollection($this);

        if($path == ""){
            $service = $this->openstack->objectStoreV1();
            foreach ($service->listContainers() as $container) {

                $this->addCollectionObject(
                    $container->name ,
                    CloudObjectType::CONTAINER,
                    "/",
                    $container->name,
                    $collection
                );
            }
        }else{
            $path = trim($path, "/");
            $list = $this->listAll($path);

            foreach ($list as $object) {

                $this->addCollectionObject(
                    $object['name'],
                    $object['type'],
                    $path,
                    $object['container'],
                    $collection
                );
            }

        }

        return $collection;
    }

    /**
     * {@inheritdoc}
     */
    protected function listAll($path)
    {
        $objects = [];
        $path = trim($path, "/");
        $pathInfo = $this->parsePath($path);

        $container = $this->openstack->objectStoreV1()
            ->getContainer($pathInfo['container']);

        foreach ($container->listObjects() as $object) {
            $fullPath = $pathInfo['container'] . "/" . $object->name;
            if(strpos($fullPath, $path, 0) === 0 && $fullPath != $path) {

                $objects[] = [
                    'container' => $pathInfo['container'],
                    'parent' => $path,
                    'path'  => $fullPath,
                    'name' => $object->name,
                    'type' => ($object->contentType == "application/directory") ? CloudObjectType::CONTAINER : CloudObjectType::OBJECT
                ];
            }
        }

        return $objects;
    }




    /**
     * {@inheritdoc}
     */
    public function setObject($path, $content, $createContainer = true)
    {
        $pathInfo = $this->parsePath($path);

        if($pathInfo['name'] == "")
            throw new Atlex\Cloud\Exception\NotValidObjectNameException();

        if(is_resource($content)){
            $handle = $content;
            $content = "";
            while(!feof($handle)) {
                $content .= fgets($handle);
            }
            fclose($handle);
        }

        $options = [
            'name' => $pathInfo['name'],
            'content' => $content,
        ];

        $container = $this->openstack->objectStoreV1()->getContainer($pathInfo['container']);
        try {
            $container->retrieve();
        } catch (OpenStack\Common\Error\BadResponseError $e) {
            if($e->getResponse()->getStatusCode() == 404 && $createContainer) {
                $this->createContainer($pathInfo['container']);
                $this->setObject($path, $content, false);
            }else if($e->getResponse()->getStatusCode() == 404){
                throw new Atlex\Cloud\Exception\ContainerNotExistsException($pathInfo['container']);
            }else{
                throw new CloudException($e->getResponse()->getReasonPhrase());
            }
        }

        try{
            $container->createObject($options);
        }catch(OpenStack\Common\Error\BadResponseError $err){
            throw new CloudException($err->getResponse()->getReasonPhrase());
        }

    }

    /**
     * {@inheritdoc}
     */
    public function getObject($path, $handle=null)
    {
        $pathInfo = $this->parsePath($path);
        $object = $this->openstack->objectStoreV1()
            ->getContainer($pathInfo['container'])
            ->getObject($pathInfo['name'])
            ->download();


        if(is_resource($handle)){
            fwrite($handle, $object->read($object->getSize()));
            fclose($handle);
        }else{
            return $object->read($object->getSize());
        }

    }

    /**
     * {@inheritdoc}
     */
    public function deleteObject($path)
    {
        $pathInfo = $this->parsePath($path);
        $this->openstack->objectStoreV1()
            ->getContainer($pathInfo['container'])
            ->getObject($pathInfo['name'])
            ->delete();
    }


    /**
     * {@inheritdoc}
     */
    public function download($object, $localDir)
    {
        $localDir = $this->normalizeLocalPath($localDir);
        $this->checkLocalDirectory($localDir);
        if (is_dir($localDir)){
            if(is_writable($localDir)) {

                switch($object->getType())
                {
                    case CloudObjectType::OBJECT:
                        $this->getObject($object->getPath(), fopen($localDir . DIRECTORY_SEPARATOR . $object->getName(), "wb+"));

                        break;
                    case CloudObjectType::CONTAINER:

                        $this->downloadContainer($object, $localDir);

                        break;

                    default:
                        break;
                }

            }else{
                throw new CloudException("Permission denied directory is not writable.");
            }
        }else{
            throw new CloudException("Local directory not found.");
        }
    }

    private function downloadContainer($container, $localDir)
    {

        if($container->isContainer()) {
            $localDir = $localDir . DIRECTORY_SEPARATOR. $container->getName();
            $this->checkLocalDirectory($localDir);

            $objects = $this->listAll($container->getPath());

            foreach ($objects as $obj) {
                $isFile = $obj['type'] != CloudObjectType::CONTAINER;
                $fullPath = $localDir.DIRECTORY_SEPARATOR.$this->getRelativePath($obj);
                $this->checkLocalDirectory($fullPath, $isFile);
                if($isFile)
                {
                    $this->getObject($obj['path'], fopen($fullPath, "wb+"));
                }
            }
        }else{
            throw new CloudException("Incorrect container");
        }

    }

    /**
     * {@inheritdoc}
     */
    public function upload($localPath, $remotePath)
    {
        $localPath = trim($localPath, DIRECTORY_SEPARATOR);
        $remotePath = trim($remotePath, DIRECTORY_SEPARATOR);

        $files = $this->getLocalFiles($localPath);
        foreach($files as $file)
        {
            $this->setObject($remotePath . "/" . $this->createObjectName($localPath, $file), fopen($file, "r"));
        }
    }
}