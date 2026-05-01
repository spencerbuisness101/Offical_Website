<?php
/**
 * Support Help Page - Spencer's Website v7.0
 * Phase 2: Full UI redesign with role-based ticket limits.
 * Community = 3 tickets/day, all other roles = 10 tickets/day.
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$csrfToken = generateCsrfToken();
$userId = (int) $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username'] ?? 'User');
$role = $_SESSION['role'] ?? 'community';

$db = null;
$tickets = [];
$ticketResponses = [];
$todayCount = 0;
$dailyLimit = ($role === 'community') ? 3 : 10;

try {
    $database = new Database();
    $db = $database->getConnection();

    // Auto-create tables
    $db->exec("CREATE TABLE IF NOT EXISTS support_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        category ENUM('bug','feature','account','payment','general') NOT NULL DEFAULT 'general',
        description TEXT NOT NULL,
        priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
        status ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS support_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        is_admin BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ticket_id (ticket_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

} catch (Exception $e) {
    error_log("Support page DB error: " . $e->getMessage());
}

// Handle AJAX POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $postCsrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($postCsrf)) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
        exit;
    }

    if ($_POST['action'] === 'submit_ticket' && $db) {
        $subject = trim($_POST['subject'] ?? '');
        $category = $_POST['category'] ?? 'general';
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';

        if (empty($subject) || strlen($subject) > 255) {
            echo json_encode(['success' => false, 'error' => 'Subject must be 1-255 characters.']);
            exit;
        }
        if (empty($description) || strlen($description) > 5000) {
            echo json_encode(['success' => false, 'error' => 'Description must be 1-5000 characters.']);
            exit;
        }
        if (!in_array($category, ['bug', 'feature', 'account', 'payment', 'general'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid category.']);
            exit;
        }
        if (!in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid priority.']);
            exit;
        }

        // Role-based daily ticket limit
        $stmt = $db->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id = ? AND created_at >= CURDATE()");
        $stmt->execute([$userId]);
        $todayTickets = (int) $stmt->fetchColumn();
        $maxDaily = ($role === 'community') ? 3 : 10;

        if ($todayTickets >= $maxDaily) {
            $roleName = ($role === 'community') ? 'Community' : ucfirst($role);
            echo json_encode(['success' => false, 'error' => "{$roleName} accounts can submit up to {$maxDaily} tickets per day. Limit reached."]);
            exit;
        }

        $subject = htmlspecialchars($subject, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = htmlspecialchars($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $stmt = $db->prepare("INSERT INTO support_tickets (user_id, subject, category, description, priority) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $subject, $category, $description, $priority]);

        echo json_encode(['success' => true, 'message' => 'Ticket submitted successfully!']);
        exit;
    }

    if ($_POST['action'] === 'add_response' && $db) {
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        if (!$ticketId || empty($message) || strlen($message) > 2000) {
            echo json_encode(['success' => false, 'error' => 'Message must be 1-2000 characters.']);
            exit;
        }

        $stmt = $db->prepare("SELECT user_id, status FROM support_tickets WHERE id = ?");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket || ($ticket['user_id'] !== $userId && $role !== 'admin')) {
            echo json_encode(['success' => false, 'error' => 'Ticket not found.']);
            exit;
        }
        if ($ticket['status'] === 'closed') {
            echo json_encode(['success' => false, 'error' => 'This ticket is closed.']);
            exit;
        }

        $message = htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $isAdmin = ($role === 'admin') ? 1 : 0;

        $stmt = $db->prepare("INSERT INTO support_responses (ticket_id, user_id, message, is_admin) VALUES (?, ?, ?, ?)");
        $stmt->execute([$ticketId, $userId, $message, $isAdmin]);

        echo json_encode(['success' => true, 'message' => 'Response added.']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
    exit;
}

// Load user's tickets + responses
if ($db) {
    try {
        $stmt = $db->prepare("SELECT id, user_id, subject, description, category, priority, status, created_at, updated_at FROM support_tickets WHERE user_id = ? ORDER BY updated_at DESC LIMIT 50");
        $stmt->execute([$userId]);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Preload responses for all tickets
        if (!empty($tickets)) {
            $ticketIds = array_column($tickets, 'id');
            $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
            $stmt = $db->prepare("SELECT sr.id, sr.ticket_id, sr.user_id, sr.response_text, sr.is_admin, sr.created_at, u.username FROM support_responses sr LEFT JOIN users u ON sr.user_id = u.id WHERE sr.ticket_id IN ($placeholders) ORDER BY sr.created_at ASC");
            $stmt->execute($ticketIds);
            $allResponses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($allResponses as $r) {
                $ticketResponses[$r['ticket_id']][] = $r;
            }
        }

        // Today's ticket count
        $stmt = $db->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id = ? AND created_at >= CURDATE()");
        $stmt->execute([$userId]);
        $todayCount = (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Support tickets load error: " . $e->getMessage());
    }
}

$remaining = max(0, $dailyLimit - $todayCount);

// Status counts for filter pills
$statusCounts = ['all' => count($tickets), 'open' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
foreach ($tickets as $t) {
    if (isset($statusCounts[$t['status']])) $statusCounts[$t['status']]++;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - Spencer's Website</title>
    <link rel="icon" href="/assets/images/favicon.webp">
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
    <style>
        .sp-wrap { max-width: 1120px; margin: 0 auto; padding: 28px 20px 60px; }

        .sp-back { display: inline-flex; align-items: center; gap: 6px; color: #64748b; text-decoration: none; font-size: 0.88rem; margin-bottom: 20px; transition: color .2s; }
        .sp-back:hover { color: #4ECDC4; }

        .sp-hero { text-align: center; margin-bottom: 28px; }
        .sp-hero h1 {
            font-size: 2rem; font-weight: 800;
            background: linear-gradient(135deg, #4ECDC4, #6366f1);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .sp-hero p { color: #94a3b8; font-size: 0.95rem; margin-top: 4px; }

        .sp-limit-bar {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            background: rgba(15,23,42,0.6); border: 1px solid rgba(255,255,255,0.06);
            border-radius: 10px; padding: 10px 16px; margin-bottom: 24px; font-size: 0.85rem; color: #94a3b8;
        }
        .sp-limit-bar .lim-count { color: #4ECDC4; font-weight: 700; }
        .sp-limit-bar .lim-role { color: #6366f1; font-weight: 600; text-transform: capitalize; }

        /* Two-column layout */
        .sp-grid { display: grid; grid-template-columns: 380px 1fr; gap: 24px; align-items: start; }
        @media (max-width: 860px) { .sp-grid { grid-template-columns: 1fr; } }

        /* Form panel */
        .sp-form-panel {
            background: rgba(15,23,42,0.75); border: 1px solid rgba(78,205,196,0.15);
            border-radius: 14px; padding: 24px; position: sticky; top: 20px;
        }
        .sp-form-panel h2 { font-size: 1.1rem; font-weight: 700; color: #e2e8f0; margin-bottom: 16px; }

        .sp-fg { margin-bottom: 14px; }
        .sp-fg label { display: block; font-size: 0.82rem; font-weight: 600; color: #94a3b8; margin-bottom: 4px; text-transform: uppercase; letter-spacing: .5px; }
        .sp-fg input, .sp-fg textarea, .sp-fg select {
            width: 100%; padding: 10px 12px; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px; color: #e2e8f0; font-size: 0.92rem; font-family: inherit; transition: border-color .2s;
        }
        .sp-fg input:focus, .sp-fg textarea:focus, .sp-fg select:focus { outline: none; border-color: #4ECDC4; }
        .sp-fg textarea { resize: vertical; min-height: 110px; }
        .sp-fg .char-count { text-align: right; font-size: 0.72rem; color: #475569; margin-top: 3px; }

        .sp-fg-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width: 480px) { .sp-fg-row { grid-template-columns: 1fr; } }

        .sp-submit-btn {
            width: 100%; padding: 11px; border: none; border-radius: 10px;
            background: linear-gradient(135deg, #4ECDC4, #6366f1); color: #fff;
            font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: opacity .2s, box-shadow .2s;
        }
        .sp-submit-btn:hover { box-shadow: 0 4px 24px rgba(78,205,196,0.3); }
        .sp-submit-btn:disabled { opacity: .45; cursor: not-allowed; }

        .sp-form-msg { margin-top: 8px; font-size: 0.85rem; min-height: 1.2em; }
        .sp-form-msg.ok { color: #10b981; }
        .sp-form-msg.err { color: #ef4444; }

        /* Ticket list panel */
        .sp-tickets-panel h2 { font-size: 1.1rem; font-weight: 700; color: #e2e8f0; margin-bottom: 12px; }

        /* Filter pills */
        .sp-filters { display: flex; gap: 6px; margin-bottom: 16px; flex-wrap: wrap; }
        .sp-pill {
            padding: 5px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: 600;
            background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
            color: #94a3b8; cursor: pointer; transition: all .2s; user-select: none;
        }
        .sp-pill:hover { border-color: rgba(78,205,196,0.3); color: #cbd5e1; }
        .sp-pill.active { background: rgba(78,205,196,0.15); border-color: #4ECDC4; color: #4ECDC4; }
        .sp-pill .pill-n { font-size: 0.7rem; opacity: .7; margin-left: 3px; }

        /* Ticket cards */
        .sp-ticket {
            background: rgba(15,23,42,0.7); border: 1px solid rgba(255,255,255,0.06);
            border-radius: 12px; margin-bottom: 10px; overflow: hidden; transition: border-color .2s;
            border-left: 3px solid transparent;
        }
        .sp-ticket:hover { border-color: rgba(78,205,196,0.2); }
        .sp-ticket[data-status="open"] { border-left-color: #3b82f6; }
        .sp-ticket[data-status="in_progress"] { border-left-color: #f59e0b; }
        .sp-ticket[data-status="resolved"] { border-left-color: #10b981; }
        .sp-ticket[data-status="closed"] { border-left-color: #475569; }

        .sp-ticket-head {
            display: flex; align-items: center; justify-content: space-between; padding: 14px 16px;
            cursor: pointer; gap: 10px; flex-wrap: wrap;
        }
        .sp-ticket-head:hover { background: rgba(255,255,255,0.02); }
        .sp-ticket-title { font-weight: 700; font-size: 0.92rem; color: #e2e8f0; flex: 1; min-width: 0; }
        .sp-ticket-title .tid { color: #475569; font-weight: 500; margin-right: 6px; }

        .sp-badges { display: flex; gap: 5px; flex-shrink: 0; flex-wrap: wrap; }
        .sp-badge {
            padding: 2px 9px; border-radius: 6px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .3px;
        }
        .b-open { background: rgba(59,130,246,0.15); color: #60a5fa; }
        .b-in_progress { background: rgba(245,158,11,0.15); color: #fbbf24; }
        .b-resolved { background: rgba(16,185,129,0.15); color: #34d399; }
        .b-closed { background: rgba(100,116,139,0.15); color: #94a3b8; }
        .b-bug { background: rgba(239,68,68,0.1); color: #f87171; }
        .b-feature { background: rgba(139,92,246,0.1); color: #a78bfa; }
        .b-account { background: rgba(59,130,246,0.1); color: #60a5fa; }
        .b-payment { background: rgba(245,158,11,0.1); color: #fbbf24; }
        .b-general { background: rgba(100,116,139,0.1); color: #94a3b8; }
        .b-low { background: rgba(100,116,139,0.08); color: #94a3b8; }
        .b-medium { background: rgba(59,130,246,0.08); color: #60a5fa; }
        .b-high { background: rgba(245,158,11,0.08); color: #fbbf24; }
        .b-urgent { background: rgba(239,68,68,0.1); color: #f87171; }

        .sp-chevron { color: #475569; transition: transform .25s; font-size: 0.8rem; }
        .sp-ticket.expanded .sp-chevron { transform: rotate(180deg); }

        /* Expanded body */
        .sp-ticket-body { display: none; padding: 0 16px 16px; }
        .sp-ticket.expanded .sp-ticket-body { display: block; }

        .sp-desc { color: #94a3b8; font-size: 0.88rem; line-height: 1.65; margin-bottom: 12px; padding: 12px; background: rgba(0,0,0,0.2); border-radius: 8px; }
        .sp-ticket-meta { color: #475569; font-size: 0.75rem; margin-bottom: 14px; }

        /* Response thread */
        .sp-thread { border-top: 1px solid rgba(255,255,255,0.05); padding-top: 12px; }
        .sp-thread-title { font-size: 0.82rem; font-weight: 600; color: #64748b; margin-bottom: 10px; }
        .sp-resp {
            display: flex; gap: 10px; margin-bottom: 10px; padding: 10px 12px;
            background: rgba(0,0,0,0.15); border-radius: 8px; border-left: 2px solid #334155;
        }
        .sp-resp.admin-resp { border-left-color: #4ECDC4; background: rgba(78,205,196,0.04); }
        .sp-resp-meta { font-size: 0.75rem; color: #64748b; margin-bottom: 4px; }
        .sp-resp-meta .resp-user { font-weight: 700; color: #94a3b8; }
        .sp-resp-meta .resp-admin-tag { color: #4ECDC4; font-weight: 700; margin-left: 4px; font-size: 0.68rem; }
        .sp-resp-text { font-size: 0.85rem; color: #cbd5e1; line-height: 1.55; }

        /* Reply form */
        .sp-reply-form { display: flex; gap: 8px; margin-top: 10px; }
        .sp-reply-form input {
            flex: 1; padding: 9px 12px; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px; color: #e2e8f0; font-size: 0.88rem; font-family: inherit;
        }
        .sp-reply-form input:focus { outline: none; border-color: #4ECDC4; }
        .sp-reply-btn {
            padding: 9px 16px; border: none; border-radius: 8px;
            background: rgba(78,205,196,0.2); color: #4ECDC4; font-weight: 600; font-size: 0.85rem;
            cursor: pointer; transition: background .2s; white-space: nowrap;
        }
        .sp-reply-btn:hover { background: rgba(78,205,196,0.35); }

        /* Empty state */
        .sp-empty { text-align: center; padding: 48px 16px; color: #475569; }
        .sp-empty i { font-size: 2.5rem; margin-bottom: 12px; display: block; opacity: .3; }
        .sp-empty p { font-size: 0.9rem; }

        .sp-hidden { display: none !important; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/identity_bar.php'; ?>
<div class="sp-wrap">
    <a href="main.php" class="sp-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

    <div class="sp-hero">
        <h1><i class="fas fa-life-ring"></i> Support Center</h1>
        <p>Submit a ticket and we'll get back to you as soon as possible.</p>
    </div>

    <div class="sp-limit-bar">
        <i class="fas fa-ticket-alt" style="color:#4ECDC4;"></i>
        <span>Today: <span class="lim-count"><?php echo $todayCount; ?>/<?php echo $dailyLimit; ?></span> tickets used</span>
        <span style="color:#334155;">|</span>
        <span><span class="lim-count"><?php echo $remaining; ?></span> remaining</span>
        <span style="color:#334155;">|</span>
        <span>Limit for <span class="lim-role"><?php echo htmlspecialchars($role); ?></span> role</span>
    </div>

    <div class="sp-grid">
        <!-- Left: Submit form -->
        <div class="sp-form-panel">
            <h2><i class="fas fa-plus-circle" style="color:#4ECDC4; margin-right:6px;"></i>New Ticket</h2>
            <form id="ticketForm" onsubmit="return submitTicket(event)">
                <div class="sp-fg">
                    <label for="tSubject">Subject</label>
                    <input type="text" id="tSubject" maxlength="255" placeholder="Brief summary of your issue" required>
                </div>
                <div class="sp-fg-row">
                    <div class="sp-fg">
                        <label for="tCategory">Category</label>
                        <select id="tCategory">
                            <option value="general">General</option>
                            <option value="bug">Bug Report</option>
                            <option value="feature">Feature Request</option>
                            <option value="account">Account Issue</option>
                            <option value="payment">Payment Issue</option>
                        </select>
                    </div>
                    <div class="sp-fg">
                        <label for="tPriority">Priority</label>
                        <select id="tPriority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="sp-fg">
                    <label for="tDesc">Description</label>
                    <textarea id="tDesc" maxlength="5000" placeholder="Describe your issue in detail..." required oninput="updateCharCount()"></textarea>
                    <div class="char-count"><span id="charNum">0</span> / 5000</div>
                </div>
                <button type="submit" class="sp-submit-btn" id="btnSubmit" <?php echo $remaining <= 0 ? 'disabled' : ''; ?>>
                    <i class="fas fa-paper-plane"></i> Submit Ticket
                </button>
                <?php if ($remaining <= 0): ?>
                <div class="sp-form-msg err">Daily ticket limit reached. Try again tomorrow.</div>
                <?php endif; ?>
                <div class="sp-form-msg" id="formMsg"></div>
            </form>
        </div>

        <!-- Right: Ticket list -->
        <div class="sp-tickets-panel">
            <h2><i class="fas fa-inbox" style="color:#6366f1; margin-right:6px;"></i>Your Tickets</h2>

            <div class="sp-filters">
                <span class="sp-pill active" data-filter="all">All <span class="pill-n">(<?php echo $statusCounts['all']; ?>)</span></span>
                <span class="sp-pill" data-filter="open">Open <span class="pill-n">(<?php echo $statusCounts['open']; ?>)</span></span>
                <span class="sp-pill" data-filter="in_progress">In Progress <span class="pill-n">(<?php echo $statusCounts['in_progress']; ?>)</span></span>
                <span class="sp-pill" data-filter="resolved">Resolved <span class="pill-n">(<?php echo $statusCounts['resolved']; ?>)</span></span>
                <span class="sp-pill" data-filter="closed">Closed <span class="pill-n">(<?php echo $statusCounts['closed']; ?>)</span></span>
            </div>

            <?php if (empty($tickets)): ?>
                <div class="sp-empty">
                    <i class="fas fa-inbox"></i>
                    <p>No tickets yet. Submit one using the form.</p>
                </div>
            <?php else: ?>
                <?php foreach ($tickets as $ticket): ?>
                <div class="sp-ticket" data-status="<?php echo htmlspecialchars($ticket['status']); ?>" data-id="<?php echo (int)$ticket['id']; ?>">
                    <div class="sp-ticket-head" onclick="toggleTicket(this)">
                        <div class="sp-ticket-title">
                            <span class="tid">#<?php echo (int)$ticket['id']; ?></span><?php echo htmlspecialchars($ticket['subject']); ?>
                        </div>
                        <div class="sp-badges">
                            <span class="sp-badge b-<?php echo htmlspecialchars($ticket['category']); ?>"><?php echo htmlspecialchars($ticket['category']); ?></span>
                            <span class="sp-badge b-<?php echo htmlspecialchars($ticket['priority']); ?>"><?php echo htmlspecialchars($ticket['priority']); ?></span>
                            <span class="sp-badge b-<?php echo htmlspecialchars($ticket['status']); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', $ticket['status'])); ?></span>
                        </div>
                        <i class="fas fa-chevron-down sp-chevron"></i>
                    </div>
                    <div class="sp-ticket-body">
                        <div class="sp-desc"><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></div>
                        <div class="sp-ticket-meta">
                            Submitted <?php echo date('M j, Y \a\t g:ia', strtotime($ticket['created_at'])); ?>
                            <?php if ($ticket['updated_at'] !== $ticket['created_at']): ?>
                                &middot; Updated <?php echo date('M j, Y \a\t g:ia', strtotime($ticket['updated_at'])); ?>
                            <?php endif; ?>
                        </div>

                        <!-- Response thread -->
                        <div class="sp-thread">
                            <div class="sp-thread-title"><i class="fas fa-comments" style="margin-right:4px;"></i> Conversation</div>
                            <?php
                            $resps = $ticketResponses[$ticket['id']] ?? [];
                            if (empty($resps)): ?>
                                <p style="color:#475569; font-size:0.82rem; font-style:italic;">No responses yet.</p>
                            <?php else:
                                foreach ($resps as $r): ?>
                                <div class="sp-resp <?php echo $r['is_admin'] ? 'admin-resp' : ''; ?>">
                                    <div style="flex:1; min-width:0;">
                                        <div class="sp-resp-meta">
                                            <span class="resp-user"><?php echo htmlspecialchars($r['username'] ?? 'Unknown'); ?></span>
                                            <?php if ($r['is_admin']): ?><span class="resp-admin-tag">ADMIN</span><?php endif; ?>
                                            &middot; <?php echo date('M j, g:ia', strtotime($r['created_at'])); ?>
                                        </div>
                                        <div class="sp-resp-text"><?php echo nl2br(htmlspecialchars($r['message'])); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>

                            <?php if ($ticket['status'] !== 'closed'): ?>
                            <div class="sp-reply-form">
                                <input type="text" placeholder="Write a reply..." maxlength="2000" id="reply-<?php echo $ticket['id']; ?>">
                                <button class="sp-reply-btn" onclick="sendReply(<?php echo $ticket['id']; ?>)"><i class="fas fa-reply"></i> Reply</button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (file_exists(__DIR__ . '/includes/consent_banner.php')) include_once __DIR__ . '/includes/consent_banner.php'; ?>
<?php if (file_exists(__DIR__ . '/includes/policy_footer.php')) include_once __DIR__ . '/includes/policy_footer.php'; ?>

<script>
const csrfToken = '<?php echo htmlspecialchars($csrfToken); ?>';

function updateCharCount() {
    const t = document.getElementById('tDesc');
    document.getElementById('charNum').textContent = t.value.length;
}

function toggleTicket(head) {
    head.closest('.sp-ticket').classList.toggle('expanded');
}

// Filter pills
document.querySelectorAll('.sp-pill').forEach(pill => {
    pill.addEventListener('click', function() {
        document.querySelectorAll('.sp-pill').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        const f = this.dataset.filter;
        document.querySelectorAll('.sp-ticket').forEach(t => {
            if (f === 'all' || t.dataset.status === f) {
                t.classList.remove('sp-hidden');
            } else {
                t.classList.add('sp-hidden');
            }
        });
    });
});

async function submitTicket(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmit');
    const msg = document.getElementById('formMsg');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    msg.textContent = '';
    msg.className = 'sp-form-msg';

    const body = new URLSearchParams({
        action: 'submit_ticket',
        csrf_token: csrfToken,
        subject: document.getElementById('tSubject').value,
        category: document.getElementById('tCategory').value,
        priority: document.getElementById('tPriority').value,
        description: document.getElementById('tDesc').value,
    });

    try {
        const resp = await fetch('supporthelp.php', { method: 'POST', credentials: 'same-origin', body });
        const data = await resp.json();
        msg.textContent = data.message || data.error || 'Done';
        msg.className = 'sp-form-msg ' + (data.success ? 'ok' : 'err');
        if (data.success) {
            document.getElementById('ticketForm').reset();
            document.getElementById('charNum').textContent = '0';
            setTimeout(() => location.reload(), 1200);
        }
    } catch (err) {
        msg.textContent = 'Network error. Please try again.';
        msg.className = 'sp-form-msg err';
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Ticket';
    return false;
}

async function sendReply(ticketId) {
    const input = document.getElementById('reply-' + ticketId);
    const message = input.value.trim();
    if (!message) return;

    const body = new URLSearchParams({
        action: 'add_response',
        csrf_token: csrfToken,
        ticket_id: ticketId,
        message: message,
    });

    try {
        const resp = await fetch('supporthelp.php', { method: 'POST', credentials: 'same-origin', body });
        const data = await resp.json();
        if (data.success) {
            input.value = '';
            location.reload();
        } else {
            alert(data.error || 'Failed to send reply.');
        }
    } catch (err) {
        alert('Network error.');
    }
}
</script>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
