<?php

namespace App\Http\Controllers\Accounts\Api;

use App\Http\Controllers\Accounts\PasswordController;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureNotBlocked;
use App\Models\PersonalAccessToken;
use App\Support\ApiError;
use App\Support\Auth\Accounts;
use App\Support\Auth\ApiTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** /auth/*: registracija, prijava, osvežavanje tokena, odjava, nova lozinka. */
class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate(Accounts::registrationRules(phoneRequired: false) + [
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        if (Accounts::emailTaken($data['email'])) {
            return ApiError::response('email_taken', 'Nalog sa ovom email adresom već postoji.', 409, [
                'email' => 'Nalog sa ovom email adresom već postoji.',
            ]);
        }

        $user = Accounts::register($data);
        $tokens = ApiTokens::issue($user, $data['device_name'] ?? null);

        return response()->json($tokens + ['user' => MePresenter::me($user)], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string', 'max:'.Accounts::PASSWORD_MAX],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        [$user, $error] = Accounts::attempt($data['email'], $data['password']);

        if ($user === null) {
            return $error === 'account_blocked'
                ? ApiError::response('account_blocked', EnsureNotBlocked::MESSAGE, 401)
                : ApiError::response('invalid_credentials', 'Pogrešan email ili lozinka.', 401);
        }

        $tokens = ApiTokens::issue($user, $data['device_name'] ?? null);

        return response()->json($tokens + ['user' => MePresenter::me($user)]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate(['refresh_token' => ['required', 'string', 'max:200']]);

        $result = ApiTokens::refresh($data['refresh_token']);

        if ($result === null) {
            return ApiError::response('invalid_refresh_token', 'Prijava je istekla. Prijavite se ponovo.', 401);
        }

        return response()->json($result['tokens'] + ['user' => MePresenter::me($result['user'])]);
    }

    public function logout(Request $request): Response
    {
        $data = $request->validate([
            'refresh_token' => ['nullable', 'string', 'max:200'],
            'all_devices' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        if ($request->boolean('all_devices')) {
            ApiTokens::revokeAll($user);
        } else {
            $current = $user->currentAccessToken();
            ApiTokens::revokeDevice($user, $current instanceof PersonalAccessToken ? $current : null, $data['refresh_token'] ?? null);
        }

        return response()->noContent();
    }

    public function passwordReset(Request $request): Response
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:191']]);

        PasswordController::sendLink($data['email']);

        return response()->noContent();
    }
}
