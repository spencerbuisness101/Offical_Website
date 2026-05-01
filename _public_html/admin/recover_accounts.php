<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/csrf.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Check if user has admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die('<div style="color: red; text-align: center; padding: 20px;">❌ Access Denied: Admin privileges required</div>');
}

try {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    die('<div style="color: red; text-align: center; padding: 20px;">❌ Database Connection Error: ' . htmlspecialchars($e->getMessage()) . '</div>');
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Recovery - Admin Panel</title>
    <link rel="icon" type="image/webp" href="/assets/images/favicon.webp">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }

        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            border: 3px solid #4ECDC4;
            color: #2c3e50;
        }

        .welcome-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .welcome-header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #FF6B6B, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
        }

        .admin-section {
            background: rgba(0, 0, 0, 0.85);
            border: 3px solid #4ECDC4;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
            background: #1e293b;
            color: white;
            border-radius: 10px;
            overflow: hidden;
        }

        .user-table th {
            background: #4ECDC4;
            color: black;
            padding: 15px;
            text-align: left;
            font-weight: 700;
        }

        .user-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #334155;
        }

        .password-input {
            width: 200px;
            padding: 10px;
            border: 2px solid #4ECDC4;
            border-radius: 8px;
            font-size: 14px;
            margin-right: 10px;
            background: #f8f9fa;
            color: #2c3e50;
            font-weight: 500;
        }

        .submit-btn {
            background: linear-gradient(45deg, #FF6B6B, #4ECDC4);
            color: #ffffff;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
        }

        .create-user-form {
            background: rgba(0, 0, 0, 0.85);
            padding: 25px;
            border-radius: 15px;
            border: 3px solid #4ECDC4;
            max-width: 500px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #4ECDC4;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #4ECDC4;
            border-radius: 8px;
            font-size: 16px;
            background: #f8f9fa;
            color: #2c3e50;
            box-sizing: border-box;
        }

        .message {
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
        }

        .success {
            background: rgba(46, 204, 113, 0.9);
            color: white;
        }

        .error {
            background: rgba(231, 76, 60, 0.9);
            color: white;
        }

        .logout-btn {
            position: fixed;
            top: 25px;
            right: 25px;
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            color: #ffffff;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            z-index: 1001;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
        }

        .back-btn {
            background: linear-gradient(45deg, #3498db, #2980b9);
            color: #ffffff;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <button class="logout-btn" onclick="logout()">🚪 Logout</button>

    <div class="admin-container">
        <div class="welcome-header">
            <h1>🔓 Admin Account Recovery</h1>
            <p style="color: #5a6c7d;">Manage user accounts and reset passwords</p>
        </div>

        <?php
        try {
            // Get all users from database
            $stmt = $db->query("SELECT id, username, role, created_at FROM users ORDER BY id");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo '<div class="admin-section">';
            echo '<h3 style="color: #4ECDC4; text-align: center; margin-bottom: 20px;">📋 Existing Users in Database</h3>';
            
            if (empty($users)) {
                echo '<div class="error message">No users found in database!</div>';
            } else {
                echo '<table class="user-table">';
                echo '<tr>';
                echo '<th>ID</th><th>Username</th><th>Role</th><th>Created</th><th>Action</th>';
                echo '</tr>';
                
                foreach ($users as $user) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($user['id']) . '</td>';
                    echo '<td><strong>' . htmlspecialchars($user['username']) . '</strong></td>';
                    echo '<td>' . htmlspecialchars($user['role']) . '</td>';
                    echo '<td>' . htmlspecialchars($user['created_at']) . '</td>';
                    echo '<td>';
                    echo '<form method="POST" style="display: inline;">';
                    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">';
                    echo '<input type="hidden" name="username" value="' . htmlspecialchars($user['username']) . '">';
                    echo '<input type="hidden" name="action" value="reset_password">';
                    echo '<input type="text" name="new_password" placeholder="New password" required class="password-input">';
                    echo '<button type="submit" class="submit-btn">Reset Password</button>';
                    echo '</form>';
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
            echo '</div>';
            
            // Handle password reset
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
                if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                    echo '<div class="error message">❌ Invalid CSRF token. Please refresh and try again.</div>';
                } else {
                $username = $_POST['username'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                
                if (!empty($username) && !empty($new_password)) {
                    $password_hash = password_hash($new_password, PASSWORD_ARGON2ID);
                    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
                    $stmt->execute([$password_hash, $username]);
                    
                    echo '<div class="success message">';
                    echo '✅ Password reset for <strong>' . htmlspecialchars($username) . '</strong>';
                    echo '</div>';
                }
                }
            }
            
        } catch (Exception $e) {
            echo '<div class="error message">❌ Database Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>

        <div class="admin-section">
            <h3 style="color: #4ECDC4; text-align: center; margin-bottom: 20px;">👑 Create New Admin User</h3>
            <form method="POST" class="create-user-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="create_user">
                <div class="form-group">
                    <label>Username:</label>
                    <input type="text" name="username" required class="form-input">
                </div>
                <div class="form-group">
                    <label>Password:</label>
                    <input type="text" name="password" required class="form-input">
                </div>
                <button type="submit" class="submit-btn" style="width: 100%;">Create Admin User</button>
            </form>
        </div>

        <?php
        // Handle new user creation
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                echo '<div class="error message">❌ Invalid CSRF token. Please refresh and try again.</div>';
            } else {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (!empty($username) && !empty($password)) {
                try {
                    $password_hash = password_hash($password, PASSWORD_ARGON2ID);
                    $stmt = $db->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')");
                    $stmt->execute([$username, $password_hash]);
                    
                    echo '<div class="success message">';
                    echo '✅ New admin user created: <strong>' . htmlspecialchars($username) . '</strong>';
                    echo '</div>';
                } catch (Exception $e) {
                    echo '<div class="error message">❌ Error creating user: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
            }
        }
        ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="../main.php" class="back-btn">↩ Back to Main Panel</a>
        </div>

        <div style="text-align: center; margin-top: 20px; padding: 15px; background: rgba(231, 76, 60, 0.9); border-radius: 8px;">
            <p style="color: white; font-weight: 700; margin: 0;">
                ⚠️ SECURITY NOTICE: This page should be deleted in production environment!
            </p>
        </div>
    </div>

    <script>
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                const _csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                window.location.href = '../auth/logout.php?csrf_token=' + encodeURIComponent(_csrfToken);
            }
        }
    </script>
</body>
</html>