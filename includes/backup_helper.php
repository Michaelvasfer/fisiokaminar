<?php

require_once __DIR__ . '/../db.php';

if (!function_exists('backupBuildInsertSql')) {
    function backupBuildInsertSql(PDO $pdo, string $table, array $row): string {
        $columns = array_map(fn($col) => '`' . str_replace('`', '``', $col) . '`', array_keys($row));
        $values = [];

        foreach ($row as $value) {
            if ($value === null) {
                $values[] = 'NULL';
            } elseif (is_bool($value)) {
                $values[] = $value ? '1' : '0';
            } elseif (is_int($value) || is_float($value)) {
                $values[] = (string)$value;
            } else {
                $values[] = $pdo->quote((string)$value);
            }
        }

        return "INSERT INTO `" . $table . "` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
    }
}

if (!function_exists('cleanupOperationalHistory')) {
    function cleanupOperationalHistory(PDO $pdo, string $baseDir, int $retentionDays = 60): array {
        ensureAuditSchema($pdo);

        $retentionDays = max(1, (int)$retentionDays);
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . $retentionDays . ' days'));
        $deletedAudit = 0;
        $deletedRuns = 0;
        $deletedFiles = 0;

        try {
            $stmt = pdoQuery($pdo, "DELETE FROM audit_logs WHERE created_at < ?", [$cutoff]);
            $deletedAudit = (int)$stmt->rowCount();
        } catch (Throwable $e) {
        }

        try {
            $oldRuns = pdoQuery(
                $pdo,
                "SELECT id, backup_file
                 FROM backup_runs
                 WHERE started_at < ?",
                [$cutoff]
            )->fetchAll(PDO::FETCH_ASSOC);

            foreach ($oldRuns as $run) {
                $backupFile = trim((string)($run['backup_file'] ?? ''));
                if ($backupFile !== '') {
                    $fullPath = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($backupFile);
                    if (is_file($fullPath) && @unlink($fullPath)) {
                        $deletedFiles++;
                    }
                }
            }

            $stmt = pdoQuery($pdo, "DELETE FROM backup_runs WHERE started_at < ?", [$cutoff]);
            $deletedRuns = (int)$stmt->rowCount();
        } catch (Throwable $e) {
        }

        return [
            'retention_days' => $retentionDays,
            'deleted_audit_logs' => $deletedAudit,
            'deleted_backup_runs' => $deletedRuns,
            'deleted_backup_files' => $deletedFiles,
        ];
    }
}

if (!function_exists('runDatabaseBackup')) {
    function runDatabaseBackup(PDO $pdo, string $baseDir, string $runType = 'manual', ?int $createdBy = null): array {
        ensureAuditSchema($pdo);

        if (!is_dir($baseDir) && !@mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
            throw new RuntimeException('No se pudo crear la carpeta de backups');
        }

        $runId = startBackupRun($pdo, $runType, $createdBy, 'Iniciando backup SQL');
        $timestamp = date('Ymd_His');
        $fileName = 'kaminarfisio_' . $timestamp . '.sql';
        $filePath = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

        try {
            $content = "-- KaminarFisio backup\n";
            $content .= "-- Generated at " . date('Y-m-d H:i:s') . "\n";
            $content .= "SET NAMES utf8mb4;\n";
            $content .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                $createRow = $pdo->query("SHOW CREATE TABLE `" . str_replace('`', '``', $table) . "`")->fetch(PDO::FETCH_NUM);
                $createSql = $createRow[1] ?? '';

                $content .= "-- --------------------------------------------------\n";
                $content .= "-- Table: " . $table . "\n";
                $content .= "-- --------------------------------------------------\n";
                $content .= "DROP TABLE IF EXISTS `" . $table . "`;\n";
                $content .= $createSql . ";\n\n";

                $rows = $pdo->query("SELECT * FROM `" . str_replace('`', '``', $table) . "`", PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $content .= backupBuildInsertSql($pdo, $table, $row);
                }
                $content .= "\n";
            }

            $content .= "SET FOREIGN_KEY_CHECKS=1;\n";

            file_put_contents($filePath, $content);
            $size = is_file($filePath) ? filesize($filePath) : 0;
            $cleanup = cleanupOperationalHistory($pdo, $baseDir, 60);

            finishBackupRun($pdo, $runId, 'success', basename($filePath), $size, 'Backup completado. Limpieza 60 dias aplicada');
            appLog($pdo, 'backup.run', 'backup', (string)$runId, [
                'run_type' => $runType,
                'file' => basename($filePath),
                'size_bytes' => $size,
                'cleanup' => $cleanup,
            ], [
                'user_id' => $createdBy,
                'user_name' => $createdBy ? ($_SESSION['name'] ?? null) : 'Sistema',
                'user_role' => $createdBy ? ($_SESSION['role'] ?? null) : 'system',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ]);

            return [
                'run_id' => $runId,
                'file' => $filePath,
                'file_name' => basename($filePath),
                'size_bytes' => $size,
                'cleanup' => $cleanup,
            ];
        } catch (Throwable $e) {
            finishBackupRun($pdo, $runId, 'error', basename($filePath), null, $e->getMessage());
            appLog($pdo, 'backup.error', 'backup', (string)$runId, [
                'run_type' => $runType,
                'error' => $e->getMessage(),
            ], [
                'user_id' => $createdBy,
                'user_name' => $createdBy ? ($_SESSION['name'] ?? null) : 'Sistema',
                'user_role' => $createdBy ? ($_SESSION['role'] ?? null) : 'system',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ]);
            throw $e;
        }
    }
}
