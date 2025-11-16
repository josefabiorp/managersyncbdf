<?php

namespace App\Http\Controllers;

use App\Models\Sucursal;
use Illuminate\Http\Request;

class SucursalController extends Controller
{
    /**
     * ======================================================
     *  LISTAR TODAS LAS SUCURSALES
     * ======================================================
     */
    public function index()
    {
        $sucursales = Sucursal::with(['empresa', 'usuarios:id,nombre,email,role,sucursal_id'])
            ->orderBy('nombre')
            ->get();

        return response()->json($sucursales, 200);
    }

    /**
     * ======================================================
     *  CREAR UNA NUEVA SUCURSAL
     * ======================================================
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'empresa_id' => 'nullable|exists:empresas,id',
            'nombre'     => 'required|string|max:150',
            'codigo'     => 'nullable|string|max:50',
            'direccion'  => 'nullable|string|max:255',
            'telefono'   => 'nullable|string|max:20',
           'latitud' => 'nullable|numeric|between:-90,90',
'longitud' => 'nullable|numeric|between:-180,180',

            'mapa_url'   => 'nullable|url',
            'activa'     => 'boolean',
        ]);

        // ✅ Si no se envió mapa_url, se genera automáticamente
        if (empty($validated['mapa_url']) && isset($validated['latitud'], $validated['longitud'])) {
            $validated['mapa_url'] = sprintf(
                'https://www.google.com/maps?q=%s,%s',
                $validated['latitud'],
                $validated['longitud']
            );
        }

        $sucursal = Sucursal::create($validated);

        return response()->json([
            'message'   => 'Sucursal creada correctamente',
            'sucursal'  => $sucursal,
        ], 201);
    }

    /**
     * ======================================================
     *  MOSTRAR UNA SUCURSAL ESPECÍFICA
     * ======================================================
     */
    public function show($id)
    {
        $sucursal = Sucursal::with(['empresa', 'usuarios'])->find($id);

        if (!$sucursal) {
            return response()->json(['error' => 'Sucursal no encontrada'], 404);
        }

        return response()->json($sucursal, 200);
    }

    /**
     * ======================================================
     *  ACTUALIZAR SUCURSAL
     * ======================================================
     */
    public function update(Request $request, $id)
    {
        $sucursal = Sucursal::find($id);
        if (!$sucursal) {
            return response()->json(['error' => 'Sucursal no encontrada'], 404);
        }

        $validated = $request->validate([
            'nombre'     => 'sometimes|string|max:150',
            'codigo'     => 'nullable|string|max:50',
            'direccion'  => 'nullable|string|max:255',
            'telefono'   => 'nullable|string|max:20',
            'latitud' => 'nullable|numeric|between:-90,90',
'longitud' => 'nullable|numeric|between:-180,180',

            'mapa_url'   => 'nullable|url',
            'activa'     => 'boolean',
        ]);

        // 🔁 Si se actualizan coordenadas, regenerar URL del mapa
        if (
            (isset($validated['latitud']) && isset($validated['longitud'])) &&
            (empty($validated['mapa_url']) || $validated['mapa_url'] !== $sucursal->mapa_url)
        ) {
            $validated['mapa_url'] = sprintf(
                'https://www.google.com/maps?q=%s,%s',
                $validated['latitud'],
                $validated['longitud']
            );
        }

        $sucursal->update($validated);

        return response()->json([
            'message'  => 'Sucursal actualizada correctamente',
            'sucursal' => $sucursal,
        ], 200);
    }

    /**
     * ======================================================
     *  ELIMINAR SUCURSAL
     * ======================================================
     */
    public function destroy($id)
    {
        $sucursal = Sucursal::find($id);
        if (!$sucursal) {
            return response()->json(['error' => 'Sucursal no encontrada'], 404);
        }

        $sucursal->delete();

        return response()->json(['message' => 'Sucursal eliminada correctamente'], 200);
    }

    /**
     * ======================================================
     *  SUCURSALES CON EMPLEADOS (para dashboard)
     * ======================================================
     */
    public function sucursalesConUsuarios()
    {
        $sucursales = Sucursal::with(['usuarios:id,nombre,email,role,sucursal_id'])
            ->orderBy('nombre')
            ->get();

        return response()->json($sucursales, 200);
    }
}
