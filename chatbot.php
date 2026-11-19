<?php
require_once 'config.php';
header('Content-Type: application/json');

$input   = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$action  = trim($input['action']  ?? '');   // direct action key

if (empty($message) && empty($action)) {
    echo json_encode(['type' => 'text', 'reply' => 'Please type a message.']);
    exit;
}

$key = $action ?: strtolower($message);

/* ─── helper ─── */
function text($reply, $quick = []) {
    return json_encode(['type' => 'text', 'reply' => $reply, 'quick' => $quick]);
}
function menu($title, $items) {
    return json_encode(['type' => 'menu', 'title' => $title, 'items' => $items]);
}
function cards($intro, $items) {
    return json_encode(['type' => 'cards', 'intro' => $intro, 'items' => $items]);
}
function steps($title, $list, $note = '') {
    return json_encode(['type' => 'steps', 'title' => $title, 'list' => $list, 'note' => $note]);
}

/* ══════════════════════════════════════════
   MAIN MENU
══════════════════════════════════════════ */
if (preg_match('/\b(menu|start|home|back|main)\b/i', $key) || $action === 'main_menu') {
    echo menu('How can I help you today?', [
        ['action' => 'svc_complaint',  'icon' => 'fa-file-alt',       'label' => 'Submit a Complaint',    'desc' => 'Report an issue in your area'],
        ['action' => 'svc_track',      'icon' => 'fa-search-location', 'label' => 'Track My Complaint',    'desc' => 'Check status & updates'],
        ['action' => 'svc_register',   'icon' => 'fa-user-plus',       'label' => 'Register / Login',      'desc' => 'Create or access your account'],
        ['action' => 'svc_services',   'icon' => 'fa-th-large',        'label' => 'Our Services',          'desc' => 'See what we offer'],
        ['action' => 'svc_types',      'icon' => 'fa-tags',            'label' => 'Complaint Types',       'desc' => 'What can you report?'],
        ['action' => 'svc_priority',   'icon' => 'fa-exclamation-triangle', 'label' => 'Priority Levels', 'desc' => 'Understand urgency levels'],
        ['action' => 'svc_contact',    'icon' => 'fa-phone-alt',       'label' => 'Contact Us',            'desc' => 'Reach the barangay office'],
        ['action' => 'svc_faq',        'icon' => 'fa-question-circle', 'label' => 'FAQs',                  'desc' => 'Common questions answered'],
    ]);
    exit;
}

/* ══════════════════════════════════════════
   SUBMIT COMPLAINT
══════════════════════════════════════════ */
if ($action === 'svc_complaint' || preg_match('/\b(complaint|report|submit|issue|problem)\b/i', $key)) {
    echo menu('📝 Submit a Complaint', [
        ['action' => 'complaint_registered', 'icon' => 'fa-user-check', 'label' => 'I have an account',   'desc' => 'Submit as a registered resident'],
        ['action' => 'complaint_guest',      'icon' => 'fa-user',       'label' => 'I\'m a guest',         'desc' => 'Submit without an account'],
        ['action' => 'complaint_types_sub',  'icon' => 'fa-tags',       'label' => 'What can I report?',   'desc' => 'View all complaint categories'],
    ]);
    exit;
}

if ($action === 'complaint_registered') {
    echo steps(
        '📝 Submit as Registered Resident',
        [
            'Log in to your account at the login page',
            'Go to your <strong>Dashboard</strong>',
            'Click <strong>"New Complaint"</strong> button',
            'Click on the map to pin the exact location',
            'Select the complaint type from the dropdown',
            'Fill in the description details',
            'Click <strong>"Submit Complaint"</strong>',
        ],
        '✅ Your info is auto-filled and you can track the complaint in your dashboard!'
    );
    exit;
}

if ($action === 'complaint_guest') {
    echo steps(
        '👤 Submit as Guest',
        [
            'Visit the <strong>Public Map</strong> page',
            'Click <strong>"Submit Complaint (Guest)"</strong>',
            'Click on the map to pin the exact location',
            'Fill in your name and contact number',
            'Select the complaint type',
            'Write a description of the issue',
            'Click <strong>"Submit"</strong>',
        ],
        '💡 Tip: Register for an account to track your complaints and receive status notifications!'
    );
    exit;
}

