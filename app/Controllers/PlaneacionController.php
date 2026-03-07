<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\TrainingPlanRepository;
use App\Services\Auth;
use App\Services\Flash;

final class PlaneacionController
{
    private TrainingPlanRepository $repository;

    public function __construct()
    {
        $this->repository = new TrainingPlanRepository();
    }

    public function index(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$this->userCanAccessModule($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $records = $this->repository->findForUser((int) $user['id']);

        return Response::view('planeacion/index', [
            'pageTitle' => 'Planeación anual de capacitaciones',
            'records' => $records,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$this->userCanAccessModule($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $professional = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'roles' => $user['roles'] ?? [],
        ];

        $primaryRole = (string) ($user['role'] ?? ($professional['roles'][0] ?? ''));

        return Response::view('planeacion/form', [
            'pageTitle' => 'Nueva planeación anual',
            'mode' => 'create',
            'plan' => null,
            'professional' => $professional,
            'role' => $primaryRole,
            'planYear' => (int) date('Y'),
        ]);
    }

    public function store(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$this->userCanAccessModule($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $subregion = trim((string) $request->input('subregion'));
        $municipality = trim((string) $request->input('municipality'));
        $planYear = (int) ($request->input('plan_year') ?? date('Y'));

        $errors = [];

        if ($subregion === '') {
            $errors[] = 'Debes seleccionar la subregión.';
        }

        if ($municipality === '') {
            $errors[] = 'Debes seleccionar el municipio.';
        }

        $months = [
            'enero' => 'Enero',
            'febrero' => 'Febrero',
            'marzo' => 'Marzo',
            'abril' => 'Abril',
            'mayo' => 'Mayo',
            'junio' => 'Junio',
            'julio' => 'Julio',
            'agosto' => 'Agosto',
            'septiembre' => 'Septiembre',
            'octubre' => 'Octubre',
            'noviembre' => 'Noviembre',
            'diciembre' => 'Diciembre',
        ];

        $payload = [];

        foreach ($months as $key => $label) {
            /** @var array<int, string> $topics */
            $topics = $request->input($key . '_temas', []);
            $topics = is_array($topics) ? array_values(array_filter(array_map('strval', $topics))) : [];

            $population = trim((string) $request->input($key . '_poblacion'));

            if (empty($topics) || $population === '') {
                $errors[] = "Debes seleccionar al menos un tema y definir la población objetivo para {$label}.";
            }

            $payload[$key] = [
                'label' => $label,
                'topics' => $topics,
                'population' => $population,
            ];
        }

        if (!empty($errors)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Revisa la planeación',
                'message' => implode("\n", $errors),
            ]);

            return Response::redirect('/planeacion/nueva');
        }

        $this->repository->create([
            'user_id' => (int) $user['id'],
            'professional_name' => (string) $user['name'],
            'professional_email' => (string) $user['email'],
            'professional_role' => (string) (($user['roles'] ?? [])[0] ?? ''),
            'subregion' => $subregion,
            'municipality' => $municipality,
            'plan_year' => $planYear,
            'editable' => 1,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        Flash::set([
            'type' => 'success',
            'title' => 'Planeación registrada',
            'message' => 'La planeación anual de capacitaciones se ha guardado correctamente.',
        ]);

        return Response::redirect('/planeacion');
    }

    public function export(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }

        if (!$this->userCanAccessModule($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $records = $this->repository->findForUser((int) $user['id']);

        if ($records === []) {
            Flash::set([
                'type' => 'info',
                'title' => 'Sin registros para exportar',
                'message' => 'Aún no tienes planeaciones anuales registradas para exportar.',
            ]);

            return Response::redirect('/planeacion');
        }

        $months = [
            'enero' => 'Enero',
            'febrero' => 'Febrero',
            'marzo' => 'Marzo',
            'abril' => 'Abril',
            'mayo' => 'Mayo',
            'junio' => 'Junio',
            'julio' => 'Julio',
            'agosto' => 'Agosto',
            'septiembre' => 'Septiembre',
            'octubre' => 'Octubre',
            'noviembre' => 'Noviembre',
            'diciembre' => 'Diciembre',
        ];

        $lines = [];
        $lines[] = implode(';', [
            'Año',
            'Profesional',
            'Rol',
            'Subregión',
            'Municipio',
            'Mes',
            'Temas / módulos',
            'Población objetivo',
        ]);

        foreach ($records as $plan) {
            $payload = [];
            if (!empty($plan['payload'])) {
                $decoded = json_decode((string) $plan['payload'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            foreach ($months as $key => $label) {
                $monthData = $payload[$key] ?? ['topics' => [], 'population' => ''];
                $topics = $monthData['topics'] ?? [];
                $population = (string) ($monthData['population'] ?? '');

                $topicsText = is_array($topics) ? implode(' | ', $topics) : '';

                $row = [
                    (string) $plan['plan_year'],
                    (string) $plan['professional_name'],
                    (string) $plan['professional_role'],
                    (string) $plan['subregion'],
                    (string) $plan['municipality'],
                    $label,
                    $topicsText,
                    $population,
                ];

                $lines[] = implode(';', array_map(static function (string $value): string {
                    $escaped = str_replace('"', '""', $value);
                    return '"' . $escaped . '"';
                }, $row));
            }
        }

        $csvContent = implode("\r\n", $lines) . "\r\n";

        $filename = sprintf('planeacion_capacitaciones_%s.csv', date('Ymd_His'));

        return new Response(
            $csvContent,
            200,
            [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    private function userCanAccessModule(array $user): bool
    {
        $roles = $user['roles'] ?? [];
        $allowed = ['abogado', 'medico', 'psicologo', 'admin'];

        return (bool) array_intersect($roles, $allowed);
    }
}

