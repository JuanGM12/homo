-- Módulo 7: Listado de Asistencia - actividades y asistentes registrados

CREATE TABLE asistencia_actividades (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE COMMENT 'Código para QR y URL de registro (ej. 871812)',

    subregion VARCHAR(120) NOT NULL,
    municipality VARCHAR(120) NOT NULL,
    lugar VARCHAR(300) NOT NULL,

    advisor_user_id BIGINT UNSIGNED NOT NULL,
    advisor_name VARCHAR(200) NOT NULL,

    activity_date DATE NOT NULL,
    actividad_tipos JSON NOT NULL COMMENT 'Array de tipos de listado seleccionados (multi-select)',

    status ENUM('Pendiente', 'Activo', 'Cerrado') NOT NULL DEFAULT 'Pendiente',

    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_asistencia_advisor FOREIGN KEY (advisor_user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asistencia_asistentes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actividad_id BIGINT UNSIGNED NOT NULL,
    document_number VARCHAR(30) NOT NULL,
    full_name VARCHAR(200) NOT NULL,
    entity VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    zone VARCHAR(50) NULL COMMENT 'Urbana, Rural, etc.',
    registered_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_asistente_actividad FOREIGN KEY (actividad_id) REFERENCES asistencia_actividades (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
