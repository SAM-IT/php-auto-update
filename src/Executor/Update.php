<?php

namespace SamIT\AutoUpdater\Executor;
/**
 * An update package contains data the client uses for an upgrade.
 */
class Update extends Base {
    
    protected $basePath;
    
    protected $metaData = [];
    /**
     *
     * @var \ZipArchive
     */
    protected $archive;
    /**
     * Runs the update package.
     * This runs the pre-checks and a live upgrade.
     */
    public function run() {
        $results = [
            $this->doDelete(),
            $this->doUpdate(),
            $this->doCreate()
        ];
        return array_search(false, $results, true) === false;
    }
    
    protected function doDelete() {
        $result = [];
        $counter = 0;
        foreach ($this->metaData['removedFiles'] as $removedFile) {
            // We try to delete the file, but if we fail and the file does not exist we are okay with that.
            if (!@unlink("{$this->basePath}/$removedFile") && file_exists("{$this->basePath}/$removedFile")) {
                $this->messages[] = "Failed to delete: $removedFile";
            } else {
                $counter++;
            }
        }
        $this->messages[] = "$counter files deleted.";
        return $result;
    }
    
    protected function doUpdate() {
        $result = [];
        $counter = 0;
        foreach ($this->metaData['changedFiles'] as $changedFile) {
            $fullName = "{$this->basePath}/$changedFile";
            if ($this->matchesTargetHash($changedFile)) {
                $counter++;
            } elseif (false === $handle = @fopen($fullName, 'w')) {
                $this->messages[] = "Could not open $changedFile for writing.";
                $result = false;
            } else {
                $this->archive->getStream($changedFile);
                stream_copy_to_stream($this->archive->getStream($changedFile), $handle);
                fclose($handle);
                if (!$this->matchesTargetHash($changedFile)) {
                    $this->messages[] = "$changedFile hash verification failed.";
                } else {
                    $counter++;
                }
            }
        }
        $this->messages[] = "$counter files updated.";
        return $result;
    }
    protected function matchesTargetHash($file) {
        return file_exists("{$this->basePath}/$file") 
            && isset($this->metaData['targetHashes'][$file]) 
            && $this->metaData['targetHashes'] === $this->gitHash(file_get_contents("{$this->basePath}/$file"));
    }
    
    protected function doCreate() {
        $result = true;
        $counter = 0;
        foreach ($this->metaData['createdFiles'] as $createdFile) {
            $fullName = "{$this->basePath}/$createdFile";
            if ($this->matchesTargetHash($createdFile)) {
                $counter++;
            } elseif (!is_dir(dirname($fullName)) && !mkdir(dirname($fullName), 0777, true) ) {
                $this->messages[] = "Failed to create directories for $createdFile";
                $result = false;
            } elseif (file_exists($fullName)) {
                $this->messages[] = "File to be created: $createdFile already exists.";
                $result = false;
            } elseif (false === $handle = @fopen($fullName, 'x')) {
                $this->messages[] = "No write permission: $createdFile";
                $result = false;
            } else {
                $this->archive->getStream($createdFile);
                stream_copy_to_stream($this->archive->getStream($createdFile), $handle);
                fclose($handle);
                if (!$this->matchesTargetHash($createdFile)) {
                    $this->messages[] = "$createdFile was not successfully created.";
                } else {
                    $counter++;
                }
            }
        }
        $this->messages[] = "$counter files created.";
        return $result;
    }
    
    public function init() {
        parent::init();
        $this->archive = new \ZipArchive();
    }
    protected function gitHash($string) {
        return sha1("blob " . strlen($string) . chr(0). $string);
    }

    protected function getSignature() {
        return $this->metaData['signature'];
    }

    public function getDataForSigning() {
        $count = $this->archive->numFiles;
        $hashes = [];
        for ($i = 0; $i < $count; $i++) {
            $fileName = $this->archive->getNameIndex($i);
            $hashes[$fileName] = $this->gitHash($this->archive->getFromIndex($i));
        }
        ksort($hashes);
        unset($hashes['update.json']);
//        echo sha1(json_encode($hashes));
//        die();
        return sha1(json_encode($hashes));
    }

    public function loadFromFile($fileName, $publicKey) {
        $this->archive->open($fileName);
        $this->metaData = json_decode($this->archive->getFromName('update.json'), true);
        if (isset($publicKey)) {
            $this->verifySignature($publicKey);
        }

    }

    public function loadFromString($string, $publicKey) {
        throw new \Exception("This package does not support loading from string.");
    }

}