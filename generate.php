<?php

require_once __DIR__ . '/vendor/autoload.php';

use Ay4t\PCGG\Commit;

// Baca isi dari file config.json
$configContent = file_get_contents(__DIR__ . '/config.json');

// Dekode isi JSON dan simpan ke dalam variabel $config
$config = json_decode($configContent, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    // Tangani kesalahan jika decoding JSON gagal
    throw new Exception('Error decoding JSON: ' . json_last_error_msg());
}

if (empty($config['api_key'])) {
    die("Please set API_KEY config variable\n");
}

// Get git diff
$dir    = isset($argv[1]) && $argv[1] == '-d' && isset($argv[2]) ? $argv[2] : __DIR__;
$diff   = shell_exec("cd $dir && git diff --staged");

if (empty($diff)) {
    die("No staged changes found\n");
}

// Deteksi sistem operasi
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

try {

    $commit = new Commit($config['api_key'], '', $config);
    $commit->gitDiff($diff);
    $message = $commit->generate();
    echo $message . "\n";

    // perform git commit with message
    $escapedMessage = escapeshellarg($message);
    
    if ($isWindows) {
        // Di Windows, gunakan double quotes untuk pesan commit
        $commitCmd = "cd $dir && git commit -m $escapedMessage";
    } else {
        // Di Linux/Unix, gunakan single quotes untuk pesan commit
        $commitCmd = "cd $dir && git commit -m $escapedMessage";
    }
    
    $result = shell_exec($commitCmd);
    
    if ($result === null) {
        echo "\nPerintah git commit berhasil dijalankan.\n";
    } else {
        echo "\nOutput git commit:\n$result\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if ($e->getPrevious()) {
        echo "Caused by: " . $e->getPrevious()->getMessage() . "\n";
    }
}
