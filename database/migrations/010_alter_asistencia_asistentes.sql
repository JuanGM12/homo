-- Ampliar datos del asistente para el formulario público de registro

ALTER TABLE asistencia_asistentes ADD COLUMN email VARCHAR(150) NULL AFTER phone;
ALTER TABLE asistencia_asistentes ADD COLUMN cargo VARCHAR(120) NULL AFTER entity;
ALTER TABLE asistencia_asistentes ADD COLUMN sex VARCHAR(60) NULL COMMENT 'Masculino, Femenino, No binario, Transgénero/transexual/travesti' AFTER zone;
ALTER TABLE asistencia_asistentes ADD COLUMN age TINYINT UNSIGNED NULL AFTER sex;
ALTER TABLE asistencia_asistentes ADD COLUMN etnia VARCHAR(80) NULL COMMENT 'Afrodescendiente, Indígena, Otro' AFTER age;
ALTER TABLE asistencia_asistentes ADD COLUMN etnia_otro VARCHAR(120) NULL COMMENT 'Especificación si etnia = Otro' AFTER etnia;
ALTER TABLE asistencia_asistentes ADD COLUMN grupo_poblacional JSON NULL COMMENT 'Array de opciones grupo poblacional' AFTER etnia_otro;
