<?php
// Define APP_RUNNING for config files
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';


// Security headers — centralized via security.php setSecurityHeaders()
require_once __DIR__ . '/includes/security.php';
setSecurityHeaders();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Get session data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_role = $_SESSION['role'];

// Roles that can view all feedback
$elevated_roles = ['admin', 'contributor', 'designer'];
$can_view_all = in_array($user_role, $elevated_roles);
$is_admin = ($user_role === 'admin');

// Role color map
$role_colors = [
    'admin'       => '#ef4444',
    'contributor'  => '#f59e0b',
    'designer'     => '#ec4899',
    'user'         => '#3b82f6',
    'community'    => '#10b981',
];

// Database connection
$db = null;
try {
    $db = (new Database())->getConnection();

    // Auto-create user_feedback table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS user_feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        content TEXT NOT NULL,
        admin_response TEXT NULL,
        status ENUM('pending','reviewed','resolved','dismissed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    error_log("Database connection error in feedback.php: " . $e->getMessage());
}

// Count user's submissions
$user_submission_count = 0;
if ($db) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_feedback WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_submission_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Error counting feedback: " . $e->getMessage());
    }
}
$can_submit = ($user_submission_count < 5);
$submissions_remaining = 5 - $user_submission_count;

// ── Handle AJAX POST actions ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh the page.']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        // ── Submit feedback ───────────────────────────────────────────
        if ($action === 'submit_feedback') {
            $content = trim($_POST['content'] ?? '');

            if (empty($content)) {
                echo json_encode(['success' => false, 'message' => 'Feedback content is required.']);
                exit;
            }

            // Word count check (400 max)
            $word_count = str_word_count($content);
            if ($word_count > 400) {
                echo json_encode(['success' => false, 'message' => 'Feedback exceeds 400 word limit (' . $word_count . ' words).']);
                exit;
            }

            // Submission limit check
            $stmt = $db->prepare("SELECT COUNT(*) FROM user_feedback WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $count = (int)$stmt->fetchColumn();

            if ($count >= 5) {
                echo json_encode(['success' => false, 'message' => 'You have reached the maximum of 5 feedback submissions.']);
                exit;
            }

            $stmt = $db->prepare("INSERT INTO user_feedback (user_id, content) VALUES (?, ?)");
            $stmt->execute([$user_id, $content]);
            $new_id = $db->lastInsertId();

            // Log submission
            try {
                $logStmt = $db->prepare("INSERT INTO system_logs (level, message, user_id) VALUES ('info', ?, ?)");
                $logStmt->execute(["Feedback submitted (ID: {$new_id}) by {$username}", $user_id]);
            } catch (Exception $e) { /* system_logs may not exist */ }

            echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully! Thank you.']);
            exit;
        }

        // ── Edit feedback (admin or submitter only) ───────────────────
        if ($action === 'edit_feedback') {
            $feedback_id = intval($_POST['feedback_id'] ?? 0);
            $content = trim($_POST['content'] ?? '');

            if (empty($content)) {
                echo json_encode(['success' => false, 'message' => 'Feedback content is required.']);
                exit;
            }

            $word_count = str_word_count($content);
            if ($word_count > 400) {
                echo json_encode(['success' => false, 'message' => 'Feedback exceeds 400 word limit (' . $word_count . ' words).']);
                exit;
            }

            // Get the feedback
            $stmt = $db->prepare("SELECT id, user_id FROM user_feedback WHERE id = ?");
            $stmt->execute([$feedback_id]);
            $feedback = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$feedback) {
                echo json_encode(['success' => false, 'message' => 'Feedback not found.']);
                exit;
            }

            // Only admin or the original submitter can edit
            if (!$is_admin && $feedback['user_id'] != $user_id) {
                echo json_encode(['success' => false, 'message' => 'Access denied.']);
                exit;
            }

            $stmt = $db->prepare("UPDATE user_feedback SET content = ? WHERE id = ?");
            $stmt->execute([$content, $feedback_id]);

            try {
                $logStmt = $db->prepare("INSERT INTO system_logs (level, message, user_id) VALUES ('info', ?, ?)");
                $logStmt->execute(["Feedback edited (ID: {$feedback_id}) by {$username}", $user_id]);
            } catch (Exception $e) { /* system_logs may not exist */ }

            echo json_encode(['success' => true, 'message' => 'Feedback updated successfully.']);
            exit;
        }

        // ── Delete feedback (admin or submitter only) ─────────────────
        if ($action === 'delete_feedback') {
            $feedback_id = intval($_POST['feedback_id'] ?? 0);

            $stmt = $db->prepare("SELECT id, user_id FROM user_feedback WHERE id = ?");
            $stmt->execute([$feedback_id]);
            $feedback = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$feedback) {
                echo json_encode(['success' => false, 'message' => 'Feedback not found.']);
                exit;
            }

            // Only admin or the original submitter can delete
            if (!$is_admin && $feedback['user_id'] != $user_id) {
                echo json_encode(['success' => false, 'message' => 'Access denied.']);
                exit;
            }

            $stmt = $db->prepare("DELETE FROM user_feedback WHERE id = ?");
            $stmt->execute([$feedback_id]);

            try {
                $logStmt = $db->prepare("INSERT INTO system_logs (level, message, user_id) VALUES ('info', ?, ?)");
                $logStmt->execute(["Feedback deleted (ID: {$feedback_id}) by {$username}", $user_id]);
            } catch (Exception $e) { /* system_logs may not exist */ }

            echo json_encode(['success' => true, 'message' => 'Feedback deleted successfully.']);
            exit;
        }

        // ── Admin respond ─────────────────────────────────────────────
        if ($action === 'admin_respond') {
            if (!$is_admin) {
                echo json_encode(['success' => false, 'message' => 'Only admins can respond to feedback.']);
                exit;
            }

            $feedback_id = intval($_POST['feedback_id'] ?? 0);
            $response_text = trim($_POST['admin_response'] ?? '');
            $new_status = $_POST['status'] ?? null;

            if (empty($response_text)) {
                echo json_encode(['success' => false, 'message' => 'Response text is required.']);
                exit;
            }

            $stmt = $db->prepare("SELECT id, admin_response FROM user_feedback WHERE id = ?");
            $stmt->execute([$feedback_id]);
            $feedback = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$feedback) {
                echo json_encode(['success' => false, 'message' => 'Feedback not found.']);
                exit;
            }

            // Append response with timestamp
            $existing = $feedback['admin_response'] ?? '';
            $timestamp = date('M j, Y g:i A');
            $new_response = $existing
                ? $existing . "\n\n--- Admin response ({$timestamp}) ---\n" . $response_text
                : "--- Admin response ({$timestamp}) ---\n" . $response_text;

            $allowed_statuses = ['pending', 'reviewed', 'resolved', 'dismissed'];
            if ($new_status && in_array($new_status, $allowed_statuses)) {
                $stmt = $db->prepare("UPDATE user_feedback SET admin_response = ?, status = ? WHERE id = ?");
                $stmt->execute([$new_response, $new_status, $feedback_id]);
            } else {
                $stmt = $db->prepare("UPDATE user_feedback SET admin_response = ? WHERE id = ?");
                $stmt->execute([$new_response, $feedback_id]);
            }

            try {
                $logStmt = $db->prepare("INSERT INTO system_logs (level, message, user_id) VALUES ('info', ?, ?)");
                $logStmt->execute(["Admin responded to feedback (ID: {$feedback_id})", $user_id]);
            } catch (Exception $e) { /* system_logs may not exist */ }

            echo json_encode(['success' => true, 'message' => 'Response submitted successfully.']);
            exit;
        }

    } catch (Exception $e) {
        error_log("Feedback error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// ── Fetch feedback for display ────────────────────────────────────────
$feedback_list = [];
if ($db) {
    try {
        if ($can_view_all) {
            // Admin, contributor, designer see all feedback
            $stmt = $db->prepare("
                SELECT uf.*, u.username, u.role
                FROM user_feedback uf
                LEFT JOIN users u ON uf.user_id = u.id
                ORDER BY
                    FIELD(uf.status, 'pending', 'reviewed', 'resolved', 'dismissed'),
                    uf.created_at DESC
            ");
            $stmt->execute();
        } else {
            // Regular users see only their own
            $stmt = $db->prepare("
                SELECT uf.*, u.username, u.role
                FROM user_feedback uf
                LEFT JOIN users u ON uf.user_id = u.id
                WHERE uf.user_id = ?
                ORDER BY uf.created_at DESC
            ");
            $stmt->execute([$user_id]);
        }
        $feedback_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching feedback: " . $e->getMessage());
    }
}

// Stats
$stats = ['total' => 0, 'pending' => 0, 'reviewed' => 0, 'resolved' => 0, 'dismissed' => 0];
foreach ($feedback_list as $fb) {
    $stats['total']++;
    if (isset($stats[$fb['status']])) {
        $stats[$fb['status']]++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Panel - Spencer's Website v<?php echo defined('SITE_VERSION') ? SITE_VERSION : '7.0'; ?></title>
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/assets/images/favicon.webp">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
    <style>
        :root {
            --bg-dark: #0f172a;
            --card-bg: rgba(255, 255, 255, 0.05);
            --card-border: rgba(255, 255, 255, 0.1);
            --accent-gradient: linear-gradient(135deg, #f97316, #14b8a6);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;

            --status-pending: #eab308;
            --status-reviewed: #3b82f6;
            --status-resolved: #10b981;
            --status-dismissed: #6b7280;

            --role-admin: #ef4444;
            --role-contributor: #f59e0b;
            --role-designer: #ec4899;
            --role-user: #3b82f6;
            --role-community: #10b981;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }

        .container { max-width: 1100px; margin: 0 auto; }

        /* ── Header ────────────────────────────────────────────── */
        .header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 2rem 0;
            border-bottom: 1px solid var(--card-border);
        }

        .header h1 {
            font-size: 2.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, #a855f7, #14b8a6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .header p { color: var(--text-secondary); font-size: 1.15rem; }

        .header-meta {
            margin-top: 1rem;
            color: var(--text-secondary);
        }

        .header-meta strong { color: var(--text-primary); }

        /* ── Stats Grid ────────────────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
            border: 1px solid var(--card-border);
            transition: all 0.3s ease;
        }

        .stat-card:hover { transform: translateY(-3px); border-color: rgba(168, 85, 247, 0.4); }

        .stat-number { font-size: 2.25rem; font-weight: 800; margin-bottom: 0.25rem; }
        .stat-number.pending  { color: var(--status-pending); }
        .stat-number.reviewed { color: var(--status-reviewed); }
        .stat-number.resolved { color: var(--status-resolved); }
        .stat-number.dismissed { color: var(--status-dismissed); }
        .stat-label { color: var(--text-secondary); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; }

        /* ── Glassmorphism Card ────────────────────────────────── */
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .glass-card:hover {
            border-color: rgba(168, 85, 247, 0.25);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        .glass-card h2 {
            font-size: 1.4rem;
            margin-bottom: 1.5rem;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* ── Form Styles ──────────────────────────────────────── */
        .form-group { margin-bottom: 1.25rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #e2e8f0; }

        .form-control {
            width: 100%;
            padding: 0.85rem;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            background: rgba(15, 23, 42, 0.8);
            color: white;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #a855f7;
            box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.15);
        }

        textarea.form-control { min-height: 140px; resize: vertical; }

        .counter-row {
            display: flex;
            justify-content: space-between;
            margin-top: 0.4rem;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .counter-row .counter { transition: color 0.2s; }
        .counter-row .counter.warning { color: #eab308; }
        .counter-row .counter.danger  { color: #ef4444; }
        .counter-row .counter.ok      { color: #10b981; }

        .submission-limit-info {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            background: rgba(168, 85, 247, 0.15);
            color: #c084fc;
            margin-bottom: 1rem;
        }

        /* ── Buttons ──────────────────────────────────────────── */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 1rem;
            font-family: inherit;
        }

        .btn-submit {
            background: var(--accent-gradient);
            color: white;
            width: 100%;
            justify-content: center;
            font-size: 1.05rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(249, 115, 22, 0.3);
        }

        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid rgba(255, 255, 255, 0.2);
            color: white;
        }

        .btn-outline:hover {
            border-color: #a855f7;
            background: rgba(168, 85, 247, 0.1);
        }

        .btn-sm { padding: 0.45rem 0.9rem; font-size: 0.85rem; border-radius: 8px; }

        .btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .btn-danger:hover { box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); }

        .btn-primary { background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; }
        .btn-primary:hover { box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3); }

        .btn-teal { background: linear-gradient(135deg, #14b8a6, #0d9488); color: white; }
        .btn-teal:hover { box-shadow: 0 4px 15px rgba(20, 184, 166, 0.3); }

        /* ── Feedback Cards ───────────────────────────────────── */
        .feedback-list { display: flex; flex-direction: column; gap: 1.25rem; }

        .feedback-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 1.5rem;
            border-left: 4px solid var(--status-pending);
            transition: all 0.3s ease;
        }

        .feedback-card:hover {
            transform: translateX(4px);
            background: rgba(255, 255, 255, 0.07);
        }

        .feedback-card.status-pending  { border-left-color: var(--status-pending); }
        .feedback-card.status-reviewed { border-left-color: var(--status-reviewed); }
        .feedback-card.status-resolved { border-left-color: var(--status-resolved); }
        .feedback-card.status-dismissed { border-left-color: var(--status-dismissed); }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .feedback-user {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }

        .feedback-user .role-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }

        .feedback-meta {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .badge {
            padding: 0.2rem 0.7rem;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .badge-pending   { background: rgba(234, 179, 8, 0.15);  color: var(--status-pending); }
        .badge-reviewed  { background: rgba(59, 130, 246, 0.15); color: var(--status-reviewed); }
        .badge-resolved  { background: rgba(16, 185, 129, 0.15); color: var(--status-resolved); }
        .badge-dismissed { background: rgba(107, 114, 128, 0.15); color: var(--status-dismissed); }

        .badge-role {
            font-size: 0.7rem;
            padding: 0.15rem 0.55rem;
        }

        .feedback-content {
            color: #cbd5e1;
            line-height: 1.7;
            font-size: 0.95rem;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .admin-response-box {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(20, 184, 166, 0.08);
            border-radius: 10px;
            border-left: 3px solid #14b8a6;
        }

        .admin-response-box strong {
            color: #14b8a6;
            font-size: 0.85rem;
        }

        .admin-response-box .response-text {
            color: var(--text-secondary);
            margin-top: 0.5rem;
            white-space: pre-wrap;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .feedback-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 0.8rem;
            color: var(--text-muted);
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .feedback-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* ── Admin Respond Form ───────────────────────────────── */
        .admin-respond-form {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(99, 102, 241, 0.06);
            border-radius: 10px;
            border: 1px solid rgba(99, 102, 241, 0.15);
            display: none;
        }

        .admin-respond-form textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            background: rgba(15, 23, 42, 0.6);
            color: white;
            font-family: inherit;
            font-size: 0.9rem;
            min-height: 80px;
            resize: vertical;
            margin-bottom: 0.75rem;
        }

        .admin-respond-form textarea:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15);
        }

        .admin-respond-form .respond-row {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .admin-respond-form select {
            padding: 0.45rem 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            background: rgba(15, 23, 42, 0.6);
            color: white;
            font-family: inherit;
            font-size: 0.85rem;
        }

        .admin-respond-form select:focus {
            outline: none;
            border-color: #6366f1;
        }

        /* ── Modal ────────────────────────────────────────────── */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal.show { display: flex; }

        .modal-content {
            background: rgba(30, 41, 59, 0.97);
            backdrop-filter: blur(20px);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 2rem;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--card-border);
        }

        .modal-header h3 { font-size: 1.2rem; color: white; }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.2s;
        }

        .modal-close:hover { color: white; }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--card-border);
        }

        /* ── Notification ─────────────────────────────────────── */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.35);
            z-index: 10001;
            transform: translateX(420px);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-weight: 600;
            max-width: 400px;
        }

        .notification.show { transform: translateX(0); }
        .notification.error { background: linear-gradient(135deg, #ef4444, #dc2626); }

        /* ── Empty State ──────────────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }

        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.4; }
        .empty-state h3 { color: var(--text-secondary); margin-bottom: 0.5rem; }

        /* ── Navigation ───────────────────────────────────────── */
        .navigation {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
            padding-bottom: 2rem;
            flex-wrap: wrap;
        }

        /* ── Limit Reached Banner ─────────────────────────────── */
        .limit-banner {
            padding: 1rem 1.5rem;
            background: rgba(234, 179, 8, 0.1);
            border: 1px solid rgba(234, 179, 8, 0.25);
            border-radius: 10px;
            color: #eab308;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.95rem;
        }

        /* ── Responsive ───────────────────────────────────────── */
        @media (max-width: 768px) {
            .header h1 { font-size: 2rem; }
            .glass-card { padding: 1.25rem; }
            .feedback-header { flex-direction: column; }
            .feedback-footer { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/identity_bar.php'; ?>
    <div class="container">
        <!-- ── Header ─────────────────────────────────────────── -->
        <header class="header">
            <h1><i class="fas fa-comment-dots"></i> Feedback Panel</h1>
            <p>Share your thoughts and help us improve Spencer's Website</p>
            <div class="header-meta">
                Welcome, <strong><?php echo htmlspecialchars($username); ?></strong>!
                Your role: <span class="badge badge-role" style="background: <?php echo ($role_colors[$user_role] ?? '#6b7280') . '22'; ?>; color: <?php echo $role_colors[$user_role] ?? '#6b7280'; ?>;">
                    <?php echo htmlspecialchars(ucfirst($user_role)); ?>
                </span>
                <?php if ($can_view_all): ?>
                    <br><small style="color: var(--text-muted);">You can view all user feedback</small>
                <?php endif; ?>
            </div>
        </header>

        <!-- ── Stats ──────────────────────────────────────────── -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" style="color: #c084fc;"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-number pending"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number reviewed"><?php echo $stats['reviewed']; ?></div>
                <div class="stat-label">Reviewed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number resolved"><?php echo $stats['resolved']; ?></div>
                <div class="stat-label">Resolved</div>
            </div>
            <div class="stat-card">
                <div class="stat-number dismissed"><?php echo $stats['dismissed']; ?></div>
                <div class="stat-label">Dismissed</div>
            </div>
        </div>

        <!-- ── Submission Form ────────────────────────────────── -->
        <div class="glass-card">
            <h2><i class="fas fa-pen-to-square"></i> Submit Feedback</h2>

            <?php if ($can_submit): ?>
                <div class="submission-limit-info">
                    <i class="fas fa-info-circle"></i>
                    <?php echo $submissions_remaining; ?> submission<?php echo $submissions_remaining !== 1 ? 's' : ''; ?> remaining (max 5)
                </div>

                <form id="feedbackForm">
                    <?php echo csrfField(); ?>
                    <div class="form-group">
                        <label class="form-label">Your Feedback <span style="color: var(--text-muted); font-weight: 400;">(400 word limit)</span></label>
                        <textarea
                            class="form-control"
                            id="feedbackContent"
                            name="content"
                            placeholder="Tell us what you think! Share ideas, report issues, or suggest improvements..."
                            required
                        ></textarea>
                        <div class="counter-row">
                            <span class="counter" id="charCounter">0 characters</span>
                            <span class="counter" id="wordCounter">0 / 400 words</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-submit" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> Submit Feedback
                    </button>
                </form>
            <?php else: ?>
                <div class="limit-banner">
                    <i class="fas fa-exclamation-circle"></i>
                    You have reached the maximum of 5 feedback submissions. Thank you for your input!
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Feedback List ──────────────────────────────────── -->
        <div class="glass-card">
            <h2>
                <i class="fas fa-list-ul"></i>
                <?php echo $can_view_all ? 'All Feedback' : 'My Feedback'; ?>
                <span style="font-size: 0.8rem; font-weight: 400; color: var(--text-muted); margin-left: 0.5rem;">
                    (<?php echo count($feedback_list); ?> entr<?php echo count($feedback_list) !== 1 ? 'ies' : 'y'; ?>)
                </span>
            </h2>

            <?php if (empty($feedback_list)): ?>
                <div class="empty-state">
                    <i class="fas fa-comment-slash"></i>
                    <h3>No Feedback Yet</h3>
                    <p>Be the first to share your thoughts using the form above!</p>
                </div>
            <?php else: ?>
                <div class="feedback-list">
                    <?php foreach ($feedback_list as $fb):
                        $fb_username = htmlspecialchars($fb['username'] ?? 'Unknown');
                        $fb_role = $fb['role'] ?? 'community';
                        $fb_role_color = $role_colors[$fb_role] ?? '#6b7280';
                        $is_own = ($fb['user_id'] == $user_id);
                        $can_edit = ($is_admin || $is_own);
                        $status_class = 'status-' . $fb['status'];
                    ?>
                        <div class="feedback-card <?php echo htmlspecialchars($status_class); ?>" data-id="<?php echo (int)$fb['id']; ?>" id="feedback-<?php echo (int)$fb['id']; ?>">
                            <div class="feedback-header">
                                <div class="feedback-user">
                                    <span class="role-dot" style="background: <?php echo htmlspecialchars($fb_role_color); ?>"></span>
                                    <span style="color: <?php echo htmlspecialchars($fb_role_color); ?>"><?php echo $fb_username; ?></span>
                                    <span class="badge badge-role" style="background: <?php echo htmlspecialchars($fb_role_color . '22'); ?>; color: <?php echo htmlspecialchars($fb_role_color); ?>">
                                        <?php echo htmlspecialchars(ucfirst($fb_role)); ?>
                                    </span>
                                    <?php if ($is_own): ?>
                                        <span style="font-size: 0.75rem; color: var(--text-muted);">(you)</span>
                                    <?php endif; ?>
                                </div>
                                <div class="feedback-meta">
                                    <span class="badge badge-<?php echo htmlspecialchars($fb['status']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($fb['status'])); ?>
                                    </span>
                                    <span style="font-size: 0.75rem; color: var(--text-muted);">#<?php echo (int)$fb['id']; ?></span>
                                </div>
                            </div>

                            <div class="feedback-content"><?php echo nl2br(htmlspecialchars($fb['content'])); ?></div>

                            <?php if (!empty($fb['admin_response'])): ?>
                                <div class="admin-response-box">
                                    <strong><i class="fas fa-shield-halved"></i> Admin Response</strong>
                                    <div class="response-text"><?php echo nl2br(htmlspecialchars($fb['admin_response'])); ?></div>
                                </div>
                            <?php endif; ?>

                            <!-- Admin respond form (hidden by default) -->
                            <?php if ($is_admin): ?>
                                <div class="admin-respond-form" id="respond-form-<?php echo $fb['id']; ?>">
                                    <textarea id="respond-text-<?php echo $fb['id']; ?>" placeholder="Write your response..."></textarea>
                                    <div class="respond-row">
                                        <select id="respond-status-<?php echo $fb['id']; ?>">
                                            <option value="">Keep current status</option>
                                            <option value="reviewed">Reviewed</option>
                                            <option value="resolved">Resolved</option>
                                            <option value="dismissed">Dismissed</option>
                                            <option value="pending">Pending</option>
                                        </select>
                                        <button class="btn btn-sm btn-teal" onclick="submitAdminResponse(<?php echo $fb['id']; ?>)">
                                            <i class="fas fa-reply"></i> Send Response
                                        </button>
                                        <button class="btn btn-sm btn-outline" onclick="toggleRespondForm(<?php echo $fb['id']; ?>)">
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="feedback-footer">
                                <span>
                                    <i class="far fa-clock"></i>
                                    <?php echo date('M j, Y \a\t g:i A', strtotime($fb['created_at'])); ?>
                                    <?php if ($fb['updated_at'] !== $fb['created_at']): ?>
                                        <span style="margin-left: 0.5rem; opacity: 0.6;">(edited)</span>
                                    <?php endif; ?>
                                </span>

                                <div class="feedback-actions">
                                    <?php if ($is_admin): ?>
                                        <button class="btn btn-sm btn-teal" onclick="toggleRespondForm(<?php echo $fb['id']; ?>)" title="Respond">
                                            <i class="fas fa-reply"></i> Respond
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($can_edit): ?>
                                        <button class="btn btn-sm btn-outline" onclick="openEditModal(<?php echo $fb['id']; ?>)" title="Edit">
                                            <i class="fas fa-pen"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteFeedback(<?php echo $fb['id']; ?>)" title="Delete">
                                            <i class="fas fa-trash-can"></i> Delete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Navigation ─────────────────────────────────────── -->
        <div class="navigation">
            <a href="main.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Main Site</a>
            <?php if ($is_admin): ?>
                <a href="admin.php" class="btn btn-primary"><i class="fas fa-cog"></i> Admin Panel</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Edit Modal ─────────────────────────────────────────── -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-pen-to-square"></i> Edit Feedback</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form id="editForm">
                <input type="hidden" id="editFeedbackId">
                <div class="form-group">
                    <label class="form-label">Content <span style="color: var(--text-muted); font-weight: 400;">(400 word limit)</span></label>
                    <textarea class="form-control" id="editContent" required></textarea>
                    <div class="counter-row">
                        <span class="counter" id="editCharCounter">0 characters</span>
                        <span class="counter" id="editWordCounter">0 / 400 words</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-submit" style="width: auto;">
                        <i class="fas fa-check"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Tracking / Fingerprint Scripts ─────────────────────── -->
    <script src="js/tracking.js?v=7.0" defer></script>
    <script src="js/fingerprint.js" defer></script>

    <script>
        // ── CSRF token ───────────────────────────────────────────
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '<?php echo generateCsrfToken(); ?>';

        // ── Feedback data for editing ────────────────────────────
        const feedbackData = <?php echo json_encode(array_map(function($fb) {
            return [
                'id'      => $fb['id'],
                'content' => $fb['content'],
            ];
        }, $feedback_list)); ?>;

        // ── Word / character counter helpers ──────────────────────
        function countWords(text) {
            const trimmed = text.trim();
            if (!trimmed) return 0;
            return trimmed.split(/\s+/).length;
        }

        function updateCounters(textarea, charEl, wordEl) {
            const text = textarea.value;
            const chars = text.length;
            const words = countWords(text);

            charEl.textContent = chars + ' character' + (chars !== 1 ? 's' : '');

            wordEl.textContent = words + ' / 400 words';
            wordEl.className = 'counter';
            if (words > 400) {
                wordEl.classList.add('danger');
            } else if (words > 350) {
                wordEl.classList.add('warning');
            } else {
                wordEl.classList.add('ok');
            }
        }

        // ── Main form counters ───────────────────────────────────
        const feedbackContent = document.getElementById('feedbackContent');
        const charCounter = document.getElementById('charCounter');
        const wordCounter = document.getElementById('wordCounter');

        if (feedbackContent && charCounter && wordCounter) {
            feedbackContent.addEventListener('input', () => updateCounters(feedbackContent, charCounter, wordCounter));
        }

        // ── Edit modal counters ──────────────────────────────────
        const editContent = document.getElementById('editContent');
        const editCharCounter = document.getElementById('editCharCounter');
        const editWordCounter = document.getElementById('editWordCounter');

        if (editContent && editCharCounter && editWordCounter) {
            editContent.addEventListener('input', () => updateCounters(editContent, editCharCounter, editWordCounter));
        }

        // ── Submit feedback ──────────────────────────────────────
        document.getElementById('feedbackForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();

            const content = feedbackContent.value.trim();
            if (!content) {
                showNotification('Please enter your feedback.', true);
                return;
            }

            const words = countWords(content);
            if (words > 400) {
                showNotification('Feedback exceeds 400 word limit (' + words + ' words).', true);
                return;
            }

            const btn = document.getElementById('submitBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            btn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('ajax', '1');
                formData.append('csrf_token', csrfToken);
                formData.append('action', 'submit_feedback');
                formData.append('content', content);

                const response = await fetch('feedback.php', { method: 'POST', credentials: 'same-origin', body: formData });
                const data = await response.json();

                if (data.success) {
                    showNotification(data.message, false);
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showNotification(data.message, true);
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Feedback';
                    btn.disabled = false;
                }
            } catch (error) {
                showNotification('Error submitting feedback. Please try again.', true);
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Feedback';
                btn.disabled = false;
            }
        });

        // ── Edit feedback ────────────────────────────────────────
        function openEditModal(id) {
            const fb = feedbackData.find(f => f.id == id);
            if (!fb) return;

            document.getElementById('editFeedbackId').value = fb.id;
            editContent.value = fb.content;
            updateCounters(editContent, editCharCounter, editWordCounter);
            document.getElementById('editModal').classList.add('show');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        document.getElementById('editForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();

            const id = document.getElementById('editFeedbackId').value;
            const content = editContent.value.trim();

            if (!content) {
                showNotification('Content cannot be empty.', true);
                return;
            }

            const words = countWords(content);
            if (words > 400) {
                showNotification('Feedback exceeds 400 word limit (' + words + ' words).', true);
                return;
            }

            try {
                const formData = new FormData();
                formData.append('ajax', '1');
                formData.append('csrf_token', csrfToken);
                formData.append('action', 'edit_feedback');
                formData.append('feedback_id', id);
                formData.append('content', content);

                const response = await fetch('feedback.php', { method: 'POST', credentials: 'same-origin', body: formData });
                const data = await response.json();

                if (data.success) {
                    showNotification(data.message, false);
                    closeEditModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message, true);
                }
            } catch (error) {
                showNotification('Error updating feedback.', true);
            }
        });

        // ── Delete feedback ──────────────────────────────────────
        async function deleteFeedback(id) {
            if (!confirm('Are you sure you want to delete this feedback? This cannot be undone.')) return;

            try {
                const formData = new FormData();
                formData.append('ajax', '1');
                formData.append('csrf_token', csrfToken);
                formData.append('action', 'delete_feedback');
                formData.append('feedback_id', id);

                const response = await fetch('feedback.php', { method: 'POST', credentials: 'same-origin', body: formData });
                const data = await response.json();

                if (data.success) {
                    showNotification(data.message, false);
                    const card = document.getElementById('feedback-' + id);
                    if (card) {
                        card.style.transition = 'all 0.4s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'translateX(30px)';
                        setTimeout(() => card.remove(), 400);
                    }
                } else {
                    showNotification(data.message, true);
                }
            } catch (error) {
                showNotification('Error deleting feedback.', true);
            }
        }

        // ── Admin respond ────────────────────────────────────────
        function toggleRespondForm(id) {
            const form = document.getElementById('respond-form-' + id);
            if (form) {
                form.style.display = form.style.display === 'block' ? 'none' : 'block';
            }
        }

        async function submitAdminResponse(id) {
            const text = document.getElementById('respond-text-' + id)?.value.trim();
            const status = document.getElementById('respond-status-' + id)?.value;

            if (!text) {
                showNotification('Please enter a response.', true);
                return;
            }

            try {
                const formData = new FormData();
                formData.append('ajax', '1');
                formData.append('csrf_token', csrfToken);
                formData.append('action', 'admin_respond');
                formData.append('feedback_id', id);
                formData.append('admin_response', text);
                if (status) formData.append('status', status);

                const response = await fetch('feedback.php', { method: 'POST', credentials: 'same-origin', body: formData });
                const data = await response.json();

                if (data.success) {
                    showNotification(data.message, false);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message, true);
                }
            } catch (error) {
                showNotification('Error submitting response.', true);
            }
        }

        // ── Notification ─────────────────────────────────────────
        function showNotification(message, isError) {
            const existing = document.querySelector('.notification');
            if (existing) existing.remove();

            const n = document.createElement('div');
            n.className = 'notification' + (isError ? ' error' : '');
            n.innerHTML = '<i class="fas fa-' + (isError ? 'exclamation-triangle' : 'check-circle') + '"></i> ' + message;
            document.body.appendChild(n);

            requestAnimationFrame(() => {
                requestAnimationFrame(() => n.classList.add('show'));
            });

            setTimeout(() => {
                n.classList.remove('show');
                setTimeout(() => n.remove(), 400);
            }, 4000);
        }

        // ── Modal close handlers ─────────────────────────────────
        document.getElementById('editModal')?.addEventListener('click', e => {
            if (e.target.id === 'editModal') closeEditModal();
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeEditModal();
        });

        // ── Draft auto-save ──────────────────────────────────────
        let draftTimeout;
        function autoSaveDraft() {
            clearTimeout(draftTimeout);
            draftTimeout = setTimeout(() => {
                const content = feedbackContent?.value;
                if (content) {
                    localStorage.setItem('feedbackDraft', JSON.stringify({
                        content: content,
                        timestamp: new Date().toISOString()
                    }));
                }
            }, 2000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Restore draft
            const draft = localStorage.getItem('feedbackDraft');
            if (draft && feedbackContent) {
                try {
                    const { content, timestamp } = JSON.parse(draft);
                    if (content && confirm('Restore draft from ' + new Date(timestamp).toLocaleString() + '?')) {
                        feedbackContent.value = content;
                        updateCounters(feedbackContent, charCounter, wordCounter);
                    }
                } catch (e) {}
            }

            feedbackContent?.addEventListener('input', autoSaveDraft);

            // Clear draft on submit
            document.getElementById('feedbackForm')?.addEventListener('submit', () => {
                localStorage.removeItem('feedbackDraft');
            });
        });
    </script>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
