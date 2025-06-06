<?php

require_once __DIR__ . '/vendor/autoload.php';

use Ay4t\PCGG\Commit;
use Dotenv\Dotenv;

//load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get the API key from environment variable
$apiKey = $_SERVER['GEMINI_API_KEY'];

if (empty($apiKey)) {
    die("Please set GEMINI_API_KEY environment variable\n");
}

// Get git diff
$dir    = isset($argv[1]) && $argv[1] == '-d' && isset($argv[2]) ? $argv[2] : __DIR__;
$diff   = shell_exec("cd $dir && git diff --staged");

if (empty($diff)) {
    die("No staged changes found\n");
}

try {
    // Konfigurasi untuk Google Gemini API
    $config = [
        'endpoint' => 'https://generativelanguage.googleapis.com/v1beta',
        'model' => 'gemini-2.0-flash-exp'
    ];
    
    /* $config = [
        'endpoint' => 'http://localhost:8081/api',
        'model' => 'ai-commit-message-generator'
    ]; */

    $commit = new Commit($apiKey, '', $config);
    $commit->gitDiff($diff);
    $message = $commit->generate();
    echo $message . "\n";

    // perform git commit with message
    $commitCmd = "cd $dir && git commit -m '$message'";
    shell_exec($commitCmd);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if ($e->getPrevious()) {
        echo "Caused by: " . $e->getPrevious()->getMessage() . "\n";
    }
}
