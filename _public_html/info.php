<?php
require_once __DIR__ . '/includes/init.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Get user info for display
$username = htmlspecialchars($_SESSION['username']);
$role = htmlspecialchars($_SESSION['role']);
$user_id = $_SESSION['user_id'];

// Get dynamic statistics
$gamesCount = 40;
$usersCount = 50;
try {
    require_once __DIR__ . '/config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    if ($db) {
        $gamesCount = $db->query("SELECT COUNT(*) FROM games WHERE is_active = 1")->fetchColumn() ?: 40;
        $usersCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 50;
    }
} catch (Exception $e) {
    // Use fallback values set above
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <script src="js/tracking.js?v=7.0" defer></script>
    <script src="common.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/assets/images/favicon.webp">
    <link rel="stylesheet" href="css/tokens.css">
    <title>Information - Spencer's Website</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        body {
            background: var(--gradient);
            color: var(--dark);
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
            position: relative;
        }

        /* Animated Background Elements */
        .bg-elements {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .bg-element {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 15s infinite linear;
        }

        @keyframes float {
            0% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
            100% { transform: translateY(0) rotate(360deg); }
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header Styles */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--light);
            color: var(--primary);
            padding: 12px 24px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 2px solid transparent;
        }

        .back-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 20px rgba(0,0,0,0.15);
            background: white;
            border-color: var(--success);
        }

        .control-buttons {
            display: flex;
            gap: 10px;
        }

        .control-btn {
            padding: 10px 15px;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }

        .home-btn {
            background: var(--light);
            color: var(--primary);
        }

        .settings-btn {
            background: var(--primary);
            color: white;
        }

        .logout-btn {
            background: var(--danger);
            color: white;
        }

        .control-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        .page-title {
            text-align: center;
            color: white;
            margin-bottom: 10px;
            font-size: 3rem;
            font-weight: 800;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
            background: linear-gradient(45deg, #fff 30%, #4cc9f0 70%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-subtitle {
            text-align: center;
            color: rgba(255,255,255,0.8);
            margin-bottom: 30px;
            font-size: 1.2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Stats Section */
        .stats-container {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 20px;
            text-align: center;
            color: white;
            min-width: 150px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* Tab Navigation */
        .tab-navigation {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 30px;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 15px;
            border-radius: var(--border-radius);
        }

        .tab-btn {
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 30px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .tab-btn.active {
            background: white;
            color: var(--primary);
            box-shadow: var(--shadow);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Main Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .info-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border-top: 4px solid var(--primary);
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        .info-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border: 3px solid transparent;
            border-radius: var(--border-radius);
            transition: var(--transition);
            z-index: 1;
            pointer-events: none;
        }

        .info-card:hover::before {
            border-color: var(--success);
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.4);
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .info-card.warning {
            border-top-color: var(--warning);
        }

        .info-card.success {
            border-top-color: var(--success);
        }

        .info-card.danger {
            border-top-color: var(--danger);
        }

        .card-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--primary);
            transition: var(--transition);
        }

        .info-card:hover .card-icon {
            transform: scale(1.1);
        }

        .warning .card-icon {
            color: var(--warning);
        }

        .success .card-icon {
            color: var(--success);
        }

        .danger .card-icon {
            color: var(--danger);
        }

        .card-title {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: var(--dark);
            font-weight: 700;
            transition: var(--transition);
        }

        .info-card:hover .card-title {
            color: var(--success);
        }

        .card-content {
            color: var(--gray);
            flex-grow: 1;
            transition: var(--transition);
        }

        .info-card:hover .card-content {
            color: #4b5563;
        }

        .card-content ul {
            padding-left: 20px;
            margin-top: 10px;
        }

        .card-content li {
            margin-bottom: 8px;
            transition: var(--transition);
        }

        .info-card:hover .card-content li {
            color: #4b5563;
        }

        .contact-email {
            color: var(--success);
            font-weight: bold;
            text-decoration: none;
            transition: var(--transition);
        }

        .contact-email:hover {
            color: var(--primary);
            text-decoration: underline;
        }

        /* Tech Stack Section */
        .tech-stack {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .tech-tag {
            background: var(--primary);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* FAQ Accordion */
        .faq-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .faq-title {
            font-size: 1.8rem;
            margin-bottom: 25px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .accordion-item {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 15px;
            overflow: hidden;
            transition: var(--transition);
        }

        .accordion-item:hover {
            border-color: var(--primary);
        }

        .accordion-header {
            background: var(--light);
            padding: 18px 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: var(--dark);
            transition: var(--transition);
        }

        .accordion-header:hover {
            background: #e9ecef;
        }

        .accordion-header.active {
            background: var(--primary);
            color: white;
        }

        .accordion-icon {
            transition: transform 0.3s ease;
            font-size: 1.2rem;
        }

        .accordion-header.active .accordion-icon {
            transform: rotate(180deg);
        }

        .accordion-body {
            padding: 0;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
            background: white;
        }

        .accordion-body.active {
            padding: 20px;
            max-height: 500px;
        }

        .accordion-body p {
            color: var(--gray);
            line-height: 1.7;
        }

        /* Version History */
        .version-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .version-title {
            font-size: 1.8rem;
            margin-bottom: 25px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .version-timeline {
            position: relative;
            padding-left: 30px;
        }

        .version-timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(180deg, var(--primary), var(--accent));
            border-radius: 3px;
        }

        .version-item {
            position: relative;
            margin-bottom: 25px;
            padding: 20px;
            background: var(--light);
            border-radius: 12px;
            transition: var(--transition);
        }

        .version-item:hover {
            transform: translateX(10px);
            box-shadow: var(--shadow);
        }

        .version-item::before {
            content: '';
            position: absolute;
            left: -26px;
            top: 25px;
            width: 14px;
            height: 14px;
            background: var(--primary);
            border: 3px solid white;
            border-radius: 50%;
            box-shadow: 0 0 0 3px var(--primary);
        }

        .version-item.current::before {
            background: var(--success);
            box-shadow: 0 0 0 3px var(--success);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 3px var(--success); }
            50% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0.3); }
        }

        .version-number {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .version-item.current .version-number {
            color: var(--success);
        }

        .version-date {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 10px;
        }

        .version-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 10px;
        }

        .badge-current {
            background: var(--success);
            color: white;
        }

        .badge-major {
            background: var(--primary);
            color: white;
        }

        .version-changes {
            list-style: none;
            padding: 0;
        }

        .version-changes li {
            padding: 5px 0;
            color: var(--gray);
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .version-changes li::before {
            content: '•';
            color: var(--primary);
            font-weight: bold;
        }

        /* Quick Start Guide */
        .quickstart-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .quickstart-title {
            font-size: 1.8rem;
            margin-bottom: 25px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .step-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .step-card {
            background: var(--light);
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            transition: var(--transition);
            position: relative;
        }

        .step-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow);
        }

        .step-number {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 35px;
            height: 35px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .step-icon {
            font-size: 2.5rem;
            margin: 15px 0;
        }

        .step-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .step-desc {
            color: var(--gray);
            font-size: 0.95rem;
        }

        /* Report Issues Section */
        .report-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .report-title {
            font-size: 1.8rem;
            margin-bottom: 25px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .report-card {
            background: var(--light);
            border-radius: 12px;
            padding: 25px;
            transition: var(--transition);
            border-left: 4px solid var(--primary);
        }

        .report-card:hover {
            transform: translateX(5px);
            box-shadow: var(--shadow);
        }

        .report-card.bug {
            border-left-color: var(--danger);
        }

        .report-card.feature {
            border-left-color: var(--success);
        }

        .report-card.feedback {
            border-left-color: var(--warning);
        }

        .report-card-icon {
            font-size: 2rem;
            margin-bottom: 15px;
        }

        .report-card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .report-card-desc {
            color: var(--gray);
            margin-bottom: 15px;
            font-size: 0.95rem;
        }

        .report-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }

        .report-card.bug .report-btn {
            background: var(--danger);
        }

        .report-card.feature .report-btn {
            background: var(--success);
        }

        .report-card.feedback .report-btn {
            background: var(--warning);
        }

        .report-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        /* Footer Section */
        .footer-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            text-align: center;
            border-top: 4px solid var(--warning);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }

        .footer-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border: 3px solid transparent;
            border-radius: var(--border-radius);
            transition: var(--transition);
            z-index: 1;
            pointer-events: none;
        }

        .footer-section:hover::before {
            border-color: var(--success);
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.4);
        }

        .footer-section:hover {
            transform: translateY(-3px);
        }

        .footer-title {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: var(--dark);
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--transition);
        }

        .footer-section:hover .footer-title {
            color: var(--success);
        }

        /* User Info Section */
        .user-info {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: var(--transition);
        }

        .user-info:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .user-details h3 {
            color: var(--dark);
            margin-bottom: 5px;
        }

        .user-details p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Quick Links */
        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
            justify-content: center;
        }

        .quick-link {
            background: var(--light);
            color: var(--primary);
            padding: 8px 15px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .quick-link:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        /* Smooth Scroll Target Highlight */
        .highlight-section {
            animation: highlightPulse 1s ease;
        }

        @keyframes highlightPulse {
            0%, 100% { box-shadow: var(--shadow); }
            50% { box-shadow: 0 0 0 4px var(--accent), var(--shadow); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }

            .page-title {
                font-size: 2.2rem;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .stats-container {
                justify-content: center;
            }

            .control-buttons {
                width: 100%;
                justify-content: center;
            }

            .tab-navigation {
                flex-direction: column;
                align-items: stretch;
            }

            .tab-btn {
                justify-content: center;
            }

            .version-timeline {
                padding-left: 25px;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 1.8rem;
            }

            .info-card, .faq-section, .version-section, .quickstart-section, .report-section {
                padding: 20px;
            }

            .user-info {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/identity_bar.php'; ?>
    <!-- Animated Background Elements -->
    <div class="bg-elements" id="bgElements"></div>

    <div class="container">
        <div class="header">
            <a href="main.php" class="back-btn">
                <span>←</span> Back to Main Site
            </a>
        </div>

        <!-- User Info -->
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
            <div class="user-details">
                <h3>Welcome, <?php echo $username; ?>!</h3>
                <p>Role: <?php echo ucfirst($role); ?> • Last login: Recently</p>
            </div>
        </div>

        <h1 class="page-title">ℹ️ Website Information</h1>
        <p class="page-subtitle">Learn more about this website's features, technology, and guidelines</p>

        <!-- Stats Section -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-value"><?php echo $gamesCount; ?>+</div>
                <div class="stat-label">Games Available</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $usersCount; ?>+</div>
                <div class="stat-label">Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">99%</div>
                <div class="stat-label">Uptime</div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-btn active" onclick="switchTab('overview')">
                📋 Overview
            </button>
            <button class="tab-btn" onclick="switchTab('quickstart')">
                🚀 Quick Start
            </button>
            <button class="tab-btn" onclick="switchTab('faq')">
                ❓ FAQ
            </button>
            <button class="tab-btn" onclick="switchTab('versions')">
                📜 Version History
            </button>
            <button class="tab-btn" onclick="switchTab('report')">
                📝 Report Issues
            </button>
        </div>

        <!-- Overview Tab -->
        <div id="tab-overview" class="tab-content active">
            <div class="content-grid">
                <div class="info-card">
                    <div class="card-icon">👨‍💻</div>
                    <h3 class="card-title">About This Website</h3>
                    <div class="card-content">
                        <p>This website was created by Spencer for educational and entertainment purposes. It features games, AI chat, and community features. Lexi was the designer and idea for games and community features.</p>
                        <p>The platform is continuously updated with new features and content to enhance user experience.</p>
                    </div>
                </div>

                <div class="info-card success">
                    <div class="card-icon">🚀</div>
                    <h3 class="card-title">Website Features</h3>
                    <div class="card-content">
                        <ul>
                            <li>HTML Games Collection</li>
                            <li>Game Library</li>
                            <li>Secure Password Protection</li>
                            <li>Responsive Design</li>
                            <li>Regular Updates</li>
                            <li>Background Customization</li>
                            <li>User Settings Panel</li>
                            <li>Community Features</li>
                        </ul>
                    </div>
                </div>

                <div class="info-card">
                    <div class="card-icon">💻</div>
                    <h3 class="card-title">Technology Used</h3>
                    <div class="card-content">
                        <p>Built with modern web technologies for optimal performance and user experience.</p>
                        <div class="tech-stack">
                            <span class="tech-tag">HTML5</span>
                            <span class="tech-tag">CSS3</span>
                            <span class="tech-tag">JavaScript</span>
                            <span class="tech-tag">PHP</span>
                            <span class="tech-tag">MySQL</span>
                            <span class="tech-tag">Responsive Design</span>
                        </div>
                    </div>
                </div>

                <div class="info-card warning">
                    <div class="card-icon">🔒</div>
                    <h3 class="card-title">Privacy & Security</h3>
                    <div class="card-content">
                        <p>This website uses client-side password protection and secure session management. All content is intended for educational and personal use only.</p>
                        <p>We prioritize user privacy and do not share personal information with third parties.</p>
                    </div>
                </div>

                <div class="info-card">
                    <div class="card-icon">📞</div>
                    <h3 class="card-title">Contact Information</h3>
                    <div class="card-content">
                        <p>For any questions or concerns regarding this website, you must contact Spencer directly.</p>
                        <p>We value your feedback and are always looking to improve the platform.</p>
                    </div>
                </div>

                <div class="info-card success">
                    <div class="card-icon">🤔</div>
                    <h3 class="card-title">Make Suggestions</h3>
                    <div class="card-content">
                        <p>Have ideas to improve our website? We'd love to hear them!</p>
                        <p>Email: <a href="mailto:spencerbuisness101@gmail.com" class="contact-email">spencerbuisness101@gmail.com</a></p>
                        <p>Your suggestions help us create a better experience for everyone.</p>
                    </div>
                </div>
            </div>

            <div class="footer-section">
                <h3 class="footer-title">⚠️ Important Notice</h3>
                <p class="card-content">Sharing access to this website may result in a ban; therefore, it is advised not to share your login credentials with others.</p>
            </div>
        </div>

        <!-- Quick Start Tab -->
        <div id="tab-quickstart" class="tab-content">
            <div class="quickstart-section">
                <h2 class="quickstart-title">🚀 Quick Start Guide</h2>
                <p style="color: var(--gray); margin-bottom: 25px;">New to the website? Follow these steps to get started!</p>

                <div class="step-grid">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <div class="step-icon">🔐</div>
                        <h4 class="step-title">Login</h4>
                        <p class="step-desc">Use your provided credentials to log into the website. Keep your password secure!</p>
                    </div>

                    <div class="step-card">
                        <div class="step-number">2</div>
                        <div class="step-icon">🏠</div>
                        <h4 class="step-title">Explore Main Page</h4>
                        <p class="step-desc">Browse the main page to see announcements, quick access panels, and navigation options.</p>
                    </div>

                    <div class="step-card">
                        <div class="step-number">3</div>
                        <div class="step-icon">🎮</div>
                        <h4 class="step-title">Play Games</h4>
                        <p class="step-desc">Visit the Games section to browse and play from our collection of HTML games.</p>
                    </div>

                    <div class="step-card">
                        <div class="step-number">4</div>
                        <div class="step-icon">🎬</div>
            <h4 class="step-title">Play Games</h4>
            <p class="step-desc">Browse the game library and find something new to play.</p>
                    </div>

                    <div class="step-card">
                        <div class="step-number">5</div>
                        <div class="step-icon">⚙️</div>
                        <h4 class="step-title">Customize Settings</h4>
                        <p class="step-desc">Visit Settings to change your background, update preferences, and personalize your experience.</p>
                    </div>

                    <div class="step-card">
                        <div class="step-number">6</div>
                        <div class="step-icon">👤</div>
                        <h4 class="step-title">User Panel</h4>
                        <p class="step-desc">Access your User Panel to view your profile, check achievements, and manage your account.</p>
                    </div>
                </div>
            </div>

            <div class="info-card" style="margin-bottom: 30px;">
                <div class="card-icon">💡</div>
                <h3 class="card-title">Pro Tips</h3>
                <div class="card-content">
                    <ul>
                        <li><strong>Keyboard Shortcuts:</strong> Press <code>Esc</code> while in a game to return to the game page</li>
                        <li><strong>Fullscreen:</strong> Most games support fullscreen mode - look for the fullscreen button</li>
                        <li><strong>Custom Backgrounds:</strong> You can set custom backgrounds in Settings</li>
                        <li><strong>Announcements:</strong> Check the main page regularly for new announcements</li>
                        <li><strong>Updates:</strong> Visit the Updates page to see what's new</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- FAQ Tab -->
        <div id="tab-faq" class="tab-content">
            <div class="faq-section">
                <h2 class="faq-title">❓ Frequently Asked Questions</h2>

                <div class="accordion-item">
                    <div class="accordion-header" onclick="toggleAccordion(this)">
                        <span>How do I get an account?</span>
                        <span class="accordion-icon">▼</span>
                    </div>
                    <div class="accordion-body">
                        <p>Accounts are created by Spencer. If you need an account, contact Spencer directly. This is a private website, so accounts are provided on an invite-only basis.</p>
                    </div>
                </div>

                <div class="accordion-item">
                    <div class="accordion-header" onclick="toggleAccordion(this)">
                        <span>Can I share my account with others?</span>
                        <span class="accordion-icon">▼</span>
                    </div>
                    <div class="accordion-body">
                        <p>Yes, It isnt recommmended however you may at your will. (I say this because having your own account means more free will)</p>
                    </div>
                </div>

                <div class="accordion-item">
                    <div class="accordion-header" onclick="toggleAccordion(this)">
                        <span>How do I change my password?</span>
                        <span class="accordion-icon">▼</span>
                    </div>
                    <div class="accordion-body">
                        <p>Must contact the administrator or creator for changing your password</p>
                    </div>
                </div>

                <div class="accordion-item">
                    <div class="accordion-header" onclick="toggleAccordion(this)">
                        <span>A game isn't loading. What should I do?</span>
                        <span class="accordion-icon">▼</span>
                    </div>
                    <div class="accordion-body">
                        <p>Try these steps: 1) Refresh the page, 2) Clear your browser cache, 3) Check your internet connection. If the issue persists, report it through the Report Issues section or contact Spencer. (STRICTLY SPENCER)</p>
                    </div>
                </div>

                <div class="accordion-item">
                    <div class="accordion-header" onclick="toggleAccordion(this)">
                        <span>How do I suggest a new game?</span>
                        <span class="accordion-icon">▼</span>
                    </div>
                    <div class="accordion-body">
                        <p>You can suggest new content by emailing spencerbuisness101@gmail.com, using the User Panel's suggestion feature (if available), or contacting Spencer directly. We review all suggestions! (EXCEPT FOR OBVIOSULY DUMB ONES)</p>
                    </div>
                </div>

                <div class="accordion-item">
                    <div class="accordion-header" onclick="toggleAccordion(this)">
                        <span>What are the different user roles?</span>
                        <span class="accordion-icon">▼</span>
                    </div>
                    <div class="accordion-body">
                        <p>There are several roles: <strong>Community</strong> (free, basic access), <strong>User</strong> (full access, paid), <strong>Contributor</strong> (can submit ideas), <strong>Designer</strong> (can submit designs), and <strong>Admin</strong> (Controls everything OWNER). Visit the Role Ranking page for more details.</p>
                    </div>
                </div>

                <div class="accordion-item">
                    <div class="accordion-header" onclick="toggleAccordion(this)">
                        <span>How do I change my background?</span>
                        <span class="accordion-icon">▼</span>
                    </div>
                    <div class="accordion-body">
                        <p>Click the Backgrounds button on the main page or any game page. You can choose from preset backgrounds or upload your own custom background. If your account is a user or a role above. You can freely choose your own background in the settings page.</p>
                    </div>
                </div>

                <div class="accordion-item">
                    <div class="accordion-header" onclick="toggleAccordion(this)">
                        <span>Is my data safe on this website?</span>
                        <span class="accordion-icon">▼</span>
                    </div>
                    <div class="accordion-body">
                        <p>Yes! We use secure session management and encrypted password storage. We don't collect unnecessary data and never share your information with third parties. Only essential data for website functionality is stored. (SAFE)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Version History Tab -->
        <div id="tab-versions" class="tab-content">
            <div class="version-section">
                <h2 class="version-title">📜 Version History</h2>
                <p style="color: var(--gray); margin-bottom: 25px;">Track our progress and see what's changed over time.</p>

                <div class="version-timeline">
                    <div class="version-item current">
                        <div class="version-number">
                            Version 7.0
                            <span class="version-badge badge-current">Current</span>
                        </div>
                        <div class="version-date">February 2026</div>
                        <ul class="version-changes">
                            <li>Multi-Layer Device Tracking & Fingerprinting</li>
                            <li>Admin Panel Overhaul with Unified Tracking</li>
                            <li>Full page redesigns (Game, Chat, HoF, AI, Settings)</li>
                            <li>Feedback Panel, Image Analysis, Payment Management</li>
                            <li>Fixed multiple amount of games</li>
                            <li>Fixed some settings functions</li>
                            <li>admin beta feedback tab replys actually work</li>
                            <li>Enhanced announcement system with custom colors and priority</li>
                            <li>AI panel adjustments</li>
                            <li>CST timezone standardization</li>
                            <li>Performance and security improvements</li>
                        </ul>
                    </div>

                    <div class="version-item">
                        <div class="version-number">
                            Version 5.0
                            <span class="version-badge badge-major">Major</span>
                        </div>
                        <div class="version-date">January 2026</div>
                        <ul class="version-changes">
                            <li>Complete UI redesign with modern look</li>
                            <li>Added user roles and permissions system</li>
                            <li>Introduced background customization</li>
                            <li>New announcements system</li>
                            <li>Added user panel with profile features</li>
                        </ul>
                    </div>

                    <div class="version-item">
                        <div class="version-number">Version 1.5</div>
                        <div class="version-date">December 2025</div>
                        <ul class="version-changes">
                            <li>Added game library section</li>
                            <li>Improved game loading times</li>
                            <li>Added settings panel</li>
                            <li>Mobile responsiveness improvements</li>
                        </ul>
                    </div>

                    <div class="version-item">
                        <div class="version-number">Version 1.0</div>
                        <div class="version-date">November 2025</div>
                        <ul class="version-changes">
                            <li>Initial website launch</li>
                            <li>Basic game collection</li>
                            <li>User authentication system</li>
                            <li>Core navigation structure</li>
                        </ul>
                    </div>
                </div>

                <div style="margin-top: 25px; text-align: center;">
                    <a href="up.php" class="report-btn" style="background: var(--primary);">
                        📋 View Full Update Log
                    </a>
                </div>
            </div>
        </div>

        <!-- Report Issues Tab -->
        <div id="tab-report" class="tab-content">
            <div class="report-section">
                <h2 class="report-title">📝 Report Issues & Feedback</h2>
                <p style="color: var(--gray); margin-bottom: 25px;">Help us improve by reporting bugs, suggesting features, or giving feedback.</p>

                <div class="report-grid">
                    <div class="report-card bug">
                        <div class="report-card-icon">🐛</div>
                        <h3 class="report-card-title">Report a Bug</h3>
                        <p class="report-card-desc">Found something broken? Games not loading? Errors appearing? Let us know so we can fix it.</p>
                        <a href="mailto:spencerbuisness101@gmail.com" class="report-btn">Report Bug</a>
                    </div>

                    <div class="report-card feature">
                        <div class="report-card-icon">💡</div>
                        <h3 class="report-card-title">Suggest a Feature</h3>
                        <p class="report-card-desc">Have an idea for a new feature or improvement? We'd love to hear your suggestions!</p>
                        <a href="mailto:spencerbuisness101@gmail.com" class="report-btn">Suggest Feature</a>
                    </div>

                    <div class="report-card feedback">
                        <div class="report-card-icon">💬</div>
                        <h3 class="report-card-title">General Feedback</h3>
                        <p class="report-card-desc">Want to share your thoughts about the website? Your feedback helps us improve.</p>
                        <a href="mailto:spencerbuisness101@gmail.com" class="report-btn">Send Feedback</a>
                    </div>
                </div>
            </div>

            <div class="info-card warning" style="margin-bottom: 30px;">
                <div class="card-icon">📋</div>
                <h3 class="card-title">When Reporting Issues</h3>
                <div class="card-content">
                    <p>Please include the following information to help us resolve your issue faster:</p>
                    <ul>
                        <li><strong>What happened:</strong> Describe the problem in detail</li>
                        <li><strong>Expected behavior:</strong> What should have happened?</li>
                        <li><strong>Steps to reproduce:</strong> How can we recreate the issue?</li>
                        <li><strong>Browser/Device:</strong> What are you using? (Chrome, Firefox, Phone, etc.)</li>
                        <li><strong>Screenshots:</strong> If possible, include screenshots of the error</li>
                    </ul>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Create animated background elements
        function createBackgroundElements() {
            const bgContainer = document.getElementById('bgElements');
            const colors = ['rgba(255,255,255,0.1)', 'rgba(255,255,255,0.05)', 'rgba(255,255,255,0.07)'];

            for (let i = 0; i < 15; i++) {
                const element = document.createElement('div');
                element.classList.add('bg-element');

                // Random properties
                const size = Math.random() * 100 + 50;
                const color = colors[Math.floor(Math.random() * colors.length)];
                const left = Math.random() * 100;
                const top = Math.random() * 100;
                const animationDuration = Math.random() * 20 + 10;

                element.style.width = `${size}px`;
                element.style.height = `${size}px`;
                element.style.background = color;
                element.style.left = `${left}%`;
                element.style.top = `${top}%`;
                element.style.animationDuration = `${animationDuration}s`;
                element.style.animationDelay = `${Math.random() * 5}s`;

                bgContainer.appendChild(element);
            }
        }

        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                const _csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                window.location.href = 'auth/logout.php?csrf_token=' + encodeURIComponent(_csrfToken);
            }
        }

        // Tab switching
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById('tab-' + tabName).classList.add('active');

            // Add active to clicked button
            event.target.closest('.tab-btn').classList.add('active');

            // Smooth scroll to top of content
            document.querySelector('.tab-navigation').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Accordion toggle
        function toggleAccordion(header) {
            const body = header.nextElementSibling;
            const isActive = header.classList.contains('active');

            // Close all accordions
            document.querySelectorAll('.accordion-header').forEach(h => {
                h.classList.remove('active');
                h.nextElementSibling.classList.remove('active');
            });

            // Open clicked one if it wasn't active
            if (!isActive) {
                header.classList.add('active');
                body.classList.add('active');
            }
        }

        // Handle URL hash for direct tab linking
        function handleHashNavigation() {
            const hash = window.location.hash.replace('#', '');
            const validTabs = ['overview', 'quickstart', 'faq', 'versions', 'report'];

            if (validTabs.includes(hash)) {
                // Find and click the appropriate tab button
                const tabBtns = document.querySelectorAll('.tab-btn');
                tabBtns.forEach(btn => {
                    if (btn.textContent.toLowerCase().includes(hash.substring(0, 4))) {
                        btn.click();
                    }
                });
            }
        }

        // Add subtle animations when page loads
        document.addEventListener('DOMContentLoaded', function() {
            createBackgroundElements();
            handleHashNavigation();

            const cards = document.querySelectorAll('.info-card, .footer-section, .stat-card, .user-info');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 * index);
            });

            // Add click effects to cards
            document.querySelectorAll('.info-card, .stat-card').forEach(card => {
                card.addEventListener('click', function() {
                    this.style.transform = 'scale(0.98)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });
        });

        // Listen for hash changes
        window.addEventListener('hashchange', handleHashNavigation);
    </script>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
