<?php

namespace SamIT\AutoUpdater\Generator;
use \SamIT\AutoUpdater\Diff\Base as BaseDiff;

/**
 * Base class for package generators.
 */
abstract class Base
{
    /**
     *
     * @var BaseDiff
     */
    protected $diff;
    /**
     *
     * @var string
     */
    protected $signature;
    
    /**
     * Location where generators may store temporary files.
     * @var string
     */
    protected $tempPath;
    /**
     * 
     * @param BaseDiff $diff
     * @param array $config
     */
    public function __construct(BaseDiff $diff, array $config = []) 
    {
        $this->diff = $diff;
        foreach($config as $key => $value) {
            $this->$key = $value;
        }
        $this->init();
    }
    
    protected function init() 
    {
        $this->initialized = true;
    }
    
    public function sign($key) 
    {
        $signature = null;
        if (openssl_sign($this->getDataForSigning(), $signature, $key) && isset($signature)) {
            $this->signature = base64_encode($signature);
            $result = true;
        } else {
            $result = false;
        }
        return $result;
    }
    /**
     * @return string The data for this package.
     */
    abstract public function getData();
    
    /**
     * @return string The data used for signining.
     * In some cases it could be desirable to not sign all of the data but instead
     * sign, for example, a hash of the data.
     */
    abstract public function getDataForSigning();
    /**
     * This function must return the data with the signature included.
     * The way it is included depends on the data type and is package specific.
     */
    abstract protected function getSignedData();
    
    public function saveToFile($fileName) {
        if (!isset($fileName)) {
            return $this->getSignedData();
        }
        return false !== file_put_contents($fileName, $this->getSignedData());
    }
    
    public function saveToFileUnsigned($fileName) {
        return false !== file_put_contents($fileName, $this->getData());
    }
}