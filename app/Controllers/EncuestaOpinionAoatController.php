<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\EncuestaOpinionAoatRepository;
use App\Repositories\UserRepository;
use App\Services\Auth;
use App\Services\Flash;

final class EncuestaOpinionAoatController
{
    /** Roles que pueden consultar y exportar encuestas (no permiten editar respuestas). */
    private const ROLES_CONSULTA_EXPORT = ['admin', 'coordinadora', 'coordinador', 'especialista'];

    public function form(Request $request): Response
    {
        $userRepo = new UserRepository();
        $advisors = $userRepo->findNonAdminAdvisors();

        return Response::view('encuesta_opinion_aoat/form', [
            'pageTitle' => 'Encuesta de Opinión - AoAT',
            'advisors' => $advisors,
        ]);
    }

    public function store(Request $request): Response
    {
        $errors = $this->validateForm($request);
        if ($errors !== []) {
            Flash::set([
                'type' => 'error',
                'title' => 'Revisa el formulario',
                'message' => implode("\n", $errors),
            ]);
            return Response::redirect('/encuesta-opinion-aoat');
        }

        $advisorUserId = (int) $request->input('advisor_user_id');
        $userRepo = new UserRepository();
        $user = $userRepo->find($advisorUserId);
        $advisorName = $user ? (string) $user['name'] : 'Asesor';

        $activityDate = trim((string) $request->input('activity_date', ''));
        $data = [
            'advisor_user_id' => $advisorUserId,
            'advisor_name' => $advisorName,
            'actividad' => trim((string) $request->input('actividad', '')),
            'lugar' => trim((string) $request->input('lugar', '')),
            'activity_date' => $activityDate,
            'subregion' => trim((string) $request->input('subregion', '')),
            'municipality' => trim((string) $request->input('municipality', '')),
            'score_objetivos' => (int) $request->input('score_objetivos', 0),
            'score_claridad' => (int) $request->input('score_claridad', 0),
            'score_pertinencia' => (int) $request->input('score_pertinencia', 0),
            'score_ayudas' => (int) $request->input('score_ayudas', 0),
            'score_relacion' => (int) $request->input('score_relacion', 0),
            'score_puntualidad' => (int) $request->input('score_puntualidad', 0),
            'comments' => trim((string) $request->input('comments', '')) ?: null,
        ];

        try {
            $repo = new EncuestaOpinionAoatRepository();
            $repo->create($data);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No se pudo guardar',
                'message' => 'Ocurrió un problema al registrar la encuesta. Intenta nuevamente.',
            ]);
            return Response::redirect('/encuesta-opinion-aoat');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'Encuesta registrada',
            'message' => 'Gracias. Tu opinión ha sido registrada correctamente.',
        ]);
        return Response::redirect('/encuesta-opinion-aoat');
    }

    public function index(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }
        if (!$this->userCanConsultExport($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $repo = new EncuestaOpinionAoatRepository();
        $records = $repo->findAll();

        return Response::view('encuesta_opinion_aoat/index', [
            'pageTitle' => 'Consultar encuestas de opinión AoAT',
            'records' => $records,
        ]);
    }

    public function export(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::redirect('/login');
        }
        if (!$this->userCanConsultExport($user)) {
            return Response::view('errors/403', ['pageTitle' => 'Acceso denegado'], 403);
        }

        $repo = new EncuestaOpinionAoatRepository();
        $records = $repo->findAll();

        $lines = [];
        $lines[] = implode(';', [
            'Fecha registro',
            'Asesor',
            'Actividad',
            'Lugar',
            'Fecha actividad',
            'Subregión',
            'Municipio',
            'Cumplimiento objetivos (1-5)',
            'Claridad y organización (1-5)',
            'Pertinencia temas (1-5)',
            'Ayudas y materiales (1-5)',
            'Relación profesional (1-5)',
            'Puntualidad (1-5)',
            'Comentarios',
        ]);

        foreach ($records as $row) {
            $lines[] = implode(';', array_map(static function ($v): string {
                $s = (string) $v;
                return '"' . str_replace('"', '""', $s) . '"';
            }, [
                (string) ($row['created_at'] ?? ''),
                (string) ($row['advisor_name'] ?? ''),
                (string) ($row['actividad'] ?? ''),
                (string) ($row['lugar'] ?? ''),
                (string) ($row['activity_date'] ?? ''),
                (string) ($row['subregion'] ?? ''),
                (string) ($row['municipality'] ?? ''),
                (string) ($row['score_objetivos'] ?? ''),
                (string) ($row['score_claridad'] ?? ''),
                (string) ($row['score_pertinencia'] ?? ''),
                (string) ($row['score_ayudas'] ?? ''),
                (string) ($row['score_relacion'] ?? ''),
                (string) ($row['score_puntualidad'] ?? ''),
                (string) ($row['comments'] ?? ''),
            ]));
        }

        $csvContent = "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
        $filename = 'encuesta_opinion_aoat_' . date('Ymd_His') . '.csv';

        return new Response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function userCanConsultExport(array $user): bool
    {
        $roles = $user['roles'] ?? [];
        return (bool) array_intersect($roles, self::ROLES_CONSULTA_EXPORT);
    }

    private function validateForm(Request $request): array
    {
        $errors = [];
        if ((int) $request->input('advisor_user_id', 0) <= 0) {
            $errors[] = 'Debes seleccionar el nombre del asesor.';
        }
        if (trim((string) $request->input('actividad', '')) === '') {
            $errors[] = 'El campo Actividad es obligatorio.';
        }
        if (trim((string) $request->input('lugar', '')) === '') {
            $errors[] = 'El campo Lugar es obligatorio.';
        }
        $date = trim((string) $request->input('activity_date', ''));
        if ($date === '') {
            $errors[] = 'El campo Fecha es obligatorio.';
        }
        if (trim((string) $request->input('subregion', '')) === '') {
            $errors[] = 'Debes seleccionar la subregión de pertenencia.';
        }
        if (trim((string) $request->input('municipality', '')) === '') {
            $errors[] = 'Debes seleccionar el municipio de pertenencia.';
        }
        foreach (['score_objetivos', 'score_claridad', 'score_pertinencia', 'score_ayudas', 'score_relacion', 'score_puntualidad'] as $key) {
            $v = (int) $request->input($key, 0);
            if ($v < 1 || $v > 5) {
                $errors[] = 'Debes marcar una valoración del 1 al 5 en cada ítem de satisfacción.';
                break;
            }
        }
        return $errors;
    }
}
