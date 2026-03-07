<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Database\Connection;
use App\Services\Flash;

final class AuthController
{
    public function showLoginForm(Request $request): Response
    {
        return Response::view('auth/login', [
            'pageTitle' => 'Iniciar sesión',
        ]);
    }

    public function login(Request $request): Response
    {
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        if ($email === '' || $password === '') {
            Flash::set([
                'type' => 'error',
                'title' => 'Datos incompletos',
                'message' => 'Debes ingresar el correo y la contraseña.',
            ]);

            return Response::redirect('/login');
        }

        $pdo = Connection::getPdo();

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email AND active = 1 LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, (string) $user['password'])) {
            Flash::set([
                'type' => 'error',
                'title' => 'Credenciales incorrectas',
                'message' => 'El correo o la contraseña no son válidos.',
            ]);

            return Response::redirect('/login');
        }

        // Cargar roles asociados
        $rolesStmt = $pdo->prepare(
            'SELECT r.name FROM roles r
             INNER JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :user_id'
        );
        $rolesStmt->execute([':user_id' => $user['id']]);
        $roles = $rolesStmt->fetchAll(\PDO::FETCH_COLUMN);

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => $roles[0] ?? 'usuario',
            'roles' => $roles,
        ];

        return Response::redirect('/');
    }

    public function logout(Request $request): Response
    {
        session_destroy();
        return Response::redirect('/login');
    }
}

