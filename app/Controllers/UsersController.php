<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserRepository;
use App\Services\Flash;

final class UsersController
{
    public function index(Request $request): Response
    {
        $repo = new UserRepository();

        $filters = [
            'query' => trim((string) $request->input('q', '')),
            'role' => trim((string) $request->input('role', '')),
            'active' => (string) $request->input('active', ''),
        ];

        $users = $repo->search($filters);
        $roles = $repo->getAllRoles();

        return Response::view('users/index', [
            'pageTitle' => 'Administración de usuarios',
            'users' => $users,
            'roles' => $roles,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        $repo = new UserRepository();

        return Response::view('users/form', [
            'pageTitle' => 'Crear usuario',
            'mode' => 'create',
            'user' => null,
            'roles' => $repo->getAllRoles(),
        ]);
    }

    public function store(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $active = (string) $request->input('active', '1') === '1' ? 1 : 0;
        $roles = (array) $request->input('roles', []);

        if ($name === '' || $email === '' || $password === '') {
            Flash::set([
                'type' => 'error',
                'title' => 'Campos obligatorios',
                'message' => 'Nombre, correo y contraseña son obligatorios.',
            ]);

            return Response::redirect('/admin/usuarios/nuevo');
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $repo = new UserRepository();

        try {
            $repo->create([
                'name' => $name,
                'email' => $email,
                'password' => $passwordHash,
                'active' => $active,
            ], $roles);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible crear el usuario',
                'message' => 'Revisa que el correo no esté repetido o intenta nuevamente.',
            ]);

            return Response::redirect('/admin/usuarios/nuevo');
        }

        Flash::set([
            'type' => 'success',
            'title' => 'Usuario creado',
            'message' => 'El usuario se creó correctamente.',
        ]);

        return Response::redirect('/admin/usuarios');
    }

    public function edit(Request $request): Response
    {
        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return Response::redirect('/admin/usuarios');
        }

        $repo = new UserRepository();
        $user = $repo->find($id);

        if ($user === null) {
            Flash::set([
                'type' => 'error',
                'title' => 'Usuario no encontrado',
                'message' => 'El usuario que intentas editar no existe.',
            ]);

            return Response::redirect('/admin/usuarios');
        }

        return Response::view('users/form', [
            'pageTitle' => 'Editar usuario',
            'mode' => 'edit',
            'user' => $user,
            'roles' => $repo->getAllRoles(),
        ]);
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->input('id', 0);
        $name = trim((string) $request->input('name', ''));
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $active = (string) $request->input('active', '1') === '1' ? 1 : 0;
        $roles = (array) $request->input('roles', []);

        if ($id <= 0 || $name === '' || $email === '') {
            Flash::set([
                'type' => 'error',
                'title' => 'Datos inválidos',
                'message' => 'Debes indicar un usuario válido, nombre y correo.',
            ]);

            return Response::redirect('/admin/usuarios');
        }

        $data = [
            'name' => $name,
            'email' => $email,
            'active' => $active,
        ];

        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        $repo = new UserRepository();

        try {
            $repo->update($id, $data, $roles);
        } catch (\PDOException $e) {
            Flash::set([
                'type' => 'error',
                'title' => 'No fue posible actualizar el usuario',
                'message' => 'Revisa que el correo no esté repetido o intenta nuevamente.',
            ]);

            return Response::redirect('/admin/usuarios/editar?id=' . $id);
        }

        Flash::set([
            'type' => 'success',
            'title' => 'Usuario actualizado',
            'message' => 'Los datos del usuario se actualizaron correctamente.',
        ]);

        return Response::redirect('/admin/usuarios');
    }

    public function deactivate(Request $request): Response
    {
        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return Response::redirect('/admin/usuarios');
        }

        $repo = new UserRepository();
        $repo->deactivate($id);

        Flash::set([
            'type' => 'success',
            'title' => 'Usuario desactivado',
            'message' => 'El usuario ha sido desactivado. Puedes reactivarlo editando su registro.',
        ]);

        return Response::redirect('/admin/usuarios');
    }
}

