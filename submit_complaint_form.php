<?php
require_once 'config.php';
require_once __DIR__ . '/complaint_notifications.php';
require_once __DIR__ . '/report_activity.php';
require_once __DIR__ . '/evidence_support.php';
require_once __DIR__ . '/resident_features.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    header('Location: login.php'); exit;
}
$query = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$query->execute([$_SESSION['user_id']]);
$user = $query->fetch();
if (!$user) { http_response_code(403); exit('Resident account unavailable.'); }
if (empty($_SESSION['complaint_csrf'])) $_SESSION['complaint_csrf'] = bin2hex(random_bytes(32));
$formError = '';
$values = ['complaint_type' => '', 'description' => '', 'address' => '', 'latitude' => '', 'longitude' => '', 'priority' => 'medium'];
requireResident();
if($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['draft'])) {
    $draft=$pdo->prepare('SELECT payload FROM resident_drafts WHERE user_id=?');$draft->execute([$_SESSION['user_id']]);$payload=$draft->fetchColumn();
    if($payload){$saved=json_decode($payload,true);if(is_array($saved))foreach($values as $key=>$default)if(is_string($saved[$key]??null))$values[$key]=$saved[$key];}
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $movedPaths = [];
    foreach ($values as $key => $default) $values[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : $default;
    try {
        if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) throw new InvalidArgumentException('Your upload exceeds the server limit. Please select smaller files.');
        if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['complaint_csrf'], $_POST['csrf_token'])) throw new InvalidArgumentException('Your form session expired. Refresh the page and try again.');
        if (!in_array($values['priority'], ['low', 'medium', 'high'], true)) throw new InvalidArgumentException('Choose a valid importance level.');
        if (!in_array($values['complaint_type'], ['Road Damage', 'Drainage Issue', 'Street Light', 'Garbage Collection', 'Noise Complaint', 'Water Supply', 'Other'], true)) throw new InvalidArgumentException('Choose a complaint category.');
        if ($values['description'] === '' || strlen($values['description']) > 60000) throw new InvalidArgumentException('Describe the concern using no more than 60,000 characters.');
        if ($values['address'] === '' || mb_strlen($values['address']) > 255) throw new InvalidArgumentException('Enter an address or landmark of up to 255 characters.');
        if (!is_numeric($values['latitude']) || !is_numeric($values['longitude']) || abs((float) $values['latitude']) > 90 || abs((float) $values['longitude']) > 180) throw new InvalidArgumentException('Pin a valid location on the map.');
        $files = validateEvidenceUploads($_FILES['evidence'] ?? []);
        $pdo->beginTransaction();
        $query = $pdo->prepare('INSERT INTO complaints (user_id, complaint_type, description, latitude, longitude, address, complainant_name, complainant_contact, priority) VALUES (?,?,?,?,?,?,?,?,?)');
        $query->execute([$_SESSION['user_id'], $values['complaint_type'], $values['description'], $values['latitude'], $values['longitude'], $values['address'], $user['full_name'], $user['contact'], $values['priority']]);
        $complaintId = (int) $pdo->lastInsertId();
        saveComplaintEvidence($pdo, $complaintId, $files, $movedPaths);
        recordReportActivity($pdo,$complaintId,'filed','Report filed.');
        notifyComplaintAdministrators($pdo, $complaintId);
        $draft=$pdo->prepare('DELETE FROM resident_drafts WHERE user_id=?');$draft->execute([$_SESSION['user_id']]);
        $pdo->commit();
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            header('Content-Type: application/json'); echo json_encode(['ok' => true, 'redirect' => 'resident_dashboard.php?success=1']);
        } else { header('Location: resident_dashboard.php?success=1'); }
        exit;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        removeFailedEvidence($movedPaths);
        if ($error instanceof InvalidArgumentException) $formError = $error->getMessage();
        else { error_log('Complaint submission failed: ' . $error->getMessage()); $formError = 'Your complaint could not be saved. Please try again.'; }
        http_response_code(422);
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            header('Content-Type: application/json'); echo json_encode(['ok' => false, 'error' => $formError]); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Complaint — Barangay Mudiang</title>
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
        .topbar-left { display: flex; align-items: center; gap: 12px; }
        .topbar-left a { color: var(--text-muted); text-decoration: none; font-size: 13px; display: flex; align-items: center; gap: 6px; }
        .topbar-left a:hover { color: var(--primary); }
        .topbar-left .sep { color: var(--border); }
        .topbar-left h1 { font-size: 18px; font-weight: 700; }
        .content { padding: 28px 32px; max-width: 860px; }

        .card { background: var(--card-bg); border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border); margin-bottom: 24px; }
        .card-header { padding: 18px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
        .card-header h2 { font-size: 15px; font-weight: 600; color: var(--text); }
        .card-header i { color: var(--primary); }
        .card-body { padding: 24px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px; }
        .form-group select, .form-group input, .form-group textarea {
            width: 100%; padding: 10px 14px; border: 1px solid var(--border);
            border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 14px; color: var(--text);
            background: white; outline: none; transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group select:focus, .form-group input:focus, .form-group textarea:focus {
            border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .form-group textarea { resize: vertical; min-height: 100px; }

        .map-hint {
            display: flex; align-items: center; gap: 8px; padding: 10px 14px;
            background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px;
            font-size: 13px; color: var(--primary); font-weight: 500; margin-bottom: 10px;
        }
        #map { width: 100%; height: 420px; border-radius: 8px; border: 1px solid var(--border); }
        .location-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 12px; }
        .location-tools p { flex: 1; min-width: 180px; font-size: 12px; color: var(--text-muted); }
        #current-location:disabled { opacity: .65; cursor: wait; }
        .location-status { font-size: 12px; color: var(--text-muted); margin-bottom: 10px; }

        .coord-row {
            display: flex; gap: 12px; margin-top: 10px;
        }
        .coord-box {
            flex: 1; background: #f8fafc; border: 1px solid var(--border); border-radius: 8px;
            padding: 10px 14px; font-size: 13px; color: var(--text-muted);
        }
        .coord-box span { font-weight: 600; color: var(--text); }

        .form-actions { display: flex; gap: 10px; margin-top: 8px; }
        .btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600;
            text-decoration: none; border: none; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-outline { background: white; color: var(--text); border: 1px solid var(--border); }
        .btn-outline:hover { background: var(--bg); }
    </style>
<?php include __DIR__ . '/ui_theme.php'; ?>
</head>
<body class="portal-page ">

<?php require_once __DIR__.'/portal_navigation.php'; renderPortalNavigation(); ?>

<div class="main">
    <div class="topbar">
        <div class="topbar-left">
            <a href="resident_dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <span class="sep">/</span>
            <h1>Submit Complaint</h1>
        </div>
    </div>

    <div class="content report-content">
        <section class="dashboard-intro report-intro"><div><span class="portal-kicker">Resident services / New report</span><h2>Help us see what needs attention.</h2><p>Tell us what happened, mark the location, and share evidence to help your barangay respond.</p></div><span class="intro-tag">Your community. Your voice.</span></section>
        <div class="report-layout">
            <div class="card report-card">
                <div class="card-header"><div><span class="portal-kicker">Community concern</span><h2>Make your report</h2></div><span class="report-required">Required fields are marked *</span></div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="complaintForm" data-resident="<?= (int)$_SESSION['user_id'] ?>" data-draft-token="<?= residentEscape($_SESSION['resident_csrf']) ?>">
                        <div class="draft-tools"><strong>Save your progress</strong><p>Text and map pins are saved automatically. Attachments must be selected again when you return.</p><div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-outline" id="save-draft">Save draft</button><button type="button" class="btn btn-outline" id="restore-draft" hidden>Restore saved draft</button><button type="button" class="btn btn-outline" id="discard-draft">Discard draft</button></div><p id="draft-status" role="status" aria-live="polite"></p></div>
                        <input type="hidden" name="csrf_token" value="<?php echo evidenceEscape($_SESSION['complaint_csrf']); ?>">
                        <div id="report-error" class="alert alert-danger" role="alert" tabindex="-1" <?php echo $formError === '' ? 'hidden' : ''; ?>><?php echo evidenceEscape($formError); ?></div>
                        <section class="report-section"><div class="report-section-title"><span>01</span><div><h3>Describe the concern</h3><p>A clear description helps the team understand your report.</p></div></div>
                            <div class="form-group"><label for="complaint-type">Category *</label><select id="complaint-type" name="complaint_type" required><option value="">Choose a category</option><?php foreach (['Road Damage', 'Drainage Issue', 'Street Light', 'Garbage Collection', 'Noise Complaint', 'Water Supply', 'Other'] as $type): ?><option value="<?php echo evidenceEscape($type); ?>" <?php echo $values['complaint_type'] === $type ? 'selected' : ''; ?>><?php echo evidenceEscape($type); ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label for="description">What happened? *</label><textarea id="description" name="description" rows="4" maxlength="60000" placeholder="Describe the problem, when it started, and who it affects." required><?php echo evidenceEscape($values['description']); ?></textarea></div>
                        </section>
                        <section class="report-section"><div class="report-section-title"><span>02</span><div><h3>Choose the importance level</h3><p>Select the level that best reflects the impact on your community.</p></div></div>
                            <fieldset class="priority-options"><legend class="visually-hidden">Complaint importance level</legend>
                            <?php foreach (['low' => ['Low', 'Minor inconvenience', 'A routine concern with limited impact.', 'fa-leaf'], 'medium' => ['Medium', 'Needs attention', 'An ongoing issue affecting daily activities.', 'fa-flag'], 'high' => ['High', 'Significant impact', 'A serious concern requiring prompt attention.', 'fa-exclamation-circle']] as $level => $details): ?>
                            <label class="priority-option priority-<?php echo $level; ?>"><input type="radio" name="priority" value="<?php echo $level; ?>" <?php echo $values['priority'] === $level ? 'checked' : ''; ?> required><span class="priority-option-body"><i class="fas <?php echo $details[3]; ?>" aria-hidden="true"></i><strong><?php echo $details[0]; ?></strong><span><?php echo $details[1]; ?></span><small><?php echo $details[2]; ?></small></span></label>
                            <?php endforeach; ?>
                            </fieldset>
                        </section>
                        <section class="report-section"><div class="report-section-title"><span>03</span><div><h3>Pinpoint the location</h3><p>Place a pin where the concern is happening.</p></div></div>
                            <div class="location-tools"><button type="button" class="btn btn-outline" id="current-location" aria-describedby="location-status"><i class="fas fa-location-arrow" aria-hidden="true"></i> Current location</button><p>Use your device location, then adjust the pin if needed.</p></div>
                            <p class="location-status" id="location-status" role="status" aria-live="polite">Allow location access when your browser asks.</p>
                            <div id="nearby-issues" class="nearby-issues" aria-live="polite"></div>
                            <div class="form-group"><div class="map-hint"><i class="fas fa-hand-pointer" aria-hidden="true"></i> Click or tap the map to place your pin</div><div id="map" aria-label="Complaint location map"></div><div class="coord-row"><div class="coord-box">Latitude <span id="lat-display"><?php echo evidenceEscape($values['latitude'] ?: '—'); ?></span></div><div class="coord-box">Longitude <span id="lng-display"><?php echo evidenceEscape($values['longitude'] ?: '—'); ?></span></div><input type="hidden" name="latitude" id="latitude" value="<?php echo evidenceEscape($values['latitude']); ?>"><input type="hidden" name="longitude" id="longitude" value="<?php echo evidenceEscape($values['longitude']); ?>"></div>
                            <div class="form-group"><label for="report-address">Address or landmark *</label><input type="text" id="report-address" name="address" maxlength="255" value="<?php echo evidenceEscape($values['address']); ?>" placeholder="e.g. Near Mudiang Barangay Hall, Purok 3" required></div>
                        </section>
                        <section class="report-section evidence-section"><div class="report-section-title"><span>04</span><div><h3>Share supporting evidence <em>Optional</em></h3><p>A photo or short video can help explain the concern.</p></div></div>
                            <label class="d-flex gap-2 mb-3"><input type="checkbox" id="optimize-photos" checked> Reduce large photos for faster uploads (uncheck before selecting files to keep originals)</label>
                            <div class="evidence-dropzone" id="evidence-dropzone"><i class="fas fa-cloud-upload-alt" aria-hidden="true"></i><strong>Photos and videos tell the story.</strong><p>Drag files here or select them from your device.</p><label class="btn btn-outline" for="evidence-files">Choose photos or videos</label><input class="visually-hidden" id="evidence-files" type="file" name="evidence[]" accept=".jpg,.jpeg,.png,.webp,.mp4,.webm,.mov" multiple aria-describedby="evidence-help"><small id="evidence-help">JPG, PNG, WebP · MP4, WebM, MOV<br>Up to 4 files · 20 MB each · 50 MB total</small></div>
                            <div class="evidence-selection" id="evidence-selection" aria-live="polite"></div><p class="evidence-feedback" id="evidence-feedback" role="status"></p>
                            <p class="evidence-privacy"><i class="fas fa-lock" aria-hidden="true"></i> Your evidence is visible to you and authorized barangay staff.</p>
                        </section>
                        <div class="upload-progress" id="upload-progress" hidden><div class="upload-progress-heading"><span id="upload-status" role="status">Uploading your report…</span><span id="upload-percent">0%</span></div><div class="progress" role="progressbar" aria-label="Evidence upload progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="progress-bar" id="upload-progress-bar"></div></div></div>
                        <div class="form-actions report-actions"><a href="resident_dashboard.php" class="btn btn-outline">Cancel</a><button type="submit" id="submit-report" class="btn btn-primary"><i class="fas fa-paper-plane" aria-hidden="true"></i> Submit report</button></div>
                    </form>
                </div>
            </div>
            <aside class="report-guide"><span class="portal-kicker">A stronger report</span><h3>Small details.<br>Clearer action.</h3><div class="guide-item"><i class="fas fa-align-left" aria-hidden="true"></i><div><strong>Be specific</strong><p>Include what you observed and when the problem started.</p></div></div><div class="guide-item"><i class="fas fa-map-pin" aria-hidden="true"></i><div><strong>Mark the right place</strong><p>A nearby landmark helps the team find the location.</p></div></div><div class="guide-item"><i class="fas fa-camera" aria-hidden="true"></i><div><strong>Add context</strong><p>Choose clear photos or short videos showing the concern.</p></div></div><div class="guide-footer"><strong>What happens next?</strong><p>Staff receive your report and selected priority. Follow status updates in your dashboard.</p></div></aside>
        </div>
    </div>
</div>
<script src="assets/evidence-upload.js?v=1" defer></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const map = L.map('map').setView([7.182708, 125.622193], 17);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);

    // Barangay Hall reference marker
    L.circleMarker([7.182708, 125.622193], {
        radius: 8, fillColor: '#2563eb', color: '#fff', weight: 2.5, fillOpacity: 0.9
    }).addTo(map).bindTooltip('Mudiang Barangay Hall', { permanent: false });

    let pinMarker = null;
    function placeLocationPin(lat, lng) {
        if (pinMarker) map.removeLayer(pinMarker);
        pinMarker = L.circleMarker([lat, lng], {
            radius: 10, fillColor: '#ef4444', color: '#fff', weight: 3, fillOpacity: 0.9
        }).addTo(map).bindPopup('Your pinned location').openPopup();
        document.getElementById('latitude').value = lat.toFixed(7);
        document.getElementById('longitude').value = lng.toFixed(7);
        document.getElementById('lat-display').textContent = lat.toFixed(6);
        document.getElementById('lng-display').textContent = lng.toFixed(6);
        document.dispatchEvent(new Event('report-location-changed'));
    }
    map.on('click', function(e) {
        placeLocationPin(e.latlng.lat, e.latlng.lng);
    });

    const savedLat = document.getElementById('latitude').value;
    const savedLng = document.getElementById('longitude').value;
    if (savedLat && savedLng) {
        pinMarker = L.circleMarker([Number(savedLat), Number(savedLng)], {radius:10, fillColor:'#17654e', color:'#fff', weight:3, fillOpacity:.9}).addTo(map);
        map.setView([Number(savedLat), Number(savedLng)], 17);
    }
</script>
<script src="assets/current-location.js?v=1"></script>
<script src="assets/resident-report.js?v=3" defer></script>
</body>
</html>
