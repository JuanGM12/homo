<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\TestResponseRepository;
use App\Services\Auth;
use App\Services\Flash;

final class EvaluacionesController
{
    /** Clave de respuestas correctas: POST - TEST Prevención de Violencias (preguntas 1 a 9) */
    private const POST_VIOLENCIAS_CORRECT = [
        1 => 'B',
        2 => 'C',
        3 => 'C',
        4 => 'C',
        5 => 'A',
        6 => 'D',
        7 => 'C',
        8 => 'C',
        9 => 'C',
    ];

    /** Clave de respuestas correctas: POST - TEST Prevención de Suicidios (preguntas 1 a 12) */
    private const POST_SUICIDIOS_CORRECT = [
        1 => 'B',
        2 => 'D',
        3 => 'B',
        4 => 'C',
        5 => 'C',
        6 => 'B',
        7 => 'B',
        8 => 'C',
        9 => 'C',
        10 => 'E',
        11 => 'D',
        12 => 'C',
    ];

    /** Clave de respuestas correctas: POST - TEST Hospitales (preguntas 1 a 20) */
    private const POST_HOSPITALES_CORRECT = [
        1 => 'C',
        2 => 'B',
        3 => 'C',
        4 => 'B',
        5 => 'C',
        6 => 'B',
        7 => 'B',
        8 => 'C',
        9 => 'B',
        10 => 'B',
        11 => 'B',
        12 => 'B',
        13 => 'B',
        14 => 'E',
        15 => 'A',
        16 => 'D',
        17 => 'A',
        18 => 'B',
        19 => 'C',
        20 => 'D',
    ];

    /** Clave de respuestas correctas: POST - TEST Prevención de Adicciones (preguntas 1 a 9) */
    private const POST_ADICCIONES_CORRECT = [
        1 => 'B',
        2 => 'B',
        3 => 'B',
        4 => 'C',
        5 => 'B',
        6 => 'B',
        7 => 'C',
        8 => 'C',
        9 => 'B',
    ];

    /** Clave de respuestas correctas: PRE - TEST Prevención de Adicciones (preguntas 1 a 9) */
    private const PRE_ADICCIONES_CORRECT = [
        1 => 'B',
        2 => 'B',
        3 => 'B',
        4 => 'C',
        5 => 'B',
        6 => 'B',
        7 => 'C',
        8 => 'C',
        9 => 'B',
    ];

    /** Clave de respuestas correctas: PRE - TEST Prevención de Suicidios (preguntas 1 a 12) */
    private const PRE_SUICIDIOS_CORRECT = [
        1 => 'B',
        2 => 'D',
        3 => 'B',
        4 => 'C',
        5 => 'C',
        6 => 'B',
        7 => 'B',
        8 => 'C',
        9 => 'C',
        10 => 'E',
        11 => 'D',
        12 => 'C',
    ];

    /** Clave de respuestas correctas: PRE - TEST Prevención de Violencias (preguntas 1 a 9) */
    private const PRE_VIOLENCIAS_CORRECT = [
        1 => 'B',
        2 => 'C',
        3 => 'C',
        4 => 'C',
        5 => 'A',
        6 => 'D',
        7 => 'C',
        8 => 'C',
        9 => 'C',
    ];

    /** Clave de respuestas correctas: PRE - TEST Hospitales (preguntas 1 a 20) */
    private const PRE_HOSPITALES_CORRECT = [
        1 => 'C',
        2 => 'C',
        3 => 'B',
        4 => 'C',
        5 => 'C',
        6 => 'D',
        7 => 'C',
        8 => 'C',
        9 => 'C',
        10 => 'B',
        11 => 'C',
        12 => 'D',
        13 => 'B',
        14 => 'E',
        15 => 'D',
        16 => 'A',
        17 => 'A',
        18 => 'C',
        19 => 'B',
        20 => 'C',
    ];

    /** @return array<string, array{name: string, color: string}> */
    public static function getTestsList(): array
    {
        return [
            'violencias' => [
                'name' => 'Prevención de Violencias',
                'color' => 'primary',
            ],
            'suicidios' => [
                'name' => 'Prevención de Suicidios',
                'color' => 'danger',
            ],
            'adicciones' => [
                'name' => 'Prevención de Adicciones',
                'color' => 'warning',
            ],
            'hospitales' => [
                'name' => 'Hospitales',
                'color' => 'success',
            ],
        ];
    }

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $tests = self::getTestsList();

