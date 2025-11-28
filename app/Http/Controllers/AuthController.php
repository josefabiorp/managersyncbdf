<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use App\Models\Sucursal;

class AuthController extends Controller
{
    public function index()
    {
        $usuarios = Usuario::with([
                'sucursal:id,nombre,direccion',
                'turnos:id,nombre,hora_inicio,hora_fin,tolerancia_entrada,tolerancia_salida,minutos_almuerzo'
            ])
            ->get(['id','nombre','email','cedula','empresa_id','sucursal_id','role']);

        // Convertimos la relación turnos (colección) en un único turno
        $usuarios->transform(function ($u) {
            $u->turno = $u->turnos->first(); // si solo usas 1 turno por empleado
            unset($u->turnos); // opcional: oculta la colección si no la necesitas
            return $u;
        });

        return response()->json($usuarios);
    }

    public function show()
    {
        try {
            if (Auth::guard('web')->check()) {
                return response()->json(Auth::guard('web')->user());
            }
            return response()->json(['error' => 'Unauthorized'], 401);
        } catch (\Exception $e) {
            \Log::error('Error in AuthController@show: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    // ========================================================
    //  REGISTER
    // ========================================================
    public function register(Request $request)
    {
        // Validaciones con mensajes personalizados
        $validator = Validator::make(
            $request->all(),
            [
                'nombre'       => 'required|string|max:255',
                'email'        => 'required|string|email|max:255|unique:usuarios,email',
                'cedula'       => 'required|string|max:12|unique:usuarios,cedula',
                'empresa_id'   => 'required|exists:empresas,id',
                'sucursal_id'  => 'nullable|exists:sucursales,id',
                'password'     => 'required|string|min:6|confirmed',
                'profile_image'=> 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'role'         => 'nullable|string|in:admin,contador,empleado',
            ],
            [
                'nombre.required'      => 'El nombre es obligatorio.',
                'email.required'       => 'El correo es obligatorio.',
                'email.email'          => 'El formato del correo no es válido.',
                'email.unique'         => 'Este correo ya está registrado.',
                'cedula.required'      => 'La cédula es obligatoria.',
                'cedula.unique'        => 'Esta cédula ya está registrada.',
                'empresa_id.required'  => 'Debés seleccionar una empresa.',
                'empresa_id.exists'    => 'La empresa seleccionada no existe.',
                'sucursal_id.exists'   => 'La sucursal seleccionada no existe.',
                'password.required'    => 'La contraseña es obligatoria.',
                'password.min'         => 'La contraseña debe tener al menos 6 caracteres.',
                'password.confirmed'   => 'Las contraseñas no coinciden.',
                'profile_image.image'  => 'La foto de perfil debe ser una imagen.',
                'profile_image.mimes'  => 'La foto debe ser jpeg, png, jpg o gif.',
                'profile_image.max'    => 'La foto no puede superar los 2MB.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Hay errores en los datos enviados.',
                'errors'  => $validator->errors()
            ], 422);
        }

        // 🚫 Regla: si ya existe un admin, no permitir crear otro desde el registro
        // (solo bloquea register; los admins sí pueden cambiar roles luego)
     $roleSolicitado = $request->role ?? 'admin';

if ($roleSolicitado === 'admin') {

    // ✔ Ahora revisa solo admins de LA MISMA EMPRESA
    $existeAdmin = Usuario::where('empresa_id', $request->empresa_id)
        ->where('role', 'admin')
        ->exists();

    if ($existeAdmin) {
        return response()->json([
            'message' => 'Esta empresa ya tiene un administrador registrado. No podés crear otro.',
            'code'    => 'ADMIN_LIMIT_BY_COMPANY'
        ], 403);
    }
}


        try {
            $imagePath = null;

            if ($request->hasFile('profile_image')) {
                $imagePath = $request->file('profile_image')
                    ->store('profile_images', 'public');
            }

            $user = Usuario::create([
                'nombre'       => $request->nombre,
                'email'        => $request->email,
                'cedula'       => $request->cedula,
                'role'         => $roleSolicitado,
                'empresa_id'   => $request->empresa_id,
                'sucursal_id'  => $request->sucursal_id,
                'password'     => Hash::make($request->password),
                'profile_image'=> $imagePath
            ]);

            if ($user->profile_image) {
                $user->profile_image = url('storage/' . $user->profile_image);
            }

            return response()->json([
                'message' => 'Usuario registrado con éxito.',
                'user'    => $user
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Error en register: ' . $e->getMessage());
            return response()->json([
                'message' => 'Ocurrió un error al registrar el usuario.',
            ], 500);
        }
    }

    // ========================================================
    //  LOGIN
    // ========================================================
    public function login(Request $request)
    {
        try {
            $credentials = $request->only('email', 'password');

            if (!Auth::guard('web')->attempt($credentials)) {
                return response()->json([
                    'message' => 'Credenciales inválidas. Verificá tu correo y contraseña.'
                ], 401);
            }

            $user = Auth::guard('web')->user();
            $token = $user->createToken('Personal Access Token')->plainTextToken;

            // Ocultar contraseña del usuario
            $user->makeHidden(['password']);

            // Cargar empresa
            $user->load('empresa');

            // Imagen de perfil
            if ($user->profile_image && !str_contains($user->profile_image, 'http')) {
                $user->profile_image = url('storage/' . $user->profile_image);
            }

            // LOGO de la empresa
            if ($user->empresa && $user->empresa->logo) {
                $user->empresa->logo = asset('storage/logos/' . $user->empresa->logo);
            }

            return response()->json([
                'token'   => $token,
                'usuario' => $user,
                'success' => true
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error en login: ' . $e->getMessage());
            return response()->json([
                'message' => 'Ocurrió un error al iniciar sesión.'
            ], 500);
        }
    }

    // ========================================================
    //  UPDATE USER (admin)
    // ========================================================
    public function adminUpdateUser(Request $request, $id)
    {
        $user = Usuario::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'El usuario que intentás actualizar no existe.'
            ], 404);
        }

        $validated = $request->validate(
            [
                'nombre'      => 'required|string|max:255',
                'email'       => 'required|string|email|max:255|unique:usuarios,email,' . $user->id,
                'cedula'      => 'required|string|max:12|unique:usuarios,cedula,' . $user->id,
                'empresa_id'  => 'required|exists:empresas,id',
                'sucursal_id' => 'nullable|exists:sucursales,id',
                'role'        => 'required|string|in:admin,contador,empleado',
            ],
            [
                'nombre.required'     => 'El nombre es obligatorio.',
                'email.required'      => 'El correo es obligatorio.',
                'email.email'         => 'El formato del correo no es válido.',
                'email.unique'        => 'Este correo ya está registrado por otro usuario.',
                'cedula.required'     => 'La cédula es obligatoria.',
                'cedula.unique'       => 'Esta cédula ya está registrada por otro usuario.',
                'empresa_id.required' => 'Debés seleccionar una empresa.',
                'empresa_id.exists'   => 'La empresa seleccionada no existe.',
                'sucursal_id.exists'  => 'La sucursal seleccionada no existe.',
                'role.required'       => 'El rol es obligatorio.',
            ]
        );

        $user->update($validated);

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'user'    => $user
        ]);
    }

    // ========================================================
    //  UPDATE PROFILE (self o por id)
    // ========================================================
    public function updateProfile(Request $request, $id = null)
    {
        $user = $id ? Usuario::find($id) : $request->user();

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        $validated = $request->validate(
            [
                'nombre'           => 'required|string|max:255',
                'email'            => 'required|string|email|max:255|unique:usuarios,email,' . $user->id,
                'cedula'           => 'required|string|max:12|unique:usuarios,cedula,' . $user->id,
                'profile_image'    => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'current_password' => 'required_with:password|string|min:6',
                'password'         => 'nullable|string|min:6|confirmed',
                'role'             => 'nullable|string|in:admin,contador,empleado',
            ],
            [
                'nombre.required'        => 'El nombre es obligatorio.',
                'email.required'         => 'El correo es obligatorio.',
                'email.email'            => 'El formato del correo no es válido.',
                'email.unique'           => 'Este correo ya está registrado por otro usuario.',
                'cedula.required'        => 'La cédula es obligatoria.',
                'cedula.unique'          => 'Esta cédula ya está registrada por otro usuario.',
                'profile_image.image'    => 'La foto de perfil debe ser una imagen.',
                'profile_image.mimes'    => 'La foto debe ser jpeg, png, jpg o gif.',
                'profile_image.max'      => 'La foto no puede superar los 2MB.',
                'current_password.required_with' => 'Debés ingresar tu contraseña actual para cambiarla.',
                'password.min'           => 'La nueva contraseña debe tener al menos 6 caracteres.',
                'password.confirmed'     => 'Las contraseñas nuevas no coinciden.',
            ]
        );

        // Cambio de contraseña (si se envía)
        if ($request->filled('password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'message' => 'La contraseña actual es incorrecta.'
                ], 403);
            }

            $user->password = Hash::make($request->password);
        }

