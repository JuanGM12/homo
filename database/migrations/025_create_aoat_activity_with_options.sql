CREATE TABLE IF NOT EXISTS aoat_activity_with_options (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(180) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_aoat_activity_with_label (label),
    KEY idx_aoat_activity_with_active_sort (active, sort_order, label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO aoat_activity_with_options (label, sort_order, active)
SELECT seed.label, seed.sort_order, 1
FROM (
    SELECT 'Adultos mayores' AS label, 10 AS sort_order
    UNION ALL SELECT 'Canales de TV', 20
    UNION ALL SELECT 'Comisaría de Familia', 30
    UNION ALL SELECT 'Comunidad en general', 40
    UNION ALL SELECT 'Coordinación PIC', 50
    UNION ALL SELECT 'Directivos Docentes', 60
    UNION ALL SELECT 'Docentes', 70
    UNION ALL SELECT 'Emisoras', 80
    UNION ALL SELECT 'Enfermeros', 90
    UNION ALL SELECT 'EPS', 100
    UNION ALL SELECT 'Estudiantes', 110
    UNION ALL SELECT 'Funcionarios Públicos', 120
    UNION ALL SELECT 'Grupos de socorro', 130
    UNION ALL SELECT 'ICBF', 140
    UNION ALL SELECT 'Iglesias', 150
    UNION ALL SELECT 'Jóvenes', 160
    UNION ALL SELECT 'Juntas comunales', 170
    UNION ALL SELECT 'Médicos', 180
    UNION ALL SELECT 'Padres de familia', 190
    UNION ALL SELECT 'Policía Nacional', 200
    UNION ALL SELECT 'Profesionales Psicosociales', 210
    UNION ALL SELECT 'Secretarías Municipales de Salud', 220
    UNION ALL SELECT 'SRPA', 230
    UNION ALL SELECT 'Universitarios', 240
) AS seed
WHERE NOT EXISTS (
    SELECT 1
    FROM aoat_activity_with_options o
    WHERE o.label = seed.label
);
