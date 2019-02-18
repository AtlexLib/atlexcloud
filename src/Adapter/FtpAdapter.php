<?php

namespace Atlex\Adapter;

use Atlex;

use Atlex\Cloud\CloudObjectType;
use Atlex\Cloud\Exception\CloudException;
use Atlex\Cloud\Exception\NotValidContainerNameException;
use Atlex\Cloud\Exception\IncorrectPathException;

class FtpAdapter extends CloudUtils
{

    private $ftpConnection;
    private $ftpRoot;

    public function __construct($url, $user, $password)
    {

        $hostip = gethostbyname($url);
        $this->ftpConnection = ftp_connect($hostip, 21);
        $ftpr = ftp_login($this->ftpConnection, $user, $password);

        if (!$this->ftpConnection || !$ftpr)
        {
            echo "FTP connection not established!"; die();
        }

        $this->ftpRoot = ftp_pwd($this->ftpConnection) . "/";
        $this->ftpRoot = str_replace("//","/", $this->ftpRoot);


    }
    

    function __destruct()
    {
        ftp_close($this->ftpConnection);
    }


    /**
     * {@inheritdoc}
     */
    public function get($path = "")
    {
        $path = trim($path, "/");
        $pathInfo = $this->parsePath($path);

        $collection = new Atlex\Cloud\CloudCollection($this);

        $ftpPath = $path;
        if($ftpPath == "")
            $ftpPath = ".";

        $list = $this->listAll($ftpPath);
        usort($list, function ($a, $b)
        {
            if ($a['type'] == $b['type']) {
                return $a['name'] > $b['name'];
            } else {
                return $a['type'] > $b['type'];
            }
        });

        foreach ($list as $object) {
            if ($path == "") {

                $this->addCollectionObject(
                    $object['name'],
                    $object['type'],
                    "/",
                    $object['name'],//container,
                    $collection
                );

            } else {

                $this->addCollectionObject(
                    $object['name'],
                    $object['type'],
                    $path,
                    $pathInfo['container'],
                    $collection
                );


            }
        }

        return $collection;
    }