/* ══════════════════════════════════════════
   TRACK COMPLAINT
══════════════════════════════════════════ */
if ($action === 'svc_track' || preg_match('/\b(track|status|check|progress|update)\b/i', $key)) {
    echo cards('📊 Complaint Status Guide', [
        ['color' => '#ef4444', 'bg' => '#fef2f2', 'icon' => 'fa-clock',        'title' => 'Filed',     'desc' => 'Your complaint has been received and is waiting to be assigned to staff.'],
        ['color' => '#59788c', 'bg' => '#e8eef3', 'icon' => 'fa-check-double', 'title' => 'Acknowledged', 'desc' => 'Staff have reviewed and acknowledged your report.'],
        ['color' => '#f59e0b', 'bg' => '#fffbeb', 'icon' => 'fa-spinner',      'title' => 'Processing', 'desc' => 'Staff is actively working on resolving your complaint.'],
        ['color' => '#10b981', 'bg' => '#f0fdf4', 'icon' => 'fa-check-circle', 'title' => 'Resolved',    'desc' => 'Your complaint has been resolved. Check resolution notes for details.'],
    ]);
    exit;
}

/* ══════════════════════════════════════════
   REGISTER / LOGIN
══════════════════════════════════════════ */
if ($action === 'svc_register' || preg_match('/\b(register|login|sign|account|password|verify)\b/i', $key)) {
    echo menu('👤 Account Help', [
        ['action' => 'reg_how',      'icon' => 'fa-user-plus',  'label' => 'How to Register',       'desc' => 'Step-by-step registration guide'],
        ['action' => 'reg_verify',   'icon' => 'fa-envelope',   'label' => 'Email Verification',    'desc' => 'Verify your Gmail address'],
        ['action' => 'reg_login',    'icon' => 'fa-sign-in-alt','label' => 'Login Help',             'desc' => 'Trouble signing in?'],
        ['action' => 'reg_benefits', 'icon' => 'fa-star',       'label' => 'Resident Benefits',     'desc' => 'Why register an account?'],
    ]);
    exit;
}

if ($action === 'reg_how') {
    echo steps('📋 How to Register', [
        'Go to the <strong>Register</strong> page',
        'Enter your <strong>Full Name</strong>',
        'Enter your <strong>Contact Number</strong>',
        'Enter a valid <strong>Gmail address</strong> (@gmail.com only)',
        'Enter your <strong>Home Address</strong>',
        'Choose a <strong>Username</strong> and <strong>Password</strong>',
        'Click <strong>"Create Account"</strong>',
        'Check your Gmail inbox and click the <strong>verification link</strong>',
    ], '✅ Once verified, you can log in and start submitting complaints!');
    exit;
}

if ($action === 'reg_verify') {
    echo text(
        "📧 Email Verification\n\nAfter registering, we send a verification link to your Gmail.\n\n• Check your <strong>Inbox</strong> and <strong>Spam</strong> folder\n• Click the link to activate your account\n• The link is one-time use only\n• You cannot log in until your email is verified\n\n⚠️ Only <strong>@gmail.com</strong> addresses are accepted.",
        [['label' => '← Back to Account Help', 'action' => 'svc_register']]
    );
    exit;
}

if ($action === 'reg_login') {
    echo text(
        "🔐 Login Troubleshooting\n\n• ✅ Make sure your Gmail is <strong>verified</strong>\n• ✅ Check your <strong>username</strong> (not email)\n• ✅ Passwords are <strong>case-sensitive</strong>\n• ✅ Clear browser cache and try again\n\n🔴 Forgot password? Contact the barangay office directly for a manual reset.",
        [['label' => '← Back to Account Help', 'action' => 'svc_register']]
    );
    exit;
}

if ($action === 'reg_benefits') {
    echo cards('🌟 Benefits of Registering', [
        ['color' => '#2563eb', 'bg' => '#eff6ff', 'icon' => 'fa-bolt',         'title' => 'Auto-filled Forms',      'desc' => 'Your name, contact, and address are filled automatically when submitting.'],
        ['color' => '#7c3aed', 'bg' => '#f5f3ff', 'icon' => 'fa-bell',         'title' => 'Status Notifications',   'desc' => 'Get notified instantly when your complaint status is updated.'],
        ['color' => '#10b981', 'bg' => '#f0fdf4', 'icon' => 'fa-history',      'title' => 'Complaint History',      'desc' => 'View all your past and current complaints in one dashboard.'],
        ['color' => '#f59e0b', 'bg' => '#fffbeb', 'icon' => 'fa-map-marked-alt','title' => 'Personal Dashboard',   'desc' => 'Track resolution progress with maps and status badges.'],
    ]);
    exit;
}

