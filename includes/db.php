<?php
// EDU2IMPACT_DB_CONFIG may point outside the web root. Default matches your existing layout.
$configPath = getenv('EDU2IMPACT_DB_CONFIG') ?: dirname(__DIR__) . '/private/database.php';
if (!is_readable($configPath)) {
    throw new RuntimeException('Database configuration is unavailable');
}
$config = require $configPath;
if (!is_array($config)) {
    throw new RuntimeException('Invalid database configuration');
}
foreach (['host', 'name', 'user', 'pass'] as $key) {
    if (!isset($config[$key]) || !is_string($config[$key])) {
        throw new RuntimeException('Invalid database configuration');
    }
}
$pdo = new PDO(
    'mysql:host=' . $config['host'] . ';dbname=' . $config['name'] . ';charset=utf8mb4',
    $config['user'],
    $config['pass'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
unset($config);
