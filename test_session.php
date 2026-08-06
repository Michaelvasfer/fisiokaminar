<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "Iniciando sesión...<br>";
session_start();
echo "✓ Sesión iniciada.<br>";
$_SESSION['test'] = time();
echo "Valor guardado: " . $_SESSION['test'];
?>
