<?php
function residentEscape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function requireResident(): void {
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'resident') {
        http_response_code(403); exit('Resident access required.');
    }
    $_SESSION['resident_csrf'] ??= bin2hex(random_bytes(32));
}
function residentCsrf(): void {
    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['resident_csrf'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403); throw new InvalidArgumentException('Your session expired. Refresh the page and try again.');
    }
}
function residentNextStep(string $status): string {
    return ['filed'=>'Staff will review and acknowledge your report. You can add details through Messages.',
        'acknowledged'=>'Your report has been received. Staff will arrange the next action and target date.',
        'processing'=>'Staff are working on your concern. Check the timeline for updates or message the team.',
        'resolved'=>'Review the resolution. Confirm it is fixed or request reopening if the concern remains.'][$status] ?? 'Check your report timeline for updates.';
}
function liveAnnouncements(PDO $pdo): array {
    return $pdo->query('SELECT id,title,body,category,published_at FROM community_announcements WHERE is_published=1 AND published_at<=NOW() AND (expires_at IS NULL OR expires_at>NOW()) ORDER BY published_at DESC,id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
}
function renderAnnouncements(PDO $pdo): void {
    // Keep the dashboard compact; the announcements page provides the full list.
    $items=array_slice(liveAnnouncements($pdo), 0, 2);
    echo '<section class="card dashboard-announcements" id="barangay-announcements" aria-labelledby="dashboard-announcements-title"><div class="card-header"><div class="announcement-heading"><span class="announcement-heading-icon"><i class="fas fa-bullhorn" aria-hidden="true"></i></span><div><h2 id="dashboard-announcements-title">Barangay announcements</h2><p>Community news and advisories, all in one place.</p></div></div><a class="announcement-all" href="announcements.php">View all <i class="fas fa-arrow-right" aria-hidden="true"></i></a></div><div class="card-body">';
    if (!$items) echo '<div class="announcement-empty"><i class="fas fa-bullhorn" aria-hidden="true"></i><h3>You&#8217;re all caught up</h3><p>No active announcements. Check back for community advisories.</p></div>';
    if ($items) echo '<div class="dashboard-announcement-grid">';
    foreach ($items as $item) {
        $published = new DateTimeImmutable($item['published_at'], new DateTimeZone('Asia/Manila'));
        $url = 'announcements.php?id='.(int)$item['id'];
        echo '<article class="dashboard-announcement"><div class="dashboard-announcement-meta"><span class="announcement-category">'.residentEscape($item['category']).'</span><time datetime="'.residentEscape($published->format(DateTimeInterface::ATOM)).'" title="'.residentEscape($published->format('F j, Y, g:i A').' (Philippine time)').'"><i class="far fa-calendar" aria-hidden="true"></i> '.residentEscape($published->format('M j, Y')).'</time></div><h3><a href="'.$url.'">'.residentEscape($item['title']).'</a></h3><p class="dashboard-announcement-preview">'.residentEscape(mb_strimwidth(preg_replace('/\s+/u', ' ', $item['body']),0,200,'…')).'</p><a class="dashboard-announcement-link" href="'.$url.'" aria-label="'.residentEscape('Read announcement: '.$item['title']).'">Read announcement <i class="fas fa-arrow-right" aria-hidden="true"></i></a></article>';
    }
    if ($items) echo '</div>';
    echo '</div></section>';
}
function residentPageStart(string $title): void {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.residentEscape($title).'</title><link rel="stylesheet" href="assets/vendor/bootstrap.min.css">';
    include __DIR__.'/ui_theme.php';
    echo '</head><body class="auth-page"><main class="login-container resident-settings"><span class="portal-kicker">Barangay Mudiang / Resident services</span><h1>'.residentEscape($title).'</h1>';
}
