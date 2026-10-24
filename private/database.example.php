<?php
// Copy to database.php on the server only. Never commit real credentials.
return [
    'host' => 'localhost',
    'name' => 'e2imedu',
    'user' => 'YOUR_MYSQL_USERNAME',
    'pass' => 'YOUR_MYSQL_PASSWORD',
    // Where pages read research, innovations, workshops, media, news, events and site settings from:
    // 'json'  = data/*.json (default)
    // 'mysql' = the tables in database/content-schema.sql. If MySQL cannot be reached, the JSON files are used.
    'content_source' => 'json',
];
