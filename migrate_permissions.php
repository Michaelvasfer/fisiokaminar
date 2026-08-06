<?php
require_once 'db.php';

try {
    // 1. Crear tabla de permisos
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        permission_key VARCHAR(50) NOT NULL,
        UNIQUE KEY idx_user_perm (user_id, permission_key),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // 2. Asegurar que la columna permissions_enabled existe en users (opcional, para flag general)
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN custom_permissions TINYINT(1) DEFAULT 0");
    } catch(Exception $e) {}

    echo "Migración de permisos completada exitosamente.";
} catch (Exception $e) {
    echo "Error en la migración: " . $e->getMessage();
}
