<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI');
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/backup_helper.php';

ensureAuditSchema($pdo);

try {
    $backupDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups';
    $result = runDatabaseBackup($pdo, $backupDir, 'scheduled', null);
    echo "Backup OK: " . $result['file_name'] . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, "Backup ERROR: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
