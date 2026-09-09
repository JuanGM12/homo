INSERT INTO roles (name, description)
VALUES ('politologo', CONVERT(0x506f6c6974c3b36c6f676f USING utf8mb4))
ON DUPLICATE KEY UPDATE description = VALUES(description);
