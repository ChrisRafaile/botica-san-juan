<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Usuario;
use App\Support\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class UsuarioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Usuario::query()->orderByDesc('created_at');

        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($search) {
                $inner->where('nombre', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('dni', 'like', "%{$search}%")
                    ->orWhere('telefono', 'like', "%{$search}%");
            });
        }

        if ($request->filled('rol') && $request->input('rol') !== 'all') {
            $query->where('rol', $request->input('rol'));
        }

        $shouldPaginate = $request->has('page') || $request->has('per_page') || $request->boolean('paginate');
        if ($shouldPaginate) {
            $perPage = (int) $request->input('per_page', 10);
            return $query->paginate(max(1, min($perPage, 100)));
        }

        return $query->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'dni' => 'required|string|size:8|unique:usuarios,dni',
            'email' => 'required|string|email|max:255|unique:usuarios',
            'password' => 'required|string|min:8',
            'rol' => 'nullable|in:cliente,administrador',
            'telefono' => 'nullable|string|max:255',
            'foto_perfil' => 'nullable|string|max:255',
            'foto_portada' => 'nullable|string|max:255',
        ]);

        $usuario = Usuario::create([
            'nombre' => $request->nombre,
            'dni' => $request->dni,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'rol' => $request->rol ?? 'cliente',
            'telefono' => $request->telefono,
            'foto_perfil' => $request->foto_perfil,
            'foto_portada' => $request->foto_portada,
        ]);

        return response()->json($usuario, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $usuario = Usuario::findOrFail($id);
        return response()->json($usuario);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $usuario = Usuario::findOrFail($id);

        $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'dni' => 'sometimes|required|string|size:8|unique:usuarios,dni,' . $id,
            'email' => 'sometimes|required|string|email|max:255|unique:usuarios,email,' . $id,
            'password' => 'sometimes|required|string|min:8',
            'rol' => 'sometimes|required|in:cliente,administrador',
            'telefono' => 'nullable|string|max:255',
            'foto_perfil' => 'nullable|string|max:255',
            'foto_portada' => 'nullable|string|max:255',
        ]);

        $data = $request->only(['nombre', 'dni', 'email', 'rol', 'telefono', 'foto_perfil', 'foto_portada']);

        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $usuario->update($data);

        return response()->json($usuario);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $usuario = Usuario::findOrFail($id);
        $usuario->delete();

        return response()->json(['message' => 'Usuario deleted successfully']);
    }

    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        $usuario = Usuario::create([
            'nombre' => $validated['nombre'],
            'dni' => $validated['dni'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'telefono' => $validated['telefono'] ?? null,
            'foto_perfil' => $validated['foto_perfil'] ?? null,
            'foto_portada' => $validated['foto_portada'] ?? null,
        ]);

        $token = $usuario->createToken('API Token')->plainTextToken;

        return response()->json([
            'user' => $usuario,
            'token' => $token,
            'token_type' => 'Bearer',
            'message' => 'Usuario registrado exitosamente'
        ], 201);
    }

    /**
     * Login user.
     */
    public function login(LoginRequest $request)
    {
        $validated = $request->validated();
        $throttleKey = sprintf('login|%s|%s', $request->ip(), $validated['dni']);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            Log::warning('Login bloqueado por exceso de intentos', [
                'dni' => $validated['dni'],
                'ip' => $request->ip(),
                'retry_after_seconds' => $seconds,
            ]);

            return response()->json([
                'message' => 'Demasiados intentos. Intenta nuevamente mas tarde.',
                'retry_after_seconds' => $seconds,
            ], 429);
        }

        $usuario = Usuario::where('dni', $validated['dni'])->first();

        if (!$usuario || !Hash::check($validated['password'], $usuario->password)) {
            $attempts = RateLimiter::attempts($throttleKey) + 1;
            $decaySeconds = min(900, 60 * (2 ** min(4, max(0, $attempts - 1))));
            RateLimiter::hit($throttleKey, $decaySeconds);

            Log::warning('Intento de login fallido', [
                'dni' => $validated['dni'],
                'ip' => $request->ip(),
                'attempts' => $attempts,
                'lock_seconds' => $decaySeconds,
                'user_agent' => (string) $request->userAgent(),
            ]);

            return response()->json([
                'message' => 'Credenciales inválidas. Verifica tu DNI y contraseña.'
            ], 401);
        }

        RateLimiter::clear($throttleKey);

        if ($usuario->rol === 'administrador' && (bool) $usuario->mfa_enabled) {
            $providedCode = (string) ($validated['mfa_code'] ?? '');
            $secret = (string) ($usuario->mfa_secret ?? '');

            if ($secret === '' || !TotpService::verifyCode($secret, $providedCode)) {
                Log::warning('Login admin requiere MFA valido', [
                    'usuario_id' => $usuario->id,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'message' => 'Se requiere codigo MFA valido para cuenta administrativa.',
                    'mfa_required' => true,
                ], 401);
            }
        }

        $token = $usuario->createToken('API Token')->plainTextToken;

        Log::info('Login exitoso', [
            'usuario_id' => $usuario->id,
            'rol' => $usuario->rol,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'user' => $usuario,
            'token' => $token,
            'token_type' => 'Bearer',
            'message' => 'Inicio de sesión exitoso'
        ]);
    }

    public function mfaSetup(Request $request)
    {
        /** @var Usuario $user */
        $user = $request->user();

        if ($user->rol !== 'administrador') {
            return response()->json([
                'message' => 'Solo administradores pueden configurar MFA.',
            ], 403);
        }

        $secret = TotpService::generateSecret();
        $user->forceFill([
            'mfa_secret' => $secret,
            'mfa_enabled' => false,
            'mfa_enabled_at' => null,
        ])->save();

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => TotpService::provisioningUri('Botica San Juan', $user->email, $secret),
            'message' => 'Escanea el QR/manual secret y confirma con /mfa/enable.',
        ]);
    }

    public function mfaEnable(Request $request)
    {
        /** @var Usuario $user */
        $user = $request->user();

        if ($user->rol !== 'administrador') {
            return response()->json([
                'message' => 'Solo administradores pueden habilitar MFA.',
            ], 403);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:12',
        ]);

        $secret = (string) ($user->mfa_secret ?? '');
        if ($secret === '' || !TotpService::verifyCode($secret, $validated['code'])) {
            return response()->json([
                'message' => 'Codigo MFA invalido.',
            ], 422);
        }

        $user->forceFill([
            'mfa_enabled' => true,
            'mfa_enabled_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'MFA habilitado correctamente.',
            'mfa_enabled' => true,
        ]);
    }

    public function mfaDisable(Request $request)
    {
        /** @var Usuario $user */
        $user = $request->user();

        if ($user->rol !== 'administrador') {
            return response()->json([
                'message' => 'Solo administradores pueden deshabilitar MFA.',
            ], 403);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:12',
        ]);

        $secret = (string) ($user->mfa_secret ?? '');
        if ($secret === '' || !TotpService::verifyCode($secret, $validated['code'])) {
            return response()->json([
                'message' => 'Codigo MFA invalido.',
            ], 422);
        }

        $user->forceFill([
            'mfa_enabled' => false,
            'mfa_secret' => null,
            'mfa_enabled_at' => null,
        ])->save();

        return response()->json([
            'message' => 'MFA deshabilitado correctamente.',
            'mfa_enabled' => false,
        ]);
    }

    /**
     * Logout user.
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }
}
