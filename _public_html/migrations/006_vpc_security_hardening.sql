ALTER TABLE parental_consent
    ADD COLUMN verification_code_hash CHAR(64) NULL AFTER verification_code,
    ADD COLUMN code_verified_at TIMESTAMP NULL AFTER code_expires_at,
    ADD COLUMN consent_token_hash CHAR(64) NULL AFTER consent_token,
    ADD COLUMN payment_intent_id VARCHAR(255) NULL AFTER transaction_id;

ALTER TABLE parental_consent
    ADD INDEX idx_verification_code_hash (verification_code_hash),
    ADD INDEX idx_consent_token_hash (consent_token_hash),
    ADD INDEX idx_payment_intent_id (payment_intent_id);
