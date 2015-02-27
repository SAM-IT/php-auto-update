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
        $this->sourceHash = $this->git('rev-parse', [$this->source]);
        $this->targetHash = $this->git('rev-parse', [$this->target]);
        if (!isset($this->sourceHash) || !isset($this->targetHash)) {
            throw new \Exception("Source or target commit not found.");
        }
        
        $this->initChanges();
        
    }
    public function run($source, $target) 
    {
        
    }
    
    protected function initChanges() 
    {
        $lines = $this->gitArray('diff', ["--name-status", $this->sourceHash, $this->targetHash]);
        foreach($lines as $line) {
            list($type, $filename) = explode("\t", $line);
            switch ($type) {
                case 'M': 
                    $this->changed[] = $filename;
                    break;
                case 'A':
                    $this->created[] = $filename;
                    break;
                case 'D': 
                    $this->removed[] = $filename;
                    break;
                default:
                    die("Unknown type: $type\n");

            }
        }
    }

    public function getSourceHashes() {
        $data = $this->gitTable('ls-tree', ['--full-name -r -l', $this->sourceHash], ['permissions', 'type', 'hash', 'size', 'name']);
        foreach ($data as $entry) {
            $result[$entry['name']] = $entry['hash'];
        }
        return $result;
    }

    public function getTargetHashes() {
        $data = $this->gitTable('ls-tree', ['--full-name -r -l', $this->targetHash], ['permissions', 'type', 'hash', 'size', 'name']);
        foreach ($data as $entry) {
            $result[$entry['name']] = $entry['hash'];
        }
        return $result;
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

}