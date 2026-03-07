-- Módulo 6: Encuesta de Opinión de AoAT (acceso libre, sin usuario)
-- Los asesores se seleccionan del listado de usuarios con rol distinto a admin.

CREATE TABLE encuesta_opinion_aoat (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Asesor con el que se realizó la actividad (snapshot del nombre para no editar)
    advisor_user_id BIGINT UNSIGNED NOT NULL,
    advisor_name VARCHAR(200) NOT NULL,

    actividad VARCHAR(500) NOT NULL,
    lugar VARCHAR(300) NOT NULL,
    activity_date DATE NOT NULL,
    subregion VARCHAR(120) NOT NULL,
    municipality VARCHAR(120) NOT NULL,

    -- Escala 1 a 5 (1 = mínimo, 5 = máximo)
    score_objetivos TINYINT UNSIGNED NOT NULL,
    score_claridad TINYINT UNSIGNED NOT NULL,
    score_pertinencia TINYINT UNSIGNED NOT NULL,
    score_ayudas TINYINT UNSIGNED NOT NULL,
    score_relacion TINYINT UNSIGNED NOT NULL,
    score_puntualidad TINYINT UNSIGNED NOT NULL,

    comments TEXT NULL,

    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_encuesta_scores CHECK (
        score_objetivos BETWEEN 1 AND 5 AND
        score_claridad BETWEEN 1 AND 5 AND
        score_pertinencia BETWEEN 1 AND 5 AND
        score_ayudas BETWEEN 1 AND 5 AND
        score_relacion BETWEEN 1 AND 5 AND
        score_puntualidad BETWEEN 1 AND 5
    ),
    CONSTRAINT fk_encuesta_advisor FOREIGN KEY (advisor_user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
