<?php
/**
 * Admin Appeal Review Interface
 * 
 * Allows admins to review lockdown appeals from users.
 * Can approve (release from lockdown) or deny (keep in lockdown).
 */

session_start();
define('APP_RUNNING', true);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/PunishmentManager.php';

// Check admin authentication
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /admin.php');
    exit;
}

$success = '';
$error = '';
$csrfToken = generateCsrfToken();

// Handle appeal actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token. Please refresh the page and try again.';
    } else {
        $appealId = intval($_POST['appeal_id'] ?? 0);
        $action = $_POST['appeal_action'] ?? '';
        $adminNotes = trim($_POST['admin_notes'] ?? '');
        
        if ($appealId > 0 && in_array($action, ['approve', 'deny'])) {
            try {
                $database = new Database();
                $db = $database->getConnection();
                $punishmentManager = new PunishmentManager();
                
                // Get appeal details
                $stmt = $db->prepare("
                    SELECT a.*, u.id as user_id, u.username, u.email
                    FROM lockdown_appeals a
                    JOIN users u ON a.user_id = u.id
                    WHERE a.id = ? AND a.status = 'pending'
                ");
                $stmt->execute([$appealId]);
                $appeal = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$appeal) {
                    $error = 'Appeal not found or already processed.';
                } else {
                    if ($action === 'approve') {
                        // Release user from lockdown
                        $result = $punishmentManager->removeLockdown(
                            $appeal['user_id'], 
                            $_SESSION['user_id'], 
                            $adminNotes
                        );
                        
                        if ($result['success']) {
                            // Update appeal status
                            $stmt = $db->prepare("
                                UPDATE lockdown_appeals 
                                SET status = 'approved', 
                                    reviewed_at = NOW(), 
                                    reviewed_by = ?,
                                    admin_notes = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([$_SESSION['user_id'], $adminNotes, $appealId]);
                            
                            $success = 'Appeal approved. User has been released from lockdown.';
                        } else {
                            $error = $result['message'];
                        }
                        
                    } else {
                        // Deny appeal - keep in lockdown
                        $stmt = $db->prepare("
                            UPDATE lockdown_appeals 
                            SET status = 'denied', 
                                reviewed_at = NOW(), 
                                reviewed_by = ?,
                                admin_notes = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$_SESSION['user_id'], $adminNotes, $appealId]);
                        
                        // Notify user
                        $punishmentManager->notifyAppealDenied($appeal['user_id'], $adminNotes);
                        
                        $success = 'Appeal denied. User remains in lockdown.';
                    }
                }
                
            } catch (Exception $e) {
                $error = 'An error occurred: ' . $e->getMessage();
            }
        }
    }
}

