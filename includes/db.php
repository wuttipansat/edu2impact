<?php
// Connection for the account and admin pages (includes/auth.php). Not loaded by any public page.
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
// The hidden admin (admin folder, includes/admin/) writes to the database. If private/database.php has admin_user and
// admin_pass they are used here, so the public site's own user can stay SELECT-only. Without them the same user is used.
$dbUser = isset($config['admin_user'], $config['admin_pass']) && is_string($config['admin_user']) && is_string($config['admin_pass']) && $config['admin_user'] !== ''
    ? $config['admin_user'] : $config['user'];
$dbPass = $dbUser === $config['user'] ? $config['pass'] : $config['admin_pass'];
$pdo = new PDO(
    'mysql:host=' . $config['host'] . ';dbname=' . $config['name'] . ';charset=utf8mb4',
    $dbUser,
    $dbPass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
unset($config, $dbUser, $dbPass);
