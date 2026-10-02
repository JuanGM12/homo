-- Población «ESE Municipales» junto a Médicos: reuniones con personal administrativo de la ESE.
INSERT INTO aoat_activity_with_options (label, sort_order, active)
SELECT 'ESE Municipales', 185, 1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM aoat_activity_with_options
    WHERE label = 'ESE Municipales'
);
