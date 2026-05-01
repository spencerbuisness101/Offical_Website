<?php
/**
 * Admin Strike Application Interface
 * 
 * Allows moderators to apply strikes to Paid Account users.
 * Shows punishment preview before application.
 */

session_start();
define('APP_RUNNING', true);

require_once __DIR__ . '/../../../includes/init.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/StrikeManager.php';

// Check admin authentication
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /admin.php');
    exit;
}

$success = '';
$error = '';
$preview = null;
$user = null;
$csrfToken = generateCsrfToken();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'preview') {
            // Preview punishment
            $userId = intval($_POST['user_id'] ?? 0);
            $ruleId = $_POST['rule_id'] ?? '';

            if ($userId > 0 && !empty($ruleId)) {
                try {
                    $database = new Database();
                    $db = $database->getConnection();

                    // Get user info
                    $stmt = $db->prepare("SELECT id, username, email, role, account_tier, account_status FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$user) {
                        $error = 'User not found.';
                    } elseif ($user['account_tier'] === 'community') {
                        $error = 'Community Accounts cannot receive strikes.';
                    } elseif ($user['role'] === 'admin') {
                        $error = 'Cannot apply strikes to admin accounts.';
                    } else {
                        // Get current active strikes
                        $strikeManager = new StrikeManager();
                        $activeStrikes = $strikeManager->countActiveStrikes($userId);

                        // Determine punishment
                        $preview = $strikeManager->determinePunishment($ruleId, $activeStrikes);
                        $preview['active_strikes'] = $activeStrikes;
                        $preview['rule'] = StrikeManager::getRule($ruleId);
                    }
                } catch (Exception $e) {
                    $error = 'An error occurred: ' . $e->getMessage();
                }
            } else {
                $error = 'Please select a user and rule.';
            }

        } elseif ($action === 'apply') {
            // Apply the strike
            $userId = intval($_POST['user_id'] ?? 0);
            $ruleId = $_POST['rule_id'] ?? '';
            $evidence = $_POST['evidence'] ?? '';
            $customDuration = !empty($_POST['custom_duration']) ? intval($_POST['custom_duration']) : null;

            if ($userId > 0 && !empty($ruleId) && !empty($evidence)) {
                try {
                    $strikeManager = new StrikeManager();
                    $result = $strikeManager->applyStrike($userId, $ruleId, $evidence, $_SESSION['user_id'], $customDuration);

                    if ($result['success']) {
                        $success = 'Strike applied successfully. ' . $result['punishment']['action'] . ' punishment enforced.';
                    } else {
                        $error = $result['message'];
                    }
                } catch (Exception $e) {
                    $error = 'An error occurred: ' . $e->getMessage();
                }
            } else {
                $error = 'Please fill in all required fields.';
            }
        }
    }
}

// Get list of users for dropdown
try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->query("
        SELECT id, username, email, account_tier, account_status 
        FROM users 
        WHERE role != 'admin' AND account_tier = 'paid'
        ORDER BY username
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $users = [];
    $error = 'Failed to load users: ' . $e->getMessage();
}

