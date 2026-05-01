<?php
require_once __DIR__ . '/includes/init.php';

// background_system.php - corrected and simplified

class BackgroundSystem {
    private $db;

    public function __construct() {
        try {
            require_once 'config/database.php';
            $database = new Database();
            $this->db = $database->getConnection();
            $this->createTablesIfNeeded();
        } catch (Exception $e) {
            error_log("Background system DB error: " . $e->getMessage());
        }
    }

    private function createTablesIfNeeded() {
        try {
            $checkTable = $this->db->query("SHOW TABLES LIKE 'user_background_preferences'");
            if ($checkTable->rowCount() == 0) {
                $sql = "
                CREATE TABLE IF NOT EXISTS user_background_preferences (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    background_id INT NOT NULL,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (background_id) REFERENCES designer_backgrounds(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_user_background (user_id),
                    INDEX idx_user_id (user_id),
                    INDEX idx_is_active (is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ";
                $this->db->exec($sql);
            }
        } catch (Exception $e) {
            error_log("Table creation error: " . $e->getMessage());
        }
    }

    public function getUserBackground($user_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT db.id, db.image_url, db.title, db.description, u.username as designer_name, ubp.created_at
                FROM user_background_preferences ubp
                JOIN designer_backgrounds db ON ubp.background_id = db.id
                LEFT JOIN users u ON db.user_id = u.id
                WHERE ubp.user_id = ? AND ubp.is_active = 1 AND db.status = 'approved'
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get user background error: " . $e->getMessage());
            return null;
        }
    }

    public function getAvailableBackgrounds() {
        try {
            $stmt = $this->db->query("
                SELECT db.id, db.image_url, db.title, db.description, u.username as designer_name, db.is_active
                FROM designer_backgrounds db
                LEFT JOIN users u ON db.user_id = u.id
                WHERE db.status = 'approved'
                ORDER BY db.is_active DESC, db.created_at DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get available backgrounds error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if user role can access background features
     */
    private function canAccessBackgrounds() {
        $user_role = $_SESSION['role'] ?? 'community';
        return in_array($user_role, ['admin', 'designer', 'contributor', 'user']);
    }

    /**
     * Set a user's background preference. This deactivates any other active rows for that user,
     * then updates existing row or inserts a new one.
     */
    public function setUserBackground($user_id, $background_id) {
        try {
            // Check role permission
            if (!$this->canAccessBackgrounds()) {
                return ['success' => false, 'message' => 'Background customization is not available for community accounts'];
            }

            // Validate background exists and is approved
            $checkStmt = $this->db->prepare("SELECT id FROM designer_backgrounds WHERE id = ? AND status = 'approved'");
            $checkStmt->execute([$background_id]);
            if (!$checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Background not found or not approved'];
            }

            $this->db->beginTransaction();

            // Deactivate all other preferences for this user
            $deactivateStmt = $this->db->prepare("UPDATE user_background_preferences SET is_active = FALSE WHERE user_id = ?");
            $deactivateStmt->execute([$user_id]);

            // Check if preference row already exists for this user
            $checkPrefStmt = $this->db->prepare("SELECT id FROM user_background_preferences WHERE user_id = ?");
            $checkPrefStmt->execute([$user_id]);
            $existing = $checkPrefStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing preference row
                $updateStmt = $this->db->prepare("UPDATE user_background_preferences SET background_id = ?, is_active = TRUE, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
                $updateStmt->execute([$background_id, $user_id]);
            } else {
                // Insert a new preference row (unique on user_id ensures only one row per user)
                $insertStmt = $this->db->prepare("INSERT INTO user_background_preferences (user_id, background_id, is_active) VALUES (?, ?, TRUE)");
                $insertStmt->execute([$user_id, $background_id]);
            }

            $this->db->commit();

            // Return basic background info
            $bgStmt = $this->db->prepare("SELECT id, title, image_url FROM designer_backgrounds WHERE id = ?");
            $bgStmt->execute([$background_id]);
            $background = $bgStmt->fetch(PDO::FETCH_ASSOC);

            return ['success' => true, 'message' => 'Background set successfully', 'background' => $background];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Set user background error: " . $e->getMessage());
            return ['success' => false, 'message' => 'A database error occurred. Please try again.'];
        }
    }

    public function removeUserBackground($user_id) {
        try {
            // Check role permission
            if (!$this->canAccessBackgrounds()) {
                return ['success' => false, 'message' => 'Background customization is not available for community accounts'];
            }

            $stmt = $this->db->prepare("DELETE FROM user_background_preferences WHERE user_id = ?");
            $stmt->execute([$user_id]);
            return ['success' => true, 'message' => 'Background preference removed'];
        } catch (Exception $e) {
            error_log("Remove user background error: " . $e->getMessage());
            return ['success' => false, 'message' => 'A database error occurred. Please try again.'];
        }
    }
}

// Initialize background system (keep this part if your main.php expects it)
$backgroundSystem = new BackgroundSystem();

$user_background = null;
$available_backgrounds = [];
if (isset($_SESSION['user_id'])) {
    $user_background = $backgroundSystem->getUserBackground($_SESSION['user_id']);
    $available_backgrounds = $backgroundSystem->getAvailableBackgrounds();
}
?>
