<?php
// Resolve both root and admin pages, including installations in a subdirectory.
$asset_base = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false) ? '../assets/' : 'assets/';
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?php echo $asset_base; ?>theme.css?v=6">
<link rel="stylesheet" href="<?php echo $asset_base; ?>civic-palette.css?v=4">
<script src="<?php echo $asset_base; ?>theme.js?v=4" defer></script>

<link rel="stylesheet" href="<?php echo $asset_base; ?>interactions.css?v=1">
<script src="<?php echo $asset_base; ?>interactions.js?v=1" defer></script>
<link rel="stylesheet" href="<?php echo $asset_base; ?>resident-features.css?v=3">
<link rel="stylesheet" href="<?php echo $asset_base; ?>portal-consistency.css?v=4">
<?php if (($_SESSION['role'] ?? '') === 'resident' && isset($_SESSION['user_id'])): ?>
<script src="<?php echo $asset_base; ?>resident-message-notifications.js?v=1" data-resident="<?php echo (int)$_SESSION['user_id']; ?>" defer></script>
<?php endif; ?>
<?php if(strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/')===false): ?>
<script src="<?php echo $asset_base; ?>resident-language.js?v=2" defer></script>
<?php endif; ?>
