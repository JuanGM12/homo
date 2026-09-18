<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;

/**
 * Enunciados y validación de cualificación por rol (AoAT y Plan de Entrenamiento).
 */
final class QualificationCatalog
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    /** @var array<string, string> */
    private const LEGACY_ARRAY_KEYS = [
        'suicidio' => 'prev_suicidio',
        'violencias' => 'prev_violencias',
        'adicciones' => 'prev_adicciones',
        'otros_temas_salud_mental' => 'salud_mental',
    ];

    public static function normalizeRole(string $role): string
    {
        $role = strtolower(trim(str_replace('_', ' ', $role)));
        if ($role === 'trabajador social') {
            return 'profesional social';
        }

        return $role;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function roleConfig(string $role): ?array
    {
        $role = self::normalizeRole($role);
        $all = self::all();
        $config = $all[$role] ?? null;

        return is_array($config) ? $config : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function sectionsForRole(string $role): array
    {
        $config = self::roleConfig($role);
        $sections = is_array($config) ? ($config['sections'] ?? []) : [];
        if (!is_array($sections)) {
            return [];
        }

        return array_values(array_filter($sections, static fn ($row): bool => is_array($row)));
    }

    public static function politologoLockedValue(): string
    {
        $sections = self::sectionsForRole('politologo');
        $first = $sections[0]['options'][0]['value'] ?? '';

        return is_string($first) ? $first : '';
    }

    public static function validateFromRequest(string $role, Request $request): ?string
    {
        $config = self::roleConfig($role);
        if ($config === null) {
            return null;
        }

        foreach (self::sectionsForRole($role) as $section) {
            $type = (string) ($section['type'] ?? 'checkbox');
            $key = (string) ($section['key'] ?? '');
            $title = (string) ($section['title'] ?? $key);
            $required = !empty($section['required']);
            $allowed = self::optionValues($section);

            if ($key === '' || $type === 'locked') {
                continue;
            }

            if ($type === 'radio') {
                $value = trim((string) $request->input($key, ''));
                if ($required && ($value === '' || !in_array($value, $allowed, true))) {
                    return 'Debes seleccionar una opción en «' . $title . '».';
                }
                if ($value !== '' && !in_array($value, $allowed, true)) {
                    return 'La opción seleccionada en «' . $title . '» no es válida.';
                }
                continue;
            }

            $values = self::inputStringArray($request, $key);
            if ($required && $values === []) {
                return 'Debes marcar al menos una opción en «' . $title . '».';
            }
            foreach ($values as $value) {
                if (!in_array($value, $allowed, true)) {
                    return 'Hay una opción no válida en «' . $title . '».';
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function collectPosted(string $role, Request $request): array
    {
        $role = self::normalizeRole($role);
        $config = self::roleConfig($role);
        $payload = [];

        if ($config === null) {
            return $payload;
        }

        foreach (self::sectionsForRole($role) as $section) {
            $type = (string) ($section['type'] ?? 'checkbox');
            $key = (string) ($section['key'] ?? '');
            if ($key === '') {
                continue;
            }

            if ($type === 'locked') {
                $values = self::optionValues($section);
                $payload[$key] = $values !== [] ? [$values[0]] : [];
                continue;
            }

            if ($type === 'radio') {
                $value = trim((string) $request->input($key, ''));
                $payload[$key] = $value;
                continue;
            }

            $payload[$key] = self::inputStringArray($request, $key);
        }

        if (!empty($config['show_otro_caso'])) {
            $payload['otro_caso'] = trim((string) $request->input('otro_caso', ''));
        }

        return $payload;
    }

    public static function optionLabel(string $role, string $key, string $value): string
    {
        foreach (self::sectionsForRole($role) as $section) {
            if ((string) ($section['key'] ?? '') !== $key) {
                continue;
            }
            foreach ((array) ($section['options'] ?? []) as $option) {
                if (!is_array($option)) {
                    continue;
                }
                if ((string) ($option['value'] ?? '') === $value) {
                    $label = trim((string) ($option['label'] ?? $value));

                    return $label !== '' ? $label : $value;
                }
            }
        }

        return $value;
    }

    /**
     * Rehidrata keys antiguas del plan de entrenamiento hacia las keys AoAT.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function hydrateLegacyPayload(array $payload, string $role = 'psicologo'): array
    {
        $role = self::normalizeRole($role !== '' ? $role : 'psicologo');

        foreach (self::LEGACY_ARRAY_KEYS as $legacyKey => $newKey) {
            $existing = $payload[$newKey] ?? null;
            if (is_array($existing) && $existing !== []) {
                continue;
            }

            $legacyValues = $payload[$legacyKey] ?? null;
            if (!is_array($legacyValues) || $legacyValues === []) {
                continue;
            }

            $mapped = [];
            foreach ($legacyValues as $raw) {
                $value = self::matchOptionValue($role, $newKey, (string) $raw);
                if ($value !== null) {
                    $mapped[] = $value;
                }
            }
            if ($mapped !== []) {
                $payload[$newKey] = array_values(array_unique($mapped));
            }
        }

        return $payload;
    }

    public static function inferRoleFromPayload(array $payload): string
    {
        if (isset($payload['prev_suicidio']) || isset($payload['suicidio']) || isset($payload['politica_publica_psicologo'])) {
            return 'psicologo';
        }
        if (isset($payload['mesa_salud_mental']) || isset($payload['safer'])) {
            return 'abogado';
        }
        if (isset($payload['temas_hospital']) || isset($payload['espacios_participacion_medico'])) {
            return 'medico';
        }
        if (isset($payload['actividad_social'])) {
            return 'profesional social';
        }
        if (isset($payload['ppmsmypa']) && is_array($payload['ppmsmypa'])) {
            $locked = self::politologoLockedValue();
            foreach ($payload['ppmsmypa'] as $value) {
                if ((string) $value === $locked) {
                    return 'politologo';
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array{title: string, values: list<string>}>
     */
    public static function displaySections(array $payload, string $role = ''): array
    {
        $payload = self::hydrateLegacyPayload($payload, $role !== '' ? $role : 'psicologo');
        if ($role === '') {
            $role = self::inferRoleFromPayload($payload);
        }

        $out = [];
        foreach (self::sectionsForRole($role) as $section) {
            $key = (string) ($section['key'] ?? '');
            $title = (string) ($section['title'] ?? $key);
            $type = (string) ($section['type'] ?? 'checkbox');
            $raw = $payload[$key] ?? null;
            $values = [];
            if ($type === 'radio') {
                $value = is_array($raw) ? (string) ($raw[0] ?? '') : trim((string) $raw);
                if ($value !== '') {
                    $values[] = self::optionLabel($role, $key, $value);
                }
            } elseif (is_array($raw)) {
                foreach ($raw as $item) {
                    $item = trim((string) $item);
                    if ($item === '') {
                        continue;
                    }
                    $values[] = self::optionLabel($role, $key, $item);
                }
            }

            $out[] = [
                'title' => $title,
                'values' => $values,
            ];
        }

        if ($out === []) {
            foreach (self::LEGACY_ARRAY_KEYS as $legacyKey => $labelKey) {
                $raw = $payload[$legacyKey] ?? null;
                if (!is_array($raw) || $raw === []) {
                    continue;
                }
                $out[] = [
                    'title' => match ($legacyKey) {
                        'suicidio' => 'Suicidio',
                        'violencias' => 'Violencias',
                        'adicciones' => 'Adicciones',
                        default => 'Otros temas en salud mental',
                    },
                    'values' => array_values(array_filter(array_map('strval', $raw))),
                ];
            }
        }

        return $out;
    }

    /**
     * Etiquetas planas del catálogo unificado para Listado AoAT. Omite «No aplica».
     *
     * @return list<string>
     */
    public static function listadoOptionsForRole(string $role): array
    {
        $seen = [];
        $out = [];
        foreach (self::sectionsForRole($role) as $section) {
            foreach ((array) ($section['options'] ?? []) as $option) {
                if (!is_array($option)) {
                    continue;
                }
                $value = trim((string) ($option['value'] ?? ''));
                $label = trim((string) ($option['label'] ?? $value));
                $display = $label !== '' ? $label : $value;
                if (
                    $display === ''
                    || strcasecmp($display, 'No aplica') === 0
                    || strcasecmp($value, 'No aplica') === 0
                ) {
                    continue;
                }
                if (isset($seen[$display])) {
                    continue;
                }
                $seen[$display] = true;
                $out[] = $display;
            }
        }

        return $out;
    }

    /**
     * Temas planos del catálogo de un rol (para Cronograma). Omite «No aplica».
     *
     * @return list<array{value: string, label: string}>
     */
    public static function topicOptionsForRole(string $role): array
    {
        $seen = [];
        $out = [];
        foreach (self::sectionsForRole($role) as $section) {
            foreach ((array) ($section['options'] ?? []) as $option) {
                if (!is_array($option)) {
                    continue;
                }
                $value = trim((string) ($option['value'] ?? ''));
                $label = trim((string) ($option['label'] ?? $value));
                if ($value === '' || strcasecmp($value, 'No aplica') === 0) {
                    continue;
                }
                if (isset($seen[$value])) {
                    continue;
                }
                $seen[$value] = true;
                $out[] = [
                    'value' => $value,
                    'label' => $label !== '' ? $label : $value,
                ];
            }
        }

        return $out;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function allTopicOptions(): array
    {
        $seen = [];
        $out = [];
        foreach (array_keys(self::all()) as $role) {
            foreach (self::topicOptionsForRole($role) as $option) {
                $value = $option['value'];
                if (isset($seen[$value])) {
                    continue;
                }
                $seen[$value] = true;
                $out[] = $option;
            }
        }

        return $out;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function all(): array
    {
        if (self::$cache === null) {
            /** @var array<string, array<string, mixed>> $data */
            $data = require dirname(__DIR__) . '/Data/QualificationStatements.php';
            self::$cache = $data;
        }

        return self::$cache;
    }

    /**
     * @param array<string, mixed> $section
     * @return list<string>
     */
    private static function optionValues(array $section): array
    {
        $out = [];
        foreach ((array) ($section['options'] ?? []) as $option) {
            if (!is_array($option)) {
                continue;
            }
            $value = trim((string) ($option['value'] ?? ''));
            if ($value !== '') {
                $out[] = $value;
            }
        }

        return $out;
    }

    private static function matchOptionValue(string $role, string $key, string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        foreach (self::sectionsForRole($role) as $section) {
            if ((string) ($section['key'] ?? '') !== $key) {
                continue;
            }
            foreach ((array) ($section['options'] ?? []) as $option) {
                if (!is_array($option)) {
                    continue;
                }
                $value = trim((string) ($option['value'] ?? ''));
                $label = trim((string) ($option['label'] ?? $value));
                if ($raw === $value || $raw === $label) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function inputStringArray(Request $request, string $key): array
    {
        $v = $request->input($key);
        if (is_array($v)) {
            return array_values(array_filter(array_map(
                static fn ($item): string => trim((string) $item),
                $v
            ), static fn (string $s): bool => $s !== ''));
        }
        if (is_string($v) && trim($v) !== '') {
            return [trim($v)];
        }

        return [];
    }
}
