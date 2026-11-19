<?php
require_once __DIR__ . '/auth.php';
function residentEscape($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$params = [];
$where = "u.role = 'resident'";
if ($search !== '') {
    $where .= ' AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.contact LIKE ? OR u.address LIKE ?)';
    $params = array_fill(0, 5, '%' . $search . '%');
}
$query = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $where");
$query->execute($params);
$total = (int) $query->fetchColumn();
$pages = max(1, (int) ceil($total / 20));
$page = min($page, $pages);
$offset = ($page - 1) * 20;
$query = $pdo->prepare("SELECT u.id, u.full_name, u.username, u.email, u.contact, u.address, u.created_at,
    (SELECT COUNT(*) FROM complaints c WHERE c.user_id = u.id) AS complaint_count
    FROM users u WHERE $where ORDER BY u.created_at DESC, u.id DESC LIMIT 20 OFFSET $offset");
$query->execute($params);
$residents = $query->fetchAll(PDO::FETCH_ASSOC);
$registered = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'resident'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Residents — Barangay Mudiang</title>
    <link rel="stylesheet" href="../assets/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/../ui_theme.php'; ?>
</head>
<body class="portal-page resident-directory">
<?php require_once __DIR__.'/../portal_navigation.php'; renderPortalNavigation(); ?>
<main class="main">
    <header class="topbar"><div class="topbar-left"><h1>Resident directory</h1><p>Registered members of the Mudiang community</p></div><div class="topbar-right"><a href="dashboard.php" class="btn btn-outline">Complaint dashboard</a></div></header>
    <div class="content">
        <section class="dashboard-intro"><div><span class="portal-kicker">Community / Residents</span><h2>Know the people you serve.</h2><p>Find registered residents, their contact details, and the number of concerns they have submitted.</p></div><span class="intro-tag"><?php echo $registered; ?> registered residents</span></section>
        <section class="card">
            <div class="card-header directory-header"><div><h2>All residents</h2><p class="directory-count"><?php echo $total; ?> <?php echo $total === 1 ? 'resident' : 'residents'; ?><?php echo $search !== '' ? ' matching your search' : ''; ?></p></div>
                <form method="get" class="directory-search"><label class="visually-hidden" for="resident-search">Search residents</label><input type="search" class="form-control" id="resident-search" name="q" value="<?php echo residentEscape($search); ?>" placeholder="Name, email, contact or address"><button class="btn btn-primary" type="submit">Search</button><?php if ($search !== ''): ?><a class="btn btn-outline" href="residents.php">Clear</a><?php endif; ?></form>
            </div>
            <div class="table-wrap"><table><caption class="visually-hidden">Registered residents and their complaint counts</caption><thead><tr><th scope="col">Resident</th><th scope="col">Email</th><th scope="col">Contact</th><th scope="col">Address</th><th scope="col">Complaints</th><th scope="col">Joined</th></tr></thead><tbody>
            <?php foreach ($residents as $resident): ?>
                <tr><td><strong><?php echo residentEscape($resident['full_name']); ?></strong><span class="resident-username">@<?php echo residentEscape($resident['username']); ?></span></td><td><?php echo residentEscape($resident['email'] ?: 'Not provided'); ?></td><td><?php echo residentEscape($resident['contact'] ?: 'Not provided'); ?></td><td class="resident-address"><?php echo residentEscape($resident['address'] ?: 'Not provided'); ?></td><td><?php echo (int) $resident['complaint_count']; ?></td><td class="text-nowrap"><?php echo date('M j, Y', strtotime($resident['created_at'])); ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$residents): ?><tr><td colspan="6"><div class="empty-state"><strong><?php echo $search !== '' ? 'No matching residents' : 'No residents registered yet'; ?></strong><p><?php echo $search !== '' ? 'Try another name or contact detail.' : 'New resident accounts will appear here after registration.'; ?></p></div></td></tr><?php endif; ?>
            </tbody></table></div>
            <nav class="directory-pagination" aria-label="Resident pages"><span>Page <?php echo $page; ?> of <?php echo $pages; ?></span><div><?php if ($page > 1): ?><a class="btn btn-outline" href="?<?php echo residentEscape(http_build_query(['q' => $search, 'page' => $page - 1])); ?>">Previous</a><?php endif; ?> <?php if ($page < $pages): ?><a class="btn btn-outline" href="?<?php echo residentEscape(http_build_query(['q' => $search, 'page' => $page + 1])); ?>">Next</a><?php endif; ?></div><a class="nav-item" href="../account.php"><i class="fas fa-lock" aria-hidden="true"></i> Account security</a></nav>
        </section>
    </div>
</main>
<?php include __DIR__ . '/notification_widget.php'; ?>
</body>
</html>
