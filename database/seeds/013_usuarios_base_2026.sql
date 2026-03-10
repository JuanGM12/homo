-- Usuarios generados desde CSV.
-- La contraseña inicial es el número de documento (si está presente en el CSV).
-- La cédula/documento se guarda en users.document_number para asociar evaluaciones y otros módulos.
-- Ejecutar después de tener las tablas users, roles y user_roles (y los roles creados).

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('SANTIAGO TAMAYO ALVAREZ', 'santiagotamayo96@gmail.com', '1152457771', '$2y$10$rcWFm3lRnJzBeRQdYnozaOS1lwYSBQdmnRYNW3xyb2il5VScsUnRG', 0, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'santiagotamayo96@gmail.com' AND r.name = 'coordinador'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('ALEXANDRA ISABEL CASTRO LONDOÑO', 'alecastrolon@gmail.com', '43726469', '$2y$10$sYfw.Aecfi2YssuLUEAaMOP6bBaBzxHhMaI5v0UNirmPK31W2AYp2', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'alecastrolon@gmail.com' AND r.name = 'abogado'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('ANA PATRICIA ALZATE ESTRADA', 'patryalzatestrada@gmail.com', '21702156', '$2y$10$5ThcThPYCtyUQHJsAlsRmu5XSxeC2BavH6ewoI3NPDh5kuzS2cfoy', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'patryalzatestrada@gmail.com' AND r.name = 'profesional social'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('ANDREA GAVIRIA CASTAÑEDA', 'andreagaviriac@gmail.com', '1017183228', '$2y$10$Jy1W46/JL/JcTbogF9THsult.Um7po6nVlvzg8EOm71M1jc7mpJRq', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'andreagaviriac@gmail.com' AND r.name = 'psicologo'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('CLAUDIA INES RESTREPO VASQUEZ', 'inesrestrepo3590@gmail.com', '42902042', '$2y$10$0tgT5zNvkE/jWxLfc.QxcOVkg/JmZSbyvEath2.UH5dyTlY1zKJYa', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'inesrestrepo3590@gmail.com' AND r.name = 'psicologo'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('CLAUDIA MARIA RESTREPO MOLINA', 'claudiarestrepomolina68@gmail.com', '42823146', '$2y$10$W4HRehQWr3SHMAKMTHPqa.1A45eyIuDrAmCW.gk8fIBMmDU5hgjMG', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'claudiarestrepomolina68@gmail.com' AND r.name = 'psicologo'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('DAVID ALEJANDRO ROLDAN CATAÑO', 'saludmentalproyectos@gmail.com', '1037547780', '$2y$10$N/tnYx1TyrwaURdEL5H4c.dHkznIHP7tPYTJMdm3fxahb64Kz46xO', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'saludmentalproyectos@gmail.com' AND r.name = 'medico'
ON DUPLICATE KEY UPDATE user_id = user_id;
INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'saludmentalproyectos@gmail.com' AND r.name = 'especialista'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('DAVID SANTIAGO VIVARES SERNA', 'tiagovivares6@gmail.com', '1000619153', '$2y$10$h6Wpn9M7GVmQUhIBOJS9pubw8IK09lDMwaT0fGU0BnqBBd.3sjAku', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'tiagovivares6@gmail.com' AND r.name = 'medico'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('DAVIER JOSE MARZOLA MUENTES', 'daviermarzola@gmail.com', '1067944695', '$2y$10$zKm6bXHzGQDrr86yP34ta.Odh.k2yY4Nr8xJwItuiQMJ7.eBuPDYy', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'daviermarzola@gmail.com' AND r.name = 'abogado'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('DIANA MARCELA DUQUE MONTES', 'duqueza65@gmail.com', '43983656', '$2y$10$7w3GypyfTUbbPgPOfjYXCOmOW1PE7.9f2ozTKw.xpDIdAIE9io60K', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'duqueza65@gmail.com' AND r.name = 'abogado'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('ESTEBAN CORDOBA MONTOYA', 'saludparaelalma.gesis@gmail.com', '1017136983', '$2y$10$cOvh4VYxSEoto8lsfJSYj.SI1Y9bNpyueU3vX6LNo7UK83pBlkb2m', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'saludparaelalma.gesis@gmail.com' AND r.name = 'admin'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('JORGE ANDRES MESA HERRERA', 'J.andresmesa@gmail.com', '1035222033', '$2y$10$lUJAxy8VxhXaVoJJj2bKbexKjtgLLYwQvcYz63m0PXUaBya.BonxS', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'J.andresmesa@gmail.com' AND r.name = 'psicologo'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('JOSE IGNACIO SANTAMARIA RESTREPO', 'santamariajose7@gmail.com', '71787832', '$2y$10$WoEBYfbS7MS/q/whM.q59.ywyJ9oc6K5G1FWqwNtw/e0wa124YsKS', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'santamariajose7@gmail.com' AND r.name = 'medico'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('JULIO CESAR ZAPATA HERRERA', 'tecosistemasaludmental@gmail.com', '98585747', '$2y$10$RQFA1oYFiSwghUkd3corlej3WKgNxqU4WqlkWfooSCNiAmQfRrUkC', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'tecosistemasaludmental@gmail.com' AND r.name = 'profesional social'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('LIBIA AMPARO HERNANDEZ MARTINEZ', 'lihemarti@gmail.com', '43072040', '$2y$10$BhBqFiNZKuRobgNOCBzv9.Mz.xeesKayxXm576uqe2nReGOtURxJO', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'lihemarti@gmail.com' AND r.name = 'abogado'
ON DUPLICATE KEY UPDATE user_id = user_id;
INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'lihemarti@gmail.com' AND r.name = 'especialista'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('LINA MARCELA MUÑOZ ZAPATA', 'munozzapatalinamarcela@gmail.com', '43754401', '$2y$10$EtKsszjpaHNIxEkz.TFcve4w6Llynzkp9sIUs741ZdgdtQuRuBcmK', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'munozzapatalinamarcela@gmail.com' AND r.name = 'psicologo'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('LLETCENIA CANO CARDONA', 'lletcenia2024@gmail.com', '43211779', '$2y$10$G9JY1RvK2ZYRy3FGrPxZeuop0d/ABYpWdEi/CijQFA7cv34d44aYy', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'lletcenia2024@gmail.com' AND r.name = 'psicologo'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('LUISA FERNANDA HERRERA RAMIREZ', 'luisafhramirez@gmail.com', '1214729556', '$2y$10$JpVrEIsweKMveiQMHrR6gOOT1vwcBVC4dPfDTkxuYuk295fm9iwvC', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'luisafhramirez@gmail.com' AND r.name = 'psicologo'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('MARIA ISABEL TORRES TORRES', 'Misabeltorrestorres@gmail.com', '1044506548', '$2y$10$gQ6mfwGGv2wmx1f/c1Op9.ufbONwaI6CQ/BBUo.l0QI7YCndckmWm', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'Misabeltorrestorres@gmail.com' AND r.name = 'psicologo'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('MARIA LESLY SANCHEZ OSORIO', 'Lessosorio02@gmail.com', '1037572412', '$2y$10$DrXt/b/n4fyA0N.OaJuR4uA32KEL03VAjmRY5N1P/kEHyz76jE5pG', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'Lessosorio02@gmail.com' AND r.name = 'psicologo'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('MARIA MANUELA OSORIO TRUILLO', 'manuelaosorio520@gmail.com', '1000204139', '$2y$10$LJNerhf3eDXuyWfULtJZ3OU4SRndHYlubBRG/Y4ZMTxhd56D8Bm2G', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'manuelaosorio520@gmail.com' AND r.name = 'medico'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('MARIANA CÁCERES MARTINEZ', 'mariana.caceresm@gmail.com', '1020482062', '$2y$10$hVDF6E/JLGEJoRWFa2IkzO18KqbuZsu17H3fQAR06PoU9A8FGXit2', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'mariana.caceresm@gmail.com' AND r.name = 'psicologo'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('MARTA RUBIELA RUIZ VIANA', 'martaruizviana@gmail.com', '32560160', '$2y$10$S.a5F1oSGSE7fNC7qjc8tu5weZn73CbR6vSYWwfUkP/dYNwrPYT7C', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'martaruizviana@gmail.com' AND r.name = 'psicologo'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('SUSANA BUSTAMANTE GOMEZ', 'susanabustamantegomez28@gmail.com', '1017203121', '$2y$10$zVNPpfvolwYgE27HjBWvYuIaTnxgFrftzc0g9WWOQNGPNON0cd8/2', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'susanabustamantegomez28@gmail.com' AND r.name = 'medico'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('VERONICA VIVIANA ARBELAEZ MEJIA', 'gestortecnico52@gmail.com', '22159952', '$2y$10$agJtwyZfmKZUorvJQVXK/Oncu6hCKKZZQgIPzOybMHd4rPsZtnWMO', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'gestortecnico52@gmail.com' AND r.name = 'coordinador'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('WILMER ALBERTO ACEVEDO GOMEZ', 'gecosistemasaludmental@gmail.com', '98482596', '$2y$10$1LG8UKoijWpcgfCO.EikfeYFGEG73HNzsEud.w9RNlEJM8RZfYNrO', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'gecosistemasaludmental@gmail.com' AND r.name = 'psicologo'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('YEISON MESA DAZA', 'mesadazayeison@gmail.com', '1152465791', '$2y$10$VAGRANhYJa0bdoscmFmaMOfQGp/g2wdX5ZMs7U8B.V0zQu4CesjQ2', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'mesadazayeison@gmail.com' AND r.name = 'psicologo'
ON DUPLICATE KEY UPDATE user_id = user_id;
INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'mesadazayeison@gmail.com' AND r.name = 'especialista'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('YESICA MARIN AGUDELO', 'yesica.1marin@gmail.com', '1037547817', '$2y$10$cZfS9NV2RAie1I0VJjn5Z.78QBA.3L6ejKBymwqegskpb6u74Ly5.', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'yesica.1marin@gmail.com' AND r.name = 'profesional social'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('YURANYS MARCELA MANJARRES SANCHEZ', 'yura.manjarres@gmail.com', '1143242585', '$2y$10$WdQoTL4EkrR7scI5HfsZ7.gAv59tBQD.RzOWXZ5JYLuveuJacriqq', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'yura.manjarres@gmail.com' AND r.name = 'psicologo'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('LUIS GABRIEL GOMEZ BRAVO', 'gomezramirez55@gmail.com', '1128446217', '$2y$10$lWKZ17DZvKqgcr/d2AQEoO8jMvwtioBXU/a/TX04hpxaOrXXB1mn6', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'gomezramirez55@gmail.com' AND r.name = 'abogado'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('SARA ELISA MONSALVE VELEZ', 'saramonsalve0723@gmail.com', '39389389', '$2y$10$XEqLiPgdPe8PYjeDHNI6jOc1qm/jtTlDuUoNLVCRXhsqtPGpi1Bd2', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'saramonsalve0723@gmail.com' AND r.name = 'psicologo'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('OLGA LUCIA MESA GONZALEZ', 'olgaluciamesagonzalez@gmail.com', '21429356', '$2y$10$LPhx4BvLzN7A.h3A2TA1xO7w./GBMUjYFLuBPx8/5rq15IZ096i/u', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'olgaluciamesagonzalez@gmail.com' AND r.name = 'abogado'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('OSCAR ALBERTO RUBIO HENAO', 'osana221@gmail.com', '71704107', '$2y$10$rzME9ruUA74b3oVDzRjQ2O1WHn3FvTHDg5xSja5AMQUMQ4tb/IXJO', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'osana221@gmail.com' AND r.name = 'psicologo'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('DUBER FANNY JURADO ECHEVERRI', 'fannyjurado15@gmail.com', '39446880', '$2y$10$rHLIkUmjdm9uo4bfkDC/2.IfDeYuqeHuU8YEBXeU7.nugBPvKAjZO', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);

INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = 'fannyjurado15@gmail.com' AND r.name = 'psicologo'
ON DUPLICATE KEY UPDATE user_id = user_id;

