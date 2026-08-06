<?php
if (!function_exists('ensureCsrfToken')) {
    function ensureCsrfToken() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('getCsrfToken')) {
    function getCsrfToken() {
        return ensureCsrfToken();
    }
}

if (!function_exists('verifyCsrfRequest')) {
    function verifyCsrfRequest() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }

        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $requestToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');

        if (!$sessionToken || !$requestToken || !hash_equals($sessionToken, $requestToken)) {
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            $secFetchMode = $_SERVER['HTTP_SEC_FETCH_MODE'] ?? '';
            $isNavigation = stripos($accept, 'text/html') !== false || $secFetchMode === 'navigate';

            if ($isNavigation) {
                $redirectBase = stripos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false ? '../' : '';
                header('Location: ' . $redirectBase . 'index.php');
                exit;
            }

            http_response_code(419);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Token de seguridad invalido o ausente']);
            exit;
        }

        return true;
    }
}