        $roles = $user['roles'] ?? [];
        $canSeeAll = $user && (in_array('admin', $roles, true) || in_array('coordinador', $roles, true) || in_array('coordinadora', $roles, true));

        $filters = [
            'test_key' => (string) $request->input('test_key', ''),
            'phase' => (string) $request->input('phase', ''),
            'document_number' => trim((string) $request->input('document_number', '')),
            'subregion' => trim((string) $request->input('subregion', '')),
            'municipality' => trim((string) $request->input('municipality', '')),
            'date_from' => (string) $request->input('date_from', ''),
            'date_to' => (string) $request->input('date_to', ''),
        ];

        // Si no es admin/coordinador, fuerza a ver solo sus propios registros (por documento asociado)
        if (!$canSeeAll && $user && !empty($user['document_number'])) {
            $filters['document_number'] = (string) $user['document_number'];
        }

        $records = [];
        if ($user) {
            $repo = new TestResponseRepository();
            $records = $repo->search($filters);
        }

        return Response::view('evaluaciones/index', [
            'pageTitle' => 'Evaluaciones - Test',
            'tests' => $tests,
            'filters' => $filters,
            'records' => $records,
            'currentUser' => $user,
            'canSeeAll' => (bool) $canSeeAll,
        ]);
    }

    // PRE
    public function preViolencias(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->storePreViolencias($request);
        }

        return $this->renderForm($request, 'violencias', 'pre');
    }

    public function preSuicidios(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->storePreSuicidios($request);
        }

        return $this->renderForm($request, 'suicidios', 'pre');
    }

    public function preAdicciones(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->storePreAdicciones($request);
        }

        return $this->renderForm($request, 'adicciones', 'pre');
    }

    public function preHospitales(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->storePreHospitales($request);
        }

        return $this->renderForm($request, 'hospitales', 'pre');
    }

    // POST
    public function postViolencias(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->storePostViolencias($request);
        }

        return $this->renderForm($request, 'violencias', 'post');
    }

    public function postSuicidios(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->storePostSuicidios($request);
        }

        return $this->renderForm($request, 'suicidios', 'post');
    }

    private function storePreAdicciones(Request $request): Response
    {
        $testKey = 'adicciones';
        $phase = 'pre';

        $documentNumber = trim((string) $request->input('document_number', ''));
        $firstName = trim((string) $request->input('first_name', ''));
        $lastName = trim((string) $request->input('last_name', ''));
        $subregion = trim((string) $request->input('subregion', ''));
        $municipality = trim((string) $request->input('municipality', ''));

        if ($documentNumber === '' || !preg_match('/^[0-9]+$/', $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Número de documento no válido',
                'message' => 'El número de documento debe contener solo números.',
            ]);

            return Response::redirect('/evaluaciones/adicciones/pre');
        }

        if ($firstName === '' || $lastName === '' || $subregion === '' || $municipality === '') {
            Flash::set([
                'type' => 'error',
                'title' => 'Campos obligatorios incompletos',
                'message' => 'Por favor completa todos los campos requeridos.',
            ]);

            return Response::redirect('/evaluaciones/adicciones/pre');
        }

        $answers = [];
        $totalQuestions = 9;
        $correctCount = 0;

        for ($i = 1; $i <= $totalQuestions; $i++) {
            $value = (string) $request->input('q' . $i, '');
            if ($value === '') {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Preguntas incompletas',
                    'message' => 'Debes responder todas las preguntas del test.',
                ]);

                return Response::redirect('/evaluaciones/adicciones/pre');
            }

            $correctOption = self::PRE_ADICCIONES_CORRECT[$i] ?? null;
            $isCorrect = $correctOption !== null && strtoupper($value) === $correctOption;
            if ($isCorrect) {
                $correctCount++;
            }

            $answers[] = [
                'question_number' => $i,
                'selected_option' => strtoupper($value),
                'is_correct' => $isCorrect ? 1 : 0,
            ];
        }

        $scorePercent = $totalQuestions > 0 ? round((float) ($correctCount / $totalQuestions) * 100, 2) : 0.0;

        $repo = new TestResponseRepository();

        if ($repo->existsForPerson($testKey, $phase, $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro duplicado',
                'message' => 'Ya existe un PRE - TEST de Prevención de Adicciones para este número de documento.',
            ]);

            return Response::redirect('/evaluaciones/adicciones/pre');
        }

        $data = [
            'test_key' => $testKey,
            'phase' => $phase,
            'document_number' => $documentNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'subregion' => $subregion,
            'municipality' => $municipality,
            'profession' => null,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'score_percent' => $scorePercent,
        ];

        try {
            $repo->create($data, $answers);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible guardar el test',
                'message' => 'Ocurrió un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
            ]);

            return Response::redirect('/evaluaciones/adicciones/pre');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'PRE - TEST enviado',
            'message' => sprintf(
                'Tu PRE - TEST de Prevención de Adicciones ha sido registrado correctamente. Respuestas correctas: %d de %d (%.0f%%).',
                $correctCount,
                $totalQuestions,
                $scorePercent
            ),
        ]);

        return Response::redirect('/evaluaciones/adicciones/pre');
    }

    public function postAdicciones(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->storePostAdicciones($request);
        }

        return $this->renderForm($request, 'adicciones', 'post');
    }

    public function postHospitales(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->storePostHospitales($request);
        }

        return $this->renderForm($request, 'hospitales', 'post');
    }

    public function checkPre(Request $request): Response
    {
        $testKey = (string) $request->input('test_key', '');
        $documentNumber = trim((string) $request->input('document_number', ''));

        $allowedTests = ['violencias', 'suicidios', 'adicciones', 'hospitales'];

        if (
            $testKey === '' ||
            !in_array($testKey, $allowedTests, true) ||
            $documentNumber === '' ||
            !preg_match('/^[0-9]+$/', $documentNumber)
        ) {
            return Response::json(
                ['ok' => false, 'exists' => false, 'error' => 'Parámetros no válidos'],
                400
            );
        }

        $repo = new TestResponseRepository();
        $pre = $repo->findByPerson($testKey, 'pre', $documentNumber);
        $exists = $pre !== null;

        return Response::json([
            'ok' => true,
            'exists' => $exists,
            'pre' => $exists ? [
                'first_name' => (string) ($pre['first_name'] ?? ''),
                'last_name' => (string) ($pre['last_name'] ?? ''),
                'subregion' => (string) ($pre['subregion'] ?? ''),
                'municipality' => (string) ($pre['municipality'] ?? ''),
                'profession' => (string) ($pre['profession'] ?? ''),
            ] : null,
        ]);
    }

    private function storePreHospitales(Request $request): Response
    {
        $testKey = 'hospitales';
        $phase = 'pre';

        $documentNumber = trim((string) $request->input('document_number', ''));
        $firstName = trim((string) $request->input('first_name', ''));
        $lastName = trim((string) $request->input('last_name', ''));
        $profession = trim((string) $request->input('profession', ''));
        $subregion = trim((string) $request->input('subregion', ''));
        $municipality = trim((string) $request->input('municipality', ''));

        if ($documentNumber === '' || !preg_match('/^[0-9]+$/', $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Número de documento no válido',
                'message' => 'El número de documento debe contener solo números.',
            ]);

            return Response::redirect('/evaluaciones/hospitales/pre');
        }

        if ($firstName === '' || $lastName === '' || $profession === '' || $subregion === '' || $municipality === '') {
            Flash::set([
                'type' => 'error',
                'title' => 'Campos obligatorios incompletos',
                'message' => 'Por favor completa todos los campos requeridos.',
            ]);

            return Response::redirect('/evaluaciones/hospitales/pre');
        }

        $answers = [];
        $totalQuestions = 20;
        $correctCount = 0;

        for ($i = 1; $i <= $totalQuestions; $i++) {
            $value = (string) $request->input('q' . $i, '');
            if ($value === '') {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Preguntas incompletas',
                    'message' => 'Debes responder todas las preguntas del test.',
                ]);

                return Response::redirect('/evaluaciones/hospitales/pre');
            }

            $correctOption = self::PRE_HOSPITALES_CORRECT[$i] ?? null;
            $isCorrect = $correctOption !== null && strtoupper($value) === $correctOption;
            if ($isCorrect) {
                $correctCount++;
            }

            $answers[] = [
                'question_number' => $i,
                'selected_option' => strtoupper($value),
                'is_correct' => $isCorrect ? 1 : 0,
            ];
        }

        $scorePercent = $totalQuestions > 0 ? round((float) ($correctCount / $totalQuestions) * 100, 2) : 0.0;

        $repo = new TestResponseRepository();

        if ($repo->existsForPerson($testKey, $phase, $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro duplicado',
                'message' => 'Ya existe un PRE - TEST de Hospitales para este número de documento.',
            ]);

            return Response::redirect('/evaluaciones/hospitales/pre');
        }

        $data = [
            'test_key' => $testKey,
            'phase' => $phase,
            'document_number' => $documentNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'subregion' => $subregion,
            'municipality' => $municipality,
            'profession' => $profession,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'score_percent' => $scorePercent,
        ];

        try {
            $repo->create($data, $answers);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible guardar el test',
                'message' => 'Ocurrió un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
            ]);

            return Response::redirect('/evaluaciones/hospitales/pre');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'PRE - TEST enviado',
            'message' => sprintf(
                'Tu PRE - TEST de Hospitales ha sido registrado correctamente. Respuestas correctas: %d de %d (%.0f%%).',
                $correctCount,
                $totalQuestions,
                $scorePercent
            ),
        ]);

        return Response::redirect('/evaluaciones/hospitales/pre');
    }

    private function storePostHospitales(Request $request): Response
    {
        $testKey = 'hospitales';
        $phase = 'post';

        $documentNumber = trim((string) $request->input('document_number', ''));

        if ($documentNumber === '' || !preg_match('/^[0-9]+$/', $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Número de documento no válido',
                'message' => 'El número de documento debe contener solo números.',
            ]);

            return Response::redirect('/evaluaciones/hospitales/post');
        }

        $repo = new TestResponseRepository();

        // Debe existir un PRE previo para poder diligenciar el POST
        $pre = $repo->findByPerson($testKey, 'pre', $documentNumber);
        if ($pre === null) {
            Flash::set([
                'type' => 'error',
                'title' => 'PRE - TEST no encontrado',
                'message' => 'Para diligenciar el POST - TEST debes haber completado primero el PRE - TEST con el mismo número de documento.',
            ]);

            return Response::redirect('/evaluaciones/hospitales/post');
        }

        // No permitir más de un POST para la misma persona
        if ($repo->existsForPerson($testKey, $phase, $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro duplicado',
                'message' => 'Ya existe un POST - TEST de Hospitales para este número de documento.',
            ]);

            return Response::redirect('/evaluaciones/hospitales/post');
        }

        // Usamos los datos personales del PRE (regla: migrar datos)
        $firstName = (string) ($pre['first_name'] ?? '');
        $lastName = (string) ($pre['last_name'] ?? '');
        $profession = (string) ($pre['profession'] ?? '');
        $subregion = (string) ($pre['subregion'] ?? '');
        $municipality = (string) ($pre['municipality'] ?? '');

        // Recoger respuestas a preguntas (q1..q20) y comparar con la clave correcta
        $answers = [];
        $totalQuestions = 20;
        $correctCount = 0;

        for ($i = 1; $i <= $totalQuestions; $i++) {
            $value = (string) $request->input('q' . $i, '');
            if ($value === '') {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Preguntas incompletas',
                    'message' => 'Debes responder todas las preguntas del test.',
                ]);

                return Response::redirect('/evaluaciones/hospitales/post');
            }

            $correctOption = self::POST_HOSPITALES_CORRECT[$i] ?? null;
            $isCorrect = $correctOption !== null && strtoupper($value) === $correctOption;
            if ($isCorrect) {
                $correctCount++;
            }

            $answers[] = [
                'question_number' => $i,
                'selected_option' => strtoupper($value),
                'is_correct' => $isCorrect ? 1 : 0,
            ];
        }

        $scorePercent = $totalQuestions > 0 ? round((float) ($correctCount / $totalQuestions) * 100, 2) : 0.0;

        $data = [
            'test_key' => $testKey,
            'phase' => $phase,
            'document_number' => $documentNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'subregion' => $subregion,
            'municipality' => $municipality,
            'profession' => $profession,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'score_percent' => $scorePercent,
        ];

        try {
            $repo->create($data, $answers);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible guardar el test',
                'message' => 'Ocurrió un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
            ]);

            return Response::redirect('/evaluaciones/hospitales/post');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'POST - TEST enviado',
            'message' => sprintf(
                'Tu POST - TEST de Hospitales ha sido registrado correctamente. Respuestas correctas: %d de %d (%.0f%%).',
                $correctCount,
                $totalQuestions,
                $scorePercent
            ),
        ]);

        return Response::redirect('/evaluaciones/hospitales/post');
    }

    private function storePreViolencias(Request $request): Response
    {
        $testKey = 'violencias';
        $phase = 'pre';

        $documentNumber = trim((string) $request->input('document_number', ''));
        $firstName = trim((string) $request->input('first_name', ''));
        $lastName = trim((string) $request->input('last_name', ''));
        $subregion = trim((string) $request->input('subregion', ''));
        $municipality = trim((string) $request->input('municipality', ''));

        // Validaciones básicas
        if ($documentNumber === '' || !preg_match('/^[0-9]+$/', $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Número de documento no válido',
                'message' => 'El número de documento debe contener solo números.',
            ]);

            return Response::redirect('/evaluaciones/violencias/pre');
        }

        if ($firstName === '' || $lastName === '' || $subregion === '' || $municipality === '') {
            Flash::set([
                'type' => 'error',
                'title' => 'Campos obligatorios incompletos',
                'message' => 'Por favor completa todos los campos requeridos.',
            ]);

            return Response::redirect('/evaluaciones/violencias/pre');
        }

        // Recoger respuestas a preguntas (q1..q9) y comparar con la clave correcta
        $answers = [];
        $totalQuestions = 9;
        $correctCount = 0;

        for ($i = 1; $i <= $totalQuestions; $i++) {
            $value = (string) $request->input('q' . $i, '');
            if ($value === '') {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Preguntas incompletas',
                    'message' => 'Debes responder todas las preguntas del test.',
                ]);

                return Response::redirect('/evaluaciones/violencias/pre');
            }

            $correctOption = self::PRE_VIOLENCIAS_CORRECT[$i] ?? null;
            $isCorrect = $correctOption !== null && strtoupper($value) === $correctOption;
            if ($isCorrect) {
                $correctCount++;
            }

            $answers[] = [
                'question_number' => $i,
                'selected_option' => strtoupper($value),
                'is_correct' => $isCorrect ? 1 : 0,
            ];
        }

        $scorePercent = $totalQuestions > 0 ? round((float) ($correctCount / $totalQuestions) * 100, 2) : 0.0;

        $repo = new TestResponseRepository();

        // Verificar si ya existe PRE para esta persona en este test
        if ($repo->existsForPerson($testKey, $phase, $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro duplicado',
                'message' => 'Ya existe un PRE - TEST de Prevención de Violencias para este número de documento.',
            ]);

            return Response::redirect('/evaluaciones/violencias/pre');
        }

        $data = [
            'test_key' => $testKey,
            'phase' => $phase,
            'document_number' => $documentNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'subregion' => $subregion,
            'municipality' => $municipality,
            'profession' => null,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'score_percent' => $scorePercent,
        ];

        try {
            $repo->create($data, $answers);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible guardar el test',
                'message' => 'Ocurrió un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
            ]);

            return Response::redirect('/evaluaciones/violencias/pre');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'PRE - TEST enviado',
            'message' => sprintf(
                'Tu PRE - TEST de Prevención de Violencias ha sido registrado correctamente. Respuestas correctas: %d de %d (%.0f%%).',
                $correctCount,
                $totalQuestions,
                $scorePercent
            ),
        ]);

        return Response::redirect('/evaluaciones/violencias/pre');
    }

    private function storePostViolencias(Request $request): Response
    {
        $testKey = 'violencias';
        $phase = 'post';

        $documentNumber = trim((string) $request->input('document_number', ''));

        if ($documentNumber === '' || !preg_match('/^[0-9]+$/', $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Número de documento no válido',
                'message' => 'El número de documento debe contener solo números.',
            ]);

            return Response::redirect('/evaluaciones/violencias/post');
        }

        $repo = new TestResponseRepository();

        // Debe existir un PRE previo para poder diligenciar el POST
        $pre = $repo->findByPerson($testKey, 'pre', $documentNumber);
        if ($pre === null) {
            Flash::set([
                'type' => 'error',
                'title' => 'PRE - TEST no encontrado',
                'message' => 'Para diligenciar el POST - TEST debes haber completado primero el PRE - TEST con el mismo número de documento.',
            ]);

            return Response::redirect('/evaluaciones/violencias/post');
        }

        // No permitir más de un POST para la misma persona
        if ($repo->existsForPerson($testKey, $phase, $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro duplicado',
                'message' => 'Ya existe un POST - TEST de Prevención de Violencias para este número de documento.',
            ]);

            return Response::redirect('/evaluaciones/violencias/post');
        }

        // Usamos los datos personales del PRE (regla: migrar datos)
        $firstName = (string) ($pre['first_name'] ?? '');
        $lastName = (string) ($pre['last_name'] ?? '');
        $subregion = (string) ($pre['subregion'] ?? '');
        $municipality = (string) ($pre['municipality'] ?? '');

        // Recoger respuestas a preguntas (q1..q9) y comparar con la clave correcta
        $answers = [];
        $totalQuestions = 9;
        $correctCount = 0;

        for ($i = 1; $i <= $totalQuestions; $i++) {
            $value = (string) $request->input('q' . $i, '');
            if ($value === '') {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Preguntas incompletas',
                    'message' => 'Debes responder todas las preguntas del test.',
                ]);

                return Response::redirect('/evaluaciones/violencias/post');
            }

            $correctOption = self::POST_VIOLENCIAS_CORRECT[$i] ?? null;
            $isCorrect = $correctOption !== null && strtoupper($value) === $correctOption;
            if ($isCorrect) {
                $correctCount++;
            }

            $answers[] = [
                'question_number' => $i,
                'selected_option' => strtoupper($value),
                'is_correct' => $isCorrect ? 1 : 0,
            ];
        }

        $scorePercent = $totalQuestions > 0 ? round((float) ($correctCount / $totalQuestions) * 100, 2) : 0.0;

        $data = [
            'test_key' => $testKey,
            'phase' => $phase,
            'document_number' => $documentNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'subregion' => $subregion,
            'municipality' => $municipality,
            'profession' => null,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'score_percent' => $scorePercent,
        ];

        try {
            $repo->create($data, $answers);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible guardar el test',
                'message' => 'Ocurrió un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
            ]);

            return Response::redirect('/evaluaciones/violencias/post');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'POST - TEST enviado',
            'message' => sprintf(
                'Tu POST - TEST de Prevención de Violencias ha sido registrado correctamente. Respuestas correctas: %d de %d (%.0f%%).',
                $correctCount,
                $totalQuestions,
                $scorePercent
            ),
        ]);

        return Response::redirect('/evaluaciones/violencias/post');
    }

    private function storePreSuicidios(Request $request): Response
    {
        $testKey = 'suicidios';
        $phase = 'pre';

        $documentNumber = trim((string) $request->input('document_number', ''));
        $firstName = trim((string) $request->input('first_name', ''));
        $lastName = trim((string) $request->input('last_name', ''));
        $subregion = trim((string) $request->input('subregion', ''));
        $municipality = trim((string) $request->input('municipality', ''));

        if ($documentNumber === '' || !preg_match('/^[0-9]+$/', $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Número de documento no válido',
                'message' => 'El número de documento debe contener solo números.',
            ]);

            return Response::redirect('/evaluaciones/suicidios/pre');
        }

        if ($firstName === '' || $lastName === '' || $subregion === '' || $municipality === '') {
            Flash::set([
                'type' => 'error',
                'title' => 'Campos obligatorios incompletos',
                'message' => 'Por favor completa todos los campos requeridos.',
            ]);

            return Response::redirect('/evaluaciones/suicidios/pre');
        }

        $answers = [];
        $totalQuestions = 12;
        $correctCount = 0;

        for ($i = 1; $i <= $totalQuestions; $i++) {
            $value = (string) $request->input('q' . $i, '');
            if ($value === '') {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Preguntas incompletas',
                    'message' => 'Debes responder todas las preguntas del test.',
                ]);

                return Response::redirect('/evaluaciones/suicidios/pre');
            }

            $correctOption = self::PRE_SUICIDIOS_CORRECT[$i] ?? null;
            $isCorrect = $correctOption !== null && strtoupper($value) === $correctOption;
            if ($isCorrect) {
                $correctCount++;
            }

            $answers[] = [
                'question_number' => $i,
                'selected_option' => strtoupper($value),
                'is_correct' => $isCorrect ? 1 : 0,
            ];
        }

        $scorePercent = $totalQuestions > 0 ? round((float) ($correctCount / $totalQuestions) * 100, 2) : 0.0;

        $repo = new TestResponseRepository();

        if ($repo->existsForPerson($testKey, $phase, $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro duplicado',
                'message' => 'Ya existe un PRE - TEST de Prevención de Suicidios para este número de documento.',
            ]);

            return Response::redirect('/evaluaciones/suicidios/pre');
        }

        $data = [
            'test_key' => $testKey,
            'phase' => $phase,
            'document_number' => $documentNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'subregion' => $subregion,
            'municipality' => $municipality,
            'profession' => null,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'score_percent' => $scorePercent,
        ];

        try {
            $repo->create($data, $answers);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible guardar el test',
                'message' => 'Ocurrió un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
            ]);

            return Response::redirect('/evaluaciones/suicidios/pre');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'PRE - TEST enviado',
            'message' => sprintf(
                'Tu PRE - TEST de Prevención de Suicidios ha sido registrado correctamente. Respuestas correctas: %d de %d (%.0f%%).',
                $correctCount,
                $totalQuestions,
                $scorePercent
            ),
        ]);

        return Response::redirect('/evaluaciones/suicidios/pre');
    }

    private function storePostSuicidios(Request $request): Response
    {
        $testKey = 'suicidios';
        $phase = 'post';

        $documentNumber = trim((string) $request->input('document_number', ''));

        if ($documentNumber === '' || !preg_match('/^[0-9]+$/', $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Número de documento no válido',
                'message' => 'El número de documento debe contener solo números.',
            ]);

            return Response::redirect('/evaluaciones/suicidios/post');
        }

        $repo = new TestResponseRepository();

        // Debe existir un PRE previo para poder diligenciar el POST
        $pre = $repo->findByPerson($testKey, 'pre', $documentNumber);
        if ($pre === null) {
            Flash::set([
                'type' => 'error',
                'title' => 'PRE - TEST no encontrado',
                'message' => 'Para diligenciar el POST - TEST debes haber completado primero el PRE - TEST con el mismo número de documento.',
            ]);

            return Response::redirect('/evaluaciones/suicidios/post');
        }

        // No permitir más de un POST para la misma persona
        if ($repo->existsForPerson($testKey, $phase, $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro duplicado',
                'message' => 'Ya existe un POST - TEST de Prevención de Suicidios para este número de documento.',
            ]);

            return Response::redirect('/evaluaciones/suicidios/post');
        }

        // Usamos los datos personales del PRE (regla: migrar datos)
        $firstName = (string) ($pre['first_name'] ?? '');
        $lastName = (string) ($pre['last_name'] ?? '');
        $subregion = (string) ($pre['subregion'] ?? '');
        $municipality = (string) ($pre['municipality'] ?? '');

        // Recoger respuestas a preguntas (q1..q12) y comparar con la clave correcta
        $answers = [];
        $totalQuestions = 12;
        $correctCount = 0;

        for ($i = 1; $i <= $totalQuestions; $i++) {
            $value = (string) $request->input('q' . $i, '');
            if ($value === '') {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Preguntas incompletas',
                    'message' => 'Debes responder todas las preguntas del test.',
                ]);

                return Response::redirect('/evaluaciones/suicidios/post');
            }

            $correctOption = self::POST_SUICIDIOS_CORRECT[$i] ?? null;
            $isCorrect = $correctOption !== null && strtoupper($value) === $correctOption;
            if ($isCorrect) {
                $correctCount++;
            }

            $answers[] = [
                'question_number' => $i,
                'selected_option' => strtoupper($value),
                'is_correct' => $isCorrect ? 1 : 0,
            ];
        }

        $scorePercent = $totalQuestions > 0 ? round((float) ($correctCount / $totalQuestions) * 100, 2) : 0.0;

        $data = [
            'test_key' => $testKey,
            'phase' => $phase,
            'document_number' => $documentNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'subregion' => $subregion,
            'municipality' => $municipality,
            'profession' => null,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'score_percent' => $scorePercent,
        ];

        try {
            $repo->create($data, $answers);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible guardar el test',
                'message' => 'Ocurrió un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
            ]);

            return Response::redirect('/evaluaciones/suicidios/post');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'POST - TEST enviado',
            'message' => sprintf(
                'Tu POST - TEST de Prevención de Suicidios ha sido registrado correctamente. Respuestas correctas: %d de %d (%.0f%%).',
                $correctCount,
                $totalQuestions,
                $scorePercent
            ),
        ]);

        return Response::redirect('/evaluaciones/suicidios/post');
    }

    private function storePostAdicciones(Request $request): Response
    {
        $testKey = 'adicciones';
        $phase = 'post';

        $documentNumber = trim((string) $request->input('document_number', ''));

        if ($documentNumber === '' || !preg_match('/^[0-9]+$/', $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Número de documento no válido',
                'message' => 'El número de documento debe contener solo números.',
            ]);

            return Response::redirect('/evaluaciones/adicciones/post');
        }

        $repo = new TestResponseRepository();

        // Debe existir un PRE previo para poder diligenciar el POST
        $pre = $repo->findByPerson($testKey, 'pre', $documentNumber);
        if ($pre === null) {
            Flash::set([
                'type' => 'error',
                'title' => 'PRE - TEST no encontrado',
                'message' => 'Para diligenciar el POST - TEST debes haber completado primero el PRE - TEST con el mismo número de documento.',
            ]);

            return Response::redirect('/evaluaciones/adicciones/post');
        }

        // No permitir más de un POST para la misma persona
        if ($repo->existsForPerson($testKey, $phase, $documentNumber)) {
            Flash::set([
                'type' => 'error',
                'title' => 'Registro duplicado',
                'message' => 'Ya existe un POST - TEST de Prevención de Adicciones para este número de documento.',
            ]);

            return Response::redirect('/evaluaciones/adicciones/post');
        }

        // Usamos los datos personales del PRE (regla: migrar datos)
        $firstName = (string) ($pre['first_name'] ?? '');
        $lastName = (string) ($pre['last_name'] ?? '');
        $subregion = (string) ($pre['subregion'] ?? '');
        $municipality = (string) ($pre['municipality'] ?? '');

        // Recoger respuestas a preguntas (q1..q9) y comparar con la clave correcta
        $answers = [];
        $totalQuestions = 9;
        $correctCount = 0;

        for ($i = 1; $i <= $totalQuestions; $i++) {
            $value = (string) $request->input('q' . $i, '');
            if ($value === '') {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Preguntas incompletas',
                    'message' => 'Debes responder todas las preguntas del test.',
                ]);

                return Response::redirect('/evaluaciones/adicciones/post');
            }

            $correctOption = self::POST_ADICCIONES_CORRECT[$i] ?? null;
            $isCorrect = $correctOption !== null && strtoupper($value) === $correctOption;
            if ($isCorrect) {
                $correctCount++;
            }

            $answers[] = [
                'question_number' => $i,
                'selected_option' => strtoupper($value),
                'is_correct' => $isCorrect ? 1 : 0,
            ];
        }

        $scorePercent = $totalQuestions > 0 ? round((float) ($correctCount / $totalQuestions) * 100, 2) : 0.0;

        $data = [
            'test_key' => $testKey,
            'phase' => $phase,
            'document_number' => $documentNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'subregion' => $subregion,
            'municipality' => $municipality,
            'profession' => null,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctCount,
            'score_percent' => $scorePercent,
        ];

        try {
            $repo->create($data, $answers);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible guardar el test',
                'message' => 'Ocurrió un problema al registrar tus respuestas. Intenta nuevamente en unos minutos.',
            ]);

            return Response::redirect('/evaluaciones/adicciones/post');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'POST - TEST enviado',
            'message' => sprintf(
                'Tu POST - TEST de Prevención de Adicciones ha sido registrado correctamente. Respuestas correctas: %d de %d (%.0f%%).',
                $correctCount,
                $totalQuestions,
                $scorePercent
            ),
        ]);

        return Response::redirect('/evaluaciones/adicciones/post');
    }

    private function renderForm(Request $request, string $testKey, string $phase): Response
    {
        $config = $this->configFor($testKey, $phase);

        $currentUser = Auth::user();
        $prefill = [
            'document_number' => '',
            'first_name' => '',
            'last_name' => '',
            'subregion' => '',
            'municipality' => '',
        ];

        if ($currentUser !== null) {
            $prefill['document_number'] = (string) ($currentUser['document_number'] ?? '');
        }

        return Response::view('evaluaciones/form', [
            'pageTitle' => $config['title'],
            'config' => $config,
            'prefill' => $prefill,
        ]);
    }

    private function configFor(string $testKey, string $phase): array
    {
        $topics = [
            'violencias' => 'Prevención de Violencias',
            'suicidios' => 'Prevención de Suicidios',
            'adicciones' => 'Prevención de Adicciones',
            'hospitales' => 'Hospitales',
        ];

        $labelsPhase = [
            'pre' => 'PRE - TEST',
            'post' => 'POST - TEST',
        ];

        return [
            'key' => $testKey,
            'phase' => $phase,
            'title' => sprintf('%s - %s', $labelsPhase[$phase] ?? strtoupper($phase), $topics[$testKey] ?? ''),
            'topic' => $topics[$testKey] ?? '',
            'isHospital' => $testKey === 'hospitales',
        ];
    }
}

