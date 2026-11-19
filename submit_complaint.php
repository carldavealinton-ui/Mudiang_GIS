<?php
require_once 'config.php';
require_once __DIR__ . '/complaint_notifications.php';
require_once __DIR__ . '/report_activity.php';
require_once __DIR__ . '/evidence_support.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $movedPaths = [];
    try {
        $priority = $_POST['priority'] ?? 'medium';
        if (!is_string($priority) || !in_array($priority, ['low', 'medium', 'high'], true)) throw new InvalidArgumentException('Invalid complaint importance.');
        $files = validateEvidenceUploads($_FILES['evidence'] ?? []);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO complaints (complaint_type, description, latitude, longitude, address, complainant_name, complainant_contact, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$_POST['complaint_type'], $_POST['description'], $_POST['latitude'], $_POST['longitude'], $_POST['address'], $_POST['complainant_name'], $_POST['complainant_contact'], $priority]);
        $complaintId = (int) $pdo->lastInsertId();
        saveComplaintEvidence($pdo, $complaintId, $files, $movedPaths);
        recordReportActivity($pdo,$complaintId,'filed','Report filed.');
        notifyComplaintAdministrators($pdo, $complaintId);
        $pdo->commit();
        header('Location: index.php?success=1');
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        removeFailedEvidence($movedPaths);
        error_log('Complaint submission failed: ' . $error->getMessage());
        header('Location: index.php?error=1');
    }
    exit;
}
http_response_code(405);
header('Allow: POST');
