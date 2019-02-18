<?php


namespace Atlex\Adapter;

use Aws\CloudFormation\Exception\CloudFormationException;
use Aws\S3\S3Client;
use Atlex;

use Atlex\Cloud\CloudObjectType;
use Atlex\Cloud\Exception\CloudException;
use Atlex\Cloud\Exception\NotValidContainerNameException;


class S3Adapter extends CloudUtils
{

    private $client;

    public function __construct($url, $key, $secret)
    {

        $this->client = S3Client::factory(
            array(
                'key'  => $key,
                'secret'  => $secret,
                'endpoint' => $url,
                'signature_version' => 'v2',
            )
        );


    }

    /**
     * {@inheritdoc}
     */
    public function createContainer($name)
    {


        $name = trim($name);
        $path = trim($name, "/");
        $pathInfo = $this->parsePath($path);

        if(strpos($path, "/") === false) {
            if ($this->checkContainerName($name)) {
                try {
                    $this->client->createBucket(array('Bucket' => $name, 'PathStyle' => true));
                } catch (\Aws\S3\Exception\S3Exception $e) {
                    if ($e->getAwsErrorCode() != "BucketAlreadyExists")
                        throw new CloudException($e->getMessage() . " " . $e->getAwsErrorCode());
                }
            } else {
                throw new NotValidContainerNameException();
            }
        } else {

            try {

                $this->client->putObject(array(
                    'Bucket' => $pathInfo['container'],
                    'Key' => $pathInfo['name'],
                    'Body' => '',
                    'ContentType' => 'application/directory',
                    'PathStyle' => true
                ));

            }catch(\Aws\S3\Exception\S3Exception $e){
                if( $e->getAwsErrorCode() == "NoSuchBucket" ){
                    $this->createContainer($pathInfo['container']);
                    $this->client->putObject(array(
                        'Bucket' => $pathInfo['container'],
                        'Key' => $pathInfo['name'],
                        'Body' => '',
                        'ContentType' => 'application/directory',
                        'PathStyle' => true
                    ));
                }else if( $e->getAwsErrorCode() == "NoSuchBucket") {
                    throw new Atlex\Cloud\Exception\ContainerNotExistsException($pathInfo['container']);
                }else{
                    throw new CloudException($e->getMessage() . " " . $e->getAwsErrorCode());
                }
            }



        }
    }



    /**
     * {@inheritdoc}
     */
    public function setObject($path, $content, $createContainer = true)
    {
        $pathInfo = $this->parsePath($path);

        if($pathInfo['name'] == "")
            throw new Atlex\Cloud\Exception\NotValidObjectNameException();

        try {
            $this->client->putObject(array(
                'Bucket' => $pathInfo['container'],
                'Key' => $pathInfo['name'],
                'Body' => $content,
                'PathStyle' => true
            ));
        }catch(\Aws\S3\Exception\S3Exception $e){
            if( $e->getAwsErrorCode() == "NoSuchBucket" && $createContainer){
                $this->createContainer($pathInfo['container']);
                $this->setObject($path, $content, false);
            }else if( $e->getAwsErrorCode() == "NoSuchBucket") {
                throw new Atlex\Cloud\Exception\ContainerNotExistsException($pathInfo['container']);
            }else{
                    throw new CloudException($e->getMessage() . " " . $e->getAwsErrorCode());
            }
        }
    }



    /**
     * {@inheritdoc}
     */
    public function getObject($path, $handle=null)
    {
        $pathInfo = $this->parsePath($path);
        try {
            $result = $this->client->getObject(array(
                'Bucket' => $pathInfo['container'],
                'Key' => $pathInfo['name'],
                'PathStyle' => true
            ));

            if (is_resource($handle)) {
                fwrite($handle, $result['Body']);
                fclose($handle);
            } else {
                return $result['Body'];
            }
        }catch(\Aws\S3\Exception\NoSuchBucketException $e) {
            throw new CloudException($e->getMessage(), $e->getAwsErrorCode());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteObject($path)
    {
        $pathInfo = $this->parsePath($path);
        $this->client->deleteObject(array(
            'Bucket' => $pathInfo['container'],
            'Key' => $pathInfo['name'],
            'PathStyle' => true
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function deleteContainer($path)
    {

        if($path == ""){

            throw new Atlex\Cloud\Exception\IncorrectPathException($path);

        } else {

            $list = $this->listAll($path);

            foreach ($list as $object) {
                try {
                    $this->deleteObject($object["path"]);
                }catch(\Exception $e){

                }
            }

            try {
                if(strpos($path, "/") === false) {
                    $this->client->deleteBucket(array(
                        'Bucket' => $path,
                        'PathStyle' => true
                    ));
                }else{
                    $this->deleteObject($path);
                }

            }catch(\Exception $e){

            }

        }


    }

    /**
     * {@inheritdoc}
     */
    public function get($path = "")
    {
        $collection = new Atlex\Cloud\CloudCollection($this);

        if($path == ""){

            $result = $this->client->listBuckets();

            foreach($result['Buckets'] as $container){
                $list[] = $container['Name'];
                $this->addCollectionObject(
                    $container['Name'] ,
                    CloudObjectType::CONTAINER,
                    "/",
                    $container['Name'],
                    $collection
                );
            }

        } else {

            $path = trim($path, "/");
            $collection = new Atlex\Cloud\CloudCollection($this);
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
                //var_dump($obj);
                $isFile = $obj['type'] != CloudObjectType::CONTAINER;
                $fullPath = $localDir.DIRECTORY_SEPARATOR.$this->getRelativePath($obj);
                //echo "{{".$fullPath."}}";
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
    protected function listAll($path)
    {
        $objects = [];
        $path = trim($path, "/");
        $pathInfo = $this->parsePath($path);


        $iterator = $this->client->getIterator('ListObjects', array(
            'Bucket' => $pathInfo['container'],
            'Prefix' => $pathInfo['name'],
            'PathStyle' => true
        ));

        foreach ($iterator as $object) {
            $fullPath = $pathInfo['container'] . "/" . $object['Key'];
            if(strpos($fullPath, $path, 0) === 0 && $fullPath != $path) {

                $type = Atlex\Cloud\CloudObjectType::OBJECT;
                if($object['Size'] == "0"){
                    $result = $this->client->getObject([
                        'Bucket' => $pathInfo['container'],
                        'Key'    => $object['Key'],
                        'PathStyle' => true
                    ]);

                    if($result->get("ContentType") == "application/directory")
                        $type = Atlex\Cloud\CloudObjectType::CONTAINER;

                }

                $objects[] = [
                    'container' => $pathInfo['container'],
                    'parent' => $path,
                    'path' => $fullPath,
                    'name' => $object['Key'],
                    'type' => $type
                ];

            }
        }

        return $objects;
    }

    /**
     * {@inheritdoc}
     */
    public function upload($localPath, $remotePath)
    {
        $localPath = rtrim($localPath, DIRECTORY_SEPARATOR);
        $remotePath = trim($remotePath, DIRECTORY_SEPARATOR);

        $files = $this->getLocalFiles($localPath);
        foreach($files as $file)
        {

            if($file["type"] == "file"){
                $this->setObject($remotePath . "/" . $this->createObjectName($localPath, $file["path"]), fopen($file["path"], "r"));
            } else if($file["type"] == "dir"){
                $this->createContainer($remotePath . "/" . $this->createObjectName($localPath, $file["path"]));
            }
        }
    }
}