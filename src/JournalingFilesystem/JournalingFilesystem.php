<?php

namespace JournalingFilesystem;

class JournalingFilesystem
{
    protected $sessionName = 'a';
    
    protected $recordPath;
    
    protected $filesystem;
    
    protected $record = array();
    
    /* */
    
    public function __construct($recordPath = null, FilesystemInterface $filesystem)
    {
        $this->setRecordPath($recordPath);
        
        $this->filesystem = $filesystem;
    }
    
    /* */
    
    public function setRecordPath($recordPath)
    {
        $this->recordPath = $recordPath;
        
        return $this;
    }
    
    /* */
    
    public function transaction(\Closure $callback)
    {
        $this->start();

        try {
            $result = $callback($this);

            $this->commit();
        } catch (\Exception $exception) {
            $this->rollback()->end();

            throw $exception;
        }
        
        $this->end();

        return $result;
    }
    
    public function start()
    {
        $this->sessionName = $this->getUniqueName($this->recordPath, true);

        $this->filesystem->makeDirectory("{$this->recordPath}/{$this->sessionName}", 0755, true, true);

        return $this;
    }
    
    public function commit()
    {
        return $this->cleanup();
    }
    
    public function cleanup()
    {
        $this->filesystem->deleteDirectory("{$this->recordPath}/{$this->sessionName}", true);
        
        $this->record = array();
        
        return $this;
    }
    
    public function back()
    {
        $record = array_pop($this->record);
        
        if ($record) {
            list($type, $info) = $record;
            
            switch ($type) {
                case 'create':
                    // Delete the file that was created.
                    
                    $filename = $info;
                    
                    if ($this->filesystem->isDirectory($filename)) {
                        $this->filesystem->deleteDirectory($filename);
                    } else {
                        $this->filesystem->delete($filename);
                    }
                    
                    break;
                
                case 'change':
                    // Restore the original file.
                    
                    list($new, $old) = $info;
                    
                    $this->filesystem->move("{$this->recordPath}/{$this->sessionName}/{$old}", $new);
                    
                    break;
                
                default:
                    // ?
            }
        }
        
        return $this;
    }
    
    public function undo($number = 1) {
        while (!empty($this->record) && $number > 0) {
            $this->back();
            
            --$number;
        }
        
        return $this;
    }
    
    public function rollback()
    {
        while (!empty($this->record)) {
            $this->back();
        }
        
        // ...
        
        return $this->cleanup();
    }
    
    public function end()
    {
        $this->filesystem->deleteDirectory("{$this->recordPath}/{$this->sessionName}");
        
        return $this;
    }
    
    /* */
    
    protected function getUniqueName($path, $directory = false)
    {
        $name = mt_rand(1000, 100000);
        
        do {
            $name = md5(str_shuffle($name.mt_rand(1000, 100000)));
        } while ($this->checkRecordPathExists("{$path}/{$name}", $directory));
        
        return $name;
    }
    
    protected function checkRecordPathExists($path, $directory = false)
    {
        if ($directory) {
            return $this->filesystem->isDirectory($path);
        } else {
            return $this->filesystem->exists($path);
        }
    }
    
    protected function makeRecord($action, $info)
    {
        $this->record[] = array($action, $info);
        
        return $this;
    }
    
    protected function getRealType($typeHint)
    {
        $aliases = array(
            'put' => 'change',
            'prepend' => 'change',
            'append' => 'change',
            'move' => 'change'
        );
        
        if (isset($aliases[$typeHint])) {
            $type = $aliases[$typeHint];
        } else {
            $type = $typeHint;
        }
        
        return $type;
    }

    protected function recordChange($type, $paths, $isDirectory = false)
    {
        if (!is_array($paths)) {
            $paths = array($paths);
        }
        
        foreach ($paths as $path) {
            // Check whether file/dir exists.
            
            if ($isDirectory) {
                $exists = $this->filesystem->isDirectory($path);
            } else {
                $exists = $this->filesystem->exists($path);
            }

            // ...
            
            if ($type == 'change' && !$exists) {
                $type = 'create';
            }
            
            // If necessary, make a copy.

            if ($type == 'change' || $type == 'delete') {
                $copyName = $this->getUniqueName("{$this->recordPath}/{$this->sessionName}");

                if ($isDirectory) {
                    $this->filesystem->copyDirectory($path, "{$this->recordPath}/{$this->sessionName}/{$copyName}");
                } else {
                    $this->filesystem->copy($path, "{$this->recordPath}/{$this->sessionName}/{$copyName}");
                }

                $recordInfo = array($path, $copyName);
            } else {
                $recordInfo = $path;
            }

            $this->makeRecord($type, $recordInfo);
        }

        return $this;
    }
    
    /* Filesystem overrides */

    public function delete($paths)
    {
        $paths = is_array($paths) ? $paths : func_get_args();
        
        $this->recordChange('change', $paths);

        return $this->filesystem->delete($paths);
    }
    
    public function move($path, $target)
    {
        $this->recordChange('change', $path);
        $this->recordChange('change', $target);

        return $this->filesystem->move($path, $target);
    }
    
    public function makeDirectory($path, $mode = 0777, $recursive = false, $force = false)
    {
        $this->recordChange('create', $path, true);
        
        return $this->filesystem->makeDirectory($path, $mode, $recursive, $force);
    }

    /* Handles everything else, which all follows the same format. */
    
    public function __call($method, $args)
    {
        $path = reset($args);
        
        $directoryMethods = array('cleanDirectory', 'deleteDirectory', 'copyDirectory', );
        
        $this->recordChange('change', $path, in_array($method, $directoryMethods));
        
        return call_user_func_array(array($this->filesystem, $method), $args);
    }
}