<?php

namespace SamIT\AutoUpdater\Executor;

abstract class Base
{
    protected $basePath;
    
    protected $messages = [];
    /**
     * 
     * @param string|resource $data The string data or the file handle.
     * @param array $config
     */
    public function __construct(array $config = []) 
    {
        foreach($config as $key => $value) {
            $this->$key = $value;
        }
        if (!isset($this->basePath)) {
            throw new \Exception("basePath is a required configuration parameter.");
        }
        $this->init();
    }
    
    protected function init() 
    {
        $this->initialized = true;
    }
    
    public function getMessages() {
        return $this->messages;
    }
    /**
     * Verifies configuration via an HMAC..
     * @param array $config
     * @param string $publicKey
     */
    public function verifySignature($publicKey) 
    {
        $signature = $this->getSignature();
        $data = $this->getDataForSigning();
        
        if (!isset($signature)) {
            throw new \Exception("Missing signature");
        } elseif (1 != $result = openssl_verify($data, base64_decode ($signature), $publicKey)) {
            throw new \Exception("OpenSSL verification failed.");
        }
    }
    
    /**
     * @return string
     */
    abstract protected function getSignature();
    /**
     * @return string
     */
    abstract public function getDataForSigning();
    
    abstract public function loadFromFile($fileName, $publicKey);
    
    abstract public function loadFromString($string, $publicKey);
}