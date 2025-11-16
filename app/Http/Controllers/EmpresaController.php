<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmpresaController extends Controller
{
    // ───────────────────────────────────────────────
    // GET /empresas — Lista de empresas
    // ───────────────────────────────────────────────
    public function index()
    {
        return Empresa::all()->map(function ($empresa) {
            $empresa->logo_url = $empresa->logo
                ? asset('storage/logos/' . $empresa->logo)
                : null;

            return $empresa;
        });
    }

    // ───────────────────────────────────────────────
    // POST /empresas — Crear empresa (con logo)
    // ───────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'nombre'            => 'required|string|max:255|unique:empresas',
            'telefono'          => 'nullable|string|max:15',
            'correo'            => 'nullable|email',
            'cedula_empresa'    => 'nullable|string|max:12|unique:empresas',
            'provincia'         => 'nullable|string|max:255',
            'canton'            => 'nullable|string|max:255',
            'distrito'          => 'nullable|string|max:255',
            'otras_senas'       => 'nullable|string',
            'codigo_actividad'  => 'nullable|string|max:12|unique:empresas',
            'descripcion'       => 'required|string',
            'empresa'           => 'required|string',
            'logo'              => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $data = $request->except('logo');

        // Subir logo (si viene)
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            $file->storeAs("public/logos", $fileName);

            $data['logo'] = $fileName;
        }

        $empresa = Empresa::create($data);

        // Respuesta con URL completa del logo
        $empresa->logo_url = $empresa->logo
            ? asset('storage/logos/' . $empresa->logo)
            : null;

        return response()->json($empresa, 201);
    }

    // ───────────────────────────────────────────────
    // GET /empresas/{id}
    // ───────────────────────────────────────────────
    public function show($id)
    {
        $empresa = Empresa::findOrFail($id);

        $empresa->logo_url = $empresa->logo
            ? asset('storage/logos/' . $empresa->logo)
            : null;

        return response()->json($empresa);
    }

    // ───────────────────────────────────────────────
    // PUT /empresas/{id} — Actualizar con logo opcional
    // ───────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $empresa = Empresa::findOrFail($id);

        $request->validate([
            'nombre'            => 'required|string|max:255|unique:empresas,nombre,' . $empresa->id,
            'telefono'          => 'nullable|string|max:15',
            'correo'            => 'nullable|email',
            'cedula_empresa'    => 'nullable|string|max:12|unique:empresas,cedula_empresa,' . $empresa->id,
            'provincia'         => 'nullable|string|max:255',
            'canton'            => 'nullable|string|max:255',
            'distrito'          => 'nullable|string|max:255',
            'otras_senas'       => 'nullable|string',
            'codigo_actividad'  => 'nullable|string|max:12|unique:empresas,codigo_actividad,' . $empresa->id,
            'descripcion'       => 'required|string',
            'empresa'           => 'required|string',
            'logo'              => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $data = $request->except('logo');

        // Si viene un logo nuevo → borrar el viejo
        if ($request->hasFile('logo')) {

            // eliminar logo viejo si existe
            if ($empresa->logo && Storage::exists("public/logos/{$empresa->logo}")) {
                Storage::delete("public/logos/{$empresa->logo}");
            }

            $file = $request->file('logo');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->storeAs("public/logos", $fileName);

            $data['logo'] = $fileName;
        }

        $empresa->update($data);

        // Añadir logo_url
        $empresa->logo_url = $empresa->logo
            ? asset('storage/logos/' . $empresa->logo)
            : null;

        return response()->json($empresa);
    }

    // ───────────────────────────────────────────────
    // DELETE /empresas/{id}
    // ───────────────────────────────────────────────
    public function destroy($id)
    {
        $empresa = Empresa::findOrFail($id);

        // eliminar logo físico
        if ($empresa->logo && Storage::exists("public/logos/{$empresa->logo}")) {
            Storage::delete("public/logos/{$empresa->logo}");
        }

        $empresa->delete();
        return response()->json(null, 204);
    }
}
