<?php
// Copy this file to config/visits.local.php (it is not committed) and set your own secret:
//   php -r 'echo bin2hex(random_bytes(32)), "\n";'
// The secret keys the visitor hashes in includes/visits.php. Without this file a random secret is created
// once in storage/.visits-secret.
return ['secret' => 'replace-with-at-least-16-random-characters'];
