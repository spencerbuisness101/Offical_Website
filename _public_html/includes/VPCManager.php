<?php
/**
 * VPC Manager (Verifiable Parental Consent)
 * 
 * Handles the parental consent process for under-13 users who want to 
 * upgrade from a Community Account to a Paid Account.
 * 
 * COPPA COMPLIANCE:
 * - Email verification code (6-digit) sent first
 * - Parent enters code on website
 * - Then consent link sent
 * - $1.00 non-refundable charge for verification
 * - Parent can revoke consent at any time
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/payment.php';
require_once __DIR__ . '/system_mailer.php';

class VPCManager {
    private $db;
    private $pepper;
    private $columnCache = [];
    
    public function __construct() {
        $this->db = db();
        $this->pepper = $_ENV['PEPPER_SECRET'] ?? '';
    }
    
    private function hasColumn($table, $column) {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnCache)) {
            return $this->columnCache[$cacheKey];
        }
        
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
            $stmt->execute([$column]);
            $this->columnCache[$cacheKey] = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('VPC schema inspection error: ' . $e->getMessage());
            $this->columnCache[$cacheKey] = false;
        }
        
        return $this->columnCache[$cacheKey];
    }
    
    private function hashSecret($namespace, $value) {
        return hash('sha256', $namespace . '|' . $value . '|' . $this->pepper);
    }
    
    private function hashVerificationCode($childUserId, $code) {
        return $this->hashSecret('vpc_code:' . (int)$childUserId, (string)$code);
    }
    
    private function hashConsentToken($token) {
        return $this->hashSecret('vpc_token', (string)$token);
    }
    
    private function findPendingConsentByToken($consentToken) {
        $hasTokenHash = $this->hasColumn('parental_consent', 'consent_token_hash');
        
        if ($hasTokenHash) {
            $tokenHash = $this->hashConsentToken($consentToken);
            $stmt = $this->db->prepare(" 
                SELECT * FROM parental_consent 
                WHERE status = 'pending_consent' 
                  AND (consent_token_hash = ? OR consent_token = ?)
                LIMIT 1
            ");
            $stmt->execute([$tokenHash, $consentToken]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($record && !empty($record['consent_token']) && hash_equals((string)$record['consent_token'], (string)$consentToken)) {
                $this->db->prepare("UPDATE parental_consent SET consent_token_hash = ?, consent_token = NULL WHERE id = ?")
                    ->execute([$tokenHash, $record['id']]);
                $record['consent_token_hash'] = $tokenHash;
                $record['consent_token'] = null;
            }
            
            return $record;
        }
        
        $stmt = $this->db->prepare(" 
            SELECT * FROM parental_consent 
            WHERE consent_token = ? AND status = 'pending_consent'
            LIMIT 1
        ");
        $stmt->execute([$consentToken]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getPendingConsentByToken($consentToken) {
        try {
            if ($consentToken === '') {
                return false;
            }
            return $this->findPendingConsentByToken($consentToken);
        } catch (Exception $e) {
            error_log('VPC pending consent lookup error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Step 1: Request VPC - Send verification code to parent email
     * 
     * @param int $childUserId The child's user ID
     * @param string $parentEmail Parent's email address
     * @return array Result with status and message
     */
    public function requestConsent($childUserId, $parentEmail) {
        try {
            // Validate email format
            if (!filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email address.'];
            }
            
            // Check if child already has consent pending or verified
            $stmt = $this->db->prepare("SELECT status, parent_email FROM parental_consent WHERE child_user_id = ?");
            $stmt->execute([$childUserId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                if ($existing['status'] === 'verified') {
                    return ['success' => false, 'message' => 'Parental consent has already been granted for this account.'];
                }
                if ($existing['status'] === 'pending_code' || $existing['status'] === 'pending_consent') {
                    return ['success' => false, 'message' => 'A parental consent request is already pending. Please check the email: ' . $this->maskEmail($existing['parent_email'])];
                }
            }
            
            // Generate 6-digit verification code
            $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Generate single-use consent token
            $consentToken = bin2hex(random_bytes(32));
            
            // Generate transaction ID
            $transactionId = 'VPC-' . uniqid() . '-' . $childUserId;
            
            // Set expiration times
            $codeExpires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $tokenExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Insert or update consent record
            $hasCodeHash = $this->hasColumn('parental_consent', 'verification_code_hash');
            $hasTokenHash = $this->hasColumn('parental_consent', 'consent_token_hash');
            $verificationCodeHash = $hasCodeHash ? $this->hashVerificationCode($childUserId, $verificationCode) : null;
            $consentTokenHash = $hasTokenHash ? $this->hashConsentToken($consentToken) : null;

            if ($hasCodeHash && $hasTokenHash) {
                $stmt = $this->db->prepare(" 
                    INSERT INTO parental_consent 
                    (child_user_id, parent_email, email_verified, verification_code, verification_code_hash, code_expires_at, consent_token, consent_token_hash, token_expires_at, transaction_id, status, created_at) 
                    VALUES (?, ?, FALSE, NULL, ?, ?, ?, ?, ?, ?, 'pending_code', NOW())
                    ON DUPLICATE KEY UPDATE
                    parent_email = VALUES(parent_email),
                    email_verified = FALSE,
                    verification_code = VALUES(verification_code),
                    verification_code_hash = VALUES(verification_code_hash),
                    code_expires_at = VALUES(code_expires_at),
                    consent_token = VALUES(consent_token),
                    consent_token_hash = VALUES(consent_token_hash),
                    token_expires_at = VALUES(token_expires_at),
                    transaction_id = VALUES(transaction_id),
                    status = 'pending_code',
                    consent_granted_at = NULL,
                    consent_revoked_at = NULL,
                    charge_processed_at = NULL,
                    revocation_reason = NULL,
                    created_at = NOW()
                ");
                $stmt->execute([$childUserId, $parentEmail, $verificationCodeHash, $codeExpires, $consentToken, $consentTokenHash, $tokenExpires, $transactionId]);
            } elseif ($hasCodeHash) {
                $stmt = $this->db->prepare(" 
                    INSERT INTO parental_consent 
                    (child_user_id, parent_email, email_verified, verification_code, verification_code_hash, code_expires_at, consent_token, token_expires_at, transaction_id, status, created_at) 
                    VALUES (?, ?, FALSE, NULL, ?, ?, ?, ?, ?, 'pending_code', NOW())
                    ON DUPLICATE KEY UPDATE
                    parent_email = VALUES(parent_email),
                    email_verified = FALSE,
                    verification_code = VALUES(verification_code),
                    verification_code_hash = VALUES(verification_code_hash),
                    code_expires_at = VALUES(code_expires_at),
                    consent_token = VALUES(consent_token),
                    token_expires_at = VALUES(token_expires_at),
                    transaction_id = VALUES(transaction_id),
                    status = 'pending_code',
                    consent_granted_at = NULL,
                    consent_revoked_at = NULL,
                    charge_processed_at = NULL,
                    revocation_reason = NULL,
                    created_at = NOW()
                ");
                $stmt->execute([$childUserId, $parentEmail, $verificationCodeHash, $codeExpires, $consentToken, $tokenExpires, $transactionId]);
            } elseif ($hasTokenHash) {
                $stmt = $this->db->prepare(" 
                    INSERT INTO parental_consent 
                    (child_user_id, parent_email, email_verified, verification_code, code_expires_at, consent_token, consent_token_hash, token_expires_at, transaction_id, status, created_at) 
                    VALUES (?, ?, FALSE, ?, ?, ?, ?, ?, ?, 'pending_code', NOW())
                    ON DUPLICATE KEY UPDATE
                    parent_email = VALUES(parent_email),
                    email_verified = FALSE,
                    verification_code = VALUES(verification_code),
                    code_expires_at = VALUES(code_expires_at),
                    consent_token = VALUES(consent_token),
                    consent_token_hash = VALUES(consent_token_hash),
                    token_expires_at = VALUES(token_expires_at),
                    transaction_id = VALUES(transaction_id),
                    status = 'pending_code',
                    consent_granted_at = NULL,
                    consent_revoked_at = NULL,
                    charge_processed_at = NULL,
                    revocation_reason = NULL,
                    created_at = NOW()
                ");
                $stmt->execute([$childUserId, $parentEmail, $verificationCode, $codeExpires, $consentToken, $consentTokenHash, $tokenExpires, $transactionId]);
            } else {
                $stmt = $this->db->prepare(" 
                    INSERT INTO parental_consent 
                    (child_user_id, parent_email, email_verified, verification_code, code_expires_at, consent_token, token_expires_at, transaction_id, status, created_at) 
                    VALUES (?, ?, FALSE, ?, ?, ?, ?, ?, 'pending_code', NOW())
                    ON DUPLICATE KEY UPDATE
                    parent_email = VALUES(parent_email),
                    email_verified = FALSE,
                    verification_code = VALUES(verification_code),
                    code_expires_at = VALUES(code_expires_at),
                    consent_token = VALUES(consent_token),
                    token_expires_at = VALUES(token_expires_at),
                    transaction_id = VALUES(transaction_id),
                    status = 'pending_code',
                    consent_granted_at = NULL,
                    consent_revoked_at = NULL,
                    charge_processed_at = NULL,
                    revocation_reason = NULL,
                    created_at = NOW()
                ");
                $stmt->execute([$childUserId, $parentEmail, $verificationCode, $codeExpires, $consentToken, $tokenExpires, $transactionId]);
            }
            
            // Send verification code email
            $emailResult = $this->sendVerificationCodeEmail($parentEmail, $verificationCode, $childUserId);
            
            if (!$emailResult) {
                return ['success' => false, 'message' => 'Failed to send verification email. Please try again.'];
            }
            
            return [
                'success' => true, 
                'message' => 'Verification code sent to parent email.',
                'masked_email' => $this->maskEmail($parentEmail),
                'code_expires' => $codeExpires
            ];
            
        } catch (Exception $e) {
            error_log("VPC request error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again.'];
        }
    }
    
    /**
     * Step 2: Verify the 6-digit code entered by parent
     * 
     * @param int $childUserId The child's user ID
     * @param string $enteredCode The 6-digit code entered by parent
     * @return array Result with status and message
     */
    public function verifyCode($childUserId, $enteredCode) {
        try {
            $stmt = $this->db->prepare(" 
                SELECT * FROM parental_consent 
                WHERE child_user_id = ? AND status = 'pending_code'
            ");
            $stmt->execute([$childUserId]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$record) {
                return ['success' => false, 'message' => 'No pending consent request found.'];
            }
            
            if (strtotime($record['code_expires_at']) < time()) {
                return ['success' => false, 'message' => 'Verification code has expired. Please request a new one.'];
            }
            
            $hasCodeHash = $this->hasColumn('parental_consent', 'verification_code_hash');
            $codeValid = false;
            if ($hasCodeHash && !empty($record['verification_code_hash'])) {
                $expectedCodeHash = $this->hashVerificationCode($childUserId, $enteredCode);
                $codeValid = hash_equals((string)$record['verification_code_hash'], $expectedCodeHash);
            } elseif (!empty($record['verification_code'])) {
                $codeValid = hash_equals((string)$record['verification_code'], (string)$enteredCode);
            }
            
            if (!$codeValid) {
                return ['success' => false, 'message' => 'Invalid verification code. Please check the email and try again.'];
            }
            
            $consentLinkToken = $record['consent_token'] ?? '';
            if ($consentLinkToken === '') {
                return ['success' => false, 'message' => 'Unable to generate consent link. Please request a new verification code.'];
            }
            
            $consentLink = 'https://' . $_SERVER['HTTP_HOST'] . '/auth/parent_consent_portal.php?token=' . $consentLinkToken;
            $emailResult = $this->sendConsentLinkEmail($record['parent_email'], $consentLink, $childUserId);
            
            if (!$emailResult) {
                return ['success' => false, 'message' => 'Failed to send consent link. Please try again.'];
            }
            
            $updateFields = [
                "status = 'pending_consent'",
                "email_verified = TRUE"
            ];
            $updateParams = [];
            
            if ($this->hasColumn('parental_consent', 'code_verified_at')) {
                $updateFields[] = 'code_verified_at = NOW()';
            }
            
            if ($hasCodeHash) {
                $updateFields[] = 'verification_code = NULL';
                $updateFields[] = 'verification_code_hash = NULL';
            }
            
            if ($this->hasColumn('parental_consent', 'consent_token_hash')) {
                $updateFields[] = 'consent_token_hash = ?';
                $updateParams[] = $this->hashConsentToken($consentLinkToken);
                $updateFields[] = 'consent_token = NULL';
            }
            
            $updateParams[] = $record['id'];
            $stmt = $this->db->prepare("UPDATE parental_consent SET " . implode(', ', $updateFields) . " WHERE id = ?");
            $stmt->execute($updateParams);
            
            return [
                'success' => true,
                'message' => 'Email verified! Consent link sent to parent.'
            ];
            
        } catch (Exception $e) {
            error_log("VPC code verification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again.'];
        }
    }
    
    /**
     * Step 3: Process consent and payment
     * 
     * @param string $consentToken The single-use consent token
     * @param string $paymentToken Payment processor token (Stripe, etc.)
     * @return array Result with status and message
     */
    public function processConsentAndPayment($consentToken, $paymentToken) {
        try {
            $record = $this->findPendingConsentByToken($consentToken);
            
            if (!$record) {
                return ['success' => false, 'message' => 'Invalid or expired consent link.'];
            }
            
            if (strtotime($record['token_expires_at']) < time()) {
                return ['success' => false, 'message' => 'Consent link has expired. Please request a new verification code.'];
            }
            
            $chargeResult = $this->processVerificationCharge($paymentToken, $record);
            
            if (!$chargeResult['success']) {
                return ['success' => false, 'message' => 'Payment verification failed: ' . $chargeResult['message']];
            }
            
            $this->db->beginTransaction();
            
            $updateFields = [
                "status = 'verified'",
                'consent_granted_at = NOW()',
                'charge_processed_at = NOW()'
            ];
            $updateParams = [];
            if ($this->hasColumn('parental_consent', 'payment_intent_id')) {
                $updateFields[] = 'payment_intent_id = ?';
                $updateParams[] = $chargeResult['charge_id'];
            }
            $updateParams[] = $record['id'];
            $stmt = $this->db->prepare("UPDATE parental_consent SET " . implode(', ', $updateFields) . " WHERE id = ?");
            $stmt->execute($updateParams);
            
            $stmt = $this->db->prepare(" 
                UPDATE users 
                SET account_tier = 'paid',
                    age_verified_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$record['child_user_id']]);
            
            $this->db->commit();
            
            require_once __DIR__ . '/CommunityAuth.php';
            CommunityAuth::logout();
            
            $record['consent_granted_at'] = date('Y-m-d H:i:s');
            $this->sendConfirmationEmails($record);
            
            return [
                'success' => true,
                'message' => 'Parental consent granted! Account upgraded to Paid tier.'
            ];
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("VPC consent processing error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again.'];
        }
    }
    
    /**
     * Revoke parental consent (parent-initiated)
     * 
     * @param int $consentId The parental_consent record ID
     * @param string $reason Optional reason for revocation
     * @return array Result with status and message
     */
    public function revokeConsent($consentId, $reason = '') {
        try {
            // Get consent record
            $stmt = $this->db->prepare("SELECT * FROM parental_consent WHERE id = ? AND status = 'verified'");
            $stmt->execute([$consentId]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$record) {
                return ['success' => false, 'message' => 'Consent record not found or already revoked.'];
            }
            
            // Update consent record
            $stmt = $this->db->prepare("
                UPDATE parental_consent 
                SET status = 'revoked',
                    consent_revoked_at = NOW(),
                    revocation_reason = ?
                WHERE id = ?
            ");
            $stmt->execute([$reason, $consentId]);
            
            // Downgrade child's account to Community
            $stmt = $this->db->prepare("
                UPDATE users 
                SET account_tier = 'community',
                    reupgrade_blocked_until = DATE_ADD(NOW(), INTERVAL 30 DAY)
                WHERE id = ?
            ");
            $stmt->execute([$record['child_user_id']]);
            
            // Log downgrade in audit table
            $stmt = $this->db->prepare("
                INSERT INTO account_downgrades 
                (user_id, from_tier, to_tier, initiated_by, reason, reupgrade_eligible_at, created_at)
                VALUES (?, 'paid', 'community', 'parent_revocation', ?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())
            ");
            $stmt->execute([$record['child_user_id'], $reason]);
            
            // Trigger anonymization (posts/messages → [Deleted User])
            // This would call DowngradeManager
            
            return ['success' => true, 'message' => 'Parental consent revoked. Account downgraded to Community tier.'];
            
        } catch (Exception $e) {
            error_log("VPC revocation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again.'];
        }
    }
    
    /**
     * Send verification code email to parent
     */
    private function sendVerificationCodeEmail($parentEmail, $code, $childUserId) {
        $subject = 'Verification Code for Parental Consent';
        $body = "
            <h2>Parental Verification Code</h2>
            <p>You are receiving this email because a child (User ID: {$childUserId}) has requested your permission to upgrade their account on our platform.</p>
            <p><strong>Your 6-digit verification code is: <span style='font-size:24px; font-weight:bold; color:#4ECDC4;'>{$code}</span></strong></p>
            <p>Please enter this code on the website to verify you have access to this email address.</p>
            <p><strong>Important:</strong></p>
            <ul>
                <li>This code expires in 10 minutes</li>
                <li>You have 3 attempts to enter it correctly</li>
                <li>After verification, you will receive a consent link to complete the process</li>
                <li>A non-refundable $1.00 verification charge will be processed upon granting consent</li>
            </ul>
            <p>If you did not request this, please ignore this email.</p>
        ";
        
        // Use system_mailer.php sendSystemNotification or similar
        return sendSystemNotification($parentEmail, $subject, $body);
    }
    
    /**
     * Send consent link email to parent
     */
    private function sendConsentLinkEmail($parentEmail, $consentLink, $childUserId) {
        $subject = 'Parental Consent Required';
        $body = "
            <h2>Parental Consent Request</h2>
            <p>Thank you for verifying your email address.</p>
            <p>Your child (User ID: {$childUserId}) has requested to upgrade to a Paid Account on our platform.</p>
            <p><strong>To grant consent, please click the link below:</strong></p>
            <p><a href='{$consentLink}' style='display:inline-block; padding:15px 30px; background:linear-gradient(135deg, #4ECDC4, #6366f1); color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>Grant Parental Consent</a></p>
            <p>Or copy and paste this URL: {$consentLink}</p>
            <p><strong>Important Information:</strong></p>
            <ul>
                <li>This link expires in 24 hours</li>
                <li>You will be asked to provide payment information to verify your identity</li>
                <li>A non-refundable $1.00 charge will be processed</li>
                <li>You can revoke this consent at any time by clicking the link in future emails</li>
                <li>By granting consent, you agree to our Terms of Service and Privacy Policy</li>
            </ul>
            <p>If you do not want to grant consent, simply ignore this email. No action is required.</p>
        ";
        
        return sendSystemNotification($parentEmail, $subject, $body);
    }
    
    /**
     * Send confirmation emails after consent granted
     */
    private function sendConfirmationEmails($record) {
        // Send to parent
        $subject = 'Parental Consent Granted - Confirmation';
        $body = "
            <h2>Consent Confirmation</h2>
            <p>You have successfully granted parental consent for your child's Paid Account.</p>
            <p><strong>Details:</strong></p>
            <ul>
                <li>Child User ID: {$record['child_user_id']}</li>
                <li>Consent Date: {$record['consent_granted_at']}</li>
                <li>Verification Charge: $1.00 (non-refundable)</li>
            </ul>
            <p><strong>You can revoke this consent at any time by clicking this link:</strong></p>
            <p><a href='https://" . $_SERVER['HTTP_HOST'] . "/auth/revoke_consent.php?id={$record['id']}' style='color:#ef4444;'>Revoke Parental Consent</a></p>
            <p>Upon revocation, your child's account will be immediately downgraded to a Community Account and all personal data will be anonymized.</p>
        ";
        
        sendSystemNotification($record['parent_email'], $subject, $body);
        
        // Send to child (if they have a way to receive it - they'll see it on next login)
        // This would be a SYSTEM notification once they're on Paid tier
    }
    
    /**
     * Process verification charge with payment processor
     */
    private function processVerificationCharge($paymentToken, $record) {
        if (empty($paymentToken) || !is_array($record)) {
            return ['success' => false, 'message' => 'Missing payment confirmation.'];
        }

        $paymentIntent = verifyStripePaymentIntent((string)$paymentToken);
        if (!$paymentIntent) {
            return ['success' => false, 'message' => 'Unable to verify the payment with Stripe.'];
        }

        $status = $paymentIntent['status'] ?? '';
        $currency = strtolower((string)($paymentIntent['currency'] ?? ''));
        $amountCents = (int)($paymentIntent['amount_received'] ?? ($paymentIntent['amount'] ?? 0));
        $metadata = is_array($paymentIntent['metadata'] ?? null) ? $paymentIntent['metadata'] : [];

        if ($status !== 'succeeded') {
            return ['success' => false, 'message' => 'The verification payment has not completed yet.'];
        }

        if ($currency !== 'usd' || $amountCents !== 100) {
            return ['success' => false, 'message' => 'Unexpected verification payment amount.'];
        }

        if (($metadata['purpose'] ?? '') !== 'parental_consent') {
            return ['success' => false, 'message' => 'Invalid verification payment purpose.'];
        }

        if (($metadata['transaction_id'] ?? '') !== (string)($record['transaction_id'] ?? '')) {
            return ['success' => false, 'message' => 'Verification payment does not match this consent request.'];
        }

        if (($metadata['child_user_id'] ?? '') !== (string)($record['child_user_id'] ?? '')) {
            return ['success' => false, 'message' => 'Verification payment is bound to a different account.'];
        }

        return ['success' => true, 'charge_id' => (string)($paymentIntent['id'] ?? $paymentToken)];
    }
    
    /**
     * Mask email address for privacy display
     */
    private function maskEmail($email) {
        $parts = explode('@', $email);
        $username = $parts[0];
        $domain = $parts[1];
        
        $maskedUsername = substr($username, 0, 2) . str_repeat('*', max(0, strlen($username) - 4)) . substr($username, -2);
        $domainParts = explode('.', $domain);
        $maskedDomain = substr($domainParts[0], 0, 1) . str_repeat('*', max(0, strlen($domainParts[0]) - 1));
        
        return $maskedUsername . '@' . $maskedDomain . '.' . end($domainParts);
    }
    
    /**
     * Get consent status for a child user
     */
    public function getConsentStatus($childUserId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM parental_consent WHERE child_user_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$childUserId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
}
