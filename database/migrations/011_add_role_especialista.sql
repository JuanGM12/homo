INSERT INTO roles (name, description)
SELECT 'especialista', 'Especialista en revisión y aprobación'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'especialista');

