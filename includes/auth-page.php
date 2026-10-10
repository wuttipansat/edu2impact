<?php
// Reserved for future authenticated phase. Not loaded by any public page.
require __DIR__ . '/bootstrap.php';
header('Cache-Control: no-store, private');
try {
    auth_private_transport();
    $accountUser = auth_user();
} catch (Throwable $exception) { auth_service_error($exception); }
