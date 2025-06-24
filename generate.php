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

// Parse command line arguments
$options = getopt("d:h:e:", ["dir:", "history-file:", "enable-history::"]);

// Get directory
$dir = isset($options['d']) ? $options['d'] : (isset($options['dir']) ? $options['dir'] : __DIR__);

// Get history file name (optional)
$historyFile = isset($options['h']) ? $options['h'] : (isset($options['history-file']) ? $options['history-file'] : null);

// Check if history should be enabled (optional)
$enableHistory = true;
if (isset($options['e'])) {
    $enableHistory = filter_var($options['e'], FILTER_VALIDATE_BOOLEAN);
} elseif (isset($options['enable-history'])) {
    $enableHistory = filter_var($options['enable-history'], FILTER_VALIDATE_BOOLEAN);
}

// Get git diff
$diff = shell_exec("cd $dir && git diff --staged");

if (empty($diff)) {
    die("No staged changes found\n");
}

// Deteksi sistem operasi
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

try {
    // Tambahkan project directory ke konfigurasi
    $config['project_dir'] = $dir;

    $commit = new Commit($config['api_key'], '', $config);
    
    // Set history file name if provided
    if ($historyFile !== null) {
        $commit->setHistoryFileName($historyFile);
    }
    
    // Set enable/disable history
    $commit->enableCommitHistory($enableHistory);
    
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
