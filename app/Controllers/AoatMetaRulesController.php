<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AoatMetaRuleRepository;
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
        ]);
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
            if ($targetValue <= 0 && $active === 1) {
                continue;
            }

            $clean[] = [
                'role_key' => $roleKey,
                'scope' => $scope,
                'target_value' => $targetValue,
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
