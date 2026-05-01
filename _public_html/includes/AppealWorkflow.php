<?php
/**
 * Appeal Workflow Automation - Phase 5 Implementation
 * 
 * Automated processing of appeals based on configurable rules:
 * - Auto-approve: First-time minor violations with sincere appeal
 * - Auto-deny: Empty/spam appeals, repeat offenders
 * - Flag for review: Complex cases requiring human judgment
 * 
 * Rules are stored in database and evaluated in priority order.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/PunishmentManager.php';
require_once __DIR__ . '/../includes/SystemNotificationManager.php';

class AppealWorkflow {
    private $db;
    private $notificationManager;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->notificationManager = new SystemNotificationManager();
    }
    
    /**
     * Process pending appeals through workflow rules
     * Called by cron job every 5 minutes
     * 
     * @param int $batchSize Number of appeals to process
     * @return array Processing results
     */
    public function processPendingAppeals($batchSize = 10) {
        try {
            // Get active workflow rules
            $rules = $this->getActiveRules();
            
            // Get pending appeals that haven't been auto-processed
            $stmt = $this->db->prepare("
                SELECT a.*, u.username, u.account_tier, u.lockdown_rule,
                       s.rule_id, s.violation_type, s.created_at as strike_date
                FROM lockdown_appeals a
                JOIN users u ON a.user_id = u.id
                LEFT JOIN user_strikes s ON u.lockdown_strike_id = s.id
                WHERE a.status = 'pending'
                AND a.auto_processed = FALSE
                AND a.created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                ORDER BY a.created_at ASC
                LIMIT ?
            ");
            $stmt->execute([$batchSize]);
            $appeals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = [
                'processed' => 0,
                'auto_approved' => 0,
                'auto_denied' => 0,
                'flagged_for_review' => 0,
                'errors' => []
            ];
            
            foreach ($appeals as $appeal) {
                try {
                    $decision = $this->evaluateRules($appeal, $rules);
                    
                    switch ($decision['action']) {
                        case 'approve':
                            $this->autoApprove($appeal, $decision['rule']);
                            $results['auto_approved']++;
                            break;
                            
                        case 'deny':
                            $this->autoDeny($appeal, $decision['rule'], $decision['reason']);
                            $results['auto_denied']++;
                            break;
                            
                        case 'flag':
                            $this->flagForReview($appeal, $decision['rule'], $decision['reason']);
                            $results['flagged_for_review']++;
                            break;
                            
                        default:
                            // No matching rule - leave for manual review
                            break;
                    }
                    
                    $results['processed']++;
                    
                } catch (Exception $e) {
                    $results['errors'][] = "Appeal {$appeal['id']}: " . $e->getMessage();
                    error_log("Workflow error for appeal {$appeal['id']}: " . $e->getMessage());
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("Process pending appeals error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Get active workflow rules ordered by priority
     */
    private function getActiveRules() {
        $stmt = $this->db->query("
            SELECT id, name, conditions, action, priority FROM appeal_workflow_rules
            WHERE is_active = TRUE
            ORDER BY priority ASC, id ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Evaluate appeal against all rules
     * 
     * @param array $appeal Appeal data
     * @param array $rules Workflow rules
     * @return array Decision with action and rule info
     */
    private function evaluateRules($appeal, $rules) {
        // Get user's appeal history
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN status = 'denied' THEN 1 ELSE 0 END) as denied_count
            FROM lockdown_appeals 
            WHERE user_id = ? AND id != ?
        ");
        $stmt->execute([$appeal['user_id'], $appeal['id']]);
        $history = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get appeal metrics
        $appealLength = strlen($appeal['appeal_text']);
        $wordCount = str_word_count($appeal['appeal_text']);
        
        // Check for spam indicators
        $hasSpamIndicators = $this->hasSpamIndicators($appeal['appeal_text']);
        $isSincere = $this->appearsSincere($appeal['appeal_text']);
        
        foreach ($rules as $rule) {
            $conditions = json_decode($rule['conditions'], true);
            $matches = true;
            
            // Evaluate each condition
            foreach ($conditions as $key => $value) {
                switch ($key) {
                    case 'min_length':
                        if ($appealLength < $value) {
                            $matches = false;
                        }
                        break;
                        
                    case 'max_length':
                        if ($appealLength > $value) {
                            $matches = false;
                        }
                        break;
                        
                    case 'min_words':
                        if ($wordCount < $value) {
                            $matches = false;
                        }
                        break;
                        
                    case 'previous_appeals_denied':
                        if ($history['denied_count'] < $value) {
                            $matches = false;
                        }
                        break;
                        
                    case 'first_appeal':
                        if ($value && $history['total'] > 0) {
                            $matches = false;
                        }
                        if (!$value && $history['total'] == 0) {
                            $matches = false;
                        }
                        break;
                        
                    case 'rule':
                        if ($appeal['lockdown_rule'] !== $value) {
                            $matches = false;
                        }
                        break;
                        
                    case 'no_spam':
                        if ($value && $hasSpamIndicators) {
                            $matches = false;
                        }
                        break;
                        
                    case 'appears_sincere':
                        if ($value && !$isSincere) {
                            $matches = false;
                        }
                        break;
                        
                    case 'account_age_days':
                        $accountAge = $this->getAccountAge($appeal['user_id']);
                        if ($accountAge < $value) {
                            $matches = false;
                        }
                        break;
                }
                
                if (!$matches) {
                    break;
                }
            }
            
            if ($matches) {
                return [
                    'action' => $rule['action'],
                    'rule' => $rule['name'],
                    'reason' => "Matched rule: {$rule['name']}"
                ];
            }
        }
        
        // No matching rule
        return [
            'action' => 'manual_review',
            'rule' => null,
            'reason' => 'No matching workflow rules'
        ];
    }
    
    /**
     * Auto-approve appeal
     */
    private function autoApprove($appeal, $ruleName) {
        $this->db->beginTransaction();
        
        try {
            // Update appeal
            $stmt = $this->db->prepare("
                UPDATE lockdown_appeals 
                SET status = 'approved',
                    reviewed_at = NOW(),
                    reviewed_by = 0,
                    admin_notes = ?,
                    auto_processed = TRUE
                WHERE id = ?
            ");
            $stmt->execute([
                "Auto-approved by workflow rule: {$ruleName}",
                $appeal['id']
            ]);
            
            // Release lockdown
            $punishmentManager = new PunishmentManager();
            $punishmentManager->removeLockdown(
                $appeal['user_id'],
                0, // System user
                "Auto-approved by workflow: {$ruleName}"
            );
            
            // Send notification
            $this->notificationManager->send(
                $appeal['user_id'],
                'APPEAL_RESULT',
                'Appeal Auto-Approved',
                "Your appeal has been automatically approved based on our review criteria. Your account lockdown has been removed.",
                ['priority' => 'high']
            );
            
            // Log action
            $this->logWorkflowAction('auto_approve', $appeal['id'], $ruleName);
            
            $this->db->commit();
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Auto-deny appeal
     */
    private function autoDeny($appeal, $ruleName, $reason) {
        // Update appeal
        $stmt = $this->db->prepare("
            UPDATE lockdown_appeals 
            SET status = 'denied',
                reviewed_at = NOW(),
                reviewed_by = 0,
                admin_notes = ?,
                auto_processed = TRUE
            WHERE id = ?
        ");
        $stmt->execute([
            "Auto-denied by workflow rule: {$ruleName}. {$reason}",
            $appeal['id']
        ]);
        
        // Send notification
        $this->notificationManager->send(
            $appeal['user_id'],
            'APPEAL_RESULT',
            'Appeal Denied',
            "Your appeal has been denied: {$reason}. You may submit another appeal after 7 days.",
            ['priority' => 'high']
        );
        
        // Log action
        $this->logWorkflowAction('auto_deny', $appeal['id'], $ruleName);
    }
    
    /**
     * Flag appeal for manual review
     */
    private function flagForReview($appeal, $ruleName, $reason) {
        // Mark as flagged but keep pending
        $stmt = $this->db->prepare("
            UPDATE lockdown_appeals 
            SET flagged_for_review = TRUE,
                flag_reason = ?,
                auto_processed = TRUE
            WHERE id = ?
        ");
        $stmt->execute(["{$ruleName}: {$reason}", $appeal['id']]);
        
        // Notify admins
        $this->notificationManager->sendToAdmins(
            'APPEAL_FLAGGED',
            'Appeal Requires Manual Review',
            "User {$appeal['username']} submitted an appeal that was flagged by workflow rule: {$ruleName}. Reason: {$reason}",
            ['link' => '/admin/review_appeals.php']
        );
        
        // Log action
        $this->logWorkflowAction('flag_for_review', $appeal['id'], $ruleName);
    }
    
    /**
     * Check for spam indicators in appeal text
     */
    private function hasSpamIndicators($text) {
        $indicators = [
            'http://', 'https://', 'www.',
            'click here', 'buy now', 'limited time',
            '!!!!!', '?????', 'CAPS LOCK'
        ];
        
        $lowerText = strtolower($text);
        
        foreach ($indicators as $indicator) {
            if (stripos($lowerText, $indicator) !== false) {
                return true;
            }
        }
        
        // Check for excessive repetition
        if (preg_match('/(.{5,})\1{3,}/', $text)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if appeal appears sincere
     */
    private function appearsSincere($text) {
        // Sincere appeals typically contain:
        // - Acknowledgment words
        // - Apology words
        // - Future commitment words
        // - First person pronouns
        
        $acknowledgmentWords = ['i understand', 'i realize', 'i acknowledge', 'i know'];
        $apologyWords = ['sorry', 'apologize', 'regret', 'mistake'];
        $commitmentWords = ['will not', 'promise', 'commit', 'ensure', 'prevent'];
        
        $lowerText = strtolower($text);
        
        $hasAcknowledgment = false;
        foreach ($acknowledgmentWords as $word) {
            if (stripos($lowerText, $word) !== false) {
                $hasAcknowledgment = true;
                break;
            }
        }
        
        $hasApology = false;
        foreach ($apologyWords as $word) {
            if (stripos($lowerText, $word) !== false) {
                $hasApology = true;
                break;
            }
        }
        
        $hasCommitment = false;
        foreach ($commitmentWords as $word) {
            if (stripos($lowerText, $word) !== false) {
                $hasCommitment = true;
                break;
            }
        }
        
        // Must have at least 2 of 3 to be considered sincere
        $score = ($hasAcknowledgment ? 1 : 0) + ($hasApology ? 1 : 0) + ($hasCommitment ? 1 : 0);
        
        return $score >= 2;
    }
    
    /**
     * Get account age in days
     */
    private function getAccountAge($userId) {
        $stmt = $this->db->prepare("SELECT created_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $created = $stmt->fetchColumn();
        
        if (!$created) {
            return 0;
        }
        
        $createdDate = new DateTime($created);
        $now = new DateTime();
        $diff = $createdDate->diff($now);
        
        return $diff->days;
    }
    
    /**
     * Log workflow action for audit trail
     */
    private function logWorkflowAction($action, $appealId, $ruleName) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO admin_audit_log 
                (admin_id, action, target_type, target_id, details, created_at)
                VALUES (0, ?, 'appeal', ?, ?, NOW())
            ");
            $stmt->execute([
                "workflow_{$action}",
                $appealId,
                json_encode(['rule' => $ruleName])
            ]);
        } catch (Exception $e) {
            error_log("Log workflow action error: " . $e->getMessage());
        }
    }
    
    /**
     * Add new workflow rule
     */
    public function addRule($name, $ruleType, $conditions, $action, $priority = 0, $createdBy = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO appeal_workflow_rules 
                (name, rule_type, conditions, action, priority, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $name,
                $ruleType,
                json_encode($conditions),
                $action,
                $priority,
                $createdBy
            ]);
            
            return ['success' => true, 'rule_id' => $this->db->lastInsertId()];
            
        } catch (Exception $e) {
            error_log("Add workflow rule error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Update workflow rule
     */
    public function updateRule($ruleId, $updates) {
        try {
            $allowedFields = ['name', 'rule_type', 'conditions', 'action', 'priority', 'is_active'];
            $fields = [];
            $values = [];
            
            foreach ($updates as $key => $value) {
                if (in_array($key, $allowedFields)) {
                    $fields[] = "{$key} = ?";
                    if ($key === 'conditions') {
                        $values[] = json_encode($value);
                    } else {
                        $values[] = $value;
                    }
                }
            }
            
            if (empty($fields)) {
                return ['success' => false, 'error' => 'No valid fields to update'];
            }
            
            $sql = "UPDATE appeal_workflow_rules SET " . implode(', ', $fields) . " WHERE id = ?";
            $values[] = $ruleId;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Update workflow rule error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Delete workflow rule
     */
    public function deleteRule($ruleId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM appeal_workflow_rules WHERE id = ?");
            $stmt->execute([$ruleId]);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Delete workflow rule error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get workflow statistics
     */
    public function getStatistics($days = 30) {
        try {
            $stats = [
                'auto_approved' => 0,
                'auto_denied' => 0,
                'flagged' => 0,
                'manual_review' => 0
            ];
            
            $stmt = $this->db->prepare("
                SELECT 
                    SUM(CASE WHEN status = 'approved' AND auto_processed = TRUE THEN 1 ELSE 0 END) as auto_approved,
                    SUM(CASE WHEN status = 'denied' AND auto_processed = TRUE THEN 1 ELSE 0 END) as auto_denied,
                    SUM(CASE WHEN flagged_for_review = TRUE THEN 1 ELSE 0 END) as flagged,
                    SUM(CASE WHEN auto_processed = FALSE AND status = 'pending' THEN 1 ELSE 0 END) as manual_review
                FROM lockdown_appeals
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'stats' => [
                    'auto_approved' => intval($row['auto_approved']),
                    'auto_denied' => intval($row['auto_denied']),
                    'flagged' => intval($row['flagged']),
                    'manual_review' => intval($row['manual_review'])
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Get workflow stats error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
