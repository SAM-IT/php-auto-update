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
        return sha1("blob " . strlen($string) . chr(0). $string);
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
            if (!$zip->addFile("$path/$changedFile", $changedFile)) {
            }
            
            $hashes[$changedFile] = $targetHashes[$changedFile];
        }
        foreach ($this->diff->getCreated() as $createdFile) {
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
                throw new \Exception("incorrect hash during verification.");
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