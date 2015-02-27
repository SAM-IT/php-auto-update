<?php

namespace SamIT\AutoUpdater\Package;
/**
 * A pre-update package contains information need to support the following operations on the client:
 * - Show the changelog
 * - Run custom, build specific, pre-check code.
 * - Check for local file modifications.
 * - Perform a simulated upgrade.
 */
class PreUpdate extends Base implements \JsonSerializable {
    protected $precheck;
    
    protected $basePath;
    protected $changelog = [];
    protected $removedFiles = [];
    protected $changedFiles = [];
    protected $createdFiles = [];
    protected $sourceHashes = [];
    protected $targetHashes = [];
    protected $hash;
    
    public function jsonSerialize() {
        $result = [
            'type' => __CLASS__,
            'removedFiles' => $this->removedFiles,
            'changedFiles' => $this->changedFiles,
            'createdFiles' => $this->createdFiles,
            'sourceHashes' => $this->sourceHashes,
            'targetHashes' => $this->targetHashes,
            'changeLog' => $this->changeLog,
            'hash' => $this->hash
            
        ];
        parent::sign($result);
        return $result;
    }
    
    /**
     * Runs the pre update package.
     * This runs the pre-checks and a simulation upgrade.
     */
    public function run() {
        $results = array_merge(
            $this->simulateDelete(),
            $this->simulateUpdate(),
            $this->simulateCreate()
        );
        return $results;
    }
    
    protected function simulateDelete() {
        $result = [];
        foreach ($this->removedFiles as $removedFile) {
            if (!$this->deletable($removedFile)) {
                $result[] = "Not deletable: $removedFile";
            }
        }
        return $result;
    }
    
    protected function deletable($path) {
        $fullPath = "{$this->basePath}/$path";
        return !file_exists($fullPath) || is_writable($fullPath);
    }
    /**
     * Checks if a file is writable, if the file does not exist, traverses up the file path.
     * @param string $path
     * @return boolean
     */
    protected function writable($path) {
        $fullPath = "{$this->basePath}/$path";
        while (!file_exists($fullPath) && $fullPath != basename($fullPath)) {
            $fullPath = dirname($fullPath);
        }
        return is_writable($fullPath);
    }
    
    protected function changed($path) 
    {
        $fullPath = "{$this->basePath}/$path";
        return !file_exists($fullPath) || sha1_file($fullPath) == $this->sourceHashes[$path];
    }
    
    protected function simulateUpdate() {
        $result = [];
        foreach ($this->changedFiles as $changedFile) {
            if (!$this->writable($changedFile)) {
                $result[] = "Not writable: $changedFile";
            } elseif($this->changed($changedFile)) {
                $result[] = "Local changes: $changedFile";
            }
        }
        return $result;
    }
    
    protected function simulateCreate() {
        $result = [];
        foreach ($this->createdFiles as $createdFile) {
            if (!$this->writable($createdFile)) {
                $result[] = "Not writable: $createdFile";
            }
        }
        return $result;
    }

    
    public function saveToFile($fileName) {
        return false !== file_put_contents($fileName, json_encode($this, JSON_PRETTY_PRINT));
    }
}