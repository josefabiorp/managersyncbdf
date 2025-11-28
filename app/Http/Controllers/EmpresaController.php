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
        try {
            $empresas = Empresa::all()->map(function ($empresa) {
                $empresa->logo_url = $empresa->logo
                    ? asset('storage/logos/' . $empresa->logo)
                    : null;

                return $empresa;
            });

            return response()->json($empresas);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'No se pudieron cargar las empresas.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    // ───────────────────────────────────────────────
    // POST /empresas — Crear empresa (con logo)
    // ───────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'nombre'            => 'required|string|max:255|unique:empresas,nombre',
            'telefono'          => 'nullable|string|max:15',
            'correo'            => 'nullable|email',
            'cedula_empresa'    => 'nullable|string|max:12|unique:empresas,cedula_empresa',
            'provincia'         => 'nullable|string|max:255',
            'canton'            => 'nullable|string|max:255',
            'distrito'          => 'nullable|string|max:255',
            'otras_senas'       => 'nullable|string',
            'codigo_actividad'  => 'nullable|string|max:12|unique:empresas,codigo_actividad',
            'descripcion'       => 'required|string',
            'empresa'           => 'required|string',
            'logo'              => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'nombre.unique'             => 'Ya existe una empresa registrada con ese nombre.',
            'cedula_empresa.unique'     => 'La cédula jurídica ya está registrada.',
            'codigo_actividad.unique'   => 'Ese código de actividad ya está en uso.',
            'nombre.required'           => 'El nombre de la empresa es obligatorio.',
            'descripcion.required'      => 'Debés ingresar una descripción.',
            'empresa.required'          => 'El tipo de empresa es obligatorio.',
            'logo.image'                => 'El archivo debe ser una imagen válida.',
        ]);

        try {
            $data = $request->except('logo');

            // Subir logo (si viene)
            if ($request->hasFile('logo')) {
                $file = $request->file('logo');
                $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->storeAs("public/logos", $fileName);
                $data['logo'] = $fileName;
            }

            $empresa = Empresa::create($data);

            // Añadir URL completa del logo
            $empresa->logo_url = $empresa->logo
                ? asset('storage/logos/' . $empresa->logo)
                : null;

            return response()->json([
                'message' => 'Empresa creada exitosamente.',
                'empresa' => $empresa
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'No se pudo crear la empresa.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    // ───────────────────────────────────────────────
    // GET /empresas/{id}
    // ───────────────────────────────────────────────
    public function show($id)
    {
        try {
            $empresa = Empresa::find($id);

            if (!$empresa) {
                return response()->json([
                    'error' => 'La empresa no existe.',
                ], 404);
            }

            $empresa->logo_url = $empresa->logo
                ? asset('storage/logos/' . $empresa->logo)
                : null;

            return response()->json($empresa);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'No se pudo cargar la información de la empresa.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    // ───────────────────────────────────────────────
    // PUT /empresas/{id} — Actualizar con logo opcional
    // ───────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $empresa = Empresa::find($id);

        if (!$empresa) {
            return response()->json([
                'error' => 'La empresa que intentás actualizar no existe.',
            ], 404);
        }

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
        ], [
            'nombre.unique'             => 'Ya existe otra empresa con ese nombre.',
            'cedula_empresa.unique'     => 'La cédula jurídica ya está registrada por otra empresa.',
            'codigo_actividad.unique'   => 'El código de actividad ya lo usa otra empresa.',
        ]);

        try {
            $data = $request->except('logo');

            // Si viene logo nuevo → eliminar viejo
            if ($request->hasFile('logo')) {
                if ($empresa->logo && Storage::exists("public/logos/{$empresa->logo}")) {
                    Storage::delete("public/logos/{$empresa->logo}");
                }

                $file = $request->file('logo');
                $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->storeAs("public/logos", $fileName);
                $data['logo'] = $fileName;
            }

            $empresa->update($data);

            $empresa->logo_url = $empresa->logo
                ? asset('storage/logos/' . $empresa->logo)
                : null;

            return response()->json([
                'message' => 'Empresa actualizada correctamente.',
                'empresa' => $empresa
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'No se pudo actualizar la empresa.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    // ───────────────────────────────────────────────
    // DELETE /empresas/{id}
    // ───────────────────────────────────────────────
    public function destroy($id)
    {
        $empresa = Empresa::find($id);

        if (!$empresa) {
            return response()->json([
                'error' => 'No se puede eliminar: la empresa no existe.',
            ], 404);
        }

        try {
            // Eliminar logo
            if ($empresa->logo && Storage::exists("public/logos/{$empresa->logo}")) {
                Storage::delete("public/logos/{$empresa->logo}");
            }

            $empresa->delete();

            return response()->json([
                'message' => 'Empresa eliminada correctamente.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'No se pudo eliminar la empresa.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
