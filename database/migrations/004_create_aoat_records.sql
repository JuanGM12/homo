CREATE TABLE aoat_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id BIGINT UNSIGNED NOT NULL,

    -- Datos del profesional (se guardan como snapshot, no editables en el formulario)
    professional_name VARCHAR(150) NOT NULL,
    professional_last_name VARCHAR(150) NOT NULL,
    professional_email VARCHAR(150) NOT NULL,
    professional_role VARCHAR(80) NOT NULL, -- ej: medico, abogado, psicologo, trabajador_social
    profession VARCHAR(150) NOT NULL,
    subregion VARCHAR(120) NOT NULL,
    municipality VARCHAR(120) NOT NULL,

    -- Estado del registro de AoAT
    state ENUM('Asignada','Realizado','Devuelta','Aprobada') NOT NULL DEFAULT 'Asignada',

    -- Información de auditoría por especializados
    audit_observation TEXT NULL,
    audit_motive VARCHAR(120) NULL, -- ej: Sin Cargar en AoAT / Sin cargar en Drive / ...

    -- Contenedor flexible para las respuestas del formulario (JSON o texto serializado)
    payload JSON NULL,

    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_aoat_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

