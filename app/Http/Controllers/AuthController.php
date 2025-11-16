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

class AuthController extends Controller
{
    public function index()
    {
        return Usuario::with('sucursal:id,nombre,direccion')
            ->get(['id','nombre','email','cedula','empresa_id','sucursal_id','role']);
    }

    public function show()
    {
        try {
            if (Auth::guard('web')->check()) {
                return response()->json(Auth::guard('web')->user());
            }
            return response()->json(['error' => 'Unauthorized'], 401);
        } catch (\Exception $e) {
            \Log::error('Error in UsuarioController@show: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }
    

    // ========================================================
    //  REGISTER
    // ========================================================
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:usuarios',
            'cedula' => 'required|string|max:12|unique:usuarios',
            'empresa_id' => 'required|exists:empresas,id',
            'sucursal_id' => 'nullable|exists:sucursales,id',
            'password' => 'required|string|min:6|confirmed',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $imagePath = null;

        if ($request->hasFile('profile_image')) {
            $imagePath = $request->file('profile_image')
                ->store('profile_images', 'public');
        }

        $user = Usuario::create([
            'nombre' => $request->nombre,
            'email' => $request->email,
            'cedula' => $request->cedula,
            'role' => $request->role ?? 'admin',
            'empresa_id' => $request->empresa_id,
            'sucursal_id' => $request->sucursal_id,
            'password' => Hash::make($request->password),
            'profile_image' => $imagePath
        ]);

        if ($user->profile_image) {
            $user->profile_image = url('storage/' . $user->profile_image);
        }

        return response()->json([
            'message' => 'Usuario registrado con éxito',
            'user' => $user
        ], 201);
    }


    // ========================================================
    //  LOGIN
    // ========================================================
   public function login(Request $request)
{
    $credentials = $request->only('email', 'password');

    if (!Auth::guard('web')->attempt($credentials)) {
        return response()->json(['message' => 'Credenciales inválidas'], 401);
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
        'token' => $token,
        'usuario' => $user,
        'success' => true
    ], 200);
}


    // ========================================================
    //  UPDATE PROFILE
    // ========================================================





    public function adminUpdateUser(Request $request, $id)
{
    $user = Usuario::findOrFail($id);

    $validated = $request->validate([
        'nombre' => 'required|string|max:255',
        'email' => 'required|string|email|max:255',
        'cedula' => 'required|string|max:12',
        'empresa_id' => 'required|exists:empresas,id',
        'sucursal_id' => 'nullable|exists:sucursales,id',
        'role' => 'required|string|in:admin,contador,empleado',
    ]);

    $user->update($validated);

    return response()->json([
        'message' => 'Usuario actualizado correctamente',
        'user' => $user
    ]);
}



    public function updateProfile(Request $request, $id = null)
    {
        $user = $id ? Usuario::find($id) : $request->user();

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'cedula' => 'required|string|max:12',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'current_password' => 'required_with:password|string|min:6',
            'password' => 'nullable|string|min:6|confirmed',
            'role' => 'nullable|string|in:admin,contador,empleado',
        ]);

        if ($request->filled('password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json(['message' => 'La contraseña actual es incorrecta.'], 403);
            }

            $user->password = Hash::make($request->password);
        }

        unset($validated['password']);
        unset($validated['password_confirmation']);
        unset($validated['current_password']);

        if ($request->hasFile('profile_image')) {
            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }

            $validated['profile_image'] = $request->file('profile_image')
                ->store('profile_images', 'public');
        }

        if ($request->filled('role')) {
            $user->role = $validated['role'];
        }

        $user->update($validated);

        if ($user->profile_image) {
            $user->profile_image = url('storage/' . $user->profile_image);
        }

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'user' => $user
        ]);
    }


    // ========================================================
    //  DELETE ACCOUNT
    // ========================================================
    public function deleteAccount(Request $request, $id = null)
    {
        $usuario = $id ? Usuario::find($id) : $request->user();

        if ($usuario) {
            if ($usuario->profile_image) {
                Storage::disk('public')->delete($usuario->profile_image);
            }

            $usuario->delete();
            return response()->json(['message' => 'Usuario eliminado con éxito.'], 200);
        }

        return response()->json(['message' => 'Usuario no encontrado.'], 404);
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

}
