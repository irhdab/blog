<?php
header('Content-Type: application/json');
$info = [
    'php_version' => PHP_VERSION,
    'pdo_drivers' => PDO::getAvailableDrivers(),
    'pgsql_extension' => extension_loaded('pgsql'),
    'pdo_pgsql_extension' => extension_loaded('pdo_pgsql'),
    'display_errors' => ini_get('display_errors'),
    'memory_limit' => ini_get('memory_limit'),
];
echo json_encode($info);
?>