<?php
// includes/auth_helper.php
if (!function_exists('hasPermission')) {
    function hasPermission($pdo, $userId, $userRole, $permissionKey) {
        // Admin siempre tiene permiso
        if ($userRole === 'admin') return true;
        
        // Verificar si tiene permisos personalizados activos
        try {
            $userData = pdoQuery($pdo, "SELECT custom_permissions, role FROM users WHERE id = ?", [$userId])->fetch();
            
            if ($userData && isset($userData['custom_permissions']) && $userData['custom_permissions']) {
                $check = pdoQuery($pdo, "SELECT id FROM user_permissions WHERE user_id = ? AND permission_key = ?", [$userId, $permissionKey])->fetch();
                if ($check) return true;
            }
        } catch (Exception $e) {
            // Si la columna no existe aún, ignoramos y seguimos con los permisos base
        }

        // Roles default
        if ($userRole === 'receptionist') {
            $recPerms = ['add_apt', 'view_apt', 'add_payment', 'edit_payment', 'delete_payment', 'view_patient'];
            return in_array($permissionKey, $recPerms);
        }
        
        if ($userRole === 'therapist') {
            $therapistPerms = ['add_apt', 'view_apt', 'add_note', 'view_patient', 'add_clinical_hx'];
            return in_array($permissionKey, $therapistPerms);
        }

        return false;
    }
}
