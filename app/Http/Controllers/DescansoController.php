<?php

namespace App\Http\Controllers;

use App\Models\Descanso;
use Illuminate\Http\Request;

class DescansoController extends Controller
{
    // ============================
    // LISTAR DESCANSOS
    // ============================
    public function index(Request $request)
    {
        $user = $request->user();

        // 🔥 FIX: si no hay usuario autenticado → evita error
        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $role = strtolower($user->role ?? $user->rol ?? '');
        $isAdmin = in_array($role, ['admin', 'administrador']);

        $query = Descanso::with(['usuario', 'sucursal'])
            ->orderBy('created_at', 'desc');

        if (!$isAdmin) {
            $query->where('usuario_id', $user->id);
        } else {
            if ($request->sucursal_id) {
                $query->where('sucursal_id', $request->sucursal_id);
            }
            if ($request->estado) {
                $query->where('estado', $request->estado);
            }
        }

        return response()->json($query->paginate(25));
    }

    // ============================
    // INICIAR DESCANSO
    // ============================
    public function iniciar(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $data = $request->validate([
            'tipo' => 'required|string|max:50',
            'nota' => 'nullable|string',
            'hora_inicio' => 'required|date_format:H:i',
        ]);

        $descanso = Descanso::create([
            'usuario_id'  => $user->id,
            'empresa_id'  => $user->empresa_id,
            'sucursal_id' => $user->sucursal_id,
            'tipo'        => $data['tipo'],
            'hora_inicio' => $data['hora_inicio'],
            'estado'      => 'abierto',
            'nota'        => $data['nota'] ?? null,
        ]);

        return response()->json([
            'message' => 'Descanso iniciado',
            'descanso' => $descanso->load(['usuario']),
        ], 201);
    }

    // ============================
    // FINALIZAR DESCANSO
    // ============================
    public function finalizar(Request $request, Descanso $descanso)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        if ($descanso->usuario_id !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $data = $request->validate([
            'hora_fin' => 'required|date_format:H:i',
        ]);

        $descanso->update([
            'hora_fin' => $data['hora_fin'],
            'estado'   => 'cerrado',
        ]);

        return response()->json([
            'message'  => 'Descanso finalizado',
            'descanso' => $descanso->fresh(),
        ]);
    }

    // ============================
    // ADMIN: CERRAR FORZADO
    // ============================
    public function forzarCierre(Request $request, Descanso $descanso)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $role = strtolower($user->role ?? $user->rol ?? '');

        if (!in_array($role, ['admin', 'administrador'])) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $data = $request->validate([
            'hora_fin' => 'required|date_format:H:i',
            'nota'     => 'nullable|string',
        ]);

        $descanso->update([
            'hora_fin'    => $data['hora_fin'],
            'estado'      => 'forzado',
            'cerrado_por' => $user->id,
            'nota'        => $data['nota'] ?? null,
        ]);

        return response()->json([
            'message' => 'Descanso cerrado por administración',
            'descanso' => $descanso->fresh(),
        ]);
    }

    // ============================
    // CANCELAR DESCANSO
    // ============================
    public function cancelar(Request $request, Descanso $descanso)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        if ($descanso->usuario_id !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $descanso->update([
            'estado' => 'cancelado',
        ]);

        return response()->json([
            'message' => 'Descanso cancelado',
            'descanso' => $descanso->fresh(),
        ]);
    }
}
