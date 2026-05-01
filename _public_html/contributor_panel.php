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

// Initialize access control
$access_denied = false;

// Only allow contributors and admins
if ($_SESSION['role'] !== 'contributor' && $_SESSION['role'] !== 'admin') {
    $access_denied = true;
}

// Database connection
$db = null;
try {
    $db = (new Database())->getConnection();

    // Table schema is maintained by migrations/016_create_missing_tables.sql

    $db->exec("CREATE TABLE IF NOT EXISTS idea_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        idea_id INT NOT NULL,
        user_id INT NOT NULL,
        vote_type ENUM('up', 'down') DEFAULT 'up',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_vote (idea_id, user_id),
        INDEX idx_idea_id (idea_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh the page.']);
        exit;
    }

    if ($access_denied) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        // v5.2: Reply to admin feedback
        if ($action === 'reply_to_admin') {
            $idea_id = intval($_POST['idea_id'] ?? 0);
            $reply_text = trim($_POST['reply_text'] ?? '');

            if (empty($reply_text)) {
                echo json_encode(['success' => false, 'message' => 'Reply text is required']);
                exit;
            }

            // Verify ownership
            $stmt = $db->prepare("SELECT id, title, admin_notes FROM contributor_ideas WHERE id = ? AND user_id = ?");
            $stmt->execute([$idea_id, $_SESSION['user_id']]);
            $idea = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$idea) {
                echo json_encode(['success' => false, 'message' => 'Idea not found or access denied']);
                exit;
            }

            // Append reply to admin_notes
            $existingNotes = $idea['admin_notes'] ?? '';
            $timestamp = date('M j, Y g:i A');
            $username = htmlspecialchars($_SESSION['username']);
            $reply_text = htmlspecialchars($reply_text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $newNotes = $existingNotes . "\n\n--- Reply from {$username} ({$timestamp}) ---\n" . $reply_text;

            $stmt = $db->prepare("UPDATE contributor_ideas SET admin_notes = ? WHERE id = ?");
            $stmt->execute([trim($newNotes), $idea_id]);

            echo json_encode(['success' => true, 'message' => 'Reply sent successfully']);
            exit;
        }

        if ($action === 'delete_idea') {
            $idea_id = intval($_POST['idea_id'] ?? 0);

            // Verify ownership
            $stmt = $db->prepare("SELECT id, title FROM contributor_ideas WHERE id = ? AND user_id = ?");
            $stmt->execute([$idea_id, $_SESSION['user_id']]);
            $idea = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$idea) {
                echo json_encode(['success' => false, 'message' => 'Idea not found']);
                exit;
            }

            // Delete votes first
            $db->prepare("DELETE FROM idea_votes WHERE idea_id = ?")->execute([$idea_id]);

            // Delete idea
            $db->prepare("DELETE FROM contributor_ideas WHERE id = ? AND user_id = ?")->execute([$idea_id, $_SESSION['user_id']]);

            // Log deletion
            $logStmt = $db->prepare("INSERT INTO system_logs (level, message, user_id) VALUES ('info', ?, ?)");
            $logStmt->execute(["Idea deleted: '{$idea['title']}' by {$_SESSION['username']}", $_SESSION['user_id']]);

            echo json_encode(['success' => true, 'message' => 'Idea deleted successfully']);
            exit;
        }

        if ($action === 'update_idea') {
            $idea_id = intval($_POST['idea_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category = $_POST['category'] ?? 'improvement';
            $priority = $_POST['priority'] ?? 'medium';
            $estimated_effort = $_POST['estimated_effort'] ?? 'small';

            // Verify ownership and pending status
            $stmt = $db->prepare("SELECT id, status FROM contributor_ideas WHERE id = ? AND user_id = ?");
            $stmt->execute([$idea_id, $_SESSION['user_id']]);
            $idea = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$idea) {
                echo json_encode(['success' => false, 'message' => 'Idea not found']);
                exit;
            }

            if ($idea['status'] !== 'pending') {
                echo json_encode(['success' => false, 'message' => 'Can only edit pending ideas']);
                exit;
            }

            if (strlen($title) < 5 || strlen($title) > 255) {
                echo json_encode(['success' => false, 'message' => 'Title must be 5-255 characters']);
                exit;
            }

            if (strlen($description) < 20) {
                echo json_encode(['success' => false, 'message' => 'Description must be at least 20 characters']);
                exit;
            }

            $stmt = $db->prepare("UPDATE contributor_ideas SET title = ?, description = ?, category = ?, priority = ?, estimated_effort = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$title, $description, $category, $priority, $estimated_effort, $idea_id, $_SESSION['user_id']]);

            // Log update
            $logStmt = $db->prepare("INSERT INTO system_logs (level, message, user_id) VALUES ('info', ?, ?)");
            $logStmt->execute(["Idea updated: ID {$idea_id} by {$_SESSION['username']}", $_SESSION['user_id']]);

            echo json_encode(['success' => true, 'message' => 'Idea updated successfully']);
            exit;
        }

        if ($action === 'vote_idea') {
            $idea_id = intval($_POST['idea_id'] ?? 0);

            // Check if already voted
            $stmt = $db->prepare("SELECT id FROM idea_votes WHERE idea_id = ? AND user_id = ?");
            $stmt->execute([$idea_id, $_SESSION['user_id']]);

            if ($stmt->fetch()) {
                // Remove vote
                $db->prepare("DELETE FROM idea_votes WHERE idea_id = ? AND user_id = ?")->execute([$idea_id, $_SESSION['user_id']]);
                echo json_encode(['success' => true, 'voted' => false, 'message' => 'Vote removed']);
            } else {
                // Add vote
                $db->prepare("INSERT INTO idea_votes (idea_id, user_id, vote_type) VALUES (?, ?, 'up')")->execute([$idea_id, $_SESSION['user_id']]);
                echo json_encode(['success' => true, 'voted' => true, 'message' => 'Vote added']);
            }
            exit;
        }

    } catch (Exception $e) {
        error_log("Contributor panel error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// Handle form submission
if (!$access_denied && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $_SESSION['flash_message'] = "Invalid request. Please refresh the page and try again.";
        $_SESSION['flash_type'] = 'error';
        header("Location: contributor_panel.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'submit_idea') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category = $_POST['category'] ?? 'improvement';
            $priority = $_POST['priority'] ?? 'medium';
            $estimated_effort = $_POST['estimated_effort'] ?? 'small';

            if (strlen($title) < 5) {
                $_SESSION['flash_message'] = "Title must be at least 5 characters.";
                $_SESSION['flash_type'] = 'error';
            } elseif (strlen($title) > 255) {
                $_SESSION['flash_message'] = "Title must be less than 255 characters.";
                $_SESSION['flash_type'] = 'error';
            } elseif (strlen($description) < 20) {
                $_SESSION['flash_message'] = "Description must be at least 20 characters.";
                $_SESSION['flash_type'] = 'error';
            } else {
                $stmt = $db->prepare("INSERT INTO contributor_ideas (user_id, title, description, category, priority, estimated_effort) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $title, $description, $category, $priority, $estimated_effort]);
                $new_idea_id = $db->lastInsertId();

                // Log the submission
                $logStmt = $db->prepare("INSERT INTO system_logs (level, message, user_id) VALUES ('info', ?, ?)");
                $logStmt->execute(["New idea submitted: '{$title}' (ID: {$new_idea_id})", $_SESSION['user_id']]);

                $_SESSION['flash_message'] = "Idea submitted successfully! Thank you for your contribution.";
                $_SESSION['flash_type'] = 'success';

                // Clear draft
                echo "<script>localStorage.removeItem('ideaDraft');</script>";
            }
        }

        // v7.0: Announcement creation removed — admin-only

        header("Location: contributor_panel.php");
        exit;

    } catch (Exception $e) {
        error_log("Contributor panel error: " . $e->getMessage());
        $_SESSION['flash_message'] = "Failed to process request: " . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
}

// Get user's ideas
$user_ideas = [];
$idea_stats = ['total' => 0, 'pending' => 0, 'under_review' => 0, 'approved' => 0, 'completed' => 0, 'rejected' => 0];

if (!$access_denied && $db) {
    try {
        $stmt = $db->prepare("
            SELECT ci.*,
                   COUNT(iv.id) as vote_count,
                   (SELECT COUNT(*) FROM idea_votes WHERE idea_id = ci.id AND user_id = ?) as user_voted
            FROM contributor_ideas ci
            LEFT JOIN idea_votes iv ON ci.id = iv.idea_id AND iv.vote_type = 'up'
            WHERE ci.user_id = ?
            GROUP BY ci.id
            ORDER BY
                FIELD(ci.status, 'pending', 'under_review', 'approved', 'completed', 'rejected'),
                ci.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        $user_ideas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($user_ideas as $idea) {
            $idea_stats['total']++;
            if (isset($idea_stats[$idea['status']])) {
                $idea_stats[$idea['status']]++;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching ideas: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contributor Panel - Spencer's Website</title>
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/assets/images/favicon.webp">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: var(--light);
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }

        .container { max-width: 1400px; margin: 0 auto; }

        .header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 2rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header h1 {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .header p { color: #94a3b8; font-size: 1.2rem; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(30, 41, 59, 0.7);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .stat-card:hover { transform: translateY(-3px); border-color: var(--primary); }

        .stat-number { font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem; }
        .stat-number.pending { color: var(--warning); }
        .stat-number.approved { color: var(--secondary); }
        .stat-number.completed { color: var(--info); }
        .stat-number.rejected { color: var(--danger); }

        .stat-label { color: #94a3b8; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; }

        .panel-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 3rem; }
        @media (max-width: 968px) { .panel-grid { grid-template-columns: 1fr; } .header h1 { font-size: 2rem; } }

        .card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 2rem;
            transition: all 0.3s ease;
        }

        .card:hover { border-color: rgba(99, 102, 241, 0.3); box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2); }
        .card h2 { font-size: 1.5rem; margin-bottom: 1.5rem; color: white; display: flex; align-items: center; gap: 0.5rem; }

        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #e2e8f0; }
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(15, 23, 42, 0.8);
            color: white;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
        textarea.form-control { min-height: 120px; resize: vertical; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 1rem;
        }

        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3); }
        .btn-success { background: linear-gradient(135deg, var(--secondary), #059669); color: white; }
        .btn-danger { background: linear-gradient(135deg, var(--danger), #dc2626); color: white; }
        .btn-outline { background: transparent; border: 2px solid rgba(255, 255, 255, 0.2); color: white; }
        .btn-outline:hover { border-color: var(--primary); background: rgba(99, 102, 241, 0.1); }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.875rem; }

        .idea-list { display: flex; flex-direction: column; gap: 1rem; max-height: 600px; overflow-y: auto; }

        .idea-item {
            background: rgba(15, 23, 42, 0.5);
            border-radius: 8px;
            padding: 1.5rem;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }

        .idea-item:hover { transform: translateX(5px); background: rgba(15, 23, 42, 0.7); }
        .idea-item.status-approved { border-left-color: var(--secondary); }
        .idea-item.status-rejected { border-left-color: var(--danger); }
        .idea-item.status-pending { border-left-color: var(--warning); }
        .idea-item.status-completed { border-left-color: var(--info); }

        .idea-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; }
        .idea-title { font-size: 1.1rem; font-weight: 600; color: white; margin-bottom: 0.5rem; }
        .idea-meta { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.5rem; }

        .badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .badge-primary { background: rgba(99, 102, 241, 0.2); color: var(--primary); }
        .badge-success { background: rgba(16, 185, 129, 0.2); color: var(--secondary); }
        .badge-warning { background: rgba(245, 158, 11, 0.2); color: var(--warning); }
        .badge-info { background: rgba(59, 130, 246, 0.2); color: var(--info); }
        .badge-danger { background: rgba(239, 68, 68, 0.2); color: var(--danger); }

        .idea-description { color: #94a3b8; line-height: 1.5; font-size: 0.9rem; }
        .idea-actions { display: flex; gap: 0.5rem; margin-top: 1rem; }

        .idea-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.8rem;
            color: #64748b;
        }

        .admin-feedback {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 6px;
            border-left: 3px solid var(--info);
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            background: var(--secondary);
            color: white;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            z-index: 10000;
            transform: translateX(400px);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notification.show { transform: translateX(0); }
        .notification.error { background: var(--danger); }

        .empty-state { text-align: center; padding: 3rem; color: #64748b; }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }

        .navigation { display: flex; justify-content: center; gap: 1rem; margin-top: 2rem; flex-wrap: wrap; }

        .access-denied { text-align: center; padding: 4rem 2rem; max-width: 600px; margin: 0 auto; }
        .access-denied-icon { font-size: 4rem; color: #ef4444; margin-bottom: 2rem; }
        .access-denied h2 { font-size: 2rem; margin-bottom: 1rem; color: white; }
        .access-denied p { color: #94a3b8; font-size: 1.1rem; margin-bottom: 2rem; line-height: 1.6; }
        .current-role { background: rgba(239, 68, 68, 0.2); color: #ef4444; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; margin: 1rem 0; display: inline-block; }

        .char-counter { text-align: right; font-size: 0.8rem; color: #64748b; margin-top: 0.25rem; }
        .char-counter.warning { color: var(--warning); }
        .char-counter.danger { color: var(--danger); }

        .priority-indicator { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 5px; }
        .priority-high { background: #ef4444; }
        .priority-medium { background: #f59e0b; }
        .priority-low { background: #10b981; }
        .priority-critical { background: #dc2626; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); z-index: 10000; justify-content: center; align-items: center; padding: 20px; }
        .modal.show { display: flex; }
        .modal-content { background: rgba(30, 41, 59, 0.95); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 2rem; max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .modal-header h3 { font-size: 1.25rem; color: white; }
        .modal-close { background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; }
        .modal-close:hover { color: white; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.1); }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/identity_bar.php'; ?>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-lightbulb"></i> Contributor Panel</h1>
            <p>Share your ideas and help improve the website!</p>
            <?php if (!$access_denied): ?>
                <div style="margin-top: 1rem; color: #94a3b8;">
                    Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>!
                    Your role: <span class="badge badge-primary"><?php echo htmlspecialchars($_SESSION['role']); ?></span>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <br><small>Admin access granted to contributor features</small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </header>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="notification show <?php echo $_SESSION['flash_type'] ?? ''; ?>">
                <i class="fas fa-<?php echo ($_SESSION['flash_type'] ?? '') === 'error' ? 'exclamation-triangle' : 'check-circle'; ?>"></i>
                <?php
                echo htmlspecialchars($_SESSION['flash_message']);
                unset($_SESSION['flash_message']);
                unset($_SESSION['flash_type']);
                ?>
            </div>
        <?php endif; ?>

        <?php if ($access_denied): ?>
            <div class="access-denied">
                <div class="access-denied-icon"><i class="fas fa-ban"></i></div>
                <h2>Access Denied</h2>
                <p>Sorry, this panel is exclusively for users with the <strong>Contributor</strong> role.</p>
                <div class="current-role">Your current role: <strong><?php echo htmlspecialchars($_SESSION['role']); ?></strong></div>
                <p>If you believe this is a mistake, please contact the administrator to request contributor access.</p>
                <div class="navigation">
                    <a href="main.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Main Site</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $idea_stats['total']; ?></div>
                    <div class="stat-label">Total Ideas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number pending"><?php echo $idea_stats['pending']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number approved"><?php echo $idea_stats['approved']; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number completed"><?php echo $idea_stats['completed']; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>

            <div class="panel-grid">
                <!-- Submit Idea Form -->
                <div class="card">
                    <h2><i class="fas fa-plus-circle"></i> Submit New Idea</h2>
                    <form method="POST" id="ideaForm">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="submit_idea">

                        <div class="form-group">
                            <label class="form-label">Idea Title *</label>
                            <input type="text" name="title" class="form-control" placeholder="Brief description of your idea" required maxlength="255" id="titleInput">
                            <div class="char-counter" id="titleCounter">255 characters remaining</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Detailed Description *</label>
                            <textarea name="description" class="form-control" placeholder="Describe your idea in detail. What problem does it solve?" required id="descriptionInput" minlength="20"></textarea>
                            <div class="char-counter" id="descriptionCounter">0 characters (min 20)</div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-control">
                                    <option value="improvement">Improvement</option>
                                    <option value="feature">New Feature</option>
                                    <option value="bug_fix">Bug Fix</option>
                                    <option value="design">Design Update</option>
                                    <option value="content">Content Addition</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-control">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Estimated Effort</label>
                            <select name="estimated_effort" class="form-control">
                                <option value="quick">Quick (1-2 hours)</option>
                                <option value="small" selected>Small (1 day)</option>
                                <option value="medium">Medium (2-3 days)</option>
                                <option value="large">Large (1 week)</option>
                                <option value="xlarge">X-Large (2+ weeks)</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;" id="submitButton">
                            <i class="fas fa-paper-plane"></i> Submit Idea
                        </button>
                    </form>

                    <div style="margin-top: 1.5rem; padding: 1rem; background: rgba(59, 130, 246, 0.1); border-radius: 8px; border-left: 3px solid var(--info);">
                        <strong><i class="fas fa-info-circle"></i> Tips:</strong>
                        <ul style="margin-top: 0.5rem; margin-left: 1.5rem; color: #94a3b8; font-size: 0.9rem;">
                            <li>Be specific about what problem your idea solves</li>
                            <li>Include examples or mockups if possible</li>
                            <li>Choose accurate priority and effort estimates</li>
                        </ul>
                    </div>
                </div>

                <!-- My Ideas -->
                <div class="card">
                    <h2><i class="fas fa-list"></i> My Submitted Ideas</h2>

                    <?php if (empty($user_ideas)): ?>
                        <div class="empty-state">
                            <i class="fas fa-lightbulb"></i>
                            <h3>No Ideas Yet</h3>
                            <p>Submit your first idea using the form!</p>
                        </div>
                    <?php else: ?>
                        <div class="idea-list">
                            <?php foreach ($user_ideas as $idea): ?>
                                <div class="idea-item status-<?php echo $idea['status']; ?>" data-id="<?php echo $idea['id']; ?>">
                                    <div class="idea-header">
                                        <div style="flex: 1;">
                                            <div class="idea-title"><?php echo htmlspecialchars($idea['title']); ?></div>
                                            <div class="idea-meta">
                                                <span class="badge badge-<?php echo ['feature' => 'success', 'improvement' => 'primary', 'bug_fix' => 'danger', 'design' => 'info'][$idea['category']] ?? 'warning'; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $idea['category'])); ?>
                                                </span>
                                                <span class="badge badge-<?php echo ['critical' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'success'][$idea['priority']] ?? 'primary'; ?>">
                                                    <span class="priority-indicator priority-<?php echo $idea['priority']; ?>"></span>
                                                    <?php echo ucfirst($idea['priority']); ?>
                                                </span>
                                                <span class="badge badge-<?php echo ['approved' => 'success', 'rejected' => 'danger', 'completed' => 'info', 'under_review' => 'primary'][$idea['status']] ?? 'warning'; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $idea['status'])); ?>
                                                </span>
                                                <?php if ($idea['vote_count'] > 0): ?>
                                                    <span class="badge badge-info"><i class="fas fa-thumbs-up"></i> <?php echo $idea['vote_count']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="idea-description">
                                        <?php echo nl2br(htmlspecialchars(substr($idea['description'], 0, 200))); ?>
                                        <?php if (strlen($idea['description']) > 200): ?>...<?php endif; ?>
                                    </div>

                                    <?php if (!empty($idea['admin_notes'])): ?>
                                        <div class="admin-feedback">
                                            <strong><i class="fas fa-comment"></i> Admin Feedback:</strong>
                                            <div style="color: #94a3b8; margin-top: 0.5rem;">
                                                <?php echo nl2br(htmlspecialchars($idea['admin_notes'])); ?>
                                            </div>
                                        </div>
                                        <!-- v5.2: Reply to admin feedback -->
                                        <div class="reply-section" id="reply-section-<?php echo $idea['id']; ?>" style="margin-top: 0.75rem; padding: 0.75rem; background: rgba(139, 92, 246, 0.05); border-radius: 8px; border: 1px solid rgba(139, 92, 246, 0.15);">
                                            <button class="btn btn-sm btn-outline" onclick="toggleReplyForm(<?php echo $idea['id']; ?>)" style="margin-bottom: 0.5rem;">
                                                <i class="fas fa-reply"></i> Reply to Admin
                                            </button>
                                            <form id="reply-form-<?php echo $idea['id']; ?>" style="display: none;" onsubmit="submitReply(event, <?php echo $idea['id']; ?>, 'idea')">
                                                <textarea class="form-control" id="reply-text-<?php echo $idea['id']; ?>" placeholder="Write your reply to the admin's feedback..." rows="3" style="margin-bottom: 0.5rem; background: rgba(15, 23, 42, 0.5); border: 1px solid #334155; color: #e2e8f0; border-radius: 8px; padding: 0.75rem; width: 100%; resize: vertical;"></textarea>
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-paper-plane"></i> Send Reply
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($idea['status'] === 'pending'): ?>
                                        <div class="idea-actions">
                                            <button class="btn btn-sm btn-outline" onclick="editIdea(<?php echo $idea['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteIdea(<?php echo $idea['id']; ?>, '<?php echo addslashes(htmlspecialchars($idea['title'])); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    <?php endif; ?>

                                    <div class="idea-footer">
                                        <span>Submitted: <?php echo date('M j, Y', strtotime($idea['created_at'])); ?></span>
                                        <span>ID: #<?php echo $idea['id']; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="navigation">
                <a href="main.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Main Site</a>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin.php" class="btn btn-primary"><i class="fas fa-cog"></i> Admin Panel</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Idea</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form id="editForm">
                <input type="hidden" name="idea_id" id="editIdeaId">
                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" id="editTitle" required maxlength="255" minlength="5">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" id="editDescription" required minlength="20"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-control" id="editCategory">
                            <option value="improvement">Improvement</option>
                            <option value="feature">New Feature</option>
                            <option value="bug_fix">Bug Fix</option>
                            <option value="design">Design Update</option>
                            <option value="content">Content Addition</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-control" id="editPriority">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Estimated Effort</label>
                    <select name="estimated_effort" class="form-control" id="editEffort">
                        <option value="quick">Quick (1-2 hours)</option>
                        <option value="small">Small (1 day)</option>
                        <option value="medium">Medium (2-3 days)</option>
                        <option value="large">Large (1 week)</option>
                        <option value="xlarge">X-Large (2+ weeks)</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // v5.2: Reply to admin feedback
        function toggleReplyForm(ideaId) {
            const form = document.getElementById('reply-form-' + ideaId);
            const button = event.target;
            
            if (form.style.display === 'none') {
                form.style.display = 'block';
                button.innerHTML = '<i class="fas fa-times"></i> Cancel Reply';
                button.classList.remove('btn-outline');
                button.classList.add('btn-danger');
            } else {
                form.style.display = 'none';
                button.innerHTML = '<i class="fas fa-reply"></i> Reply to Admin';
                button.classList.remove('btn-danger');
                button.classList.add('btn-outline');
            }
        }
        
        async function submitReply(event, ideaId, type) {
            event.preventDefault();
            const text = document.getElementById('reply-text-' + ideaId).value.trim();
            if (!text) { alert('Please enter a reply.'); return; }

            try {
                const response = await fetch('contributor_panel.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: `action=reply_to_admin&idea_id=${ideaId}&reply_text=${encodeURIComponent(text)}&csrf_token=<?php echo $_SESSION['csrf_token'] ?? ''; ?>`
                });
                const data = await response.json();
                if (data.success) {
                    alert('Reply sent successfully!');
                    location.reload();
                } else {
                    alert(data.message || 'Error sending reply.');
                }
            } catch (error) {
                alert('Error sending reply. Please try again.');
            }
        }

        // Auto-hide notifications
        setTimeout(() => {
            document.querySelectorAll('.notification').forEach(n => {
                n.classList.remove('show');
                setTimeout(() => n.remove(), 300);
            });
        }, 5000);

        // Character counters
        const titleInput = document.getElementById('titleInput');
        const descInput = document.getElementById('descriptionInput');
        const titleCounter = document.getElementById('titleCounter');
        const descCounter = document.getElementById('descriptionCounter');

        if (titleInput && titleCounter) {
            titleInput.addEventListener('input', () => {
                const remaining = 255 - titleInput.value.length;
                titleCounter.textContent = `${remaining} characters remaining`;
                titleCounter.className = 'char-counter' + (remaining < 50 ? ' warning' : '') + (remaining < 20 ? ' danger' : '');
            });
        }

        if (descInput && descCounter) {
            descInput.addEventListener('input', () => {
                const count = descInput.value.length;
                descCounter.textContent = `${count} characters (min 20)`;
                descCounter.className = 'char-counter' + (count < 20 ? ' danger' : '');
            });
        }

        // Form validation
        document.getElementById('ideaForm')?.addEventListener('submit', function(e) {
            const title = titleInput?.value.trim() || '';
            const desc = descInput?.value.trim() || '';

            if (title.length < 5) {
                e.preventDefault();
                showNotification('Title must be at least 5 characters', true);
                return;
            }

            if (desc.length < 20) {
                e.preventDefault();
                showNotification('Description must be at least 20 characters', true);
                return;
            }

            const btn = document.getElementById('submitButton');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            btn.disabled = true;
        });

        // Get idea data for editing
        const ideasData = <?php echo json_encode(array_map(function($idea) {
            return [
                'id' => $idea['id'],
                'title' => $idea['title'],
                'description' => $idea['description'],
                'category' => $idea['category'],
                'priority' => $idea['priority'],
                'estimated_effort' => $idea['estimated_effort']
            ];
        }, $user_ideas)); ?>;

        function editIdea(id) {
            const idea = ideasData.find(i => i.id == id);
            if (!idea) return;

            document.getElementById('editIdeaId').value = idea.id;
            document.getElementById('editTitle').value = idea.title;
            document.getElementById('editDescription').value = idea.description;
            document.getElementById('editCategory').value = idea.category;
            document.getElementById('editPriority').value = idea.priority;
            document.getElementById('editEffort').value = idea.estimated_effort;
            document.getElementById('editModal').classList.add('show');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        // Get CSRF token for AJAX requests
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

        document.getElementById('editForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'update_idea');
            formData.append('idea_id', document.getElementById('editIdeaId').value);
            formData.append('title', document.getElementById('editTitle').value);
            formData.append('description', document.getElementById('editDescription').value);
            formData.append('category', document.getElementById('editCategory').value);
            formData.append('priority', document.getElementById('editPriority').value);
            formData.append('estimated_effort', document.getElementById('editEffort').value);

            try {
                const response = await fetch('contributor_panel.php', { method: 'POST', credentials: 'same-origin', body: formData });
                const data = await response.json();

                if (data.success) {
                    showNotification(data.message, false);
                    closeEditModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message, true);
                }
            } catch (error) {
                showNotification('Error updating idea', true);
            }
        });

        async function deleteIdea(id, title) {
            if (!confirm(`Delete "${title}"? This cannot be undone.`)) return;

            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'delete_idea');
            formData.append('idea_id', id);

            try {
                const response = await fetch('contributor_panel.php', { method: 'POST', credentials: 'same-origin', body: formData });
                const data = await response.json();

                if (data.success) {
                    showNotification(data.message, false);
                    document.querySelector(`.idea-item[data-id="${id}"]`)?.remove();
                } else {
                    showNotification(data.message, true);
                }
            } catch (error) {
                showNotification('Error deleting idea', true);
            }
        }

        function showNotification(message, isError) {
            const existing = document.querySelector('.notification');
            if (existing) existing.remove();

            const n = document.createElement('div');
            n.className = 'notification show' + (isError ? ' error' : '');
            n.innerHTML = `<i class="fas fa-${isError ? 'exclamation-triangle' : 'check-circle'}"></i> ${message}`;
            document.body.appendChild(n);

            setTimeout(() => {
                n.classList.remove('show');
                setTimeout(() => n.remove(), 300);
            }, 4000);
        }

        // Modal close handlers
        document.getElementById('editModal')?.addEventListener('click', e => { if (e.target.id === 'editModal') closeEditModal(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeEditModal(); });

        // Draft auto-save
        let draftTimeout;
        function autoSaveDraft() {
            clearTimeout(draftTimeout);
            draftTimeout = setTimeout(() => {
                const title = titleInput?.value;
                const desc = descInput?.value;
                if (title || desc) {
                    localStorage.setItem('ideaDraft', JSON.stringify({ title, description: desc, timestamp: new Date().toISOString() }));
                }
            }, 2000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const draft = localStorage.getItem('ideaDraft');
            if (draft && titleInput && descInput) {
                try {
                    const { title, description, timestamp } = JSON.parse(draft);
                    if ((title || description) && confirm('Restore draft from ' + new Date(timestamp).toLocaleString() + '?')) {
                        titleInput.value = title || '';
                        descInput.value = description || '';
                        titleInput.dispatchEvent(new Event('input'));
                        descInput.dispatchEvent(new Event('input'));
                    }
                } catch (e) {}
            }

            titleInput?.addEventListener('input', autoSaveDraft);
            descInput?.addEventListener('input', autoSaveDraft);

            document.getElementById('ideaForm')?.addEventListener('submit', () => localStorage.removeItem('ideaDraft'));
        });
    </script>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
