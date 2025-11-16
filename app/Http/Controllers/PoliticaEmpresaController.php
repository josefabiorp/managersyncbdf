<?php

namespace App\Http\Controllers;

use App\Models\PoliticaEmpresa;
use Illuminate\Http\Request;

class PoliticaEmpresaController extends Controller
{
    // GET api/politicas-empresa
    public function show(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->empresa_id) {
            return response()->json([
                'message' => 'Usuario sin empresa asociada'
            ], 422);
        }

        $politica = PoliticaEmpresa::firstOrCreate(
            ['empresa_id' => $user->empresa_id],
            [] // usa los valores por defecto del modelo/migración
        );

        return response()->json($politica, 200);
    }

    // PUT api/politicas-empresa
    public function update(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->empresa_id) {
            return response()->json([
                'message' => 'Usuario sin empresa asociada'
            ], 422);
        }

        $data = $request->validate([
            'jornada_diaria_horas'          => 'required|integer|min:4|max:12',
            'minutos_tolerancia_atraso'    => 'required|integer|min:0|max:60',
            'minutos_tolerancia_salida'    => 'required|integer|min:0|max:60',
            'minutos_almuerzo'             => 'required|integer|min:0|max:180',
            'minutos_descanso'             => 'required|integer|min:0|max:60',
            'cantidad_descansos_permitidos'=> 'required|integer|min:0|max:10',
            'permite_acumular_descansos'   => 'required|boolean',
            'descuenta_permiso_sin_goce'   => 'required|boolean',
            'max_horas_extra_por_dia'      => 'required|integer|min:0|max:8',
            'politica_redondeo_tiempos'    => 'required|in:arriba,abajo,normal',
        ]);

        $politica = PoliticaEmpresa::firstOrCreate(
            ['empresa_id' => $user->empresa_id],
            []
        );

        $politica->update($data);

        return response()->json([
            'message'  => 'Políticas actualizadas correctamente',
            'politica' => $politica->fresh(),
        ], 200);
    }
}
