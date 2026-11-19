<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
session_save_path(sys_get_temp_dir());
require __DIR__.'/../config.php';
$pdo->exec(file_get_contents(__DIR__.'/../migrations/20261007_announcement_notifications.sql'));
echo "Announcement notifications migration applied.\n";
