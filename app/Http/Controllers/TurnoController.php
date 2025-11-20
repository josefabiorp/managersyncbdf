<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTurnoRequest;
use App\Http\Requests\UpdateTurnoRequest;
use App\Models\Turno;
use Illuminate\Http\Request;

class TurnoController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $turnos = Turno::where('empresa_id', $empresaId)->get();

        return response()->json($turnos);
    }

    public function store(StoreTurnoRequest $request)
    {
        $user = $request->user();

        $data = $request->validated();
        $data['empresa_id'] = $user->empresa_id;

        $turno = Turno::create($data);

        return response()->json($turno, 201);
    }

    public function update(UpdateTurnoRequest $request, Turno $turno)
    {
        $user = $request->user();

        if ($turno->empresa_id !== $user->empresa_id) {
            abort(403, 'No autorizado para modificar este turno.');
        }

        $turno->update($request->validated());

        return response()->json($turno);
    }

    public function destroy(Request $request, Turno $turno)
    {
        $user = $request->user();

        if ($turno->empresa_id !== $user->empresa_id) {
            abort(403, 'No autorizado para eliminar este turno.');
        }

        $turno->delete();

        return response()->json(['message' => 'Turno eliminado correctamente']);
    }
}
