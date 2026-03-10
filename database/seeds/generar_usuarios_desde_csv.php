<?php
/**
 * Genera SQL de INSERT para usuarios a partir de un CSV exportado desde Excel.
 *
 * Uso (en la raíz del proyecto):
 *   php database/seeds/generar_usuarios_desde_csv.php "ruta/al/archivo.csv"
 *
 * O copia el CSV a database/seeds/base_2026_usuarios.csv y ejecuta:
 *   php database/seeds/generar_usuarios_desde_csv.php
 *
 * Columnas base del CSV: Cedula, Nombre, Rol, Estado, Email
 * - Contraseña inicial = Cedula.
 * - Estado: Activo -> active=1, otro (ej. Inactivo) -> active=0.
 * - Rol se convierte a roles de BD en mapearRolARoles().
 */

declare(strict_types=1);

const DEFAULT_PASSWORD = 'Homo2026!';
const OUTPUT_SQL_FILE = __DIR__ . '/013_usuarios_base_2026.sql';

$csvPath = $argv[1] ?? __DIR__ . '/base_2026_usuarios.csv';

if (!is_readable($csvPath)) {
    echo "No se encontró o no se puede leer: {$csvPath}\n";
    echo "Exporta tu Excel a CSV (UTF-8) y pasa la ruta:\n  php database/seeds/generar_usuarios_desde_csv.php \"ruta/al/archivo.csv\"\n";
    exit(1);
}

function firstKey(array $row, array $keys): ?string
{
    foreach ($keys as $k) {
        if (isset($row[$k]) && (string) $row[$k] !== '') {
            return (string) $row[$k];
        }
    }
    foreach ($row as $header => $value) {
        $h = mb_strtolower(trim((string) $header), 'UTF-8');
        foreach ($keys as $k) {
            if ($h === $k || str_contains($h, $k) || str_contains($k, $h)) {
                if ((string) $value !== '') {
                    return (string) $value;
                }
            }
        }
    }
    return null;
}

$rows = leerCsv($csvPath);
if ($rows === []) {
    echo "El CSV está vacío o no tiene filas de datos.\n";
    exit(1);
}

$esca = static function (string $s): string {
    return str_replace(["\\", "'"], ["\\\\", "''"], $s);
};

$sql = "-- Usuarios generados desde CSV.\n";
$sql .= "-- La contraseña inicial es el número de documento (si está presente en el CSV).\n";
$sql .= "-- La cédula/documento se guarda en users.document_number para asociar evaluaciones y otros módulos.\n";
$sql .= "-- Ejecutar después de tener las tablas users, roles y user_roles (y los roles creados).\n\n";

$nameKeys = ['nombre', 'name', 'nombres', 'nombre completo', 'nombres y apellidos'];
$emailKeys = ['email', 'correo', 'correo electronico', 'correo electrónico', 'e-mail', 'mail'];
$rolKeys = ['rol', 'roles', 'cargo', 'perfil', 'tipo'];
$docKeys = ['cedula', 'cédula', 'documento', 'document', 'nro documento', 'nro doc', 'numero documento', 'número documento'];
$estadoKeys = ['estado', 'status', 'activo', 'state'];

