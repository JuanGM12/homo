<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

final class HomeController
{
    public function index(Request $request): Response
    {
        $tests = [
            'violencias' => ['name' => 'Prevención de Violencias', 'color' => 'primary'],
            'suicidios' => ['name' => 'Prevención de Suicidios', 'color' => 'danger'],
            'adicciones' => ['name' => 'Prevención de Adicciones', 'color' => 'warning'],
            'hospitales' => ['name' => 'Hospitales', 'color' => 'success'],
        ];

        return Response::view('home/index', [
            'pageTitle' => 'Acción en Territorio',
            'tests' => $tests,
        ]);
    }
}

