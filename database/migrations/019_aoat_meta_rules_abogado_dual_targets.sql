-- Metas separadas SAFER / política pública para abogados (configurables en administración).
ALTER TABLE aoat_meta_rules
    ADD COLUMN target_safer SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'Meta mensual SAFER (solo rol abogado)' AFTER target_value,
    ADD COLUMN target_politica SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'Meta mensual política pública (solo rol abogado)' AFTER target_safer;

UPDATE aoat_meta_rules
SET target_safer = target_value,
    target_politica = target_value
WHERE role_key = 'abogado';
