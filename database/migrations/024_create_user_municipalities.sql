CREATE TABLE IF NOT EXISTS user_municipalities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    subregion VARCHAR(120) NOT NULL,
    municipality VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_municipality (user_id, municipality),
    KEY idx_user_municipalities_user (user_id),
    KEY idx_user_municipalities_location (subregion, municipality),
    CONSTRAINT fk_user_municipalities_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
);
