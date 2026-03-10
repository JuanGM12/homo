INSERT INTO roles (name, description)
SELECT 'coordinador', 'Coordinador/a con acceso de auditoría'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'coordinador');

