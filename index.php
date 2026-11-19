<?php
// Preserve existing sign-in clients that post to the former home/login page.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require __DIR__ . '/login.php';
    exit;
}
if (session_status() === PHP_SESSION_NONE) session_start();
$dashboard = match ($_SESSION['role'] ?? '') {
    'admin' => 'admin/dashboard.php',
    'staff' => 'admin/staff_dashboard.php',
    default => 'resident_dashboard.php',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Connect with Barangay Mudiang. Report local concerns, pinpoint locations, and follow the progress of your community reports.">
    <title>Barangay Mudiang | Your community, connected</title>
    <link rel="stylesheet" href="assets/landing.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header wrap">
    <a class="brand" href="index.php" aria-label="Barangay Mudiang home"><span class="brand-mark" aria-hidden="true">M<span></span></span><span>Barangay Mudiang<small>COMMUNITY SERVICES PORTAL</small></span></a>
    <nav aria-label="Main navigation"><a class="section-link" href="#services">Services</a><a class="section-link" href="#how-it-works">How it works</a><?php if (isset($_SESSION['user_id'])): ?><a class="button small" href="<?php echo $dashboard; ?>">My dashboard <span aria-hidden="true">↗</span></a><?php else: ?><a href="login.php">Sign in</a><a class="button small" href="register.php">Join the community <span aria-hidden="true">↗</span></a><?php endif; ?></nav>
</header>
<main id="main">
<section class="hero wrap" aria-labelledby="hero-title">
    <div class="hero-copy"><p class="eyebrow"><span class="live-dot"></span> LOCAL CONCERNS. SHARED ACTION.</p><h1 id="hero-title">A better barangay<br>starts with <em>you.</em></h1><p class="hero-description">Your voice helps Mudiang move forward. Report a concern, put it on the map, and stay connected as your community takes action.</p><div class="hero-actions"><a class="button" href="<?php echo isset($_SESSION['user_id']) ? 'submit_complaint_form.php' : 'register.php'; ?>">Get started <span aria-hidden="true">↗</span></a><a class="text-link" href="#how-it-works">See how it works <span aria-hidden="true">↓</span></a></div><div class="hero-note"><span class="note-icon" aria-hidden="true">✓</span> For residents. For our community.</div></div>
    <div class="map-scene" role="img" aria-label="Illustration of community concerns marked on a map, with a report progressing from filed to resolved">
        <div class="map-topline"><span><span class="live-dot"></span> MUDIANG, CONNECTED</span><span>COMMUNITY MAP</span></div>
        <svg class="neighborhood-map" viewBox="0 0 600 490" aria-hidden="true"><defs><pattern id="blocks" width="100" height="100" patternUnits="userSpaceOnUse" patternTransform="rotate(-20)"><rect width="100" height="100" fill="#e5ebd8"/><rect x="10" y="10" width="32" height="32" rx="5" fill="#d4dec6"/><rect x="51" y="10" width="38" height="32" rx="5" fill="#dbe3ce"/><rect x="10" y="51" width="79" height="38" rx="5" fill="#d7e0c9"/></pattern></defs><rect width="600" height="490" fill="url(#blocks)"/><path d="M450 -30 Q290 100 470 230 T440 530" fill="none" stroke="#adcdbb" stroke-width="55"/><g fill="none" stroke="#faf9f0" stroke-width="17"><path d="M-30 380L650 130M130 -40L345 530M-30 140L650 390"/></g><path d="M100 345L250 290L308 208L427 239" fill="none" stroke="#507b5a" stroke-width="3" stroke-dasharray="7 7"/><g fill="#28634f" stroke="#fffefa" stroke-width="5"><circle cx="100" cy="345" r="13"/><circle cx="308" cy="208" r="13"/></g><circle cx="427" cy="239" r="14" fill="#bd9650" stroke="#fffefa" stroke-width="5"/><circle cx="308" cy="208" r="30" fill="none" stroke="#28634f" stroke-opacity=".2" stroke-width="12"/></svg>
        <div class="map-label label-one"><span class="pin-dot"></span> Road concern</div><div class="map-label label-two"><span class="pin-dot gold"></span> Street lighting</div>
        <div class="report-preview"><div class="preview-heading"><span class="preview-icon" aria-hidden="true">✓</span><div><strong>Every report has a journey.</strong><span>Follow the progress from your dashboard</span></div></div><div class="progress-track"><span>Filed</span><span>Acknowledged</span><span>Processing</span><span>Resolved</span></div><p>Illustrative map &amp; report workflow</p></div>
    </div>
</section>
<div class="community-strip"><div class="wrap"><span>A shared space for a stronger Mudiang</span><span>REPORT <b aria-hidden="true">·</b> CONNECT <b aria-hidden="true">·</b> FOLLOW THROUGH</span></div></div>
<section id="services" class="services wrap" aria-labelledby="services-title"><div class="section-heading"><p class="eyebrow">YOUR COMMUNITY, WITHIN REACH</p><h2 id="services-title">Small actions.<br>Meaningful local change.</h2><p>One place to raise concerns and keep track of what happens next.</p></div><div class="service-grid"><article class="service"><span class="service-icon" aria-hidden="true">↗</span><h3>Put concerns on the map</h3><p>Pin the location of a road issue, broken street light, or another local concern. Add details and evidence to help staff understand.</p><a href="submit_complaint_form.php">File a report <span aria-hidden="true">→</span></a></article><article class="service"><span class="service-icon" aria-hidden="true">◷</span><h3>Follow every step</h3><p>Check your report's status and communicate with barangay staff. Keep the conversation and updates together in your account.</p><a href="resident_dashboard.php">View your dashboard <span aria-hidden="true">→</span></a></article><article class="service"><span class="service-icon" aria-hidden="true">◎</span><h3>Stay in the loop</h3><p>Read community announcements and explore nearby concerns through the resident portal.</p><a href="resident_dashboard.php">Explore the portal <span aria-hidden="true">→</span></a></article></div></section>
<section id="how-it-works" class="how-section" aria-labelledby="how-title"><div class="wrap how-layout"><div><p class="eyebrow">FROM YOUR VOICE TO ACTION</p><h2 id="how-title">Getting involved<br>is simple.</h2><a class="text-link" href="register.php">Create your resident account <span aria-hidden="true">↗</span></a></div><ol class="steps-list"><li><span>01</span><div><h3>Make yourself at home</h3><p>Register with your details and Gmail address. Your account is ready to use immediately.</p></div></li><li><span>02</span><div><h3>Tell us what needs attention</h3><p>Describe your concern, mark its location, and attach supporting evidence when available.</p></div></li><li><span>03</span><div><h3>Stay part of the conversation</h3><p>Sign in to follow updates, exchange messages with staff, and review the resolution.</p></div></li></ol></div></section>
<section class="join-section wrap"><div><p class="eyebrow">LET'S MOVE MUDIANG FORWARD</p><h2>Your neighborhood.<br>Your voice. Your next step.</h2><p>Be part of a more connected community.</p></div><a class="button light" href="register.php">Create an account <span aria-hidden="true">↗</span></a></section>
</main>
<footer class="site-footer wrap"><span>© <?php echo date('Y'); ?> Barangay Mudiang <small>Community Services Portal</small></span><nav aria-label="Footer navigation"><a href="login.php">Resident sign in</a><a href="admin/login.php">Staff access</a></nav></footer>
</body>
</html>
