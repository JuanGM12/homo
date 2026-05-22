-- FIPC: identidad de género, orientación sexual; normalización de sexo histórico

ALTER TABLE asistencia_asistentes
    ADD COLUMN genero_identidad VARCHAR(40) NULL COMMENT 'Cisgenero, Transgenero, Otra' AFTER sex,
    ADD COLUMN genero_identidad_otro VARCHAR(150) NULL AFTER genero_identidad,
    ADD COLUMN orientacion_sexual VARCHAR(40) NULL COMMENT 'Lesbiana, Gay, Bisexual, Heterosexual, Otra' AFTER genero_identidad_otro,
    ADD COLUMN orientacion_sexual_otro VARCHAR(150) NULL AFTER orientacion_sexual;

-- Normalizar valores antiguos de sex al nuevo conjunto Hombre/Mujer/Intersexual
UPDATE asistencia_asistentes SET sex = 'Hombre' WHERE sex = 'Masculino';
UPDATE asistencia_asistentes SET sex = 'Mujer' WHERE sex = 'Femenino';
UPDATE asistencia_asistentes SET sex = 'Intersexual' WHERE sex IN ('No binario', 'Transgénero, transexual o travesti');
