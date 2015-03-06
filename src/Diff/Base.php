<?php
namespace SamIT\AutoUpdater\Diff;

abstract class Base {
    private $initialized = false;
    
    
    protected $basePath;
    protected $preCheckFile;
    
    public function __construct(array $config = []) 
    {
        foreach($config as $key => $value) {
            $this->$key = $value;
        }
        $this->init();
    }
    
    protected function init() 
    {
        $this->initialized = true;
    }
    
    /**
     * Returns the newly created files.
     * @return string[] The relative filenames
     */
    abstract public function getCreated();    
    /**
     * Returns the newly created files.
     * @return string[] The relative filenames
     */
    abstract public function getRemoved();
    
    /**
     * Returns the newly created files.
     * @return string[] The relative filenames
     */
    abstract public function getChanged();
    
    /**
     * @return string[] Keys are filenames, values are hashes.
     */
    abstract public function getSourceHashes();
    
    /**
     * @return string[] Keys are filenames, values are hashes.
     */
    abstract public function getTargetHashes();
    /**
     * @return string[] A list of changes between source and target.
     */
    abstract public function getChangeLog();
    
    /**
     * @return string The source version for this diff.
     */
    abstract public function getFrom();
    
    /**
     * @return string The target version for this diff.
     */
    abstract public function getTo();
    
    public function getBasePath() {
        return $this->basePath;
    }
    
    
    /**
     * 
     * @param string|resource $privateKey A SSL private key to sign the package, or null.
     * @return \SamIT\AutoUpdater\Package\PreUpdate
     */
    public function createPreUpdatePackage(\SamIT\AutoUpdater\Package\Update $update, $privateKey = null) {
         return new \SamIT\AutoUpdater\Package\PreUpdate([
            'removedFiles' => $this->getRemoved(),
            'changedFiles' => $this->getChanged(),
            'createdFiles' => $this->getCreated(),
            'sourceHashes' => $this->getSourceHashes(),
            'targetHashes' => $this->getTargetHashes(),
            'changeLog' => $this->getChangeLog(),
            'privateKey' => $privateKey,
            'hash' => $update->getHash() 
        ]);
    }
    
    /**
     * 
     * @param string|resource $privateKey A SSL private key to sign the package, or null.
     * @return \SamIT\AutoUpdater\Package\Update
     */
    public function createUpdatePackage() {
         return new \SamIT\AutoUpdater\Package\Update([
            'changedFiles' => $this->getChanged(),
            'createdFiles' => $this->getCreated(),
            'targetHashes' => $this->getTargetHashes(),
            'basePath' => $this->basePath
        ]);
    }
}
