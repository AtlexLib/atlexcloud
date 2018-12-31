<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require "../vendor/autoload.php";

use Atlex\AtlexCloud;

use Atlex\Adapter\S3Adapter;
use Atlex\Adapter\OpenStackAdapter;
use Atlex\Adapter\FtpAdapter;

// local directory with writable permission
$localDir = "local";


// Create cloud object
// Choose adapter for AtlexCloud
// Amazon AWS SDK or OpenStack PHP SDK
$remoteCloud = new AtlexCloud(
    new S3Adapter('{url}', '{key}', '{secret}') // Amazon AWS SDK
    //new OpenStackAdapter('{url}', '{user}', '{password}', '{project}') // OpenStack PHP SDK
    //new FtpAdapter('{host}', '{user}','{password}') //FTPAdapter
);

$containerName = "test-container";


/** @var \Atlex\Cloud\CloudCollection $containers */
$containers = $remoteCloud->get("");

echo "List of containers<br>";

$local_path = "local/all_containers";
if(!file_exists($local_path))
    mkdir($local_path, 0777);

/** @var \Atlex\Cloud\CloudObject $containerObject */
foreach($containers as $containerObject){
    echo 'Object :' . $containerObject->getName() . " Type:".$containerObject->getType()."<br>";
    //$containerObject->downloadTo($local_path);
}





/*
Create a container with only alphanumeric lower case characters, available symbols "-" "." minimum length 3 symbols
Example:
    $remoteCloud->createContainer("a1.b-2");
    $remoteCloud->createContainer("abc");
    $remoteCloud->createContainer("abc123");
 */
$remoteCloud->createContainer($containerName);



/*
Create or modify cloud object/file
$createContainer = true; // default
Example:
    $remoteCloud->setObject($containerName . "/subdir1/subdir2/test.txt","Content for object test.txt", $createContainer);

    $createContainer = false;
    $remoteCloud->setObject($containerName . "/test.txt","Content for object test.txt", $createContainer);
    // throws Exception if container not exists
 */
// use simple text or binary data for object content
$remoteCloud->setObject($containerName . "/subdir/test.txt", "Content for object test.txt");

// use file for object content
$fileName = $localDir."/download.jpeg";
if( is_file($fileName) && is_readable($fileName) )
{
    $remoteCloud->setObject($containerName . "/subdir/download.jpeg", fopen($fileName, "r"));
}





// Get object content
$content = $remoteCloud->getObject($containerName . "/subdir/test.txt");
echo $containerName . "/subdir/test.txt" . " object content:" . $content;


// Write object content to local file
try{
    $file = fopen($localDir."/some-file2.jpeg", "w+");
    $remoteCloud->getObject($containerName . "/subdir/download.jpeg", $file );


}catch(Exception $e){

}





//List of root containers
/** @var \Atlex\Cloud\CloudCollection $containers */
$containers = $remoteCloud->get();
//$containers = $remoteCloud->get("test-container/subdir/");
echo "List of containers<br>";

$local_path = "local/all_containers";
if(!file_exists($local_path))
    mkdir($local_path, 0777);

/** @var \Atlex\Cloud\CloudObject $containerObject */
foreach($containers as $containerObject){
    echo 'Container:' . $containerObject->getName() . "<br>";
    //$containerObject->downloadTo($local_path);
}




// easy example for download container to local directory
// $localDir = "./local"; // with writable permission
// download remote test-container to ./local/local-test-container/
$remoteCloud->get("test-container")->downloadTo($localDir . DIRECTORY_SEPARATOR . "local-test-container");



// Recursive directory upload to remote cloud
// upload local/local-test-container to remote upload-container/upload-test-container/somedir
$remoteCloud->upload($localDir . DIRECTORY_SEPARATOR . "local-test-container", "upload-container/upload-test-container/somedir");

//Delete remote object
/*
$remoteCloud->deleteObject($containerName . "/subdir/test.txt");
 */