// Get rules
$rules = StrikeManager::getRules();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply Strike - Admin Panel</title>
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
        }
        
        .admin-header h1 {
            font-size: 24px;
            font-weight: 700;
        }
        
        .container {
            max-width: 900px;
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
        
        .form-card {
            background: rgba(30, 41, 59, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 40px;
            margin-bottom: 30px;
        }
        
        .form-card h2 {
            font-size: 20px;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            color: #94a3b8;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        select, textarea {
            width: 100%;
            padding: 12px 16px;
            background: rgba(15, 23, 42, 0.8);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #f1f5f9;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        select:focus, textarea:focus {
            outline: none;
            border-color: #4ECDC4;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4ECDC4, #6366f1);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(78, 205, 196, 0.3);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #f1f5f9;
            margin-left: 10px;
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
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
        
        .alert-warning {
            background: rgba(234, 179, 8, 0.1);
            border: 1px solid rgba(234, 179, 8, 0.3);
            color: #eab308;
        }
        
        .preview-box {
            background: rgba(15, 23, 42, 0.8);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 25px;
            margin-top: 20px;
        }
        
        .preview-box h3 {
            font-size: 16px;
            margin-bottom: 15px;
            color: #f1f5f9;
        }
        
        .preview-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .preview-item:last-child {
            border-bottom: none;
        }
        
        .preview-label {
            color: #94a3b8;
            font-size: 13px;
        }
        
        .preview-value {
            color: #f1f5f9;
            font-size: 13px;
            font-weight: 500;
        }
        
        .tier-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .tier-1 {
            background: rgba(234, 179, 8, 0.2);
            color: #eab308;
        }
        
        .tier-2 {
            background: rgba(248, 113, 113, 0.2);
            color: #f87171;
        }
        
        .tier-3 {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        
        .rule-info {
            background: rgba(78, 205, 196, 0.1);
            border: 1px solid rgba(78, 205, 196, 0.2);
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            font-size: 13px;
            color: #94a3b8;
        }
        
        .rule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 10px;
            margin-top: 10px;
            font-size: 12px;
        }
        
        .rule-item {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 6px;
            padding: 10px;
            color: #94a3b8;
        }
        
        .rule-item strong {
            color: #f1f5f9;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>⚖️ Apply Strike</h1>
    </div>
    
    <div class="container">
        <a href="/admin.php" class="back-link">← Back to Admin Panel</a>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="form-card">
            <h2>Strike Application</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="preview" id="formAction">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                
                <div class="form-group">
                    <label for="user_id">Select User</label>
                    <select name="user_id" id="user_id" required>
                        <option value="">Choose a user...</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo (isset($user) && $user['id'] == $u['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['username']); ?> 
                                (<?php echo htmlspecialchars($u['email']); ?>) - 
                                <?php echo $u['account_status']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="rule_id">Select Rule Violated</label>
                    <select name="rule_id" id="rule_id" required onchange="showRuleInfo()">
                        <option value="">Choose a rule...</option>
                        <?php foreach ($rules as $ruleId => $rule): ?>
                            <option value="<?php echo $ruleId; ?>" <?php echo (isset($_POST['rule_id']) && $_POST['rule_id'] === $ruleId) ? 'selected' : ''; ?>>
                                <?php echo $ruleId; ?> - <?php echo htmlspecialchars($rule['name']); ?> 
                                (<?php echo htmlspecialchars($rule['category']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <div id="ruleInfo" class="rule-info" style="display:none;">
                        <strong>Rule Details:</strong>
                        <div id="ruleDetails"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="evidence">Evidence / Description</label>
                    <textarea name="evidence" id="evidence" placeholder="Describe the violation and provide evidence (links to posts, screenshots, etc.)" required><?php echo htmlspecialchars($_POST['evidence'] ?? ''); ?></textarea>
                </div>
                
                <?php if ($preview && $preview['tier'] === 2): ?>
                <div class="form-group">
                    <label for="custom_duration">Custom Duration (days) - Optional</label>
                    <select name="custom_duration" id="custom_duration">
                        <option value="">Default (<?php echo $preview['duration_days'] ?? 'N/A'; ?> days)</option>
                        <option value="1">1 day (Moderator discretion)</option>
                        <option value="3">3 days</option>
                        <option value="7">7 days</option>
                        <option value="14">14 days</option>
                    </select>
                    <small style="color:#64748b; display:block; margin-top:5px;">
                        Default is recommended. Only modify for severe cases or bad-faith behavior.
                    </small>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <?php if (!$preview): ?>
                        <button type="submit" class="btn btn-primary">Preview Punishment</button>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('formAction').value='preview'; this.form.submit();">
                            Update Preview
                        </button>
                        <button type="button" class="btn btn-primary" onclick="if(confirm('Are you sure you want to apply this strike? This action cannot be undone.')) { document.getElementById('formAction').value='apply'; this.form.submit(); }">
                            Apply Strike
                        </button>
                    <?php endif; ?>
                </div>
            </form>
            
            <?php if ($preview): ?>
            <div class="preview-box">
                <h3>Punishment Preview</h3>
                
                <div class="preview-item">
                    <span class="preview-label">Current Active Strikes:</span>
                    <span class="preview-value"><?php echo $preview['active_strikes']; ?></span>
                </div>
                
                <div class="preview-item">
                    <span class="preview-label">Punishment Tier:</span>
                    <span class="preview-value">
                        <span class="tier-badge tier-<?php echo $preview['tier']; ?>">
                            Tier <?php echo $preview['tier']; ?>
                        </span>
                    </span>
                </div>
                
                <div class="preview-item">
                    <span class="preview-label">Action Taken:</span>
                    <span class="preview-value" style="color:<?php echo $preview['tier'] === 3 ? '#ef4444' : ($preview['tier'] === 2 ? '#f87171' : '#eab308'); ?>">
                        <?php echo htmlspecialchars($preview['action']); ?>
                    </span>
                </div>
                
                <?php if (!empty($preview['duration_days'])): ?>
                <div class="preview-item">
                    <span class="preview-label">Duration:</span>
                    <span class="preview-value"><?php echo $preview['duration_days']; ?> days</span>
                </div>
                <?php endif; ?>
                
                <div class="preview-item">
                    <span class="preview-label">Can Appeal:</span>
                    <span class="preview-value"><?php echo !empty($preview['can_appeal']) ? 'Yes' : 'No'; ?></span>
                </div>
                
                <div class="preview-item">
                    <span class="preview-label">Description:</span>
                    <span class="preview-value"><?php echo htmlspecialchars($preview['description']); ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="form-card">
            <h2>Rule Reference</h2>
            <div class="rule-grid">
                <?php foreach ($rules as $ruleId => $rule): ?>
                    <div class="rule-item">
                        <strong><?php echo $ruleId; ?></strong> - <?php echo htmlspecialchars($rule['name']); ?><br>
                        <small><?php echo htmlspecialchars($rule['description']); ?></small><br>
                        <small style="color:#64748b;">
                            T1: <?php echo htmlspecialchars($rule['tier1']); ?> | 
                            T2: <?php echo htmlspecialchars($rule['tier2']); ?> | 
                            T3: <?php echo htmlspecialchars($rule['tier3']); ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <script>
        const rules = <?php echo json_encode($rules); ?>;
        
        function showRuleInfo() {
            const ruleId = document.getElementById('rule_id').value;
            const infoDiv = document.getElementById('ruleInfo');
            const detailsDiv = document.getElementById('ruleDetails');
            
            if (ruleId && rules[ruleId]) {
                const rule = rules[ruleId];
                detailsDiv.innerHTML = `
                    <strong>Category:</strong> ${rule.category}<br>
                    <strong>Description:</strong> ${rule.description}<br>
                    <strong>Tier 1:</strong> ${rule.tier1}<br>
                    <strong>Tier 2:</strong> ${rule.tier2}<br>
                    <strong>Tier 3:</strong> ${rule.tier3}
                `;
                infoDiv.style.display = 'block';
            } else {
                infoDiv.style.display = 'none';
            }
        }
        
        // Show info if rule already selected
        if (document.getElementById('rule_id').value) {
            showRuleInfo();
        }
    </script>
</body>
</html>
