<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../report_activity.php';
require_once __DIR__ . '/../conversation_support.php';
require_once __DIR__ . '/../evidence_support.php';
require_once __DIR__ . '/../workflow_support.php';
require_once __DIR__ . '/../access_control.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$scopeQuery=$pdo->prepare('SELECT * FROM complaints WHERE id=?');$scopeQuery->execute([$id]);$scopeComplaint=$scopeQuery->fetch(PDO::FETCH_ASSOC);
if(!$scopeComplaint||!canAccessComplaint($scopeComplaint)){http_response_code(404);exit('Report not found or not assigned to you.');}
$staffMode=$_SESSION['role']==='staff';

$updateError='';$movedPaths=[];
$team=$pdo->query("SELECT id,full_name,username FROM users WHERE role IN ('admin','staff') AND is_active=1".($staffMode?' AND id='.(int)$_SESSION['user_id']:'')." ORDER BY full_name,id")->fetchAll(PDO::FETCH_ASSOC);
if(empty($_SESSION['report_update_csrf'])) $_SESSION['report_update_csrf']=bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 try {
  if(!is_string($_POST['csrf_token']??null)||!hash_equals($_SESSION['report_update_csrf'],$_POST['csrf_token']))throw new InvalidArgumentException('Refresh the page before saving this report.');
  $status=$_POST['status']??'';$priority=$_POST['priority']??($staffMode?$scopeComplaint['priority']:'');
  if(!in_array($status,['filed','acknowledged','processing','resolved'],true)||!in_array($priority,['low','medium','high'],true))throw new InvalidArgumentException('Choose a valid status and importance level.');
  $staffId=(int)($_POST['assigned_staff_id']??($staffMode?$scopeComplaint['assigned_staff_id']:0));$assigned='';
  if($staffId){$person=$pdo->prepare("SELECT full_name,username FROM users WHERE id=? AND role IN ('admin','staff') AND is_active=1");$person->execute([$staffId]);$person=$person->fetch(PDO::FETCH_ASSOC);if(!$person)throw new InvalidArgumentException('Choose an active staff account.');$assigned=mb_substr($person['full_name'].' (@'.$person['username'].')',0,100);}
  $due=trim((string)($_POST['due_date']??($staffMode?$scopeComplaint['due_date']:'')));
  if($due!==''&&(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$due)||!($date=DateTimeImmutable::createFromFormat('!Y-m-d',$due))||$date->format('Y-m-d')!==$due))throw new InvalidArgumentException('Enter a valid target completion date.');
  $notes=trim((string)($_POST['resolution_notes']??''));$remark=trim((string)($_POST['remark']??''));
  if($status==='resolved'&&$notes==='')throw new InvalidArgumentException('Add resolution details before marking this report resolved.');
  $proof=validateEvidenceUploads($_FILES['evidence']??[]);
  if(mb_strlen($assigned)>100||mb_strlen($remark)>2000||strlen($notes)>60000)throw new InvalidArgumentException('Assignment or remarks exceed the allowed length.');
  $pdo->beginTransaction();
  $old=$pdo->prepare('SELECT * FROM complaints WHERE id=? FOR UPDATE');$old->execute([$id]);$prev=$old->fetch(PDO::FETCH_ASSOC);
  if(!$prev)throw new InvalidArgumentException('Report not found.');
  if(!canAccessComplaint($prev))throw new InvalidArgumentException('This report is no longer assigned to you.');
  if($status==='filed'&&($prev['status']!=='filed'||!empty($prev['acknowledged_at'])))throw new InvalidArgumentException('An acknowledged report cannot be changed back to Filed. Administrators and assigned staff can still update its importance level.');
  if($staffMode){
   if($staffId!==(int)$prev['assigned_staff_id']||$due!==($prev['due_date']??''))throw new InvalidArgumentException('Only administrators can change assignment or deadlines.');
   if(!staffCanSetStatus($prev['status'],$status))throw new InvalidArgumentException('Staff must follow Filed → Acknowledged → Processing → Resolved. Ask an administrator about corrections; residents can request reopening.');
  }
  if((string)($_POST['version']??'')!==reportVersion($prev))throw new InvalidArgumentException('Another staff member updated this report. Reload the page to see their changes before saving.');
  if($prev['resident_confirmed_at']&&($status!==$prev['status']||$notes!==$prev['resolution_notes']))throw new InvalidArgumentException('The resident already confirmed this resolution. Preserve the closing record.');
  $q=$pdo->prepare("UPDATE complaints SET status=?,priority=?,assigned_to=?,resolution_notes=?,assigned_staff_id=?,due_date=?,acknowledged_at=CASE WHEN ?='filed' AND ?<>'filed' THEN COALESCE(acknowledged_at,NOW()) ELSE acknowledged_at END,resolved_at=CASE WHEN ?<>'resolved' AND ?='resolved' THEN NOW() WHEN ?='resolved' THEN resolved_at ELSE NULL END WHERE id=?");$q->execute([$status,$priority,$assigned,$notes,$staffId?:null,$due?:null,$prev['status'],$status,$prev['status'],$status,$status,$id]);
  if($proof){saveComplaintEvidence($pdo,$id,$proof,$movedPaths);$q=$pdo->prepare("UPDATE complaint_evidence SET purpose='resolution' WHERE complaint_id=? AND path=?");foreach($movedPaths as $path)$q->execute([$id,'evidence/'.basename($path)]);recordReportActivity($pdo,$id,'proof',count($proof).' completion evidence file(s) added.');}
  if($due!==($prev['due_date']??''))recordReportActivity($pdo,$id,'deadline',$due===''?'Target completion date cleared.':'Target completion date set to '.$due.'.');
  if($staffId!==(int)$prev['assigned_staff_id']&&$staffId)workflowNotice($pdo,$staffId,$id,'Report #'.$id.' has been assigned to you.');
  if($status!==$prev['status']){
   recordReportActivity($pdo,(int)$id,'status',ucfirst($prev['status']).' → '.ucfirst($status));
   if($prev['user_id']){$q=$pdo->prepare("INSERT INTO notifications(user_id,complaint_id,message,event_type) VALUES(?,?,?,'status')");$q->execute([$prev['user_id'],$id,'Your report #'.$id.' is now '.ucfirst($status).'.']);}
  }
  if($priority!==$prev['priority'])recordReportActivity($pdo,(int)$id,'importance',ucfirst($prev['priority']).' → '.ucfirst($priority));
  if($assigned!==($prev['assigned_to']??''))recordReportActivity($pdo,(int)$id,'assignment',$assigned===''?'Assignment cleared.':'Assigned to '.$assigned.'.');
  if($notes!==($prev['resolution_notes']??''))recordReportActivity($pdo,(int)$id,'update','Resolution notes updated: '.($notes?:'Notes cleared.'));
  if($remark!==''){
   recordReportActivity($pdo,(int)$id,'remark',$remark);
   if($prev['user_id']){$q=$pdo->prepare("INSERT INTO notifications(user_id,complaint_id,message,event_type) VALUES(?,?,?,'remark')");$q->execute([$prev['user_id'],$id,mb_substr('Staff added a remark to report #'.$id.': '.$remark,0,255)]);}
  }
  $pdo->commit();header('Location: edit_complaint.php?id='.(int)$id.'&saved=1#report-activity');exit;
 }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();removeFailedEvidence($movedPaths);$updateError=$error instanceof InvalidArgumentException?$error->getMessage():'Unable to save this update. Please try again.';if(!($error instanceof InvalidArgumentException))error_log($error->getMessage());http_response_code(422);}
}
$stmt = $pdo->prepare("SELECT * FROM complaints WHERE id=?");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) { header('Location: dashboard.php'); exit; }
$evidenceQuery = $pdo->prepare('SELECT * FROM complaint_evidence WHERE complaint_id = ? ORDER BY id');
$evidenceQuery->execute([$c['id']]);
$evidence = $evidenceQuery->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Complaint #<?php echo $c['id']; ?> — Barangay Mudiang</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link rel="stylesheet" href="../assets/vendor/bootstrap.min.css">
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
        .content { padding: 28px 32px; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .card { background: var(--card-bg); border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border); margin-bottom: 24px; }
        .card-header { padding: 18px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
        .card-header h2 { font-size: 15px; font-weight: 600; color: var(--text); }
        .card-header i { color: var(--primary); }
        .card-body { padding: 24px; }

        .info-row { display: flex; flex-direction: column; gap: 4px; margin-bottom: 16px; }
        .info-row:last-child { margin-bottom: 0; }
        .info-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); }
        .info-value { font-size: 14px; color: var(--text); font-weight: 500; }

        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: capitalize;
        }
        .badge.filed { background: #fef2f2; color: #dc2626; }
        .badge.processing { background: #fffbeb; color: #d97706; }
        .badge.resolved { background: #f0fdf4; color: #16a34a; }
        .badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        #map { width: 100%; height: 280px; border-radius: 8px; border: 1px solid var(--border); }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px; }
        .form-group select, .form-group input, .form-group textarea {
            width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px;
            font-family: 'Inter', sans-serif; font-size: 14px; color: var(--text);
            background: white; transition: border-color 0.2s, box-shadow 0.2s; outline: none;
        }
        .form-group select:focus, .form-group input:focus, .form-group textarea:focus {
            border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .form-group textarea { resize: vertical; min-height: 110px; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        .btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600;
            text-decoration: none; border: none; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-outline { background: white; color: var(--text); border: 1px solid var(--border); }
        .btn-outline:hover { background: var(--bg); }
        .form-actions { display: flex; gap: 10px; margin-top: 8px; }
    </style>
<?php include __DIR__ . '/../ui_theme.php'; ?>
</head>
<body class="portal-page ">

<?php if(($_SESSION['role']??'')==='staff'): include __DIR__.'/operations_nav.php'; else: ?><?php require_once __DIR__.'/../portal_navigation.php'; renderPortalNavigation(); ?><?php endif; ?>

<div class="main">
    <div class="topbar">
        <div class="topbar-left">
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <span class="sep">/</span>
            <h1>Review Report <span style="color:var(--primary)">#<?php echo $c['id']; ?></span></h1>
        </div>
    </div>

    <div class="content">
        <div class="grid-2">
            <!-- Left: Complaint Info -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i>
                        <h2>Complaint Details</h2>
                    </div>
                    <div class="card-body">
                        <?php $supporters=$pdo->prepare('SELECT COUNT(*) FROM complaint_supporters WHERE complaint_id=?');$supporters->execute([$c['id']]); ?>
                        <p class="mb-3">Other residents affected: <strong><?= (int)$supporters->fetchColumn() ?></strong></p>
                        <div class="form-row" style="margin-bottom:16px">
                            <div class="info-row">
                                <span class="info-label">Complaint Type</span>
                                <span class="info-value"><?php echo htmlspecialchars($c['complaint_type']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Current Status</span>
                                <span class="badge <?php echo $c['status']; ?>"><?php echo str_replace('_', ' ', $c['status']); ?></span>
                            </div>
                        </div>
                        <div class="form-row" style="margin-bottom:16px">
                            <div class="info-row">
                                <span class="info-label">Complainant</span>
                                <span class="info-value"><?php echo htmlspecialchars($c['complainant_name']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Contact</span>
                                <span class="info-value"><?php echo htmlspecialchars($c['complainant_contact']); ?></span>
                            </div>
                        </div>
                        <div class="info-row" style="margin-bottom:16px">
                            <span class="info-label">Date Submitted</span>
                            <span class="info-value"><?php echo date('F j, Y — g:i A', strtotime($c['created_at'])); ?></span>
                        </div>
                        <div class="info-row" style="margin-bottom:16px">
                            <span class="info-label">Address</span>
                            <span class="info-value"><?php echo htmlspecialchars($c['address']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Description</span>
                            <span class="info-value" style="line-height:1.6"><?php echo nl2br(htmlspecialchars($c['description'])); ?></span>
                        </div>
                    </div>
                </div>

                <section class="card evidence-review-card"><div class="card-header"><div><span class="portal-kicker">Supporting documentation</span><h2><i class="fas fa-paperclip" aria-hidden="true"></i> Resident evidence</h2></div><span class="evidence-count"><?php echo count($evidence); ?> files</span></div><div class="card-body"><?php if ($evidence): ?><?php renderEvidenceGallery($evidence, '../'); ?><?php else: ?><div class="evidence-empty"><i class="fas fa-camera" aria-hidden="true"></i><p>No evidence was attached to this report.</p></div><?php endif; ?></div></section>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-map-marker-alt"></i>
                        <h2>Pinned Location</h2>
                    </div>
                    <div class="card-body">
                        <div id="map"></div>
                    </div>
                </div>
            </div>

            <!-- Right: Resolution Form -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-tasks"></i>
                        <h2>Coordinate a response</h2>
                    </div>
                    <div class="card-body">
                        <?php if($staffMode): ?><p class="alert alert-info">You can update the status and importance of this assigned report. Assignment and deadlines are managed by your administrator.</p><?php endif; ?><form method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['report_update_csrf']); ?>"><input type="hidden" name="version" value="<?php echo reportVersion($c); ?>"><?php if($updateError): ?><div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($updateError); ?></div><?php elseif(isset($_GET['saved'])): ?><div class="alert alert-success" role="status">Report update saved.</div><?php endif; ?>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Status</label>
                                    <fieldset class="status-buttons" aria-describedby="status-help"><legend class="visually-hidden">Report status</legend><?php foreach(['filed'=>'Filed','acknowledged'=>'Acknowledged','processing'=>'Processing','resolved'=>'Resolved'] as $value=>$label): ?><label><input type="radio" name="status" value="<?php echo $value; ?>" <?php echo (($value==='filed'&&($c['status']!=='filed'||!empty($c['acknowledged_at'])))||($staffMode&&!staffCanSetStatus($c['status'],$value)))?'disabled':' '; ?> <?php echo $c['status']===$value?'checked':''; ?> required><span><?php echo $label; ?></span></label><?php endforeach; ?></fieldset>
                                    <small id="status-help">Once acknowledged, a report cannot return to Filed.</small>
                                </div>
                                <div class="form-group">
                                    <label>Importance level</label>
                                    <select name="priority" required aria-describedby="importance-help">
                                        <option value="low" <?php echo $c['priority']==='low'?'selected':''; ?>>Low</option>
                                        <option value="medium" <?php echo $c['priority']==='medium'?'selected':''; ?>>Medium</option>
                                        <option value="high" <?php echo $c['priority']==='high'?'selected':''; ?>>High</option>
                                    </select>
                                    <small id="importance-help">You can change the importance level after acknowledgment.</small>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Assigned To</label>
                                <select name="assigned_staff_id" <?php echo $staffMode?'disabled':' '; ?> aria-label="Assigned staff"><option value="">Unassigned</option><?php foreach($team as $person): ?><option value="<?= (int)$person['id'] ?>" <?= (int)$c['assigned_staff_id']===(int)$person['id']?'selected':'' ?>><?= reportActivityEscape($person['full_name'].' (@'.$person['username'].')') ?></option><?php endforeach; ?></select>
<?php if(!$c['assigned_staff_id']&&$c['assigned_to']): ?><small>Previous assignment: <?= reportActivityEscape($c['assigned_to']) ?>. Select an account to link responsibility.</small><?php endif; ?>
</div><div class="form-group"><label for="due-date">Target completion date</label><input id="due-date" type="date" name="due_date" <?php echo $staffMode?'disabled':' '; ?> value="<?= reportActivityEscape($c['due_date']??'') ?>">
</div><div class="form-group"><label for="completion-proof">Completion evidence (optional)</label><input id="completion-proof" type="file" name="evidence[]" multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime"><small>Up to four photos or videos, 20 MB each. Closing notes are required to resolve a report.</small>
                            </div>
                            <div class="form-group">
                                <label>Resolution Notes</label>
                                <textarea name="resolution_notes" placeholder="Document actions taken..."><?php echo htmlspecialchars($c['resolution_notes'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group"><label for="staff-remark">Add a report remark</label><textarea id="staff-remark" name="remark" maxlength="2000" placeholder="Share an observation or action taken. This remark is visible to the resident."><?php echo htmlspecialchars($_POST['remark']??''); ?></textarea><small class="remark-help">Remarks are added to the history with your name and date.</small></div><div class="form-actions">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                                <a href="dashboard.php" class="btn btn-outline"><i class="fas fa-times"></i> Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
<?php renderReportActivity($pdo,(int)$c['id']); ?><?php if ($c['user_id']): renderComplaintConversation((int) $c['id'], true, '../'); else: ?>
<div class="card conversation-unavailable"><i class="fas fa-comments"></i><p>This guest report has no registered resident account to message.</p></div>
<?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const map = L.map('map').setView([<?php echo $c['latitude']; ?>, <?php echo $c['longitude']; ?>], 17);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
    L.circleMarker([<?php echo $c['latitude']; ?>, <?php echo $c['longitude']; ?>], {
        radius: 10, fillColor: '#2563eb', color: '#fff', weight: 3, fillOpacity: 0.9
    }).addTo(map).bindPopup('<?php echo htmlspecialchars($c['complaint_type']); ?>').openPopup();
</script>
<?php include __DIR__ . '/notification_widget.php'; ?>
</body>
</html>
