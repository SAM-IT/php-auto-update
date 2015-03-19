<?php

namespace SamIT\AutoUpdater\Executor;
/**
 * A pre-update package contains information need to support the following operations on the client:
 * - Show the changeLog
 * - Run custom, build specific, pre-check code.
 * - Check for local file modifications.
 * - Perform a simulated upgrade.
 */
class PreUpdate extends Base {
    protected $precheck;
    
    protected $basePath;
    protected $changeLog = [];
    protected $removedFiles = [];
    protected $changedFiles = [];
    protected $createdFiles = [];
    protected $sourceHashes = [];
    protected $targetHashes = [];
    protected $hash;
    
    protected $signature;
    protected $sigData;

    /**
     * Runs the pre update package.
     * This runs the pre-checks and a simulation upgrade.
     */
    public function run() {
        $results = [
            $this->simulateDelete(),
            $this->simulateUpdate(),
            $this->simulateCreate()
        ];
        
        return array_search(false, $results, true) === false;
    }
    
    
    protected function simulateDelete() {
        $result = true;
        $counter = 0;
        foreach ($this->removedFiles as $removedFile) {
            if (!$this->deletable($removedFile)) {
                $this->messages[] = "Not deletable: $removedFile";
            } elseif ($this->changed($removedFile)) {
                $this->messages[] = "Local changes[D]: $removedFile";
                $result = false;
            } else {
                $counter++;
            }
        }
        $this->messages[] = "$counter files can be deleted.";
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
        return !file_exists($fullPath) || $this->gitHash(file_get_contents($fullPath)) == $this->sourceHashes[$path];
    }
    
    protected function simulateUpdate() {
        $result = [];
        $counter = 0;
        $alreadyUpdated = 0;
        foreach ($this->changedFiles as $changedFile) {
            if ($this->matchesTargetHash($changedFile)) {
                $alreadyUpdated++;
            }
            elseif (!$this->writable($changedFile)) {
                $this->messages[] = "Not writable: $changedFile";
            } elseif($this->changed($changedFile)) {
                $this->messages[] = "Local changes: $changedFile";
            } else {
                $counter++;
            }
        }
        $this->messages[] = "$counter files can be updated.";
        $this->messages[] = "$alreadyUpdated files are already updated.";
        return $result;
    }
    
    protected function gitHash($string) {
        return sha1("blob " . strlen($string) . chr(0). $string);
    }
    
    protected function matchesTargetHash($file) {
        return file_exists("{$this->basePath}/$file") 
            && isset($this->targetHashes[$file]) 
            && $this->targetHashes[$file] === $this->gitHash(file_get_contents("{$this->basePath}/$file"));
    }

    protected function simulateCreate() {
        $counter = 0;
        $exist = 0;
        $result = true;
        foreach ($this->createdFiles as $createdFile) {
            if (!$this->writable($createdFile)) {
                $this->messages[] = "Not writable: $createdFile";
                $result = false;
            } elseif (!$this->matchesTargetHash($createdFile)) {
                $this->messages[] = "Already exists: $createdFile";
                $exist++;
                $result = false;
            } else {
                $counter++;
            }
        }
        $this->messages[] = "$counter files can be created.";
        $this->messages[] = "$exist files already exist.";
        return $result;
    }

    
    public function saveToFile($fileName) {
        return false !== file_put_contents($fileName, json_encode($this, JSON_PRETTY_PRINT));
    }

    protected function getSignature() {
        return $this->signature;
    }

    public function getDataForSigning() {
        return $this->sigData;
    }

    public function loadFromFile($fileName, $publicKey) {
        $this->loadFromString(file_get_contents($fileName), $publicKey);
    }

    public function loadFromString($string, $publicKey) {
        $arrayData = json_decode($string, true);
        $this->signature = $arrayData['signature'];
        $this->sigData = json_encode($arrayData['data']);
        $this->changedFiles = $arrayData['data']['changedFiles'];
        $this->removedFiles = $arrayData['data']['removedFiles'];
        $this->createdFiles = $arrayData['data']['createdFiles'];
        $this->sourceHashes = $arrayData['data']['sourceHashes'];
        $this->targetHashes = $arrayData['data']['targetHashes'];
        $this->changeLog = $arrayData['data']['changeLog'];
        $this->data = $arrayData['data'];
        if (isset($publicKey)) {
            $this->verifySignature($publicKey);
        }
    }

    
    public function getChangeLog() {
        return $this->changeLog;
    }
    
    public function getCreatedFiles() {
        return $this->createdFiles;
    }

    public function getChangedFiles() {
        return $this->changedFiles;
    }
    public function getRemovedFiles() {
        return $this->removedFiles;
    }
    
    public function getFromVersion() {
        return $this->data['from'];
    }
    
    public function getToVersion() {
        return $this->data['to'];
    }
}
