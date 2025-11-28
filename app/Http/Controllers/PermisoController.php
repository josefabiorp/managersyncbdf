<?php

namespace App\Http\Controllers;

use App\Models\Permiso;
use Illuminate\Http\Request;

class PermisoController extends Controller
{
   public function index(Request $request)
{
    $user = $request->user();

    $query = Permiso::with(['usuario', 'sucursal', 'aprobador']);

    $role = strtolower($user->role ?? $user->rol ?? '');
    $isAdmin = in_array($role, ['admin', 'administrador']);

    // ============================
    //   EMPLEADO → Solo sus permisos
    // ============================
    if (!$isAdmin) {
        $query->where('usuario_id', $user->id);
    }

    // ============================
    //   ADMINISTRADOR → Solo permisos de SU EMPRESA
    // ============================
    else {

        // 🚀 FILTRO CRÍTICO QUE FALTABA
        $query->where('empresa_id', $user->empresa_id);

        // Filtros opcionales
        if ($request->sucursal_id) {
            $query->where('sucursal_id', $request->sucursal_id);
        }

        if ($request->estado) {
            $query->where('estado', $request->estado);
        }

        if ($request->search) {
            $query->whereHas('usuario', function ($q) use ($request) {
                $q->where('nombre', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }
    }

    return response()->json(
        $query->orderBy('created_at', 'desc')->paginate(20)
    );
}



    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'tipo' => 'required|string|max:100',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i|after:hora_inicio',
            'motivo' => 'nullable|string',
        ]);

        $permiso = Permiso::create([
            'usuario_id' => $user->id,
            'empresa_id' => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'tipo' => $data['tipo'],
            'fecha_inicio' => $data['fecha_inicio'],
            'fecha_fin' => $data['fecha_fin'] ?? $data['fecha_inicio'],
            'hora_inicio' => $data['hora_inicio'] ?? null,
            'hora_fin' => $data['hora_fin'] ?? null,
            'motivo' => $data['motivo'] ?? null,
            'estado' => 'pendiente',
        ]);

        return response()->json([
            'message' => 'Permiso solicitado correctamente',
            'permiso' => $permiso->load(['usuario', 'sucursal']),
        ], 201);
    }

   public function updateEstado(Request $request, Permiso $permiso)
{
    $user = $request->user();
    $role = strtolower($user->role ?? $user->rol ?? '');

    if (!in_array($role, ['admin', 'administrador'])) {
        return response()->json(['message' => 'No autorizado'], 403);
    }

    // NO permitir cambiar estado si ya está cerrado
    if (in_array($permiso->estado, ['cancelado', 'aprobado', 'rechazado'])) {
        return response()->json([
            'message' => 'Este permiso ya está cerrado y no puede modificarse.',
            'permiso' => $permiso->load(['usuario', 'sucursal', 'aprobador'])
        ], 422);
    }

    $data = $request->validate([
        'estado' => 'required|in:pendiente,aprobado,rechazado,cancelado',
        'respuesta_admin' => 'nullable|string',
    ]);

    $permiso->update([
        'estado' => $data['estado'],
        'respuesta_admin' => $data['respuesta_admin'] ?? null,
        'aprobado_por' => $user->id,
    ]);

    return response()->json([
        'message' => 'Estado del permiso actualizado',
        'permiso' => $permiso->load(['usuario', 'sucursal', 'aprobador']),
    ]);
}


   public function pendientesCount(Request $request)
{
    $user = $request->user();

    $role = strtolower($user->role ?? $user->rol ?? '');
    $isAdmin = in_array($role, ['admin', 'administrador']);

    if (!$isAdmin) {
        return response()->json(['count' => 0]);
    }

    // ADMIN ve TODOS los pendientes, sin importar empresa
    $count = Permiso::where('estado', 'pendiente')->count();

    return response()->json(['count' => $count]);
}

public function show(Permiso $permiso, Request $request)
{
    $user = $request->user();
    $role = strtolower($user->role ?? $user->rol ?? '');

    // Empleado solo puede ver sus permisos
    if (!in_array($role, ['admin', 'administrador']) && $permiso->usuario_id !== $user->id) {
        return response()->json(['message' => 'No autorizado'], 403);
    }

    return response()->json(
        $permiso->load(['usuario', 'sucursal', 'aprobador']),
        200
    );
}
public function cancelar(Request $request, Permiso $permiso)
{
    $user = $request->user();

    if ($permiso->usuario_id !== $user->id) {
        return response()->json(['message' => 'No autorizado'], 403);
    }

    if ($permiso->estado !== 'pendiente') {
        return response()->json(['message' => 'Solo se pueden cancelar permisos pendientes'], 422);
    }

    $permiso->estado = 'cancelado';
    $permiso->save();

    return response()->json([
        'message' => 'Permiso cancelado correctamente',
        'permiso' => $permiso->load(['usuario', 'sucursal']),
    ]);
}
public function resumen(Request $request)
{
    $user = $request->user();
    $role = strtolower($user->role ?? $user->rol ?? '');
    $isAdmin = in_array($role, ['admin', 'administrador']);

    $baseQuery = Permiso::query();

    if (!$isAdmin) {
        $baseQuery->where('usuario_id', $user->id);
    }

    $resumen = $baseQuery->selectRaw('estado, COUNT(*) as total')
        ->groupBy('estado')
        ->pluck('total', 'estado');

    return response()->json([
        'pendiente' => $resumen['pendiente'] ?? 0,
        'aprobado'  => $resumen['aprobado'] ?? 0,
        'rechazado' => $resumen['rechazado'] ?? 0,
        'cancelado' => $resumen['cancelado'] ?? 0,
    ]);
}


}