    /**
     * {@inheritdoc}
     */
    public function createContainer($name)
    {

        $name = trim($name);
        $name = rtrim($name, "/");


        if(strpos($name, "/") > 0)
        {
            $this->createFtpPath($name . "/");
        } else {
            
            if ($this->checkContainerName($name)) {
                ftp_mkdir($this->ftpConnection, $this->ftpRoot . $name);
            } else {
                throw new NotValidContainerNameException();
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


        set_error_handler(
            function($errno, $error) use ($path) {

                switch ($errno)
                {
                    case 2:
                        throw new IncorrectPathException($path);
                        break;

                    default:
                        throw new CloudException($error);
                }

            }
        , E_ALL);

        try {

            if (is_resource($content)) {
                $handle = $content;
                ftp_fput($this->ftpConnection, $this->ftpRoot . $path, $handle, FTP_BINARY, 0);
                fclose($handle);
            } else {
                $handle = tmpfile();
                fwrite($handle, $content);
                fseek($handle, 0);

                ftp_fput($this->ftpConnection, $this->ftpRoot . $path, $handle, FTP_BINARY, 0);
                fclose($handle);
            }

        }catch(IncorrectPathException $e){

            if($createContainer)
            {
                $this->createFtpPath($path);
                $this->setObject($path, $content, false);
            }else{
                throw new IncorrectPathException($path);
            }
        }catch(CloudException $ec){
            throw $ec;
        }


        restore_error_handler();

    }

    private function createFtpPath($path)
    {

        $remotePath = $this->ftpRoot;
        $dirs = explode("/", $path);


        for($iter = 0; $iter < count($dirs) - 1;  $iter++)
        {
            if($dirs[$iter] == "")
                continue;

            $remotePath .= $dirs[$iter] . "/";
            
            $ftp_files = @ftp_nlist($this->ftpConnection, $remotePath);
            if ($ftp_files === false) {
                ftp_mkdir($this->ftpConnection, $remotePath);
            }

        }

    }


    /**
     * {@inheritdoc}
     */
    public function deleteObject($path)
    {
        ftp_delete($this->ftpConnection , $this->ftpRoot . $path);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteContainer($path)
    {
        $this->ftp_remove_r($this->ftpRoot . $path);
    }

    private function ftp_remove_r ($path) {

        if (@ftp_delete($this->ftpConnection, $path) === false) {

            if ($files = ftp_nlist($this->ftpConnection, $path)) {
                foreach ($files as $file)
                {
                    @ftp_delete($this->ftpConnection, $path. "/" . $file);
                    $this->ftp_remove_r($path . "/" . $file);
                }

            }

            @ftp_rmdir($this->ftpConnection, $path);
        }
    }


    /**
     * {@inheritdoc}
     */
    public function getObject($path, $handle = null)
    {
        if(is_resource($handle)){
            ftp_fget($this->ftpConnection, $handle, $this->ftpRoot . $path, FTP_BINARY, 0);
            fclose($handle);
        }else{

            return $this->ftpReadSocket($this->ftpConnection, $this->ftpRoot . $path, FTP_BINARY);
        }

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

                //$this->createFtpPath($this->createObjectName($localPath, $file["path"]) . "/");
                $this->createContainer($remotePath . "/" . $this->createObjectName($localPath, $file["path"]));
            }

        }
    }

    private function downloadContainer($container, $localDir)
    {

        if($container->isContainer()) {
            $localDir = $localDir . DIRECTORY_SEPARATOR. $container->getName();
            $this->checkLocalDirectory($localDir);


            $list = $this->get($container->getPath());

            /** @var \Atlex\Cloud\CloudObject $containerObject */
            foreach($list as $containerObject){
                $this->download($containerObject, $localDir);
            }

        }else{
            throw new CloudException("Incorrect container");
        }

    }

    private function ftp_sync ($localDir, $dir)
    {

        if ($dir != ".") {
            if (ftp_chdir($this->ftpConnection, $dir) == false) {
                return;
            }
            if (!(is_dir($localDir)))
                mkdir($localDir);
            chdir($localDir);
        }

        $contents = ftp_nlist($this->ftpConnection, ".");
        foreach ($contents as $file) {

            if ($file == '.' || $file == '..')
                continue;

            if (@ftp_chdir($this->ftpConnection, $file)) {
                ftp_chdir ($this->ftpConnection, "..");
                ftp_sync ($localDir, $file);
            }
            else
                ftp_get($this->ftpConnection, $file, $file, FTP_BINARY);
        }

        ftp_chdir ($this->ftpConnection, "..");
        chdir ("..");

    }

    protected function listAll($path)
    {

        $objects = [];
        $path = trim($path, "/");

        if (!ftp_chdir($this->ftpConnection, $this->ftpRoot . $path)) {
            throw new Atlex\Cloud\Exception\ContainerNotExistsException();
        }
        $contents = ftp_rawlist ($this->ftpConnection, ".");

        if(count($contents))
        {
            foreach($contents as $line)
            {
                $fileInfo = explode(" ", $line);

                $name = $fileInfo[count($fileInfo) - 1];
                $cloudPath = str_replace(".", "", $path) . "/" .$name;
                $cloudPath = trim($cloudPath, "/");

                $cloudName = $this->parsePath($cloudPath)['name'];
                if($cloudName == "")
                    $cloudName = $name;

                if($name == "." || $name == "..")
                {
                    continue;
                }

                $type = CloudObjectType::OBJECT;

                if(strpos($fileInfo[0], "drwx", 0 ) === 0){
                    $type = CloudObjectType::CONTAINER;
                }

                $objects[] = [
                    'name' => $cloudName,
                    'type' => $type
                ];

            }
        }

        return $objects;
    }

    private function ftpReadSocket($ftp_stream, $remote_file, $mode, $resume_pos = null)
    {
        $pipes = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if($pipes === false)
            return false;
        if(!stream_set_blocking($pipes[1], 0)){
            fclose($pipes[0]);
            fclose($pipes[1]);
            return false;
        }
        $fail=false;
        $data='';
        if(is_null($resume_pos)){
            $ret = ftp_nb_fget($ftp_stream, $pipes[0], $remote_file, $mode);
        } else {
            $ret = ftp_nb_fget($ftp_stream, $pipes[0], $remote_file, $mode, $resume_pos);
        }
        while($ret == FTP_MOREDATA){
            while(!$fail && !feof($pipes[1])){
                $r = fread($pipes[1], 8192);
                if($r === '')
                    break;
                if($r === false){
                    $fail = true;
                    break;
                }
                $data .= $r;
            }
            $ret=ftp_nb_continue($ftp_stream);
        }
        while(!$fail && !feof($pipes[1])){
            $r = fread($pipes[1], 8192);
            if($r === '')
                break;
            if($r === false){
                $fail = true;
                break;
            }
            $data .= $r;
        }
        fclose($pipes[0]);
        fclose($pipes[1]);

        if($fail || $ret != FTP_FINISHED)
            return false;

        return $data;
    }

}