-- Conserva al profesional que registró el plan cuando otro, con el municipio asignado, lo edita.

SET @tp_orig_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'training_plans'
      AND COLUMN_NAME = 'original_user_id'
);
SET @tp_orig_sql := IF(
    @tp_orig_exists = 0,
    'ALTER TABLE training_plans
        ADD COLUMN original_user_id BIGINT UNSIGNED NULL AFTER user_id,
        ADD COLUMN original_professional_name VARCHAR(150) NULL AFTER professional_role,
        ADD COLUMN original_professional_email VARCHAR(150) NULL AFTER original_professional_name,
        ADD COLUMN original_professional_role VARCHAR(80) NULL AFTER original_professional_email',
    'SELECT 1'
);
PREPARE tp_orig_stmt FROM @tp_orig_sql;
EXECUTE tp_orig_stmt;
DEALLOCATE PREPARE tp_orig_stmt;

SET @ep_orig_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'entrenamiento_plans'
      AND COLUMN_NAME = 'original_user_id'
);
SET @ep_orig_sql := IF(
    @ep_orig_exists = 0,
    'ALTER TABLE entrenamiento_plans
        ADD COLUMN original_user_id BIGINT UNSIGNED NULL AFTER user_id,
        ADD COLUMN original_professional_name VARCHAR(150) NULL AFTER professional_email,
        ADD COLUMN original_professional_email VARCHAR(150) NULL AFTER original_professional_name',
    'SELECT 1'
);
PREPARE ep_orig_stmt FROM @ep_orig_sql;
EXECUTE ep_orig_stmt;
DEALLOCATE PREPARE ep_orig_stmt;
