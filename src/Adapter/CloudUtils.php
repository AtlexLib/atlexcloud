<?php


namespace Atlex\Adapter;

use Atlex;
use Atlex\Cloud\CloudObjectType;


abstract class CloudUtils extends CloudAdapter
{
    protected function parsePath($path)
    {
        $pos = strpos($path, '/');
        if($pos > 0) {
            $container = substr($path, 0, $pos);
            $name = substr($path, $pos + 1, strlen($path));
        } else {
            $container = $path;
            $name = '';
        }

        return [
            'container' => $container,
            'name' => $name
        ];
    }

    protected function addCollectionObject($objName, $objContentType, $path, $container, $collection)
    {
        if ($path == "/") {
            if ($collection->exists($objName, CloudObjectType::CONTAINER) === false) {
                $cObj = new Atlex\Cloud\CloudObject($objName, CloudObjectType::CONTAINER, $path, 0, $this);
                $collection->add($cObj);
                return true;
            }

        } else {

            $fullPath = $container . "/" . $objName;

            if (strpos($fullPath, $path, 0) === 0 && $fullPath != $path) {

                $parentLevel = count(explode("/", $path));
                $partName = explode("/", $fullPath);

                $type = CloudObjectType::OBJECT;

                if (count($partName) > ($parentLevel + 1)) {
                    $type = CloudObjectType::CONTAINER;
                }

                if ($objContentType == CloudObjectType::CONTAINER)
                    $type = CloudObjectType::CONTAINER;


                if(isset($partName[$parentLevel])) {
                    $objectName = $partName[$parentLevel];
                    if ($collection->exists($objectName, $type) === false) {
                        $cObj = new Atlex\Cloud\CloudObject($objectName, $type, $path, 0, $this);
                        $collection->add($cObj);
                        return true;
                    }
                }

            }
        }

        return false;
    }

    protected function normalizeLocalPath($localDir)
    {
        if(DIRECTORY_SEPARATOR != "/")
            $localDir = str_replace("/", DIRECTORY_SEPARATOR, $localDir);

        if(DIRECTORY_SEPARATOR != "\\")
        $localDir = str_replace("\\", DIRECTORY_SEPARATOR, $localDir);

        return rtrim($localDir, DIRECTORY_SEPARATOR);
    }

    protected function getRelativePath($object)
    {
        $start = strlen($object['parent']) + 1;
        return substr($object['path'], $start, strlen($object['path']) - $start);
    }

    protected function createObjectName($parent, $fullPath)
    {
        $start = strlen($parent) + 1;
        return substr($fullPath, $start, strlen($fullPath) - $start);
    }

    protected function checkContainerName($name)
    {

        if(strlen($name) >= 3 && preg_match('/^[a-z0-9]+[\d.-]?[a-z0-9\d.-]+$/', $name)) {
            return true;
        }

        return false;
    }

    protected function checkLocalDirectory($path, $isFile = false)
    {
        $path = $this->normalizeLocalPath($path);
        $pathParts = explode(DIRECTORY_SEPARATOR, $path);
        $count = count($pathParts);
        if($isFile == true )
            $count -= 1;
        $currentDir = $pathParts[0];
        for($i = 0; $i < $count; $i++)
        {
            if($pathParts[$i] == "")
                continue;
            $dir = $pathParts[$i];

            if($i == 0)
                $currentDir = $dir;
            else
                $currentDir .= DIRECTORY_SEPARATOR . $dir;
            if(!file_exists($currentDir)) {
                mkdir($currentDir, 0777);
            }

        }
    }

    public function getLocalFiles($dir, &$results = array()){

        $files = scandir("/".$dir);

        foreach($files as $key => $value){
            //$path = realpath($dir.DIRECTORY_SEPARATOR.$value);
            $path = $dir.DIRECTORY_SEPARATOR.$value;
            if(!is_dir($path)) {
                $results[] = ['path' => $path, 'type' => 'file'];
            } else if($value != "." && $value != "..") {
                $results[] = ['path' => $path, 'type' => 'dir'];
                $this->getLocalFiles($path, $results);
                if(!is_dir($path))
                    $results[] = ['path' => $path, 'type' => 'file'];;
            }
        }

        return $results;
    }

}