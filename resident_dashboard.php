<?php
require_once 'config.php';
require_once __DIR__ . '/conversation_support.php';
require_once __DIR__ . '/evidence_support.php';
require_once __DIR__ . '/resident_features.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$unreadConversations = unreadConversationCounts($pdo, (int) $user_id, false);

requireResident();
$search=is_string($_GET['q']??null)?mb_substr(trim($_GET['q']),0,100):'';
$filterStatus=is_string($_GET['status']??null)?$_GET['status']:'';
$filterCategory=is_string($_GET['category']??null)?$_GET['category']:'';
$filterFrom=is_string($_GET['from']??null)?$_GET['from']:'';
$filterTo=is_string($_GET['to']??null)?$_GET['to']:'';
$categories=['Road Damage','Drainage Issue','Street Light','Garbage Collection','Noise Complaint','Water Supply','Other'];
$statuses=['filed','acknowledged','processing','resolved'];
$where=['user_id = ?'];$params=[$user_id];
if($search!==''){$where[]='(description LIKE ? OR address LIKE ? OR complaint_type LIKE ?)';$escapedSearch=strtr($search,['\\'=>'\\\\','%'=>'\\%','_'=>'\\_']);for($i=0;$i<3;$i++)$params[]='%'.$escapedSearch.'%';}
if(in_array($filterStatus,$statuses,true)){$where[]='status=?';$params[]=$filterStatus;}
if(in_array($filterCategory,$categories,true)){$where[]='complaint_type=?';$params[]=$filterCategory;}
$validDate=static function($date){$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);return $parsed&&$parsed->format('Y-m-d')===$date;};
if($validDate($filterFrom)){$where[]='created_at>=?';$params[]=$filterFrom.' 00:00:00';}
if($validDate($filterTo)){$where[]='created_at<?';$params[]=(new DateTimeImmutable($filterTo))->modify('+1 day')->format('Y-m-d');}
$condition=implode(' AND ',$where);
$count=$pdo->prepare('SELECT COUNT(*) FROM complaints WHERE '.$condition);$count->execute($params);$filteredTotal=(int)$count->fetchColumn();
$pages=max(1,(int)ceil($filteredTotal/10));$page=min($pages,max(1,(int)($_GET['page']??1)));$offset=($page-1)*10;
$stmt = $pdo->prepare("SELECT * FROM complaints WHERE $condition ORDER BY created_at DESC,id DESC LIMIT 10 OFFSET $offset");
$stmt->execute($params);
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
$evidenceByComplaint = [];
if($complaints){
    $ids=array_column($complaints,'id');$placeholders=implode(',',array_fill(0,count($ids),'?'));
    $evidenceQuery=$pdo->prepare("SELECT * FROM complaint_evidence WHERE complaint_id IN ($placeholders) ORDER BY id");$evidenceQuery->execute($ids);
    foreach($evidenceQuery->fetchAll(PDO::FETCH_ASSOC) as $file)$evidenceByComplaint[$file['complaint_id']][]=$file;
}
$supportedQuery=$pdo->prepare('SELECT c.id,c.complaint_type,c.status,c.due_date,c.created_at FROM complaint_supporters s JOIN complaints c ON c.id=s.complaint_id WHERE s.user_id=? ORDER BY s.created_at DESC LIMIT 20');$supportedQuery->execute([$user_id]);$supportedIssues=$supportedQuery->fetchAll(PDO::FETCH_ASSOC);
$draftQuery=$pdo->prepare('SELECT updated_at FROM resident_drafts WHERE user_id=?');$draftQuery->execute([$user_id]);$draftUpdated=$draftQuery->fetchColumn();

$stats_q = $pdo->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='filed' THEN 1 ELSE 0 END) as filed,
    SUM(CASE WHEN status='acknowledged' THEN 1 ELSE 0 END) as acknowledged,
    SUM(CASE WHEN status='processing' THEN 1 ELSE 0 END) as processing,
    SUM(CASE WHEN status='resolved' THEN 1 ELSE 0 END) as resolved
    FROM complaints WHERE user_id=?");
