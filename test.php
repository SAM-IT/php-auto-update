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


$update = $diff->createUpdatePackage();
$update->saveToFile('/tmp/test.zip');

$pre = $diff->createPreUpdatePackage($update, $privKey);
if ($pre->saveToFile('/tmp/test.json')) {
    echo "Written /tmp/test.json\n";
} else {
    die('noo write');
}

die();
$prePackage = $diff->createPreUpdatePackage($privKey);



$prePackage2 = \SamIT\AutoUpdater\Package\Base::fromJson(file_get_contents('/tmp/test.json'), openssl_pkey_get_details($privKey)['key'], [
    'basePath' => '/home/sam/Projects/LimeSurvey'
]);

var_dump($prePackage2->run());
//var_dump($prePackage2);
//var_dump($diff->getSourceHashes());
