ALTER TABLE users
    ADD COLUMN document_number VARCHAR(50) NULL AFTER email,
    ADD UNIQUE KEY uq_users_document_number (document_number);

