<?php

require_once __DIR__ . '/vendor/autoload.php';

use Ay4t\PCGG\Commit;

// Get the API key from environment variable
$apiKey = getenv('GROQ_API_KEY');

if (empty($apiKey)) {
    die("Please set GROQ_API_KEY environment variable\n");
}
// Get git diff
$dir    = isset($argv[1]) && $argv[1] == '-d' && isset($argv[2]) ? $argv[2] : __DIR__;
$diff   = shell_exec("cd $dir && git diff --staged");

var_dump($diff, $apiKey);

if (empty($diff)) {
    die("No staged changes found\n");
}

try {
    $commit = new Commit($apiKey);
    $commit->gitDiff($diff);
    $message = $commit->generate();
    echo $message . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if ($e->getPrevious()) {
        echo "Caused by: " . $e->getPrevious()->getMessage() . "\n";
    }
}
