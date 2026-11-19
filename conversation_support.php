<?php
require_once __DIR__.'/access_control.php';
function conversationEscape($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function conversationToken(): string
{
    if (empty($_SESSION['conversation_csrf'])) $_SESSION['conversation_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['conversation_csrf'];
}
function unreadConversationCounts(PDO $pdo, int $userId, bool $isStaff): array
{
    [$scope,$params]=complaintAccessScope();$ownership=' AND '.$scope;
    $query = $pdo->prepare('SELECT m.complaint_id, COUNT(*) AS unread FROM complaint_messages m JOIN complaints c ON c.id=m.complaint_id LEFT JOIN complaint_message_reads r ON r.complaint_id=m.complaint_id AND r.user_id=? WHERE m.user_id<>? AND m.id>COALESCE(r.last_message_id,0)' . $ownership . ' GROUP BY m.complaint_id');
    $query->execute(array_merge([$userId,$userId],$params));
    return $query->fetchAll(PDO::FETCH_KEY_PAIR);
}
function renderComplaintConversation(int $complaintId, bool $isStaff, string $prefix = ''): void
{
    $token = conversationToken();
    ?>
    <section class="card conversation-card" id="conversation" data-complaint="<?php echo $complaintId; ?>" data-user="<?php echo (int) $_SESSION['user_id']; ?>" data-endpoint="<?php echo $prefix; ?>complaint_messages.php" data-token="<?php echo conversationEscape($token); ?>">
        <header class="conversation-header"><div class="conversation-emblem"><i class="fas fa-comments" aria-hidden="true"></i></div><div><span class="portal-kicker">Report #<?php echo $complaintId; ?> / Conversation</span><h2><?php echo $isStaff ? 'Talk with the resident' : 'Talk with your barangay'; ?></h2><p>Questions, context, and updates in one place.</p></div></header>
        <div class="conversation-service"><span><i class="fas fa-lock" aria-hidden="true"></i> Private to this resident and authorized staff</span><span class="conversation-connection" role="status">Connecting…</span></div>
        <button class="conversation-older" type="button" hidden>Load earlier messages</button>
        <div class="conversation-timeline" role="log" aria-label="Complaint messages" aria-live="polite" aria-relevant="additions" tabindex="0"><div class="conversation-empty"><i class="fas fa-comment-dots" aria-hidden="true"></i><strong>A conversation makes things clearer.</strong><p><?php echo $isStaff ? 'Ask for details or share an update about this report.' : 'Ask a question or share more details about your concern.'; ?></p></div></div>
        <button class="conversation-new" type="button" hidden>New messages ↓</button>
        <form class="conversation-composer"><label class="visually-hidden" for="conversation-body">Your message</label><textarea id="conversation-body" name="body" rows="3" maxlength="2000" placeholder="<?php echo $isStaff ? 'Write an update or ask the resident a question…' : 'Write a question or share more details…'; ?>" required></textarea><div class="conversation-compose-footer"><span class="conversation-counter">0 / 2,000</span><button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane" aria-hidden="true"></i> Send message</button></div><p class="conversation-error" role="alert" hidden></p></form>
        <noscript><p class="alert alert-info">Enable JavaScript to send and receive messages.</p></noscript>
    </section>
    <script src="<?php echo $prefix; ?>assets/conversation.js?v=1" defer></script>
    <?php
}
