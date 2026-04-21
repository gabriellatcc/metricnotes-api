<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Services\AuthService;
use Exception;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(LoginRequest $request)
    {
        try {
            $data = $this->authService->login($request->validated());

            return $this->respondSuccess($data, 'Usuário logado com sucesso!');
        } catch (Exception $e) {
            $code = ($e->getCode() >= 100 && $e->getCode() <= 599)
                ? (int) $e->getCode()
                : 500;

            return $this->respondError('Erro ao logar usuário: '.$e->getMessage(), null, $code);
        }
    }

    public function me()
    {
        try {
            $data = $this->authService->me();

            return $this->respondSuccess($data, 'Autenticação feita com sucesso.');
        } catch (Exception $e) {
            $code = $e->getCode() ?: 401;
            return $this->respondError('Token inválido ou expirado.', null, $code);
        }
    }

    public function refreshToken(RefreshTokenRequest $request)
    {
        try {
            $data = $this->authService->refreshToken($request->validated());

            return $this->respondSuccess($data, 'Token recarregado com sucesso.');
        } catch (Exception $e) {
            $code = $e->getCode() ?: 401;
            return $this->respondError('Não foi possível atualizar o token.', null, $code);
        }
    }
}