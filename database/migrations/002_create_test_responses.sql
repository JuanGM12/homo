CREATE TABLE test_responses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Identificación del test
    test_key VARCHAR(50) NOT NULL,      -- ej: 'violencias', 'suicidios', 'adicciones', 'hospitales'
    phase ENUM('pre','post') NOT NULL,  -- pre o post

    -- Persona evaluada
    document_number VARCHAR(50) NOT NULL,
    first_name VARCHAR(120) NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    subregion VARCHAR(120) NOT NULL,
    municipality VARCHAR(120) NOT NULL,
    profession VARCHAR(120) NULL,       -- obligatorio solo para Hospitales (a nivel de lógica)

    -- Resultados
    total_questions TINYINT UNSIGNED NULL,
    correct_answers TINYINT UNSIGNED NULL,
    score_percent DECIMAL(5,2) NULL,   -- 0.00 a 100.00

    -- Metadatos
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Reglas de negocio:
    -- 1. Solo un registro por persona, test y fase (documento + test_key + phase)
    UNIQUE KEY uq_test_person_phase (test_key, phase, document_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

