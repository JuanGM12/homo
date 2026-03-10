<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\EvaluacionesController;
use App\Core\Request;
use App\Core\Response;

final class HomeController
{
    public function index(Request $request): Response
    {
        return Response::view('home/index', [
            'pageTitle' => 'Equipo de Promoción y Prevención',
            'tests' => EvaluacionesController::getTestsList(),
        ]);
    }
}

