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
    // Hidden admin (see STRUCTURE.md, "Admin (hidden)"). Optional second MySQL user with INSERT/UPDATE/DELETE on the
    // content tables, so 'user' above can stay SELECT-only. Without these two lines the admin uses 'user' and 'pass'.
    // 'admin_user' => 'YOUR_ADMIN_MYSQL_USERNAME',
    // 'admin_pass' => 'YOUR_ADMIN_MYSQL_PASSWORD',
    // Long random text (40+ characters) typed once on the setup page that creates the first admin account.
    // Delete this line after the account exists.
    // 'setup_token' => '',
];