$stats_q->execute([$user_id]);
$stats = $stats_q->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard — Barangay Mudiang</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
    <style>
        :root {
            --primary: #2563eb; --primary-dark: #1d4ed8;
            --sidebar-bg: #0f172a; --sidebar-hover: #1e293b;
            --bg: #f1f5f9; --card-bg: #ffffff;
            --text: #0f172a; --text-muted: #64748b;
            --border: #e2e8f0; --radius: 12px;
            --shadow: 0 1px 3px rgba(0,0,0,0.08), 0 4px 16px rgba(0,0,0,0.06);
            --filed: #ef4444; --progress: #f59e0b; --resolved: #10b981;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }

        .sidebar {
            width: 260px; min-height: 100vh; background: var(--sidebar-bg);
            display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 100;
        }
        .sidebar-brand { padding: 28px 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.07); }
        .sidebar-brand .brand-icon {
            width: 42px; height: 42px; background: var(--primary); border-radius: 10px;
            display: flex; align-items: center; justify-content: center; font-size: 18px; color: white; margin-bottom: 12px;
        }
        .sidebar-brand h2 { color: white; font-size: 15px; font-weight: 600; line-height: 1.3; }
        .sidebar-brand p { color: #64748b; font-size: 12px; margin-top: 2px; }
        .sidebar-nav { padding: 16px 12px; flex: 1; }
        .nav-label { color: #475569; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; padding: 0 12px; margin: 16px 0 6px; }
        .nav-item {
            display: flex; align-items: center; gap: 12px; padding: 10px 12px;
            border-radius: 8px; color: #94a3b8; text-decoration: none;
            font-size: 14px; font-weight: 500; transition: all 0.2s; margin-bottom: 2px;
        }
        .nav-item:hover { background: var(--sidebar-hover); color: white; }
        .nav-item.active { background: var(--primary); color: white; }
        .nav-item i { width: 18px; text-align: center; font-size: 15px; }
        .sidebar-footer { padding: 16px 12px; border-top: 1px solid rgba(255,255,255,0.07); }
        .user-info {
            display: flex; align-items: center; gap: 10px; padding: 10px 12px;
            border-radius: 8px; background: rgba(255,255,255,0.05); margin-bottom: 8px;
        }
        .user-avatar {
            width: 34px; height: 34px; background: var(--primary); border-radius: 50%;
            display: flex; align-items: center; justify-content: center; font-size: 14px; color: white; font-weight: 600; flex-shrink: 0;
        }
        .user-info .user-name { color: white; font-size: 13px; font-weight: 500; }
        .user-info .user-role { color: #64748b; font-size: 11px; }

        .main { margin-left: 260px; flex: 1; display: flex; flex-direction: column; }
        .topbar {
            background: white; padding: 16px 32px; display: flex; justify-content: space-between;
            align-items: center; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 50;
        }
        .topbar-left h1 { font-size: 20px; font-weight: 700; }
        .topbar-left p { font-size: 13px; color: var(--text-muted); margin-top: 2px; }
        .content { padding: 28px 32px; }

        .btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 600;
            text-decoration: none; border: none; cursor: pointer; transition: all 0.2s;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-outline { background: white; color: var(--text); border: 1px solid var(--border); }
        .btn-outline:hover { background: var(--bg); }

        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 28px; }
        .stat-card {
            background: var(--card-bg); border-radius: var(--radius); padding: 22px 24px;
            box-shadow: var(--shadow); display: flex; align-items: center; gap: 18px;
            border: 1px solid var(--border); transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-icon {
            width: 52px; height: 52px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;
        }
        .stat-icon.blue { background: #eff6ff; color: var(--primary); }
        .stat-icon.red { background: #fef2f2; color: var(--pending); }
        .stat-icon.amber { background: #fffbeb; color: var(--progress); }
        .stat-icon.green { background: #f0fdf4; color: var(--resolved); }
        .stat-info h3 { font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-info .number { font-size: 32px; font-weight: 700; color: var(--text); line-height: 1.1; margin-top: 4px; }

        .card { background: var(--card-bg); border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border); margin-bottom: 24px; }
        .card-header { padding: 18px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .card-header h2 { font-size: 15px; font-weight: 600; color: var(--text); display: flex; align-items: center; gap: 8px; }
        .card-header h2 i { color: var(--primary); }

        .complaints-list { display: flex; flex-direction: column; gap: 0; }
        .complaint-item {
            padding: 20px 24px; border-bottom: 1px solid var(--border);
            display: grid; grid-template-columns: 1fr auto; gap: 16px; align-items: start;
        }
        .complaint-item:last-child { border-bottom: none; }
        .complaint-item:hover { background: #f8fafc; }
        .ci-type { font-size: 15px; font-weight: 600; color: var(--text); margin-bottom: 4px; }
        .ci-desc { font-size: 13px; color: var(--text-muted); margin-bottom: 8px; line-height: 1.5; }
        .ci-meta { display: flex; flex-wrap: wrap; gap: 14px; }
        .ci-meta span { font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 5px; }
        .ci-meta span i { color: #94a3b8; }
        .ci-right { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }

        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: capitalize; white-space: nowrap;
        }
        .badge.filed { background: #fef2f2; color: #dc2626; }
        .badge.processing { background: #fffbeb; color: #d97706; }
        .badge.resolved { background: #f0fdf4; color: #16a34a; }
        .badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        .priority-badge {
            font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; text-transform: capitalize;
        }
        .priority-badge.high { background: #fef2f2; color: #dc2626; }
        .priority-badge.medium { background: #fffbeb; color: #d97706; }
        .priority-badge.low { background: #f0fdf4; color: #16a34a; }

        .mini-map { height: 160px; border-radius: 8px; border: 1px solid var(--border); margin-top: 12px; }

        .resolution-box {
            background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;
            padding: 12px 14px; margin-top: 10px; font-size: 13px; color: #15803d;
        }
        .resolution-box strong { display: block; margin-bottom: 4px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }

        .empty-state { text-align: center; padding: 60px 24px; }
        .empty-state .empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 16px; }
        .empty-state h3 { font-size: 18px; font-weight: 600; color: var(--text); margin-bottom: 8px; }
        .empty-state p { font-size: 14px; color: var(--text-muted); margin-bottom: 20px; }

        /* Notification bell */
        .notif-wrap { position: relative; }
        .notif-btn {
            width: 38px; height: 38px; border-radius: 8px; border: 1px solid var(--border);
            background: white; cursor: pointer; display: flex; align-items: center; justify-content: center;
            font-size: 16px; color: var(--text-muted); transition: all 0.2s; position: relative;
        }
        .notif-btn:hover { background: var(--bg); color: var(--text); }
        .notif-badge {
            position: absolute; top: -5px; right: -5px;
            background: #ef4444; color: white; font-size: 10px; font-weight: 700;
            min-width: 18px; height: 18px; border-radius: 9px; padding: 0 4px;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid white; display: none;
        }
        .notif-dropdown {
            position: absolute; top: calc(100% + 10px); right: 0;
            width: 340px; background: white; border: 1px solid var(--border);
            border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.12);
            z-index: 200; display: none; overflow: hidden;
        }
        .notif-dropdown.open { display: block; }
        .notif-head {
            padding: 14px 18px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .notif-head span { font-size: 14px; font-weight: 600; color: var(--text); }
        .notif-head button {
            font-size: 12px; color: var(--primary); background: none; border: none;
            cursor: pointer; font-weight: 500; font-family: 'Inter', sans-serif;
        }
        .notif-head button:hover { color: var(--primary-dark); }
        .notif-list { max-height: 320px; overflow-y: auto; }
        .notif-item {
            padding: 13px 18px; border-bottom: 1px solid var(--border);
            display: flex; gap: 12px; align-items: flex-start; transition: background 0.15s;
        }
        .notif-item:last-child { border-bottom: none; }
        .notif-item.unread { background: #eff6ff; }
        .notif-item:hover { background: #f8fafc; }
        .notif-icon {
            width: 34px; height: 34px; border-radius: 8px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 14px;
        }
        .notif-icon.filed { background: #fef2f2; color: #dc2626; }
        .notif-icon.processing { background: #fffbeb; color: #d97706; }
        .notif-icon.resolved { background: #f0fdf4; color: #16a34a; }
        .notif-icon.default { background: #eff6ff; color: var(--primary); }
        .notif-msg { font-size: 13px; color: var(--text); line-height: 1.45; }
        .notif-time { font-size: 11px; color: var(--text-muted); margin-top: 3px; }
        .notif-empty { padding: 32px 18px; text-align: center; color: var(--text-muted); font-size: 13px; }
        .notif-empty i { font-size: 28px; color: #cbd5e1; display: block; margin-bottom: 8px; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }

    </style>
<?php include __DIR__ . '/ui_theme.php'; ?>
</head>
<body class="portal-page ">

<?php require_once __DIR__.'/portal_navigation.php'; renderPortalNavigation(); ?>

<div class="main">
    <div class="topbar">
        <div class="topbar-left">
            <h1>My Dashboard</h1>
            <p><?php echo date('l, F j, Y'); ?></p>
        </div>
        <div class="topbar-right">
            <!-- Notification Bell -->
            <div class="notif-wrap" id="notifWrap">
                <button class="notif-btn" id="notifBtn" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="notif-badge" id="notifBadge"></span>
                </button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-head">
                        <span>Notifications</span>
                        <button id="markAllRead">Mark all as read</button>
                    </div>
                    <div class="notif-list" id="notifList">
                        <div class="notif-empty"><i class="fas fa-bell-slash"></i>No notifications yet</div>
                    </div>
                </div>
            </div>
            <a href="submit_complaint_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Complaint</a>
        </div>
    </div>

    <div class="content">
<section class="dashboard-intro"><div><span class="portal-kicker">Barangay Mudiang / Community services</span><h2>Your voice. A better Mudiang.</h2><p>Report a concern, pinpoint its location, and follow the progress from your personal dashboard.</p></div><span class="intro-tag">GIS Complaint &amp; Resolution Portal</span></section>
        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger" role="alert"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> Your complaint could not be saved. Please try submitting it again.</div>
        <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a;padding:12px 16px;border-radius:8px;font-size:13px;font-weight:500;margin-bottom:20px;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-check-circle"></i> Complaint submitted successfully!
        </div>
        <?php endif; ?>

        <?php renderAnnouncements($pdo); ?>
        <?php if($draftUpdated): ?><div class="alert alert-info"><div><strong>You have a saved draft.</strong> Last saved: <?= residentEscape($draftUpdated) ?>. <a href="submit_complaint_form.php?draft=1">Continue your report</a></div></div><?php endif; ?>
        <?php if($supportedIssues): ?><section class="card"><div class="card-header"><h2>Issues I support</h2></div><div class="card-body"><p class="mb-3">Track shared concerns without filing another report. Private report details remain visible to the original resident and staff.</p><?php foreach($supportedIssues as $issue): ?><article class="community-advisory"><strong>Report #<?= (int)$issue['id'] ?> · <?= residentEscape($issue['complaint_type']) ?></strong><p><?= residentEscape(ucfirst($issue['status'])) ?> · Target: <?= residentEscape($issue['due_date'] ?: 'Awaiting scheduling') ?></p></article><?php endforeach; ?></div></section><?php endif; ?>
        <!-- Stats -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-info"><h3>Total</h3><div class="number"><?php echo $stats['total']; ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-clock"></i></div>
                <div class="stat-info"><h3>Filed</h3><div class="number" style="color:var(--pending)"><?php echo $stats['filed']; ?></div></div>
            </div>
            <div class="stat-card"><div class="stat-icon acknowledged"><i class="fas fa-check-double" aria-hidden="true"></i></div><div class="stat-info"><h3>Acknowledged</h3><div class="number"><?php echo (int) $stats['acknowledged']; ?></div></div></div>
<div class="stat-card">
                <div class="stat-icon amber"><i class="fas fa-spinner"></i></div>
                <div class="stat-info"><h3>Processing</h3><div class="number" style="color:var(--progress)"><?php echo $stats['processing']; ?></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info"><h3>Resolved</h3><div class="number" style="color:var(--resolved)"><?php echo $stats['resolved']; ?></div></div>
            </div>
        </div>

        <!-- Complaints List -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-list-alt"></i> My Complaints</h2>
                <span style="font-size:13px;color:var(--text-muted)"><?php echo $filteredTotal; ?> matching reports</span>
            </div>
            <form method="get" class="resident-filters card-body" aria-label="Filter reports">
                <div class="form-group"><label for="report-search">Search reports</label><input id="report-search" name="q" maxlength="100" value="<?= residentEscape($search) ?>" placeholder="Search description or address"></div>
                <div class="form-group"><label for="filter-status">Status</label><select name="status" id="filter-status"><option value="">All statuses</option><?php foreach($statuses as $status): ?><option value="<?= $status ?>" <?= $filterStatus===$status?'selected':'' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label for="filter-category">Category</label><select name="category" id="filter-category"><option value="">All categories</option><?php foreach($categories as $category): ?><option <?= $filterCategory===$category?'selected':'' ?>><?= residentEscape($category) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label for="filter-from">From date</label><input type="date" id="filter-from" name="from" value="<?= residentEscape($validDate($filterFrom)?$filterFrom:'') ?>"></div><div class="form-group"><label for="filter-to">To date</label><input type="date" id="filter-to" name="to" value="<?= residentEscape($validDate($filterTo)?$filterTo:'') ?>"></div>
                <div class="filter-actions"><button class="btn btn-primary">Apply filters</button><a href="resident_dashboard.php" class="btn btn-outline">Clear filters</a></div>
            </form>
            <?php if (empty($complaints)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-clipboard"></i></div>
                <h3>No reports found</h3>
                <p>Try different filters or submit a new report.</p>
                <a href="submit_complaint_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Submit First Complaint</a>
            </div>
            <?php else: ?>
            <div class="complaints-list">
                <?php foreach ($complaints as $c): ?>
                <div class="complaint-item">
                    <div>
                        <div class="ci-type"><?php echo htmlspecialchars($c['complaint_type']); ?></div>
                        <div class="ci-desc"><?php echo htmlspecialchars($c['description']); ?></div>
                        <div class="ci-meta">
                            <span><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($c['address']); ?></span>
                            <span><i class="fas fa-calendar"></i><?php echo date('M d, Y', strtotime($c['created_at'])); ?></span>
                            <?php if ($c['due_date']): ?><span><i class="fas fa-calendar-check" aria-hidden="true"></i> Target: <?php echo htmlspecialchars($c['due_date']); ?></span><?php endif; ?><?php if ($c['resident_confirmed_at']): ?><span><i class="fas fa-check-double" aria-hidden="true"></i> Resolution confirmed</span><?php endif; ?><?php if ($c['assigned_to']): ?>
                            <span><i class="fas fa-user-tie"></i><?php echo htmlspecialchars($c['assigned_to']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($c['resolution_notes']): ?>
                        <div class="resolution-box">
                            <strong><i class="fas fa-clipboard-check"></i> Resolution Notes</strong>
                            <?php echo htmlspecialchars($c['resolution_notes']); ?>
                        </div>
                        <?php endif; ?>
                        <p class="resident-next-step"><strong>Next step:</strong> <?= residentEscape(residentNextStep($c['status'])) ?></p>
                        <div class="mini-map" id="map-<?php echo $c['id']; ?>"></div><?php if (!empty($evidenceByComplaint[$c['id']])): ?><details class="complaint-evidence"><summary><i class="fas fa-paperclip" aria-hidden="true"></i> Evidence · <?php echo count($evidenceByComplaint[$c['id']]); ?> attached files</summary><?php renderEvidenceGallery($evidenceByComplaint[$c['id']]); ?></details><?php endif; ?>
                    </div>
                    <div class="ci-right"><a class="btn btn-outline" href="complaint_conversation.php?id=<?php echo (int)$c['id']; ?>"><i class="fas fa-list-check" aria-hidden="true"></i> <?php echo $c['status']==='resolved'?'Review resolution':'Track report'; ?></a><a href="messages.php?id=<?php echo (int) $c['id']; ?>" class="btn btn-outline complaint-message-link"><i class="fas fa-comments" aria-hidden="true"></i> Message staff <?php if (!empty($unreadConversations[$c['id']])): ?><span class="message-unread-count"><?php echo (int) $unreadConversations[$c['id']]; ?> new</span><?php endif; ?></a>
                        <span class="badge <?php echo $c['status']; ?>"><?php echo str_replace('_', ' ', $c['status']); ?></span>
                        <?php if ($c['priority']): ?>
                        <span class="priority-badge <?php echo $c['priority']; ?>"><?php echo ucfirst($c['priority']); ?> Priority</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <nav class="resident-pagination card-body" aria-label="Report pages">
            <?php $pageQuery=['q'=>$search,'status'=>$filterStatus,'category'=>$filterCategory,'from'=>$filterFrom,'to'=>$filterTo]; ?>
            <?php if($page>1): ?><a class="btn btn-outline" href="?<?= residentEscape(http_build_query($pageQuery+['page'=>$page-1])) ?>">Previous</a><?php endif; ?>
            <span>Page <?= $page ?> of <?= $pages ?></span>
            <?php if($page<$pages): ?><a class="btn btn-outline" href="?<?= residentEscape(http_build_query($pageQuery+['page'=>$page+1])) ?>">Next</a><?php endif; ?></nav>
        </div>
    </div>
</div>

<?php include 'chatbot_widget.php'; ?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const complaints = <?php echo json_encode($complaints,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
    const colorMap = { filed:'#a77853', acknowledged:'#59788c', processing:'#b08b3f', resolved:'#34755b' };
    function loadReportMap(c) {
        if (!c.latitude || !c.longitude) return;
        const map = L.map('map-' + c.id, { zoomControl: false, dragging: false, scrollWheelZoom: false })
            .setView([parseFloat(c.latitude), parseFloat(c.longitude)], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
        L.circleMarker([parseFloat(c.latitude), parseFloat(c.longitude)], {
            radius: 8, fillColor: colorMap[c.status] || '#2563eb', color: '#fff', weight: 2.5, fillOpacity: 0.9
        }).addTo(map);
    }
    if ('IntersectionObserver' in window) {
        const observer=new IntersectionObserver(entries => entries.forEach(entry => {
            if(!entry.isIntersecting)return;
            const c=complaints.find(item=>'map-'+item.id===entry.target.id);
            if(c)loadReportMap(c);
            observer.unobserve(entry.target);
        }),{rootMargin:'150px'});
        complaints.forEach(c=>{const element=document.getElementById('map-'+c.id);if(element)observer.observe(element);});
    } else complaints.forEach(loadReportMap);

    // ── Notifications ──
    const notifBtn      = document.getElementById('notifBtn');
    const notifDropdown = document.getElementById('notifDropdown');
    const notifBadge    = document.getElementById('notifBadge');
    const notifList     = document.getElementById('notifList');
    const markAllRead   = document.getElementById('markAllRead');

    function timeAgo(dateStr) {
        const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff/60) + 'm ago';
        if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
        return Math.floor(diff/86400) + 'd ago';
    }

    function statusIcon(msg) {
        if (msg.includes('Resolved')) return 'resolved';
        if (msg.includes('Processing')) return 'processing';
        if (msg.includes('Filed')) return 'filed';
        if (msg.includes('Acknowledged')) return 'acknowledged';
        return 'default';
    }

    function iconClass(type) {
        return { resolved: 'fa-check-circle', processing: 'fa-spinner', filed: 'fa-clock', default: 'fa-bell' }[type] || 'fa-bell';
    }

    function loadNotifications() {
        fetch('get_notifications.php')
            .then(r => r.json())
            .then(data => {
                // Badge
                if (data.count > 0) {
                    notifBadge.textContent = data.count > 9 ? '9+' : data.count;
                    notifBadge.style.display = 'flex';
                } else {
                    notifBadge.style.display = 'none';
                }

                // List
                if (!data.items.length) {
                    notifList.innerHTML = '<div class="notif-empty"><i class="fas fa-bell-slash"></i>No notifications yet</div>';
                    return;
                }
                notifList.replaceChildren();
                data.items.forEach(n => {
                    const row = document.createElement('div');
                    row.className = 'notif-item' + (Number(n.is_read) === 0 ? ' unread' : '');
                    const icon = document.createElement('div');
                    const type = n.event_type === 'message' ? 'default' : statusIcon(n.message);
                    icon.className = 'notif-icon ' + type;
                    const glyph = document.createElement('i');
                    glyph.className = 'fas ' + (n.event_type === 'announcement' ? 'fa-bullhorn' : n.event_type === 'message' ? 'fa-comments' : iconClass(type));
                    icon.append(glyph);
                    const content = document.createElement('div');
                    const link = document.createElement('a');
                    link.className = 'notif-msg resident-notification-link';
                    link.href = n.event_type === 'announcement' ? 'announcements.php' : (n.event_type === 'message' ? 'messages.php?id=' : 'complaint_conversation.php?id=') + Number(n.complaint_id);
                    link.textContent = n.message;
                    const time = document.createElement('div'); time.className = 'notif-time'; time.textContent = timeAgo(n.created_at);
                    content.append(link, time); row.append(icon, content); notifList.append(row);
                });
            }).catch(() => {});
    }

    notifBtn.addEventListener('click', e => {
        e.stopPropagation();
        notifDropdown.classList.toggle('open');
        if (notifDropdown.classList.contains('open')) loadNotifications();
    });

    document.addEventListener('click', e => {
        if (!document.getElementById('notifWrap').contains(e.target))
            notifDropdown.classList.remove('open');
    });

    markAllRead.addEventListener('click', () => {
        fetch('mark_notifications_read.php').then(() => {
            notifBadge.style.display = 'none';
            document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
        });
    });

    // Poll every 30 seconds
    loadNotifications();
    setInterval(() => { if(!document.hidden)loadNotifications(); }, 30000);
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)loadNotifications();});


</script>
</body>
</html>
