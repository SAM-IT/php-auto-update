<?php
use SamIT\AutoUpdater\Diff\Git;
$loader = require_once("vendor/autoload.php");
$diff = new Git([
    'basePath' => '/home/sam/Projects/LimeSurvey',
    'source' => 'HEAD~50',
    'target' => 'HEAD',
    'formatter' => function($hash, $author, $message) {
        $keywords = [
            'Fixed issue',
            'Updated feature',
            'Updated translation',
            'New feature',
            'New translation'
        ];
//        foreach(explode("\n", $message) as $line) {
        
        return array_filter(array_map('trim', array_map(function ($line) use ($keywords, $hash, $author){
            // Check if message starts with a keyword:
            foreach($keywords as $keyword) {
                if (substr_compare($keyword, $line, 0, strlen($keyword), true) === 0) {
                    if ($keyword == 'Updated translation') {
                        return "#" . $line;
                    } else {
                        return '-' . strtr($line, ['#0' => '#']) . " ($author)";
                    }
                }
            }
        }, explode("\n", $message))));
    }
]);
if (!file_exists(__DIR__ . '/priv.key')) {
    $privKey = openssl_pkey_new();
    openssl_pkey_export_to_file($privKey, __DIR__ . '/priv.key');
} else {
    $privKey = openssl_pkey_get_private(file_get_contents(__DIR__ . '/priv.key'));
}

$pre = new \SamIT\AutoUpdater\Generator\PreUpdate($diff);
$pre->sign($privKey);
$pre->saveToFile('/tmp/test.json');
$data = $pre->getDataForSigning();
$update = new SamIT\AutoUpdater\Generator\Update($diff);
$update->sign($privKey);
$update->saveToFile('/tmp/test.zip');


unset($update);
unset($pre);
$basePath = '/tmp/LimeSurvey';
$pre = new SamIT\AutoUpdater\Executor\PreUpdate(['basePath' => $basePath]);

$pre->loadFromFile('/tmp/test.json', openssl_pkey_get_details($privKey)['key']);
if ($pre->run()) {
    echo "Pre-update successful.\n";
    print_r($pre->getMessages());
}
echo "Done with preupdate.\n";

$update = new SamIT\AutoUpdater\Executor\Update(['basePath' => $basePath]);
$update->loadFromFile('/tmp/test.zip', openssl_pkey_get_details($privKey)['key']);
echo "Done with update.\n";
if ($update->run()) {
    echo "Update successful.\n";
    print_r($update->getMessages());
}


