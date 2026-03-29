ALTER TABLE asistencia_actividades
    ADD COLUMN tipo ENUM('aoat', 'actividad') NOT NULL DEFAULT 'aoat' AFTER activity_date;

UPDATE asistencia_actividades
SET tipo = 'aoat'
WHERE tipo IS NULL OR tipo = '';