/* ══════════════════════════════════════════
   OUR SERVICES
══════════════════════════════════════════ */
if ($action === 'svc_services' || preg_match('/\b(services|offer|available|features)\b/i', $key)) {
    echo cards('🔧 Our Services', [
        ['color' => '#2563eb', 'bg' => '#eff6ff', 'icon' => 'fa-map-pin',          'title' => 'GIS Complaint Mapping',  'desc' => 'Pin exact complaint locations on an interactive map for precise reporting.'],
        ['color' => '#7c3aed', 'bg' => '#f5f3ff', 'icon' => 'fa-chart-line',       'title' => 'Real-time Tracking',     'desc' => 'Monitor your complaint resolution progress with live status updates.'],
        ['color' => '#10b981', 'bg' => '#f0fdf4', 'icon' => 'fa-bell',             'title' => 'Smart Notifications',    'desc' => 'Receive instant alerts when your complaint status changes.'],
        ['color' => '#f59e0b', 'bg' => '#fffbeb', 'icon' => 'fa-users',            'title' => 'Public & Resident Access','desc' => 'Both guests and registered residents can submit complaints.'],
        ['color' => '#ef4444', 'bg' => '#fef2f2', 'icon' => 'fa-shield-alt',       'title' => 'Admin Management',       'desc' => 'Dedicated admin panel for staff to manage and resolve complaints.'],
        ['color' => '#0891b2', 'bg' => '#ecfeff', 'icon' => 'fa-chart-bar',        'title' => 'Annual Reports',         'desc' => 'Detailed yearly statistics and charts for transparency.'],
    ]);
    exit;
}

/* ══════════════════════════════════════════
   COMPLAINT TYPES
══════════════════════════════════════════ */
if ($action === 'svc_types' || $action === 'complaint_types_sub' || preg_match('/\b(types|categories|what.*report|kind)\b/i', $key)) {
    echo cards('📋 Complaint Categories', [
        ['color' => '#ef4444', 'bg' => '#fef2f2', 'icon' => 'fa-road',             'title' => 'Road Damage',            'desc' => 'Potholes, cracks, damaged pavements, and road hazards.'],
        ['color' => '#f59e0b', 'bg' => '#fffbeb', 'icon' => 'fa-trash',            'title' => 'Garbage / Waste',        'desc' => 'Uncollected garbage, illegal dumping, waste management issues.'],
        ['color' => '#eab308', 'bg' => '#fefce8', 'icon' => 'fa-lightbulb',        'title' => 'Streetlight Issues',     'desc' => 'Broken, flickering, or missing streetlights.'],
        ['color' => '#0891b2', 'bg' => '#ecfeff', 'icon' => 'fa-tint',             'title' => 'Water Supply',           'desc' => 'Water interruptions, leaks, or contamination issues.'],
        ['color' => '#2563eb', 'bg' => '#eff6ff', 'icon' => 'fa-water',            'title' => 'Drainage / Flooding',    'desc' => 'Clogged drains, flooding, and waterlogging problems.'],
        ['color' => '#7c3aed', 'bg' => '#f5f3ff', 'icon' => 'fa-volume-up',        'title' => 'Noise Complaints',       'desc' => 'Excessive noise from neighbors, construction, or events.'],
        ['color' => '#dc2626', 'bg' => '#fef2f2', 'icon' => 'fa-hard-hat',         'title' => 'Illegal Construction',   'desc' => 'Unauthorized building or construction activities.'],
        ['color' => '#16a34a', 'bg' => '#f0fdf4', 'icon' => 'fa-shield-alt',       'title' => 'Public Safety',          'desc' => 'Safety hazards, suspicious activities, or security concerns.'],
        ['color' => '#64748b', 'bg' => '#f8fafc', 'icon' => 'fa-plus-circle',      'title' => 'Other Issues',           'desc' => 'Any other community concern not listed above.'],
    ]);
    exit;
}

