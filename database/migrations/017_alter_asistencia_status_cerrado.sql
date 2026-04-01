-- Estado Cerrado para listados de asistencia (AoAT / Actividades)
ALTER TABLE asistencia_actividades
    MODIFY COLUMN status ENUM('Pendiente', 'Activo', 'Cerrado') NOT NULL DEFAULT 'Pendiente';

-- Listados que ya tienen asistentes y seguían en Pendiente pasan a Activo
UPDATE asistencia_actividades a
SET status = 'Activo'
WHERE a.status = 'Pendiente'
  AND EXISTS (SELECT 1 FROM asistencia_asistentes s WHERE s.actividad_id = a.id);