// Fetch pending appeals
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->query("
        SELECT a.*, u.username, u.email, u.lockdown_rule, u.lockdown_at
        FROM lockdown_appeals a
        JOIN users u ON a.user_id = u.id
        WHERE a.status = 'pending'
        ORDER BY a.created_at ASC
    ");
    $pendingAppeals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch processed appeals (last 50)
    $stmt = $db->query("
        SELECT a.*, u.username, admin.username as reviewed_by_name
        FROM lockdown_appeals a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN users admin ON a.reviewed_by = admin.id
        WHERE a.status != 'pending'
        ORDER BY a.reviewed_at DESC
        LIMIT 50
    ");
    $processedAppeals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = 'Failed to load appeals: ' . $e->getMessage();
    $pendingAppeals = [];
    $processedAppeals = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Appeals - Admin Panel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            min-height: 100vh;
        }
        
        .admin-header {
            background: rgba(30, 41, 59, 0.95);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-header h1 {
            font-size: 24px;
            font-weight: 700;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px;
        }
        
        .back-link {
            color: #4ECDC4;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 20px;
            display: inline-block;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #22c55e;
        }
        
        .alert-error {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: #f87171;
        }
        
        .appeals-section {
            background: rgba(30, 41, 59, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .appeals-section h2 {
            font-size: 20px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .appeal-card {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .appeal-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .appeal-user {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4ECDC4, #6366f1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
        }
        
        .user-info h4 {
            font-size: 16px;
            margin-bottom: 2px;
        }
        
        .user-info p {
            font-size: 13px;
            color: #94a3b8;
        }
        
        .appeal-meta {
            text-align: right;
        }
        
        .appeal-meta .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(248, 113, 113, 0.2);
            color: #f87171;
        }
        
        .appeal-meta .date {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
        }
        
        .appeal-text {
            background: rgba(0, 0, 0, 0.2);
            border-left: 3px solid #4ECDC4;
            padding: 15px;
            margin: 15px 0;
            border-radius: 0 8px 8px 0;
            font-size: 14px;
            line-height: 1.6;
            color: #cbd5e1;
        }
        
        .appeal-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-approve {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
        }
        
        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(34, 197, 94, 0.3);
        }
        
        .btn-deny {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .btn-deny:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
        }
        
        .admin-notes {
            width: 100%;
            margin-top: 10px;
        }
        
        .admin-notes textarea {
            width: 100%;
            padding: 10px;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            color: #f1f5f9;
            font-size: 13px;
            resize: vertical;
            min-height: 60px;
            font-family: inherit;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .processed-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        .processed-table th,
        .processed-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .processed-table th {
            color: #94a3b8;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .processed-table td {
            color: #cbd5e1;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-approved {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }
        
        .status-denied {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🔓 Appeal Review</h1>
        <a href="/admin.php" class="back-link">← Back to Admin Panel</a>
    </div>
    
    <div class="container">
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Pending Appeals -->
        <div class="appeals-section">
            <h2>Pending Appeals (<?php echo count($pendingAppeals); ?>)</h2>
            
            <?php if (empty($pendingAppeals)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>No Pending Appeals</h3>
                    <p>All lockdown appeals have been processed.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pendingAppeals as $appeal): ?>
                    <div class="appeal-card">
                        <div class="appeal-header">
                            <div class="appeal-user">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($appeal['username'], 0, 1)); ?>
                                </div>
                                <div class="user-info">
                                    <h4><?php echo htmlspecialchars($appeal['username']); ?></h4>
                                    <p><?php echo htmlspecialchars($appeal['email']); ?></p>
                                </div>
                            </div>
                            <div class="appeal-meta">
                                <span class="badge"><?php echo htmlspecialchars($appeal['lockdown_rule']); ?></span>
                                <div class="date">Submitted <?php echo date('M j, Y g:i A', strtotime($appeal['created_at'])); ?></div>
                            </div>
                        </div>
                        
                        <div class="appeal-text">
                            <?php echo nl2br(htmlspecialchars($appeal['appeal_text'])); ?>
                        </div>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="appeal_id" value="<?php echo $appeal['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            
                            <div class="admin-notes">
                                <textarea name="admin_notes" placeholder="Admin notes (optional) - visible to user"></textarea>
                            </div>
                            
                            <div class="appeal-actions">
                                <button type="submit" name="appeal_action" value="approve" class="btn btn-approve">
                                    ✓ Approve & Release
                                </button>
                                <button type="submit" name="appeal_action" value="deny" class="btn btn-deny">
                                    ✗ Deny - Keep Lockdown
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Processed Appeals -->
        <div class="appeals-section">
            <h2>Recently Processed</h2>
            
            <?php if (empty($processedAppeals)): ?>
                <div class="empty-state">
                    <p>No processed appeals yet.</p>
                </div>
            <?php else: ?>
                <table class="processed-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Rule</th>
                            <th>Status</th>
                            <th>Reviewed By</th>
                            <th>Date</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($processedAppeals as $appeal): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($appeal['username']); ?></td>
                                <td><?php echo htmlspecialchars($appeal['lockdown_rule']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $appeal['status']; ?>">
                                        <?php echo ucfirst($appeal['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($appeal['reviewed_by_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo date('M j, Y', strtotime($appeal['reviewed_at'])); ?></td>
                                <td><?php echo htmlspecialchars(substr($appeal['admin_notes'] ?? '', 0, 50)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
