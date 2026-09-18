-- La data ya cargada (AoAT, entrenamiento, PIC) pertenece a 2026-1.
-- Los registros nuevos, con 2026-2 activo, se crean en ese periodo desde el despliegue.
-- Este ajuste solo mueve filas de 2026-2 creadas antes del corte (no toca altas posteriores).

SET @period_2026_1 := (SELECT id FROM aoat_periods WHERE name = '2026-1' LIMIT 1);
SET @period_2026_2 := (SELECT id FROM aoat_periods WHERE name = '2026-2' LIMIT 1);
SET @cutoff := '2026-09-18 00:00:00';

UPDATE aoat_records
SET period_id = @period_2026_1
WHERE @period_2026_1 IS NOT NULL
  AND period_id = @period_2026_2
  AND created_at < @cutoff;

UPDATE entrenamiento_plans
SET period_id = @period_2026_1
WHERE @period_2026_1 IS NOT NULL
  AND period_id = @period_2026_2
  AND created_at < @cutoff;

UPDATE pic_records
SET period_id = @period_2026_1
WHERE @period_2026_1 IS NOT NULL
  AND period_id = @period_2026_2
  AND created_at < @cutoff;
