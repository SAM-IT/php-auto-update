<?php

namespace SamIT\AutoUpdater\Generator;
/**
 * A pre-update package contains information need to support the following operations on the client:
 * - Show the changelog
 * - Run custom, build specific, pre-check code.
 * - Check for local file modifications.
 * - Perform a simulated upgrade.
 */
class PreUpdate extends Base {
    protected $precheck;
    
    public function getData() {
        $result = [
            'removedFiles' => $this->diff->getRemoved(),
            'changedFiles' => $this->diff->getChanged(),
            'createdFiles' => $this->diff->getCreated(),
            'sourceHashes' => $this->diff->getSourceHashes(),
            'targetHashes' => $this->diff->getTargetHashes(),
            'changeLog' => $this->diff->getChangeLog(),
            'from' => $this->diff->getFrom(),
            'to' => $this->diff->getTo(),
            'precheck' => base64_encode($this->precheck)
        ];
        return json_encode($result);
    }

    /**
     * 
     * @return type
     */
    protected function getSignedData() 
    {
        $sig = json_encode($this->signature);
        return "{\"signature\":{$sig}, \"data\":{$this->getData()}}";
    }

    public function getDataForSigning() {
        return $this->getData();
    }

}