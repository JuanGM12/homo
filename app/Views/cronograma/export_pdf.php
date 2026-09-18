<?php
/** @var array<string, array<int, array<string, mixed>>> $actividadesPorFecha */
/** @var string $tituloRangoLocal */
/** @var string $logoAntioquia */
/** @var string $logoHomo */

$esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$diasSemana = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
$mesesCorto = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$fechaEstilo = static function (string $fecha) use ($diasSemana, $mesesCorto): string {
    $d = strtotime($fecha);
    return $diasSemana[(int) date('w', $d)] . ' ' . (int) date('j', $d) . ' de ' . $mesesCorto[(int) date('n', $d)] . ' de ' . date('Y', $d);
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cronograma</title>
    <style>
        body { font-family: Arial, sans-serif; color: #203246; font-size: 12px; margin: 16px; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .title { font-size: 16px; font-weight: 700; color: #214f43; }
        .sub { font-size: 10px; color: #58708b; }
        .fecha-bloque { margin-bottom: 14px; }
        .fecha-titulo { font-size: 13px; font-weight: 700; margin-bottom: 4px; }
        .evento { margin: 0 0 10px 8px; }
        .evento-hora { color: #4160a4; font-size: 11px; }
        .evento-titulo { font-weight: 700; color: #2a5543; }
        .evento-detalle { font-size: 10px; color: #475569; margin-left: 8px; }
        .footer { margin-top: 16px; font-size: 9px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <table class="header"><tr>
        <td style="width:22%;"><?= $logoAntioquia !== '' ? '<img src="' . $esc($logoAntioquia) . '" alt="Gobernación" style="height:34px;width:auto;">' : '' ?></td>
        <td style="width:56%;text-align:center;"><div class="title">Cronograma</div><div class="sub"><?= $esc((string) $tituloRangoLocal) ?></div></td>
        <td style="width:22%;text-align:right;"><?= $logoHomo !== '' ? '<img src="' . $esc($logoHomo) . '" alt="HOMO" style="height:34px;width:auto;">' : '' ?></td>
    </tr></table>

    <?php if ($actividadesPorFecha === []): ?>
        <p class="sub">No hay actividades en el período seleccionado.</p>
    <?php else: ?>
        <?php foreach ($actividadesPorFecha as $fecha => $actividades): ?>
            <div class="fecha-bloque">
                <div class="fecha-titulo"><?= $esc($fechaEstilo((string) $fecha)) ?></div>
                <?php foreach ($actividades as $a): ?>
                    <div class="evento">
                        <div class="evento-hora"><?= $esc((string) ($a['hora'] ?? '')) ?></div>
                        <div class="evento-titulo"><?= $esc((string) ($a['titulo'] ?? '')) ?></div>
                        <div class="evento-detalle">
                            <div>Tipo: <?= $esc((string) ($a['activity_type_label'] ?? '')) ?></div>
                            <div>Tema: <?= $esc((string) ($a['tema_label'] ?? '')) ?></div>
                            <div>Población atendida: <?= $esc((string) ($a['poblacion_atendida'] ?? '')) ?></div>
                            <div>Municipio: <?= $esc((string) ($a['municipio_nombre'] ?? '')) ?> · <?= $esc((string) ($a['subregion'] ?? '')) ?></div>
                            <div>Responsable: <?= $esc((string) ($a['usuario_nombre'] ?? '')) ?> (<?= $esc((string) ($a['usuario_rol'] ?? '')) ?>)</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <p class="footer">Documento generado desde Equipo de Promoción y Prevención.</p>
</body>
</html>