        unset($validated['password']);
        unset($validated['password_confirmation']);
        unset($validated['current_password']);

        // Manejo de imagen de perfil
        if ($request->hasFile('profile_image')) {
            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }

            $validated['profile_image'] = $request->file('profile_image')
                ->store('profile_images', 'public');
        }

        // Cambio de rol (solo si viene explícito)
        if ($request->filled('role')) {
            $user->role = $validated['role'];
        }

        $user->update($validated);

        if ($user->profile_image) {
            $user->profile_image = url('storage/' . $user->profile_image);
        }

        return response()->json([
            'message' => 'Perfil actualizado correctamente.',
            'user'    => $user
        ]);
    }

    // ========================================================
    //  DELETE ACCOUNT
    // ========================================================
    public function deleteAccount(Request $request, $id = null)
    {
        $usuario = $id ? Usuario::find($id) : $request->user();

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        try {
            if ($usuario->profile_image) {
                Storage::disk('public')->delete($usuario->profile_image);
            }

            $usuario->delete();

            return response()->json(['message' => 'Usuario eliminado con éxito.'], 200);

        } catch (\Exception $e) {
            \Log::error('Error al eliminar usuario: ' . $e->getMessage());
            return response()->json([
                'message' => 'No se pudo eliminar el usuario.'
            ], 500);
        }
    }

    // ========================================================
    //  RESET PASSWORD
    // ========================================================
    public function sendResetLinkEmail(Request $request)
    {
        $this->validate($request, ['email' => 'required|email']);

        $response = Password::broker('usuarios')->sendResetLink(
            $request->only('email')
        );

        return $response == Password::RESET_LINK_SENT
            ? response()->json(['message' => trans($response)], 200)
            : response()->json(['message' => trans($response)], 400);
    }

    public function showResetForm(Request $request, $token = null)
    {
        $url = url('http://localhost:5173/ResetPassword/' . $token . '?email=' . urlencode($request->email));
        return redirect($url);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'password' => 'required|confirmed|min:8',
        ]);

        $status = Password::broker('usuarios')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                $user->setRememberToken(Str::random(60));
            }
        );

        return ($status === Password::PASSWORD_RESET)
            ? response()->json(['message' => __($status)], 200)
            : response()->json(['message' => __($status)], 400);
    }

    // ========================================================
    //  USUARIOS CON SUCURSAL
    // ========================================================
    public function usuariosConSucursal()
    {
        try {
            $usuarios = Usuario::with('sucursal:id,nombre,direccion')
                ->get(['id','nombre','email','cedula','empresa_id','sucursal_id','role']);

            return response()->json($usuarios, 200);
        } catch (\Exception $e) {
            \Log::error('Error al obtener usuarios con sucursal: ' . $e->getMessage());
            return response()->json(['error' => 'No se pudieron obtener los usuarios'], 500);
        }
    }

    // ========================================================
    //  SUCURSALES POR EMPRESA DEL USUARIO
    // ========================================================
    public function sucursalesEmpresa(Request $request)
    {
        $user = $request->user();

        try {
            $sucursales = Sucursal::where('empresa_id', $user->empresa_id)
                ->orderBy('nombre')
                ->get(['id', 'nombre']);

            return response()->json($sucursales, 200);
        } catch (\Exception $e) {
            \Log::error('Error al obtener sucursales de la empresa: ' . $e->getMessage());
            return response()->json(['error' => 'No se pudieron obtener las sucursales'], 500);
        }
    }

    // ========================================================
    //  EMPLEADOS POR SUCURSAL
    // ========================================================
    public function empleadosPorSucursal(Request $request, $sucursalId)
    {
        $user = $request->user();

        try {
            // Validar que la sucursal pertenezca a la misma empresa del usuario
            $sucursal = Sucursal::where('empresa_id', $user->empresa_id)
                ->where('id', $sucursalId)
                ->firstOrFail();

            $usuarios = Usuario::with('sucursal:id,nombre')
                ->where('empresa_id', $user->empresa_id)
                ->where('sucursal_id', $sucursal->id)
                ->orderBy('nombre')
                ->get(['id','nombre','email','cedula','empresa_id','sucursal_id','role']);

            return response()->json($usuarios, 200);
        } catch (\Exception $e) {
            \Log::error('Error al obtener empleados por sucursal: ' . $e->getMessage());
            return response()->json(['error' => 'No se pudieron obtener los empleados'], 500);
        }
    }
}
