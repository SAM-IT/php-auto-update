<?php

namespace SamIT\AutoUpdater\Package;

abstract class Base
{
    private $privateKey;
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
     * Create a package from json data. Pass in a public key to require a signed
     * package.
     * @param string $json
     * @param string $publicKey
     * @return \SamIT\AutoUpdater\Package\Base
     * @throws \Exception
     */
    public static function fromJson($json, $publicKey = null, array $extraConfig = []) 
    {
        $config = json_decode($json, true);
        if (is_array($config) 
            && isset($config['type']) 
            && is_subclass_of($config['type'], __CLASS__)
        ) {
            
            $config = array_merge($extraConfig, self::verifySignature($config, $publicKey));
            $class = $config['type'];
//            var_dump($config);
            unset($config['type']);
            return new $class($config);
        }
        throw new \Exception('Invalid JSON received.');
    }
    
    /**
     * Verifies configuration via an HMAC..
     * @param array $config
     * @param string $publicKey
     */
    public static function verifySignature(array $config, $publicKey = null) 
    {
        if (isset($publicKey) && !isset($config['signature'])) {
            throw new \Exception("Missing signature");
        } elseif (isset($publicKey)) {
            $signature = hex2bin($config['signature']);
            unset($config['signature']);
            ksort($config);
            if (openssl_verify(json_encode($config), $signature, $publicKey) != 1) {
                throw new \Exception("OpenSSL verification failed.");
            }
        }
        return $config;
    }
    
    protected function sign(&$data) 
    {
        ksort($data);
        $signature = null;
        if (openssl_sign(json_encode($data), $signature, $this->privateKey) && isset($signature)) {
            $data['signature'] = bin2hex($signature);
            $result = true;
        } else {
            $result = false;
        }
        return $result;
    }
    
    abstract public function saveToFile($fileName);
}