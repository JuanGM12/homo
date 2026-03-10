<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Config;
use App\Core\Request;
use App\Core\Response;
use App\Database\Connection;
use App\Services\Flash;
use App\Services\Mailer;

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

        // Regenerar ID de sesión para evitar fijación y asegurar cookie nueva
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'document_number' => (string) ($user['document_number'] ?? ''),
            'role' => $roles[0] ?? 'usuario',
            'roles' => $roles,
            'must_change_password' => (bool) ($user['requires_password_change'] ?? 0),
        ];

        // Escribir sesión antes del redirect para que persista en cualquier navegador/incógnito
        session_write_close();

        if (!empty($user['requires_password_change'])) {
            // No mostramos flash aquí: en /perfil se abre un modal obligatorio que lo explica
            return Response::redirect('/perfil');
        }

        return Response::redirect('/');
    }

    public function showForgotPasswordForm(Request $request): Response
    {
        return Response::view('auth/forgot_password', [
            'pageTitle' => 'Recuperar contraseña',
        ]);
    }

    public function handleForgotPassword(Request $request): Response
    {
        $email = trim((string) $request->input('email', ''));

        if ($email === '') {
            Flash::set([
                'type' => 'error',
                'title' => 'Correo requerido',
                'message' => 'Ingresa el correo institucional con el que estás registrado.',
            ]);

            return Response::redirect('/recuperar-clave');
        }

        $pdo = Connection::getPdo();

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email AND active = 1 LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            Flash::set([
                'type' => 'error',
                'title' => 'Correo no encontrado',
                'message' => 'No encontramos un usuario activo con ese correo. Verifica el dato o contacta al administrador.',
            ]);

            return Response::redirect('/recuperar-clave');
        }

        $token = bin2hex(random_bytes(32));

        $expiresAt = (new \DateTimeImmutable('+2 hours'))
            ->setTimezone(new \DateTimeZone((string) Config::timezone()))
            ->format('Y-m-d H:i:s');

        // Invalida tokens previos para este usuario
        $pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id')->execute([
            ':user_id' => $user['id'],
        ]);

        $insert = $pdo->prepare(
            'INSERT INTO password_resets (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)'
        );
        $insert->execute([
            ':user_id' => $user['id'],
            ':token' => $token,
            ':expires_at' => $expiresAt,
        ]);

        $baseUrl = (string) Config::env('APP_URL', '');
        if ($baseUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $scheme . '://' . $host;
        }
        $resetUrl = rtrim($baseUrl, '/') . '/restablecer-clave?token=' . urlencode($token);

        $mailer = new Mailer();
        $mailer->sendPasswordResetEmail((string) $user['email'], (string) $user['name'], $resetUrl);

        Flash::set([
            'type' => 'success',
            'title' => 'Te enviamos un correo',
            'message' => 'Si el correo ingresado corresponde a un usuario activo, recibirás un mensaje con el enlace para definir una nueva contraseña.',
        ]);

        return Response::redirect('/login');
    }

    public function showResetPasswordForm(Request $request): Response
    {
        $token = (string) $request->input('token', '');
        if ($token === '') {
            Flash::set([
                'type' => 'error',
                'title' => 'Enlace no válido',
                'message' => 'El enlace para restablecer la contraseña no es válido o ha expirado.',
            ]);

            return Response::redirect('/login');
        }

        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare(
            'SELECT pr.*, u.email FROM password_resets pr
             INNER JOIN users u ON u.id = pr.user_id
             WHERE pr.token = :token
               AND pr.used_at IS NULL
               AND (pr.expires_at IS NULL OR pr.expires_at > NOW())
             LIMIT 1'
        );
        $stmt->execute([':token' => $token]);
        $reset = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$reset) {
            Flash::set([
                'type' => 'error',
                'title' => 'Enlace caducado o usado',
                'message' => 'El enlace para restablecer la contraseña ya fue utilizado o ha expirado. Solicita uno nuevo.',
            ]);

            return Response::redirect('/recuperar-clave');
        }

        return Response::view('auth/reset_password', [
            'pageTitle' => 'Definir nueva contraseña',
            'token' => $token,
            'email' => $reset['email'] ?? '',
        ]);
    }

    public function handleResetPassword(Request $request): Response
    {
        $token = (string) $request->input('token', '');
        $password = (string) $request->input('password', '');
        $passwordConfirm = (string) $request->input('password_confirmation', '');

        if ($token === '' || $password === '' || $passwordConfirm === '') {
            Flash::set([
                'type' => 'error',
                'title' => 'Datos incompletos',
                'message' => 'Debes completar todos los campos para definir una nueva contraseña.',
            ]);

            return Response::redirect('/restablecer-clave?token=' . urlencode($token));
        }

        if ($password !== $passwordConfirm) {
            Flash::set([
                'type' => 'error',
                'title' => 'Contraseñas no coinciden',
                'message' => 'La nueva contraseña y su confirmación deben ser iguales.',
            ]);

            return Response::redirect('/restablecer-clave?token=' . urlencode($token));
        }

        if (strlen($password) < 6) {
            Flash::set([
                'type' => 'error',
                'title' => 'Contraseña muy corta',
                'message' => 'La nueva contraseña debe tener al menos 6 caracteres.',
            ]);

            return Response::redirect('/restablecer-clave?token=' . urlencode($token));
        }

        $pdo = Connection::getPdo();
        $stmt = $pdo->prepare(
            'SELECT pr.*, u.id AS user_id FROM password_resets pr
             INNER JOIN users u ON u.id = pr.user_id
             WHERE pr.token = :token
               AND pr.used_at IS NULL
               AND (pr.expires_at IS NULL OR pr.expires_at > NOW())
             LIMIT 1'
        );
        $stmt->execute([':token' => $token]);
        $reset = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$reset) {
            Flash::set([
                'type' => 'error',
                'title' => 'Enlace no válido',
                'message' => 'El enlace para restablecer la contraseña no es válido o ha expirado. Solicita uno nuevo.',
            ]);

            return Response::redirect('/recuperar-clave');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $updateUser = $pdo->prepare('UPDATE users SET password = :password, requires_password_change = 0 WHERE id = :id');
        $updateUser->execute([
            ':password' => $hash,
            ':id' => $reset['user_id'],
        ]);

        $markUsed = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
        $markUsed->execute([':id' => $reset['id']]);

        Flash::set([
            'type' => 'success',
            'title' => 'Contraseña actualizada',
            'message' => 'Tu nueva contraseña se guardó correctamente. Ya puedes iniciar sesión con ella.',
        ]);

        return Response::redirect('/login');
    }

    public function logout(Request $request): Response
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
        return Response::redirect('/login');
    }
}

