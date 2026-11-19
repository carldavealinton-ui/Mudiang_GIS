<div class="admin-alerts" data-user="<?php echo (int) $_SESSION['user_id']; ?>" data-token="<?php echo htmlspecialchars($_SESSION['admin_notification_csrf'], ENT_QUOTES); ?>">
    <button class="admin-bell" type="button" aria-label="Complaint notifications" aria-expanded="false" aria-controls="admin-alert-panel"><i class="fas fa-bell" aria-hidden="true"></i><span class="admin-alert-count" hidden></span></button>
    <section id="admin-alert-panel" class="admin-alert-panel" hidden aria-label="Complaint notifications">
        <div class="admin-alert-heading"><strong>Reports &amp; messages</strong><button type="button" class="admin-alert-read">Mark all as read</button></div>
        <p class="admin-alert-status" role="status">Checking for updates…</p>
        <div class="admin-alert-items"></div>
    </section>
</div>
<div class="admin-toasts" aria-live="polite" aria-atomic="false"></div>
<script src="../assets/admin-notifications.js?v=4" defer></script>
