<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AoatActivityWithOptionRepository;
use App\Services\Flash;

final class AoatActivityWithOptionsController
{
    public function index(Request $request): Response
    {
        $repo = new AoatActivityWithOptionRepository();

        return Response::view('admin/aoat_activity_with_options', [
            'pageTitle' => 'Desplegable de con quién realizó AoAT',
            'options' => $repo->allForAdmin(),
        ]);
    }

    public function update(Request $request): Response
    {
        $rows = $request->input('options', []);
        if (!is_array($rows)) {
            $rows = [];
        }

        $clean = [];
        $seen = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $key = mb_strtolower($label, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $sortOrder = (int) ($row['sort_order'] ?? (($index + 1) * 10));
            if ($sortOrder <= 0) {
                $sortOrder = ($index + 1) * 10;
            }

            $clean[] = [
                'label' => $label,
                'sort_order' => $sortOrder,
                'active' => !empty($row['active']) ? 1 : 0,
            ];
        }

        if ($clean === []) {
            Flash::set([
                'type' => 'error',
                'title' => 'Sin opciones válidas',
                'message' => 'Debes dejar al menos una opción en el desplegable.',
            ]);

            return Response::redirect('/admin/aoat-con-quien');
        }

        $hasActive = false;
        foreach ($clean as $option) {
            if (!empty($option['active'])) {
                $hasActive = true;
                break;
            }
        }
        if (!$hasActive) {
            Flash::set([
                'type' => 'error',
                'title' => 'Sin opciones activas',
                'message' => 'Debe quedar al menos una opción activa para registrar AoAT.',
            ]);

            return Response::redirect('/admin/aoat-con-quien');
        }

        try {
            (new AoatActivityWithOptionRepository())->replaceAll($clean);
        } catch (\Throwable) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible guardar',
                'message' => 'Revisa las opciones e intenta nuevamente.',
            ]);

            return Response::redirect('/admin/aoat-con-quien');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'Desplegable actualizado',
            'message' => 'Las opciones de «Con quién realizó la AoAT» ya quedaron disponibles en el registro.',
        ]);

        return Response::redirect('/admin/aoat-con-quien');
    }
}
