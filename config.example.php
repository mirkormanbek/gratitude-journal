<?php
// Copy to config.php ON THE SERVER ONLY (never commit config.php).
return [
    'db_host' => 'localhost',
    'db_name' => 'luckysp_journal',
    'db_user' => 'luckysp_journal',
    'db_pass' => 'CHANGE_ME',
    'session_name' => 'growth_journal_session',
    // Random string (64 hex chars) — same value as GitHub secret MIGRATE_TOKEN.
    // Generate: php -r "echo bin2hex(random_bytes(32));"
    'migrate_token' => '',
    // 'openai_api_key' => '...', 'quran_client_id' => '...', 'quran_client_secret' => '...',
];
