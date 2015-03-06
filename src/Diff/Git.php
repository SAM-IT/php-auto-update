<?php
namespace SamIT\AutoUpdater\Diff;

class Git extends Base {
    
    
    protected $source;
    protected $target;
    /**
     * This function will be called once for each commit to generate the changelog
     * @param Closure
     */
    protected $formatter;
    
    /**
     * @var string[]
     */
    protected $changed = [];
    /**
     * @var string[]
     */
    protected $created = [];
    /**
     * @var string[]
     */
    protected $removed = [];
    /**
     * @var string The SHA1 hash of the source commit.
     */
    protected $sourceHash;
    /**
     * @var string The SHA1 hash of the target commit.
     */
    protected $targetHash;
    
    protected $sourceHashes = [];
    protected $targetHashes = [];
    public function getChanged() 
    {
        return $this->changed;
    }

    public function getCreated() 
    {
        return $this->created;
    }

    public function getRemoved() 
    {
        return $this->removed;
    }
    
//    public function

    protected function gitTable($command, array $params = [], array $columnNames = [], $columnDelimiter = '/\s+/', $lineBreak = "\n") {
        $array = $this->gitArray($command, $params, $lineBreak);
        $count = count($columnNames);
        $result = [];
        foreach ($array as $line) {
            $fields = preg_split($columnDelimiter, $line, $count);
            $result[] = !empty($columnNames) ? array_combine($columnNames, $fields) : $fields;
        }
        
        return $result;
    }
    protected function gitArray($command, array $params = [], $lineBreak = "\n") 
    {
        $string = $this->git($command, $params);
        return is_string($string) ? array_filter(explode($lineBreak, $string)) : [];
    }
    protected function git($command, array $params = []) 
    {
        $cmd = "git --git-dir {$this->basePath}/.git $command ";
        $cmd .= implode(' ', $params);
//        var_dump($cmd);
        $result = trim(shell_exec($cmd));
        return empty($result) ? null : $result;
    }
    
    public function init() {
        parent::init();
        $this->sourceHash = $this->git('rev-parse', ['--verify', $this->source . '^{commit}']);
        $this->targetHash = $this->git('rev-parse', ['--verify', $this->target . '^{commit}']);
        if (!isset($this->sourceHash) || !isset($this->targetHash)) {
            throw new \Exception("Source or target commit not found.");
        }
        
        $this->initChanges();
        
    }
    protected function initChanges() 
    {
        $lines = $this->gitArray('diff', ["--name-status", $this->sourceHash, $this->targetHash]);
        foreach($lines as $line) {
            list($type, $fileName) = explode("\t", $line);
            switch ($type) {
                case 'M': 
                    if (isset($this->getTargetHashes()[$fileName])) {
                        $this->changed[] = $fileName;
                    }
                    break;
                case 'A':
                    if (isset($this->getTargetHashes()[$fileName])) {
                        $this->created[] = $fileName;
                    }
                    break;
                case 'D': 
                    $this->removed[] = $fileName;
                    break;
                default:
                    die("Unknown type: $type\n");

            }
        }
    }

    public function getSourceHashes() {
        if (empty($this->sourceHashes)) {
            $data = $this->gitTable('ls-tree', ['--full-name -r -l', $this->sourceHash], ['permissions', 'type', 'hash', 'size', 'name']);
            foreach ($data as $entry) {
                if (substr($entry['permissions'], 0, 2) != '12') {
                    $result[$entry['name']] = $entry['hash'];
                }
            }
            $this->sourceHashes = $result;
        }
        return $this->sourceHashes;
    }

    public function getTargetHashes() {
        if (empty($this->targetHashes)) {
            echo $this->targetHash . "\n";
            $data = $this->gitTable('ls-tree', ['--full-name -r -l', $this->targetHash], ['permissions', 'type', 'hash', 'size', 'name']);
            foreach ($data as $entry) {
                if (substr($entry['permissions'], 0, 2) != '12') {
                    $result[$entry['name']] = $entry['hash'];
                }
            }
            $this->targetHashes = $result;
        }
        
        return $this->targetHashes;
    }
    
    public function getPreCheck() {
        
    }
    
    public function getChangeLog() {
        $raw = $this->gitTable('log', ["--pretty='%h---%an---%B'", '-z', '--no-merges', "{$this->sourceHash}...{$this->targetHash}"], ['hash', 'name', 'message'], '/---/', chr(0));
        $result = [];
        
        if (isset($this->formatter) && $formatter = $this->formatter) {
            foreach($raw as $entry) {
                $formatted = $formatter($entry['hash'], $entry['name'], $entry['message']);
                if (!is_array($formatted) && !empty($formatted)) {
                    $result[] = $formatted;
                } elseif (!empty($formatted)) {
                    $result = array_merge($result, $formatted);
                }
            }
        }
        return $result;
    }

    public function getFrom() {
        return $this->source;
    }

    public function getTo() {
        return $this->target;
    }

}