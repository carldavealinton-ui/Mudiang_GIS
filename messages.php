<?php
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if (in_array($_SESSION['role'] ?? '', ['admin','staff'],true)) { header('Location: admin/messages.php?' . http_build_query(array_intersect_key($_GET,array_flip(['id','q','filter','page'])))); exit; }
if (($_SESSION['role'] ?? '')!=='resident') { http_response_code(403); exit; }
$messageAdmin=false;
require __DIR__ . '/messages_view.php';
