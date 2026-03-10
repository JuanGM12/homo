ALTER TABLE users
    ADD COLUMN requires_password_change TINYINT(1) NOT NULL DEFAULT 0
        AFTER password;

