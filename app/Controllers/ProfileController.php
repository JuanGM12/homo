<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Database\Connection;
use App\Services\Auth;
use App\Services\Flash;

final class ProfileController
{
    public function edit(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        return Response::view('profile/edit', [
            'pageTitle' => 'Mi perfil',
            'user' => $user,
        ]);
    }

    public function update(Request $request): Response
    {
        $sessionUser = Auth::user();
        if ($sessionUser === null) {
            return Response::redirect('/login');
        }

        $id = (int) $sessionUser['id'];
        $name = trim((string) $request->input('name', ''));
        $email = trim((string) $request->input('email', ''));
        $currentPassword = (string) $request->input('current_password', '');
        $newPassword = (string) $request->input('new_password', '');
        $newPasswordConfirm = (string) $request->input('new_password_confirmation', '');

        if ($name === '' || $email === '') {
            Flash::set([
                'type' => 'error',
                'title' => 'Campos obligatorios',
                'message' => 'Nombre y correo no pueden estar vacíos.',
            ]);

            return Response::redirect('/perfil');
        }

        $pdo = Connection::getPdo();

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $dbUser = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$dbUser) {
            Flash::set([
                'type' => 'error',
                'title' => 'Perfil no encontrado',
                'message' => 'No fue posible cargar tu información de perfil.',
            ]);

            return Response::redirect('/perfil');
        }

        $passwordToSave = null;

        if ($newPassword !== '' || $newPasswordConfirm !== '') {
            if ($newPassword !== $newPasswordConfirm) {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Contraseñas no coinciden',
                    'message' => 'La nueva contraseña y su confirmación deben ser iguales.',
                ]);

                return Response::redirect('/perfil');
            }

            if ($currentPassword === '' || !password_verify($currentPassword, (string) $dbUser['password'])) {
                Flash::set([
                    'type' => 'error',
                    'title' => 'Contraseña actual incorrecta',
                    'message' => 'Debes ingresar tu contraseña actual correctamente para poder cambiarla.',
                ]);

                return Response::redirect('/perfil');
            }

            $passwordToSave = password_hash($newPassword, PASSWORD_BCRYPT);
        }

        $updateSql = 'UPDATE users SET name = :name, email = :email';
        $params = [
            ':id' => $id,
            ':name' => $name,
            ':email' => $email,
        ];

        if ($passwordToSave !== null) {
            $updateSql .= ', password = :password, requires_password_change = 0';
            $params[':password'] = $passwordToSave;
        }

        $updateSql .= ' WHERE id = :id';

        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute($params);

        // Actualizar datos en sesión
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        if ($passwordToSave !== null) {
            $_SESSION['user']['must_change_password'] = false;
        }

        Flash::set([
            'type' => 'success',
            'title' => 'Perfil actualizado',
            'message' => 'Tus datos se han actualizado correctamente.',
        ]);

        return Response::redirect('/perfil');
    }
}

