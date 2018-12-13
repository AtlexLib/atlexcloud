<?php
/**
 * Created by PhpStorm.
 * User: nix
 * Date: 11/20/18
 * Time: 11:26 PM
 */

namespace Atlex\Adapter;

use Atlex;



abstract class CloudAdapter
{
    abstract public function loadContainers();

    abstract public function loadObjects($path);

    /**
     * Get objects collection
     * <code>
     * Example:
     *  get(); // list of root containers
     *  get("{container}");
     *  get("{container}/dir");
     * </code>
     *
     * @param string $path remote path  {container} or {container}/subdir/subdir. empty path return root containers
     * @throws CloudException
     * @return Atlex\Cloud\CloudCollection
     */
    abstract public function get($path = "");

     protected function listAll($path){}

    /**
     * Create a container with only alphanumeric lower case characters, available symbols "-" "." minimum length 3 symbols.
     * <code>
     * Example:
     *  createContainer("abc")
     *  createContainer("abc123")
     *  createContainer("abc1-z5")
     *  createContainer("a1.b-2")
     *  createContainer("123)
     * </code>
     *
     * @param string $name
     * @throws CloudException
     */
    abstract public function createContainer($name);


    /**
     * Create or modify cloud object
     * <code>
     * Example:
     *  setObject("{container}/name", "Content for object"); // automatically create container if not exists
     *  setObject("{container}/name", "Content for object", false); // do not automatically create container
     *  setObject("{container}/name", fopen("file.txt", "r"));
     * </code>
     *
     * @param string $path remote path of object {container}/name or {container}/subdir/subdir/name
     * @param mixed $content content or file handler
     * @param bool $createContainer = true automatically create container if not exists
     * @throws CloudException
     */
    abstract public function setObject($path, $content, $createContainer = true);

    /**
     * Delete remote cloud object/file
     *
     *
     * @param string $path remote path of object {container}/name or {container}/subdir/subdir/name
     * @throws CloudException
     */
    abstract public function deleteObject($path);


    /**
     * Get remote object content or write to file
     * <code>
     * Example:
     *  $content = getObject("{container}/name");
     *  echo $content;
     *
     * //write to file
     *  getObject("{container}/name", fopen("file_name", "w+");
     * </code>
     *
     * @param string $path remote path of object {container}/name or {container}/subdir/subdir/name
     * @param resource $handle = null file handle
     *
     * @return mixed
     */
    abstract public function getObject($path, $handle = null);

    abstract public function download($object, $localDir);

    /**
     *
     * Recursive directory upload to remote cloud
     *
     * @param string $localPath
     * @param string $remotePath prefix for object name
     *
     * @return mixed
     */
    abstract public function upload($localPath, $remotePath);

}