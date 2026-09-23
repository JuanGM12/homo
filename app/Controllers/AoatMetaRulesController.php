<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AoatMetaRuleRepository;
use App\Repositories\AoatPeriodRepository;
use App\Services\Flash;

final class AoatMetaRulesController
{
    public function index(Request $request): Response
    {
        $repo = new AoatMetaRuleRepository();

        return Response::view('admin/aoat_meta_rules', [
            'pageTitle' => 'Configuracion de metas AoAT',
            'rules' => $repo->allForAdmin(),
            'roleOptions' => $this->roleOptions(),
            'scopeOptions' => $this->scopeOptions(),
            'periods' => (new AoatPeriodRepository())->all(),
        ]);
    }

    public function createPeriod(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));
        $contractNumber = trim((string) $request->input('contract_number', ''));
        $active = (string) $request->input('active', '') === '1';
        $repo = new AoatPeriodRepository();

        try {
            $repo->create($name, $active, $contractNumber);
        } catch (\Throwable) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible crear el periodo',
                'message' => 'Verifica el nombre e intenta nuevamente.',
            ]);

            return Response::redirect('/admin/aoat-metas');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'Periodo creado',
            'message' => $active ? 'El periodo quedo creado y activo para nuevos registros AoAT.' : 'El periodo quedo disponible en la configuracion.',
        ]);

        return Response::redirect('/admin/aoat-metas');
    }

    public function updatePeriod(Request $request): Response
    {
        $id = (int) $request->input('id', 0);
        $name = trim((string) $request->input('name', ''));
        $contractNumber = trim((string) $request->input('contract_number', ''));

        try {
            (new AoatPeriodRepository())->update($id, $name, $contractNumber);
        } catch (\Throwable) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible actualizar',
                'message' => 'Verifica el nombre, el número de contrato e intenta nuevamente.',
            ]);

            return Response::redirect('/admin/aoat-metas');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'Periodo actualizado',
            'message' => 'El nombre y el número de contrato quedaron guardados.',
        ]);

        return Response::redirect('/admin/aoat-metas');
    }

    public function activatePeriod(Request $request): Response
    {
        $id = (int) $request->input('id', 0);

        try {
            (new AoatPeriodRepository())->activate($id);
        } catch (\Throwable) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible activar',
                'message' => 'El periodo seleccionado no esta disponible.',
            ]);

            return Response::redirect('/admin/aoat-metas');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'Periodo activo actualizado',
            'message' => 'Los nuevos registros AoAT se guardaran en el periodo activo.',
        ]);

        return Response::redirect('/admin/aoat-metas');
    }

    public function update(Request $request): Response
    {
        $rows = $request->input('rules', []);
        if (!is_array($rows)) {
            $rows = [];
        }

        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $roleKey = trim((string) ($row['role_key'] ?? ''));
            $scope = trim((string) ($row['scope'] ?? ''));
            $targetValue = max(0, (int) ($row['target_value'] ?? 0));
            $monthFrom = max(1, min(12, (int) ($row['month_from'] ?? 1)));
            $monthTo = max(1, min(12, (int) ($row['month_to'] ?? 12)));
            $ruleYearRaw = trim((string) ($row['rule_year'] ?? ''));
            $notes = trim((string) ($row['notes'] ?? ''));
            $active = !empty($row['active']) ? 1 : 0;

            if (!in_array($roleKey, ['psicologo', 'abogado', 'medico'], true)) {
                continue;
            }
            if (!in_array($scope, ['per_territory', 'global_monthly'], true)) {
                continue;
            }
            if ($monthTo < $monthFrom) {
                [$monthFrom, $monthTo] = [$monthTo, $monthFrom];
            }

            if ($roleKey === 'abogado' && $scope === 'per_territory') {
                $ts = max(0, (int) ($row['target_safer'] ?? 0));
                $tp = max(0, (int) ($row['target_politica'] ?? 0));
                if ($ts === 0 && $tp === 0 && $targetValue > 0) {
                    $ts = $targetValue;
                    $tp = $targetValue;
                }
                if ($active === 1 && $ts <= 0 && $tp <= 0) {
                    continue;
                }
                $legacyTarget = max($ts, $tp);
                $clean[] = [
                    'role_key' => $roleKey,
                    'scope' => $scope,
                    'target_value' => $legacyTarget,
                    'target_safer' => $ts,
                    'target_politica' => $tp,
                    'month_from' => $monthFrom,
                    'month_to' => $monthTo,
                    'rule_year' => $ruleYearRaw === '' ? null : max(2020, min(2100, (int) $ruleYearRaw)),
                    'active' => $active,
                    'notes' => $notes,
                ];

                continue;
            }

            if ($targetValue <= 0 && $active === 1) {
                continue;
            }

            $clean[] = [
                'role_key' => $roleKey,
                'scope' => $scope,
                'target_value' => $targetValue,
                'target_safer' => null,
                'target_politica' => null,
                'month_from' => $monthFrom,
                'month_to' => $monthTo,
                'rule_year' => $ruleYearRaw === '' ? null : max(2020, min(2100, (int) $ruleYearRaw)),
                'active' => $active,
                'notes' => $notes,
            ];
        }

        if ($clean === []) {
            Flash::set([
                'type' => 'error',
                'title' => 'Sin reglas validas',
                'message' => 'Debes dejar al menos una regla activa para las metas AoAT.',
            ]);

            return Response::redirect('/admin/aoat-metas');
        }

        $repo = new AoatMetaRuleRepository();

        try {
            $repo->replaceAll($clean);
        } catch (\Throwable) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible guardar',
                'message' => 'Revisa la configuracion e intenta nuevamente.',
            ]);

            return Response::redirect('/admin/aoat-metas');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'Metas AoAT actualizadas',
            'message' => 'La configuracion ya quedo disponible para el cuadro territorial.',
        ]);

        return Response::redirect('/admin/aoat-metas');
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    private function roleOptions(): array
    {
        return [
            ['value' => 'psicologo', 'label' => 'Psicologo'],
            ['value' => 'abogado', 'label' => 'Abogado'],
            ['value' => 'medico', 'label' => 'Medico'],
        ];
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    private function scopeOptions(): array
    {
        return [
            ['value' => 'per_territory', 'label' => 'Por territorio'],
            ['value' => 'global_monthly', 'label' => 'Global mensual'],
        ];
    }
}
