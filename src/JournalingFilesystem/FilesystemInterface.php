<?php

namespace JournalingFilesystem;

interface FilesystemInterface {
    public function exists($path);
    
    public function get($path);

    public function getRequire($path);
    
    public function requireOnce($file);

    public function put($path, $contents);

    public function prepend($path, $data);

    public function append($path, $data);
    
    public function delete($paths);
    
    public function move($path, $target);
    
    public function copy($path, $target);
    
    public function extension($path);
   
    public function type($path);
   
    public function size($path);
    
    public function lastModified($path);
   
    public function isDirectory($directory);

    public function isWritable($path);
   
    public function isFile($file);

    public function glob($pattern, $flags = 0);

    public function files($directory);
   
    public function allFiles($directory);
    
    public function directories($directory);

    public function makeDirectory($path, $mode = 0777, $recursive = false, $force = false);
  
    public function copyDirectory($directory, $destination, $options = null);

    public function deleteDirectory($directory, $preserve = false);

    public function cleanDirectory($directory);
}