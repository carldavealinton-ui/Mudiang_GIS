<?php
require_once __DIR__ . '/auth.php';
if($_SESSION['role']==='staff'){header('Location: staff_dashboard.php');exit;}
require_once __DIR__ . '/../conversation_support.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM complaint_evidence e WHERE e.complaint_id = c.id) AS evidence_count FROM complaints c ORDER BY c.created_at DESC");
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
$unreadConversations = unreadConversationCounts($pdo, (int) $_SESSION['user_id'], true);

$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'filed' THEN 1 ELSE 0 END) as filed,
    SUM(CASE WHEN status='acknowledged' THEN 1 ELSE 0 END) as acknowledged,
    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
    FROM complaints")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Barangay Mudiang</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/bootstrap.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --sidebar-bg: #0f172a;
            --sidebar-hover: #1e293b;
            --sidebar-active: #2563eb;
            --bg: #f1f5f9;
            --card-bg: #ffffff;
            --text: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --filed: #ef4444;
            --progress: #f59e0b;
            --resolved: #10b981;
            --radius: 12px;
            --shadow: 0 1px 3px rgba(0,0,0,0.08), 0 4px 16px rgba(0,0,0,0.06);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar {
            width: 260px; min-height: 100vh; background: var(--sidebar-bg);
            display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 100;
        }
        .sidebar-brand {
            padding: 28px 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
        }
        .sidebar-brand .brand-icon {
            width: 42px; height: 42px; background: var(--primary);
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: white; margin-bottom: 12px;
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
        .nav-item.active { background: var(--sidebar-active); color: white; }
        .nav-item i { width: 18px; text-align: center; font-size: 15px; }
        .sidebar-footer {
            padding: 16px 12px; border-top: 1px solid rgba(255,255,255,0.07);
        }
        .user-info {
            display: flex; align-items: center; gap: 10px; padding: 10px 12px;
            border-radius: 8px; background: rgba(255,255,255,0.05); margin-bottom: 8px;
        }
        .user-avatar {
            width: 34px; height: 34px; background: var(--primary);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 14px; color: white; font-weight: 600; flex-shrink: 0;
        }
        .user-info .user-name { color: white; font-size: 13px; font-weight: 500; }
        .user-info .user-role { color: #64748b; font-size: 11px; }

        /* Main */
        .main { margin-left: 260px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
        .topbar {
            background: white; padding: 16px 32px; display: flex; justify-content: space-between;
            align-items: center; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 50;
        }
        .topbar-left h1 { font-size: 20px; font-weight: 700; color: var(--text); }
        .topbar-left p { font-size: 13px; color: var(--text-muted); margin-top: 2px; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .content { padding: 28px 32px; }

        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 600;
            text-decoration: none; border: none; cursor: pointer; transition: all 0.2s;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-outline { background: white; color: var(--text); border: 1px solid var(--border); }
        .btn-outline:hover { background: var(--bg); }
        .btn-danger { background: #fee2e2; color: #dc2626; }
        .btn-danger:hover { background: #fecaca; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }

        /* Stat Cards */
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

        /* Section Cards */
        .card { background: var(--card-bg); border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border); margin-bottom: 24px; }
        .card-header {
            padding: 20px 24px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-header h2 { font-size: 16px; font-weight: 600; color: var(--text); display: flex; align-items: center; gap: 8px; }
        .card-header h2 i { color: var(--primary); }
        .card-body { padding: 20px 24px; }

        /* Map */
        #map { width: 100%; height: 460px; border-radius: 8px; }
        .map-legend {
            display: flex; gap: 24px; flex-wrap: wrap; padding: 14px 0 0;
        }
        .legend-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-muted); font-weight: 500; }
        .legend-dot { width: 12px; height: 12px; border-radius: 50%; }

        /* Table */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);
            background: #f8fafc; border-bottom: 1px solid var(--border);
        }
        tbody td { padding: 14px 16px; font-size: 13px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #f8fafc; }
        .complaint-id { font-weight: 600; color: var(--primary); }
        .complaint-type { font-weight: 500; }
        .complaint-desc { color: var(--text-muted); max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* Badges */
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: capitalize;
        }
        .badge.filed { background: #fef2f2; color: #dc2626; }
        .badge.processing { background: #fffbeb; color: #d97706; }
        .badge.resolved { background: #f0fdf4; color: #16a34a; }
        .badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    </style>
<?php include __DIR__ . '/../ui_theme.php'; ?>
</head>
<body class="portal-page ">

<!-- Sidebar -->
<?php require_once __DIR__.'/../portal_navigation.php'; renderPortalNavigation(); ?>

<!-- Main Content -->
<div class="main">
    <div class="topbar">
        <div class="topbar-left">
            <h1>Dashboard</h1>
            <p><?php echo date('l, F j, Y'); ?></p>
        </div>
        <div class="topbar-right">
            <a href="annual_report.php" class="btn btn-outline"><i class="fas fa-chart-bar"></i> Reports</a>
        </div>
    </div>

    <div class="content">
<section class="dashboard-intro"><div><span class="portal-kicker">Barangay Mudiang / Community services</span><h2>A clearer view of your community.</h2><p>Locate community concerns, coordinate action, and follow every report through to resolution.</p></div><span class="intro-tag">GIS Complaint &amp; Resolution Portal</span></section>
        <!-- Stats -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-info">
                    <h3>Total</h3>
                    <div class="number"><?php echo $stats['total']; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3>Filed</h3>
                    <div class="number" style="color:var(--pending)"><?php echo $stats['filed']; ?></div>
                </div>
            </div>
            <div class="stat-card"><div class="stat-icon acknowledged"><i class="fas fa-check-double" aria-hidden="true"></i></div><div class="stat-info"><h3>Acknowledged</h3><div class="number"><?php echo (int) $stats['acknowledged']; ?></div></div></div>
<div class="stat-card">
                <div class="stat-icon amber"><i class="fas fa-spinner"></i></div>
                <div class="stat-info">
                    <h3>Processing</h3>
                    <div class="number" style="color:var(--progress)"><?php echo $stats['processing']; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3>Resolved</h3>
                    <div class="number" style="color:var(--resolved)"><?php echo $stats['resolved']; ?></div>
                </div>
            </div>
        </div>

        <!-- Map -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-map-marked-alt"></i> Complaint Locations Map</h2>
            </div>
            <div class="card-body">
                <div id="map"></div>
                <div class="map-legend">
                    <div class="legend-item"><div class="legend-dot" style="background:var(--pending)"></div> Filed</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#59788c"></div> Acknowledged</div><div class="legend-item"><div class="legend-dot" style="background:var(--progress)"></div> Processing</div>
                    <div class="legend-item"><div class="legend-dot" style="background:var(--resolved)"></div> Resolved</div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-list-alt"></i> Complaint Management</h2>
                <span style="font-size:13px;color:var(--text-muted)"><?php echo count($complaints); ?> total records</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Location</th>
                            <th>Complainant</th>
                            <th>Status</th>
                            <th>Importance</th><th>Evidence</th><th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($complaints as $c): ?>
                        <tr>
                            <td><span class="complaint-id">#<?php echo $c['id']; ?></span></td>
                            <td><span class="complaint-type"><?php echo htmlspecialchars($c['complaint_type']); ?></span></td>
                            <td><span class="complaint-desc" title="<?php echo htmlspecialchars($c['description']); ?>"><?php echo htmlspecialchars($c['description']); ?></span></td>
                            <td><?php echo htmlspecialchars($c['address']); ?></td>
                            <td><?php echo htmlspecialchars($c['complainant_name']); ?></td>
                            <td><span class="badge <?php echo $c['status']; ?>"><?php echo str_replace('_', ' ', $c['status']); ?></span></td>
                            <td><span class="importance-pill <?php echo htmlspecialchars($c['priority']); ?>"><?php echo htmlspecialchars(ucfirst($c['priority'])); ?></span></td><td><?php if ($c['evidence_count']): ?><a class="evidence-table-link" href="edit_complaint.php?id=<?php echo (int) $c['id']; ?>"><i class="fas fa-paperclip" aria-hidden="true"></i> <?php echo (int) $c['evidence_count']; ?> files</a><?php else: ?><span class="text-muted">—</span><?php endif; ?></td><td style="color:var(--text-muted);white-space:nowrap"><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                            <td>
                                <a href="edit_complaint.php?id=<?php echo $c['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-comments"></i> Review <?php if (!empty($unreadConversations[$c['id']])): ?><span class="message-unread-count"><?php echo (int) $unreadConversations[$c['id']]; ?> new</span><?php endif; ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const map = L.map('map').setView([7.182708, 125.622193], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    const colorMap = { filed:'#a77853', acknowledged:'#59788c', processing:'#b08b3f', resolved:'#34755b' };
    const complaints = <?php echo json_encode($complaints,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;

    complaints.forEach(c => {
        if (!c.latitude || !c.longitude) return;
        const color = colorMap[c.status] || '#64748b';
        const marker = L.circleMarker([parseFloat(c.latitude), parseFloat(c.longitude)], {
            radius: 9, fillColor: color, color: '#fff', weight: 2.5, fillOpacity: 0.9
        }).addTo(map);
        const popup=document.createElement('div');popup.style.cssText='font-family:Inter,sans-serif;min-width:220px';
        const title=document.createElement('strong');title.textContent=c.complaint_type;
        const details=document.createElement('p');details.textContent=c.complainant_name+' · '+c.address+' · '+c.status;
        const link=document.createElement('a');link.href='edit_complaint.php?id='+Number(c.id);link.textContent='Review report';
        popup.append(title,details,link);marker.bindPopup(popup);
    });
</script>
<?php include __DIR__ . '/notification_widget.php'; ?>
</body>
</html>
