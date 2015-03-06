<?php

namespace SamIT\AutoUpdater\Generator;
/**
 * An update package contains data the client uses for an upgrade.
 */
class Update extends Base {
    protected $precheck;
    
    protected $hash;
    /**
     *
     * @var string
     */
    protected $tempFile;
    /*
     * @var \ZipArchive;
     */
    protected $archive;
    
    public function init() {
        parent::init();
        $this->tempFile = tempnam($this->tempPath, 'php-auto-update-');
        $this->archive = new \ZipArchive();
        $this->createZipFile();
    }
    
    protected function gitHash($string) {
//        var_dump("blob " . strlen($string) . chr(0) );
        return sha1("blob " . strlen($string) . chr(0). $string);
    }
    
    protected function gitHashFile($fileName) {
        if (is_link($fileName)) {
            echo "link";
            $result = $this->gitHash(readlink($fileName));
        } else {
            $result = $this->gitHash(file_get_contents($fileName));
        }
        return $result;
            
    }
    
    protected function addMetaData() {
        $metaData = [
            'signature' => $this->signature,
            'removedFiles' => $this->diff->getRemoved(),
            'changedFiles' => $this->diff->getChanged(),
            'createdFiles' => $this->diff->getCreated(),
            'sourceHashes' => $this->diff->getSourceHashes(),
            'targetHashes' => $this->diff->getTargetHashes(),
            'hash' => $this->hash
        ];
        $this->archive->addFromString('update.json', json_encode($metaData));
        $this->saveArchive();
    }
    protected function getSignedData() {
        $this->addMetaData();
        return $this->getData();
    }
    
    /**
     * Writes the changes in the zip file to disk.
     * @throws \Exception
     */
    protected function saveArchive() {
        if (!$this->archive->close() || !$this->archive->open($this->tempFile)) {
            throw new \Exception("Saving archive failed.");
        }
    }
            

    protected function createZipFile() {
        // We include created and changed file.
        $zip = $this->archive;
        $zip->open($this->tempFile, \ZipArchive::CREATE);
        $targetHashes = $this->diff->getTargetHashes();
        $hashes = [];
        $path = $this->diff->getBasePath();
        foreach ($this->diff->getChanged() as $changedFile) {
            if (isset($targetHashes[$changedFile]) && $targetHashes[$changedFile] != $this->gitHashFile("$path/$changedFile")) {
                throw new \Exception("incorrect hash during archiving.");
            }
            if (!$zip->addFile("$path/$changedFile", $changedFile)) {
                throw new \Exception("Failed to add $changedFile to archive.");
            }
            $hashes[$changedFile] = $targetHashes[$changedFile];
        }
        foreach ($this->diff->getCreated() as $createdFile) {
            if (isset($targetHashes[$createdFile]) && $targetHashes[$createdFile] != $this->gitHashFile("$path/$createdFile")) {
                echo "File: $path/$createdFile\n";
                echo "Found hash: " . $this->gitHashFile("$path/$createdFile") . "\n";
                echo "Expected: {$targetHashes[$createdFile]}\n";
                throw new \Exception("incorrect hash during archiving: " . $createdFile);
            }
            $zip->addFile("$path/$createdFile", $createdFile);
            $hashes[$createdFile] = $targetHashes[$createdFile];
        }
        
        ksort($hashes);
        $this->hash = sha1(json_encode($hashes));
        $this->saveArchive();
        // Verify.
        $count = $zip->numFiles;
        for ($i = 0; $i < $count; $i++) {
            $fileName = $zip->getNameIndex($i);
            if (isset($targetHashes[$fileName]) && $targetHashes[$fileName] != $this->gitHash($zip->getFromIndex($i))) {
                throw new \Exception("incorrect hash during verification: " . $fileName);
            }
        }
        
    }

    public function getDataForSigning() {
        return $this->hash;
    }

    public function getData() {
        return file_get_contents($this->tempFile);
    }

}