/* ══════════════════════════════════════════
   PRIORITY LEVELS
══════════════════════════════════════════ */
if ($action === 'svc_priority' || preg_match('/\b(priority|urgent|emergency|level)\b/i', $key)) {
    echo cards('⚠️ Complaint Priority Levels', [
        ['color' => '#ef4444', 'bg' => '#fef2f2', 'icon' => 'fa-fire',         'title' => '🔴 High Priority',   'desc' => 'Urgent issues affecting public safety or a large number of residents. Addressed immediately.'],
        ['color' => '#f59e0b', 'bg' => '#fffbeb', 'icon' => 'fa-exclamation',  'title' => '🟡 Medium Priority', 'desc' => 'Important issues that need attention but are not immediately dangerous. Addressed within days.'],
        ['color' => '#10b981', 'bg' => '#f0fdf4', 'icon' => 'fa-info-circle',  'title' => '🟢 Low Priority',    'desc' => 'Minor issues that can be scheduled for later. Addressed within the week.'],
    ]);
    exit;
}

/* ══════════════════════════════════════════
   CONTACT
══════════════════════════════════════════ */
if ($action === 'svc_contact' || preg_match('/\b(contact|office|phone|email|address|hours)\b/i', $key)) {
    echo text(
        "📞 Barangay Mudiang Contact Information\n\n📍 <strong>Address:</strong> Barangay Mudiang\n📞 <strong>Phone:</strong> [Contact Number]\n📧 <strong>Email:</strong> info@barangay-mudiang.gov.ph\n⏰ <strong>Office Hours:</strong> Monday – Friday, 8:00 AM – 5:00 PM\n\n🔴 <strong>For urgent concerns</strong>, submit a complaint through this system and set priority to <strong>High</strong>.",
        [
            ['label' => '📝 Submit Urgent Complaint', 'action' => 'complaint_registered'],
            ['label' => '← Main Menu', 'action' => 'main_menu'],
        ]
    );
    exit;
}

/* ══════════════════════════════════════════
   FAQs
══════════════════════════════════════════ */
if ($action === 'svc_faq' || preg_match('/\b(faq|question|help|how|what|why|when)\b/i', $key)) {
    echo menu('❓ Frequently Asked Questions', [
        ['action' => 'faq_guest',       'icon' => 'fa-user',           'label' => 'Can I submit without an account?', 'desc' => 'Guest submission info'],
        ['action' => 'faq_track',       'icon' => 'fa-search',         'label' => 'How do I track my complaint?',     'desc' => 'Status tracking guide'],
        ['action' => 'faq_how_long',    'icon' => 'fa-clock',          'label' => 'How long does resolution take?',   'desc' => 'Expected timelines'],
        ['action' => 'faq_notif',       'icon' => 'fa-bell',           'label' => 'How do notifications work?',       'desc' => 'Notification system'],
        ['action' => 'faq_map',         'icon' => 'fa-map',            'label' => 'How do I use the map?',            'desc' => 'Map pinning guide'],
        ['action' => 'faq_multiple',    'icon' => 'fa-copy',           'label' => 'Can I submit multiple complaints?','desc' => 'Multiple submissions'],
    ]);
    exit;
}

if ($action === 'faq_guest') {
    echo text(
        "✅ <strong>Yes, guest submission is available!</strong>\n\nYou can submit a complaint without creating an account. However, as a guest:\n\n• ❌ You cannot track your complaint status\n• ❌ You won't receive notifications\n• ❌ No complaint history\n\n💡 We recommend <strong>registering</strong> for the full experience!",
        [['label' => '👤 Register Now', 'action' => 'reg_how'], ['label' => '← FAQs', 'action' => 'svc_faq']]
    );
    exit;
}

if ($action === 'faq_track') {
    echo text(
        "🔍 <strong>How to Track Your Complaint</strong>\n\n1️⃣ Log in to your account\n2️⃣ Go to <strong>My Dashboard</strong>\n3️⃣ Find your complaint in the list\n4️⃣ Check the status badge:\n   🔴 Filed → Acknowledged → Processing → Resolved\n\n🔔 You'll also get a <strong>notification</strong> automatically when the status changes!",
        [['label' => '← FAQs', 'action' => 'svc_faq']]
    );
    exit;
}

