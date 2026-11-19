<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/resident_features.php';
requireResident();
require_once __DIR__.'/portal_navigation.php';
$id = max(0, (int)($_GET['id'] ?? 0));
$page = max(1, (int)($_GET['page'] ?? 1));
$visible = 'is_published=1 AND published_at<=NOW() AND (expires_at IS NULL OR expires_at>NOW())';
if ($id) {
    $query = $pdo->prepare("SELECT id,title,body,category,published_at FROM community_announcements WHERE $visible AND id=?");
    $query->execute([$id]);
    $item = $query->fetch(PDO::FETCH_ASSOC);
    if (!$item) { http_response_code(404); exit('Announcement not found or no longer available.'); }
    $items = [$item];
    $pages = 1;
} else {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM community_announcements WHERE $visible")->fetchColumn();
    $pages = max(1, (int)ceil($count/10));
    $page = min($page, $pages);
    $offset = ($page-1)*10;
    $items = $pdo->query("SELECT id,title,body,category,published_at FROM community_announcements WHERE $visible ORDER BY published_at DESC,id DESC LIMIT 10 OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
}
renderPortalPageStart('Community announcements','Read the latest advisories and updates from Barangay Mudiang.');
?>
<section class="card"><div class="card-header"><h2><?= $id ? 'Announcement details' : 'Latest announcements' ?></h2><a href="<?= $id ? 'announcements.php' : 'resident_dashboard.php' ?>" class="btn btn-outline"><?= $id ? 'All announcements' : 'Back to dashboard' ?></a></div><div class="card-body">
<?php if (!$items): ?><div class="announcement-empty"><i class="fas fa-bullhorn" aria-hidden="true"></i><h3>No announcements right now</h3><p>Published community updates will appear here. Check back for new advisories.</p></div><?php endif; ?>
<?php foreach ($items as $item): ?>
<article class="resident-announcement">
<div class="announcement-meta"><span class="announcement-category"><?= residentEscape($item['category']) ?></span><time datetime="<?= residentEscape(str_replace(' ','T',$item['published_at'])) ?>+08:00"><?= residentEscape(date('M j, Y · g:i A',strtotime($item['published_at']))) ?> (Philippine time)</time></div>
<h3><?= residentEscape($item['title']) ?></h3>
<p class="announcement-body"><?= nl2br(residentEscape($id ? $item['body'] : mb_strimwidth($item['body'],0,280,'…'))) ?></p>
<?php if (!$id): ?><a class="announcement-view" href="announcements.php?id=<?= (int)$item['id'] ?>">View announcement <i class="fas fa-arrow-right" aria-hidden="true"></i></a><?php endif; ?>
</article>
<?php endforeach; ?>
<?php if (!$id && $pages > 1): ?><nav class="resident-pagination" aria-label="Announcement pages"><?php if ($page>1): ?><a href="?page=<?= $page-1 ?>">Previous</a><?php endif; ?><span>Page <?= $page ?> of <?= $pages ?></span><?php if ($page<$pages): ?><a href="?page=<?= $page+1 ?>">Next</a><?php endif; ?></nav><?php endif; ?>
</div></section>
<?php renderPortalPageEnd(); ?>
