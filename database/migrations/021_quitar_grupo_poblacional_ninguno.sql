-- Quitar opción "Ninguno" del grupo poblacional: limpia datos históricos en JSON.

-- Ejecutar hasta 3 veces por si hay entradas duplicadas inconsistentes.

UPDATE asistencia_asistentes
SET grupo_poblacional = JSON_REMOVE(
        grupo_poblacional,
        JSON_UNQUOTE(JSON_SEARCH(grupo_poblacional, 'one', 'Ninguno', NULL, '$[*]'))
    )
WHERE grupo_poblacional IS NOT NULL
  AND JSON_TYPE(grupo_poblacional) = 'ARRAY'
  AND JSON_SEARCH(grupo_poblacional, 'one', 'Ninguno', NULL, '$[*]') IS NOT NULL;

UPDATE asistencia_asistentes
SET grupo_poblacional = JSON_REMOVE(
        grupo_poblacional,
        JSON_UNQUOTE(JSON_SEARCH(grupo_poblacional, 'one', 'Ninguno', NULL, '$[*]'))
    )
WHERE grupo_poblacional IS NOT NULL
  AND JSON_TYPE(grupo_poblacional) = 'ARRAY'
  AND JSON_SEARCH(grupo_poblacional, 'one', 'Ninguno', NULL, '$[*]') IS NOT NULL;

UPDATE asistencia_asistentes
SET grupo_poblacional = JSON_REMOVE(
        grupo_poblacional,
        JSON_UNQUOTE(JSON_SEARCH(grupo_poblacional, 'one', 'Ninguno', NULL, '$[*]'))
    )
WHERE grupo_poblacional IS NOT NULL
  AND JSON_TYPE(grupo_poblacional) = 'ARRAY'
  AND JSON_SEARCH(grupo_poblacional, 'one', 'Ninguno', NULL, '$[*]') IS NOT NULL;