if ($action === 'faq_how_long') {
    echo text(
        "⏱️ <strong>Resolution Timelines</strong>\n\n🔴 <strong>High Priority</strong> — Within 24–48 hours\n🟡 <strong>Medium Priority</strong> — Within 3–5 business days\n🟢 <strong>Low Priority</strong> — Within 1–2 weeks\n\n⚠️ Timelines may vary depending on the complexity of the issue and available resources.",
        [['label' => '← FAQs', 'action' => 'svc_faq']]
    );
    exit;
}

if ($action === 'faq_notif') {
    echo text(
        "🔔 <strong>How Notifications Work</strong>\n\nWhen an admin updates your complaint status, you automatically receive a notification:\n\n• A <strong>bell icon</strong> appears in your dashboard topbar\n• A <strong>red badge</strong> shows the unread count\n• Click the bell to see all notifications\n• Click <strong>\"Mark all as read\"</strong> to clear them\n\n✅ Notifications are only available for <strong>registered residents</strong>.",
        [['label' => '← FAQs', 'action' => 'svc_faq']]
    );
    exit;
}

if ($action === 'faq_map') {
    echo text(
        "🗺️ <strong>How to Use the Map</strong>\n\n1️⃣ Open the complaint submission form\n2️⃣ The map shows your barangay area\n3️⃣ <strong>Click anywhere on the map</strong> to drop a pin\n4️⃣ The pin marks the exact location of the issue\n5️⃣ You can click again to move the pin\n6️⃣ The coordinates are saved automatically\n\n💡 Zoom in for more precise location pinning!",
        [['label' => '← FAQs', 'action' => 'svc_faq']]
    );
    exit;
}

if ($action === 'faq_multiple') {
    echo text(
        "📋 <strong>Multiple Complaints</strong>\n\n✅ Yes! You can submit as many complaints as needed.\n\n• Each complaint is tracked <strong>separately</strong>\n• All complaints appear in your <strong>Dashboard</strong>\n• Each has its own status, priority, and resolution notes\n• You'll get individual notifications for each one\n\n💡 Please submit <strong>one complaint per issue</strong> for better tracking.",
        [['label' => '← FAQs', 'action' => 'svc_faq']]
    );
    exit;
}

/* ══════════════════════════════════════════
   GREETINGS
══════════════════════════════════════════ */
if (preg_match('/\b(hi|hello|hey|good morning|good afternoon|good evening|kumusta|musta)\b/i', $key)) {
    $greet = ['Hello! 👋', 'Hi there! 😊', 'Good day! ✨'][array_rand(['a','b','c'])];
    echo text(
        "$greet I'm your <strong>Barangay Mudiang</strong> virtual assistant.\n\nI can help you with complaints, tracking, registration, and more. What do you need today?",
        [
            ['label' => '📋 Main Menu',          'action' => 'main_menu'],
            ['label' => '📝 Submit Complaint',   'action' => 'svc_complaint'],
            ['label' => '🔍 Track Complaint',    'action' => 'svc_track'],
        ]
    );
    exit;
}

/* ══════════════════════════════════════════
   THANK YOU
══════════════════════════════════════════ */
if (preg_match('/\b(thank|thanks|salamat|bye|goodbye|ok|okay|done)\b/i', $key)) {
    $r = ["You're welcome! 😊 Is there anything else I can help you with?", "Happy to help! ✨ Feel free to ask anytime.", "Glad I could assist! 👋 Have a great day!"][array_rand(['a','b','c'])];
    echo text($r, [['label' => '📋 Main Menu', 'action' => 'main_menu']]);
    exit;
}

/* ══════════════════════════════════════════
   DEFAULT
══════════════════════════════════════════ */
echo text(
    "🤔 I'm not sure I understood that. Here's what I can help you with:",
    [
        ['label' => '📋 Main Menu',        'action' => 'main_menu'],
        ['label' => '📝 Submit Complaint', 'action' => 'svc_complaint'],
        ['label' => '🔍 Track Status',     'action' => 'svc_track'],
        ['label' => '❓ FAQs',             'action' => 'svc_faq'],
    ]
);
