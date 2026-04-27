ALTER TABLE users
    ADD COLUMN last_login_at TIMESTAMP NULL DEFAULT NULL AFTER email_verified_at;
