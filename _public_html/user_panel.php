<?php
/**
 * User Panel
 *
 * Dashboard for users to manage premium features, personal storage,
 * and access the AI assistant.
 */

// Define APP_RUNNING for config files
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/roles.php';


// Security headers — centralized via security.php setSecurityHeaders()
require_once __DIR__ . '/includes/security.php';
setSecurityHeaders();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Allow user, contributor, designer, and admin roles
$role = $_SESSION['role'] ?? 'community';
$access_denied = !hasRoleOrHigher($role, 'user');

// Database connection
$db = null;
$user_premium = null;
$user_storage = [];
$storage_stats = ['used' => 0, 'limit' => 50, 'files' => 0];
$member_since = null;

try {
    $db = (new Database())->getConnection();

    // Create premium table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS user_premium (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        is_premium BOOLEAN DEFAULT FALSE,
        premium_since TIMESTAMP NULL,
        chat_tag VARCHAR(50) NULL,
        custom_background_url VARCHAR(500) NULL,
        storage_used_mb DECIMAL(10,2) DEFAULT 0,
        storage_limit_mb DECIMAL(10,2) DEFAULT 50,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Create storage table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS user_storage (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size_kb INT NOT NULL,
        file_type VARCHAR(50) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Get or create user premium record
    $stmt = $db->prepare("SELECT is_premium, premium_since, chat_tag, custom_background_url, storage_used_mb, storage_limit_mb FROM user_premium WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_premium = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_premium) {
        // Create record for user
        $stmt = $db->prepare("INSERT INTO user_premium (user_id) VALUES (?)");
        $stmt->execute([$_SESSION['user_id']]);
        $user_premium = [
            'is_premium' => false,
            'premium_since' => null,
            'chat_tag' => null,
            'custom_background_url' => null,
            'storage_used_mb' => 0,
            'storage_limit_mb' => 50
        ];
    }

    // Get user's storage files
    if ($user_premium['is_premium']) {
        $stmt = $db->prepare("SELECT id, storage_key, storage_type, created_at FROM user_storage WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $user_storage = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $storage_stats['used'] = floatval($user_premium['storage_used_mb']);
        $storage_stats['limit'] = floatval($user_premium['storage_limit_mb']);
        $storage_stats['files'] = count($user_storage);
    }

    // Get user's creation date
    $stmt = $db->prepare("SELECT created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $member_since = $stmt->fetchColumn();

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

    if ($access_denied || !$user_premium['is_premium']) {
        echo json_encode(['success' => false, 'message' => 'Premium access required']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_chat_tag') {
            $chat_tag = trim($_POST['chat_tag'] ?? '');

            // Validate chat tag
            if (strlen($chat_tag) > 50) {
                echo json_encode(['success' => false, 'message' => 'Chat tag must be 50 characters or less']);
                exit;
            }

            // Sanitize - only allow alphanumeric, spaces, and some emojis
            $chat_tag = preg_replace('/[<>"\']/', '', $chat_tag);

            $stmt = $db->prepare("UPDATE user_premium SET chat_tag = ? WHERE user_id = ?");
            $stmt->execute([$chat_tag ?: null, $_SESSION['user_id']]);

            echo json_encode(['success' => true, 'message' => 'Chat tag updated successfully']);
            exit;
        }

        if ($action === 'update_custom_background') {
            $bg_url = trim($_POST['background_url'] ?? '');

            if (!empty($bg_url) && !filter_var($bg_url, FILTER_VALIDATE_URL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid URL format']);
                exit;
            }

            $stmt = $db->prepare("UPDATE user_premium SET custom_background_url = ? WHERE user_id = ?");
            $stmt->execute([$bg_url ?: null, $_SESSION['user_id']]);

            echo json_encode(['success' => true, 'message' => 'Custom background updated successfully']);
            exit;
        }

    } catch (Exception $e) {
        error_log("User panel action error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

$username = htmlspecialchars($_SESSION['username']);
$is_premium = $user_premium && $user_premium['is_premium'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Spencer's Website</title>
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/assets/images/favicon.webp">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
    <script src="js/tracking.js?v=7.0" defer></script>
    <script src="js/fingerprint.js" defer></script>
    <style>
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
            background: linear-gradient(135deg, var(--premium), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .header p { color: #94a3b8; font-size: 1.2rem; }

        .premium-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-top: 1rem;
        }

        .premium-badge.active {
            background: linear-gradient(135deg, var(--premium), #d97706);
            color: white;
        }

        .premium-badge.inactive {
            background: rgba(255, 255, 255, 0.1);
            color: #94a3b8;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: var(--premium);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--premium), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            color: #94a3b8;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .panel-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        @media (max-width: 968px) {
            .panel-grid { grid-template-columns: 1fr; }
            .header h1 { font-size: 2rem; }
        }

        .card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 2rem;
            transition: all 0.3s ease;
        }

        .card:hover {
            border-color: rgba(245, 158, 11, 0.3);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .card h2 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card h2 i { color: var(--premium); }

        .form-group { margin-bottom: 1.5rem; }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #e2e8f0;
        }

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

        .form-control:focus {
            outline: none;
            border-color: var(--premium);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
        }

        .form-control:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

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

        .btn-premium {
            background: linear-gradient(135deg, var(--premium), #d97706);
            color: white;
        }

        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid rgba(255, 255, 255, 0.2);
            color: white;
        }

        .btn-outline:hover {
            border-color: var(--premium);
            background: rgba(245, 158, 11, 0.1);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Premium Upgrade Section */
        .upgrade-section {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(99, 102, 241, 0.1));
            border: 2px solid var(--premium);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
        }

        .upgrade-section h3 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            color: var(--premium);
        }

        .upgrade-price {
            font-size: 3rem;
            font-weight: 800;
            color: white;
            margin: 1rem 0;
        }

        .upgrade-price span {
            font-size: 1rem;
            color: #94a3b8;
        }

        .benefits-list {
            list-style: none;
            text-align: left;
            margin: 1.5rem 0;
        }

        .benefits-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .benefits-list li:last-child { border-bottom: none; }

        .benefits-list li i {
            color: var(--secondary);
            font-size: 1.1rem;
        }

        .upgrade-note {
            font-size: 0.85rem;
            color: #94a3b8;
            margin-top: 1rem;
            font-style: italic;
        }

        /* Storage Display */
        .storage-bar {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            height: 20px;
            overflow: hidden;
            margin: 1rem 0;
        }

        .storage-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--secondary), var(--primary));
            border-radius: 10px;
            transition: width 0.5s ease;
        }

        .storage-text {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: #94a3b8;
        }

        /* Feature Locked Overlay */
        .feature-locked {
            position: relative;
        }

        .feature-locked::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 8px;
            z-index: 1;
        }

        .feature-locked::after {
            content: 'Premium Only';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--premium);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-weight: 600;
            z-index: 2;
        }

        /* Navigation */
        .navigation {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        /* Promo Page for Community Users */
        .promo-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .promo-hero {
            text-align: center;
            padding: 3rem 2rem;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(99, 102, 241, 0.15));
            border: 2px solid var(--premium);
            border-radius: 20px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .promo-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(245, 158, 11, 0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .promo-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--premium), #d97706);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }

        .promo-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
            background: linear-gradient(135deg, #fff, var(--premium));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .promo-subtitle {
            font-size: 1.2rem;
            color: #cbd5e1;
            margin-bottom: 2rem;
            position: relative;
            z-index: 1;
        }

        .promo-price-box {
            background: rgba(0, 0, 0, 0.4);
            border-radius: 15px;
            padding: 2rem;
            display: inline-block;
            position: relative;
            z-index: 1;
        }

        .promo-price {
            font-size: 4rem;
            font-weight: 900;
            color: var(--premium);
            line-height: 1;
        }

        .promo-price-label {
            font-size: 1rem;
            color: #94a3b8;
            margin-top: 0.5rem;
        }

        .promo-lifetime {
            display: inline-block;
            background: var(--secondary);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 0.75rem;
        }

        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .benefit-card {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .benefit-card:hover {
            transform: translateY(-5px);
            border-color: var(--premium);
            box-shadow: 0 10px 30px rgba(245, 158, 11, 0.2);
        }

        .benefit-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--premium), #d97706);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: white;
            flex-shrink: 0;
        }

        .benefit-content h4 {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: white;
        }

        .benefit-content p {
            font-size: 0.9rem;
            color: #94a3b8;
            line-height: 1.5;
        }

        .promo-cta {
            text-align: center;
            padding: 2rem;
            background: rgba(30, 41, 59, 0.5);
            border-radius: 15px;
            margin-bottom: 2rem;
        }

        .promo-cta h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: white;
        }

        .promo-cta p {
            color: #94a3b8;
            margin-bottom: 1.5rem;
        }

        .btn-upgrade {
            background: linear-gradient(135deg, var(--premium), #d97706);
            color: white;
            padding: 1rem 3rem;
            font-size: 1.2rem;
            border-radius: 50px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: 0 5px 20px rgba(245, 158, 11, 0.4);
        }

        .btn-upgrade:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 30px rgba(245, 158, 11, 0.5);
        }

        .promo-note {
            text-align: center;
            color: #64748b;
            font-size: 0.85rem;
            padding: 1rem;
        }

        .promo-note i {
            color: var(--secondary);
            margin-right: 0.5rem;
        }

        .preview-section {
            background: rgba(30, 41, 59, 0.5);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .preview-section h3 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #94a3b8;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .preview-features {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .preview-item {
            text-align: center;
            opacity: 0.6;
        }

        .preview-item i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .preview-item span {
            display: block;
            font-size: 0.85rem;
            color: #64748b;
        }

        /* Access Denied - fallback */
        .access-denied {
            text-align: center;
            padding: 4rem 2rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .access-denied-icon {
            font-size: 4rem;
            color: #ef4444;
            margin-bottom: 2rem;
        }

        .access-denied h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: white;
        }

        .access-denied p {
            color: #94a3b8;
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }

        /* Notification */
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
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/identity_bar.php'; ?>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-user-circle"></i> User Dashboard</h1>
            <p>Manage your account and premium features</p>
            <?php if (!$access_denied): ?>
                <div style="margin-top: 1rem; color: #94a3b8;">
                    Welcome, <strong><?php echo htmlspecialchars($username); ?></strong>!
                </div>
                <div class="premium-badge <?php echo $is_premium ? 'active' : 'inactive'; ?>">
                    <i class="fas fa-<?php echo $is_premium ? 'crown' : 'star'; ?>"></i>
                    <?php echo $is_premium ? 'Premium Member' : 'Free Account'; ?>
                </div>
            <?php endif; ?>
        </header>

        <?php if ($access_denied): ?>
            <!-- Promotional Page for Community Users -->
            <div class="promo-container">
                <!-- Hero Section -->
                <div class="promo-hero">
                    <div class="promo-badge">Limited Time Offer</div>
                    <h1 class="promo-title">Unlock Your Premium Experience</h1>
                    <p class="promo-subtitle">Become an Official User and access exclusive features designed for power and convenience!</p>
                    <div class="promo-price-box" style="display:flex; gap:15px; justify-content:center; flex-wrap:wrap; margin-top:20px;">
                        <div style="background:rgba(255,255,255,0.08); border:2px solid rgba(255,255,255,0.2); border-radius:12px; padding:15px 20px; text-align:center; min-width:120px;">
                            <div style="font-size:28px; font-weight:800; color:white;">$2<span style="font-size:14px; font-weight:600;">/mo</span></div>
                            <div style="font-size:12px; color:rgba(255,255,255,0.6); margin-top:4px;">Cancel anytime</div>
                        </div>
                        <div style="background:rgba(59,130,246,0.12); border:2px solid rgba(59,130,246,0.5); border-radius:12px; padding:15px 20px; text-align:center; min-width:120px;">
                            <div style="font-size:28px; font-weight:800; color:white;">$30<span style="font-size:14px; font-weight:600;">/yr</span></div>
                            <div style="font-size:12px; color:rgba(255,255,255,0.6); margin-top:4px;">Save $2.50/mo</div>
                        </div>
                        <div style="background:rgba(255,215,0,0.12); border:2px solid rgba(255,215,0,0.5); border-radius:12px; padding:15px 20px; text-align:center; min-width:120px; position:relative;">
                            <div style="position:absolute; top:-8px; right:-8px; background:#FFD700; color:#000; font-size:10px; font-weight:800; padding:2px 6px; border-radius:5px;">BEST</div>
                            <div style="font-size:28px; font-weight:800; color:white;">$100</div>
                            <div style="font-size:12px; color:rgba(255,255,255,0.6); margin-top:4px;">One-time, forever</div>
                        </div>
                    </div>
                </div>

                <!-- Benefits Grid -->
                <div class="benefits-grid">
                    <div class="benefit-card">
                        <div class="benefit-icon"><i class="fas fa-user-tag"></i></div>
                        <div class="benefit-content">
                            <h4>A Unique Identity</h4>
                            <p>Your own personal username and secure password with a verified account status.</p>
                        </div>
                    </div>

                    <div class="benefit-card">
                        <div class="benefit-icon"><i class="fas fa-comment-dots"></i></div>
                        <div class="benefit-content">
                            <h4>Special Chat Tag</h4>
                            <p>Stand out with a unique prefix or tag next to your name in all chat boxes.</p>
                        </div>
                    </div>

                    <div class="benefit-card">
                        <div class="benefit-icon"><i class="fas fa-palette"></i></div>
                        <div class="benefit-content">
                            <h4>Custom Background</h4>
                            <p>Personalize your interface with your own background image or theme.</p>
                        </div>
                    </div>

                    <div class="benefit-card">
                        <div class="benefit-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                        <div class="benefit-content">
                            <h4>Personal Storage</h4>
                            <p>Your own dedicated 50MB space to save and organize your files.</p>
                        </div>
                    </div>

                    <div class="benefit-card">
                        <div class="benefit-icon"><i class="fas fa-bolt"></i></div>
                        <div class="benefit-content">
                            <h4>Priority Access</h4>
                            <p>Jump the queue! Get faster responses and higher priority than standard users.</p>
                        </div>
                    </div>

                    <div class="benefit-card">
                        <div class="benefit-icon"><i class="fas fa-robot"></i></div>
                        <div class="benefit-content">
                            <h4>Personal AI Assistant</h4>
                            <p>Get help with anything - from answering questions to solving complex problems - right at your fingertips.</p>
                        </div>
                    </div>
                </div>

                <!-- Preview Section -->
                <div class="preview-section">
                    <h3>Features You'll Unlock</h3>
                    <div class="preview-features">
                        <div class="preview-item">
                            <i class="fas fa-crown"></i>
                            <span>Premium Badge</span>
                        </div>
                        <div class="preview-item">
                            <i class="fas fa-comments"></i>
                            <span>AI Chat</span>
                        </div>
                        <div class="preview-item">
                            <i class="fas fa-hdd"></i>
                            <span>Cloud Storage</span>
                        </div>
                        <div class="preview-item">
                            <i class="fas fa-paint-brush"></i>
                            <?php if (!$is_premium): ?>
                <?php if (function_exists('arePaymentsEnabled') && !arePaymentsEnabled($db)): ?>
                    <div class="card" style="text-align: center; border-color: var(--primary);">
                        <div class="card-icon" style="background: rgba(239, 68, 68, 0.1); color: #f87171; border-color: rgba(239, 68, 68, 0.3);">
                            <i class="fas fa-store-slash"></i>
                        </div>
                        <h3>Purchases Disabled</h3>
                        <p>We are currently not accepting new payments or upgrades. Please check back later.</p>
                    </div>
                <?php else: ?>
                    <!-- Promotional Call to Action -->
                    <div class="card promo-card" style="text-align: center; border-color: var(--primary);">
                        <div class="card-icon" style="background: rgba(99, 102, 241, 0.1); color: var(--primary);">
                            <i class="fas fa-star"></i>
                        </div>
                        <h3>Ready to Upgrade?</h3>
                        <p>Plans start at just $2/month, $30/year, or $100 for lifetime access!</p>
                        <a href="main.php" class="btn btn-upgrade">
                            <i class="fas fa-crown"></i> Contact Admin to Upgrade
                        </a>
                    </div>

                    <!-- Note -->
                    <div class="promo-note">
                        <p><i class="fas fa-shield-alt"></i> Refunds are available within 48 hours of purchase.</p>
                        <p style="margin-top: 0.5rem;"><i class="fas fa-lock"></i> Secure payment handled offline through admin.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
                            <span>Customization</span>
                        </div>
                        <div class="preview-item">
                            <i class="fas fa-star"></i>
                            <span>VIP Status</span>
                        </div>
                    </div>
                </div>

                <!-- CTA Section -->
                <div class="promo-cta">
                    
                </div>

                <!-- Note -->
                <div class="promo-note">
                    
                </div>

                <!-- Navigation -->
                <div class="navigation" style="margin-top: 2rem;">
                    <a href="main.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i>
                        Back to Main Site
                    </a>
                    <a href="yaps.php" class="btn btn-outline">
                        <i class="fas fa-comments"></i>
                        Try Free Chat
                    </a>
                </div>
            </div>
        <?php else: ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $is_premium ? 'Active' : 'Free'; ?></div>
                    <div class="stat-label">Account Status</div>
                </div>
                <div class="stat-card">
                    <?php $roleInfo = getRoleInfo($role); ?>
                    <div class="stat-number" style="background: linear-gradient(135deg, <?php echo htmlspecialchars($roleInfo['color']); ?>, var(--primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 1.8rem;">
                        <?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', $role))); ?>
                    </div>
                    <div class="stat-label">Your Role</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="font-size: 1.5rem;"><?php echo $member_since ? date('M j, Y', strtotime($member_since)) : 'N/A'; ?></div>
                    <div class="stat-label">Member Since</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $user_premium['chat_tag'] ? 'Custom' : 'Default'; ?></div>
                    <div class="stat-label">Chat Tag</div>
                </div>
            </div>

            <div class="panel-grid">
                <!-- Premium Upgrade / Status -->
                <div class="card">
                    <?php if ($is_premium): ?>
                        <h2><i class="fas fa-crown"></i> Premium Status</h2>
                        <div style="text-align: center; padding: 1rem;">
                            <div style="font-size: 4rem; margin-bottom: 1rem;">
                                <i class="fas fa-crown" style="color: var(--premium);"></i>
                            </div>
                            <h3 style="color: var(--premium); margin-bottom: 0.5rem;">You're a Premium Member!</h3>
                            <p style="color: #94a3b8;">
                                Member since: <?php echo $user_premium['premium_since'] ? date('F j, Y', strtotime($user_premium['premium_since'])) : 'Recently'; ?>
                            </p>
                            <p style="color: #94a3b8; margin-top: 1rem;">
                                Thank you for supporting Spencer's Website!
                            </p>
                        </div>
                    <?php elseif (hasRoleOrHigher($role, 'user')): ?>
                        <h2><i class="fas fa-check-circle" style="color: var(--secondary);"></i> Role Active</h2>
                        <div style="text-align: center; padding: 1.5rem;">
                            <div style="font-size: 4rem; margin-bottom: 1rem;">
                                <i class="fas <?php echo htmlspecialchars($roleInfo['icon']); ?>" style="color: <?php echo htmlspecialchars($roleInfo['color']); ?>;"></i>
                            </div>
                            <h3 style="color: <?php echo htmlspecialchars($roleInfo['color']); ?>; margin-bottom: 0.5rem;">
                                You're a <?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', $role))); ?>!
                            </h3>
                            <?php if (getRoleLevel($role) >= 2): ?>
                                <div style="display: inline-block; background: linear-gradient(135deg, rgba(245,158,11,0.2), rgba(234,179,8,0.2)); border: 1px solid rgba(245,158,11,0.4); border-radius: 8px; padding: 8px 20px; margin-bottom: 1rem;">
                                    <i class="fas fa-infinity" style="color: #f59e0b;"></i>
                                    <span style="color: #fbbf24; font-weight: 700; margin-left: 6px;">Lifetime Access</span>
                                </div>
                                <p style="color: #94a3b8; margin-bottom: 0.5rem;">
                                    Your role includes all premium features and above.
                                </p>
                                <div style="text-align: left; max-width: 280px; margin: 1rem auto;">
                                    <div style="color: #cbd5e1; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                        <i class="fas fa-check" style="color: #10b981;"></i> Custom backgrounds — <span style="color:#6ee7b7;">Included with your role</span>
                                    </div>
                                    <div style="color: #cbd5e1; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                        <i class="fas fa-check" style="color: #10b981;"></i> Chat tags — <span style="color:#6ee7b7;">Included with your role</span>
                                    </div>
                                    <div style="color: #cbd5e1; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                        <i class="fas fa-check" style="color: #10b981;"></i> AI assistant — <span style="color:#6ee7b7;">Included with your role</span>
                                    </div>
                                    <div style="color: #cbd5e1; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                        <i class="fas fa-check" style="color: #10b981;"></i> Personal storage — <span style="color:#6ee7b7;">Included with your role</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p style="color: #94a3b8; margin-bottom: 1rem;">
                                    You already have access to all User-level features and customizations.
                                </p>
                            <?php endif; ?>
                            <a href="set.php" class="btn btn-primary" style="margin-top: 1.5rem;">
                                <i class="fas fa-cog"></i> Customize in Settings
                            </a>
                        </div>
                    <?php else: ?>
                        <h2><i class="fas fa-rocket"></i> Upgrade to Premium</h2>
                        <div class="upgrade-section">
                            <h3>Unlock Premium Features</h3>
                            <div class="upgrade-price">From $2<span>/mo</span></div>
                            <ul class="benefits-list">
                                <li><i class="fas fa-check-circle"></i> Custom chat tag in Yaps</li>
                                <li><i class="fas fa-check-circle"></i> Personal background URL</li>
                                <li><i class="fas fa-check-circle"></i> 50MB personal storage</li>
                                <li><i class="fas fa-check-circle"></i> AI Assistant access</li>
                                <li><i class="fas fa-check-circle"></i> Priority support</li>
                            </ul>
                            <p class="upgrade-note">
                                <i class="fas fa-info-circle"></i>
                                Contact admin to upgrade. Payment handled offline.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- v7.0: Payment & Subscription Management -->
                <div class="card" style="border-top: 3px solid #6366f1;">
                    <h2><i class="fas fa-credit-card" style="color: #6366f1;"></i> Payment & Subscription</h2>
                    <?php
                    // Fetch subscription data
                    $activeSubscription = null;
                    $paymentHistory = [];
                    try {
                        if (function_exists('getActiveSubscription')) {
                            $activeSubscription = getActiveSubscription($db, $_SESSION['user_id']);
                        }
                        $phStmt = $db->prepare("SELECT created_at, provider, plan_type, status FROM payment_sessions WHERE user_id = ? AND status IN ('completed','active') ORDER BY created_at DESC LIMIT 10");
                        $phStmt->execute([$_SESSION['user_id']]);
                        $paymentHistory = $phStmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {}
                    ?>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                        <div style="background: rgba(99,102,241,0.1); padding: 1.25rem; border-radius: 12px; border: 1px solid rgba(99,102,241,0.2);">
                            <div style="font-size: 0.8rem; color: #94a3b8; text-transform: uppercase;">Current Plan</div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: #818cf8; margin-top: 0.25rem;">
                                <?php
                                if ($activeSubscription) {
                                    echo ucfirst($activeSubscription['plan_type'] ?? 'Monthly');
                                } elseif ($is_premium) {
                                    echo 'Lifetime';
                                } else {
                                    echo 'Free';
                                }
                                ?>
                            </div>
                        </div>
                        <div style="background: rgba(16,185,129,0.1); padding: 1.25rem; border-radius: 12px; border: 1px solid rgba(16,185,129,0.2);">
                            <div style="font-size: 0.8rem; color: #94a3b8; text-transform: uppercase;">Status</div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: #34d399; margin-top: 0.25rem;">
                                <?php echo $is_premium ? 'Active' : ($activeSubscription ? ucfirst($activeSubscription['status']) : 'Inactive'); ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($activeSubscription && !empty($activeSubscription['current_period_end'])): ?>
                    <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 1rem;">
                        <i class="fas fa-calendar-alt"></i> Next billing: <?php echo date('F j, Y', strtotime($activeSubscription['current_period_end'])); ?>
                        <?php if (!empty($activeSubscription['provider'])): ?>
                            | Provider: <strong><?php echo ucfirst($activeSubscription['provider']); ?></strong>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>

                    <?php if (!$is_premium): ?>
                    <a href="community.php" style="display: inline-block; padding: 0.75rem 2rem; background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 10px; color: #fff; font-weight: 700; text-decoration: none; margin-bottom: 1rem;">
                        <i class="fas fa-arrow-up"></i> Upgrade to Premium
                    </a>
                    <?php endif; ?>

                    <?php if (!empty($paymentHistory)): ?>
                    <details style="margin-top: 1rem;">
                        <summary style="cursor: pointer; color: #e2e8f0; font-weight: 600; padding: 0.5rem 0;"><i class="fas fa-history"></i> Payment History</summary>
                        <div style="margin-top: 0.75rem; overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                                <thead>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                        <th style="padding: 0.5rem; text-align: left; color: #94a3b8;">Date</th>
                                        <th style="padding: 0.5rem; text-align: left; color: #94a3b8;">Provider</th>
                                        <th style="padding: 0.5rem; text-align: left; color: #94a3b8;">Plan</th>
                                        <th style="padding: 0.5rem; text-align: left; color: #94a3b8;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paymentHistory as $ph): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                        <td style="padding: 0.5rem; color: #cbd5e1;"><?php echo date('M j, Y', strtotime($ph['created_at'])); ?></td>
                                        <td style="padding: 0.5rem; color: #cbd5e1;"><?php echo ucfirst($ph['provider'] ?? 'N/A'); ?></td>
                                        <td style="padding: 0.5rem; color: #cbd5e1;"><?php echo ucfirst($ph['plan_type'] ?? 'lifetime'); ?></td>
                                        <td style="padding: 0.5rem;"><span style="padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; background: <?php echo $ph['status'] === 'completed' ? 'rgba(16,185,129,0.2)' : 'rgba(245,158,11,0.2)'; ?>; color: <?php echo $ph['status'] === 'completed' ? '#34d399' : '#fbbf24'; ?>;"><?php echo ucfirst($ph['status']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                    <?php endif; ?>
                </div>

                <!-- Premium Features -->
                <div class="card">
                    <h2><i class="fas fa-magic"></i> Premium Features</h2>

                    <!-- Chat Tag -->
                    <div class="form-group <?php echo !$is_premium ? 'feature-locked' : ''; ?>">
                        <label class="form-label">Custom Chat Tag</label>
                        <input type="text" id="chatTagInput" class="form-control"
                               placeholder="e.g., VIP, Legend, Cool Guy"
                               maxlength="50"
                               value="<?php echo htmlspecialchars($user_premium['chat_tag'] ?? ''); ?>"
                               <?php echo !$is_premium ? 'disabled' : ''; ?>>
                        <small style="color: #64748b; display: block; margin-top: 0.5rem;">
                            This tag will appear next to your name in Yaps chat
                        </small>
                        <?php if ($is_premium): ?>
                            <button class="btn btn-primary" style="margin-top: 1rem;" onclick="updateChatTag()">
                                <i class="fas fa-save"></i> Save Chat Tag
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Custom Background -->
                    <div class="form-group <?php echo !$is_premium ? 'feature-locked' : ''; ?>" style="margin-top: 2rem;">
                        <label class="form-label">Personal Background URL</label>
                        <input type="url" id="backgroundUrlInput" class="form-control"
                               placeholder="https://example.com/my-background.jpg"
                               value="<?php echo htmlspecialchars($user_premium['custom_background_url'] ?? ''); ?>"
                               <?php echo !$is_premium ? 'disabled' : ''; ?>>
                        <small style="color: #64748b; display: block; margin-top: 0.5rem;">
                            Your personal background for game pages
                        </small>
                        <?php if ($is_premium): ?>
                            <button class="btn btn-primary" style="margin-top: 1rem;" onclick="updateBackground()">
                                <i class="fas fa-save"></i> Save Background
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- AI Assistant -->
                <div class="card">
                    <h2><i class="fas fa-robot"></i> AI Assistant</h2>
                    <?php if ($is_premium || $role === 'admin'): ?>
                        <div style="text-align: center; padding: 1.5rem;">
                            <div style="font-size: 4rem; margin-bottom: 1rem;">
                                <i class="fas fa-robot" style="color: var(--primary);"></i>
                            </div>
                            <h3 style="margin-bottom: 0.5rem;">Chat with AI</h3>
                            <p style="color: #94a3b8; margin-bottom: 1.5rem;">
                                Get help, ask questions, or just chat with our AI assistant.
                            </p>
                            <a href="ai_panel.php" class="btn btn-premium">
                                <i class="fas fa-comments"></i> Open AI Chat
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="feature-locked" style="padding: 3rem; text-align: center;">
                            <i class="fas fa-lock" style="font-size: 3rem; color: #64748b; margin-bottom: 1rem;"></i>
                            <p style="color: #94a3b8;">Upgrade to Premium to access the AI Assistant</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="navigation">
                <a href="main.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i>
                    Back to Main Site
                </a>
                <a href="yaps.php" class="btn btn-outline">
                    <i class="fas fa-comments"></i>
                    Yaps Chat
                </a>
                <?php if ($role === 'admin'): ?>
                    <a href="admin.php" class="btn btn-premium">
                        <i class="fas fa-cog"></i>
                        Admin Panel
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="notification" id="notification"></div>

    <script>
        // CSRF token
        const csrfToken = '<?php echo htmlspecialchars(generateCsrfToken()); ?>';

        function showNotification(message, isError = false) {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = 'notification show' + (isError ? ' error' : '');

            setTimeout(() => {
                notification.classList.remove('show');
            }, 4000);
        }

        async function updateChatTag() {
            const chatTag = document.getElementById('chatTagInput').value;

            try {
                const formData = new FormData();
                formData.append('ajax', '1');
                formData.append('csrf_token', csrfToken);
                formData.append('action', 'update_chat_tag');
                formData.append('chat_tag', chatTag);

                const response = await fetch('user_panel.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                showNotification(data.message, !data.success);
            } catch (error) {
                showNotification('Error updating chat tag', true);
            }
        }

        async function updateBackground() {
            const bgUrl = document.getElementById('backgroundUrlInput').value;

            try {
                const formData = new FormData();
                formData.append('ajax', '1');
                formData.append('csrf_token', csrfToken);
                formData.append('action', 'update_custom_background');
                formData.append('background_url', bgUrl);

                const response = await fetch('user_panel.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                showNotification(data.message, !data.success);
            } catch (error) {
                showNotification('Error updating background', true);
            }
        }
    </script>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