foreach ($rows as $row) {
    $name = trim((string) (firstKey($row, $nameKeys) ?? ''));
    $email = trim((string) (firstKey($row, $emailKeys) ?? ''));
    $rolRaw = trim((string) (firstKey($row, $rolKeys) ?? ''));
    $document = trim((string) (firstKey($row, $docKeys) ?? ''));
    $estadoRaw = trim((string) (firstKey($row, $estadoKeys) ?? 'Activo'));

    if ($name === '' || $email === '') {
        continue;
    }

    $active = (mb_strtolower($estadoRaw, 'UTF-8') === 'activo') ? 1 : 0;
    $roles = mapearRolARoles($rolRaw);
    $plainPassword = $document !== '' ? $document : DEFAULT_PASSWORD;
    $passwordHash = password_hash($plainPassword, PASSWORD_BCRYPT);

    $sql .= "INSERT INTO users (name, email, document_number, password, active, requires_password_change) VALUES ('"
        . $esca($name) . "', '" . $esca($email) . "', " . ($document !== '' ? "'" . $esca($document) . "'" : "NULL") . ", '" . $esca($passwordHash) . "', " . (int) $active . ", 1)\n";
    $sql .= "ON DUPLICATE KEY UPDATE name = VALUES(name), document_number = VALUES(document_number), password = VALUES(password), active = VALUES(active), requires_password_change = VALUES(requires_password_change);\n\n";

    foreach ($roles as $roleName) {
        $sql .= "INSERT INTO user_roles (user_id, role_id) SELECT u.id, r.id FROM users u CROSS JOIN roles r WHERE u.email = '" . $esca($email) . "' AND r.name = '" . $esca($roleName) . "'\n";
        $sql .= "ON DUPLICATE KEY UPDATE user_id = user_id;\n";
    }
    $sql .= "\n";
}

file_put_contents(OUTPUT_SQL_FILE, $sql);
echo "SQL generado en: " . OUTPUT_SQL_FILE . "\n";
echo "Contraseña inicial = Cedula (columna del CSV). Usuarios con requires_password_change=1.\n";

function leerCsv(string $path): array
{
    $content = file_get_contents($path);
    if ($content === false) {
        return [];
    }
    $content = str_replace(["\xEF\xBB\xBF"], '', $content);
    $firstLine = strtok($content, "\n");
    $sep = (substr_count($firstLine, ';') >= substr_count($firstLine, ',')) ? ';' : ',';

    $handle = fopen('php://memory', 'rb+');
    fwrite($handle, $content);
    rewind($handle);

    $header = fgetcsv($handle, 0, $sep);
    if (!$header) {
        fclose($handle);
        return [];
    }

    $header = array_map(static function ($h) {
        return mb_strtolower(trim((string) $h), 'UTF-8');
    }, $header);

    $rows = [];
    while (($data = fgetcsv($handle, 0, $sep)) !== false) {
        $row = array_combine($header, array_pad($data, count($header), ''));
        if ($row !== false) {
            $rows[] = $row;
        }
    }
    fclose($handle);
    return $rows;
}

function mapearRolARoles(string $rolRaw): array
{
    $r = mb_strtolower($rolRaw, 'UTF-8');
    $roles = [];

    $esAbogado = str_contains($r, 'abogado');
    $esPsicologo = str_contains($r, 'psicólogo') || str_contains($r, 'psicologo') || str_contains($r, 'psicologa');
    $esMedico = str_contains($r, 'médico') || str_contains($r, 'medico');
    $esProfSocial = str_contains($r, 'profesional social') || str_contains($r, 'profesional_social');
    $esEspecializado = str_contains($r, 'especializado') || str_contains($r, 'especialista');
    $esCoordinador = str_contains($r, 'coordinador') || str_contains($r, 'coordinadora');
    $esAdmin = str_contains($r, 'admin') || str_contains($r, 'administrador');
    $esAsesorDeptal = str_contains($r, 'asesor') && str_contains($r, 'departamental');
    $noAplica = str_contains($r, 'no aplica');

    if ($esAdmin) {
        $roles[] = 'admin';
    }
    if ($esCoordinador || $esAsesorDeptal) {
        $roles[] = 'coordinador';
    }
    if ($esAbogado) {
        $roles[] = 'abogado';
    }
    if ($esPsicologo) {
        $roles[] = 'psicologo';
    }
    if ($esMedico) {
        $roles[] = 'medico';
    }
    if ($esProfSocial) {
        $roles[] = 'profesional social';
    }
    if ($esEspecializado) {
        $roles[] = 'especialista';
    }

    if ($roles === []) {
        if (preg_match('/^\s*especialista\s*$/iu', $rolRaw)) {
            $roles[] = 'especialista';
        } elseif ($noAplica) {
            $roles[] = 'profesional social';
        } else {
            $roles[] = 'psicologo';
        }
    }

    return array_values(array_unique($roles));
}
