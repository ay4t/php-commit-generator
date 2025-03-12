<?php

require_once __DIR__ . '/vendor/autoload.php';

use Ay4t\PCGG\Commit;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get API key
$apiKey = getenv('GEMINI_API_KEY');
if (empty($apiKey)) {
    die("Please set GEMINI_API_KEY environment variable\n");
}

// Baca file git diff
$diffFile = __DIR__ . '/test_example_git_diff.log';
if (!file_exists($diffFile)) {
    die("File test_example_git_diff.log tidak ditemukan\n");
}

$diff = file_get_contents($diffFile);
if (empty($diff)) {
    die("File git diff kosong\n");
}

try {
    // Konfigurasi untuk Google Gemini API
    $config = [
        'endpoint' => 'https://generativelanguage.googleapis.com/v1beta',
        'model' => 'gemini-2.0-flash-exp'
    ];

    echo "=== Memulai Testing Generate Commit Message ===\n";
    echo "Menggunakan file: " . $diffFile . "\n";
    echo "Panjang diff: " . strlen($diff) . " karakter\n\n";

    $commit = new Commit($apiKey, '', $config);
    $commit->gitDiff($diff);
    
    echo "Mencoba generate commit message...\n";
    $message = $commit->generate();
    
    echo "\nHasil generate commit message:\n";
    echo "-----------------------------\n";
    echo $message . "\n";
    echo "-----------------------------\n";

} catch (Exception $e) {
    echo "\nError: " . $e->getMessage() . "\n";
    if ($e->getPrevious()) {
        echo "Caused by: " . $e->getPrevious()->getMessage() . "\n";
    }
}
