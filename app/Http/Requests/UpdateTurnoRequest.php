<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTurnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i'],
            'tolerancia_entrada' => ['nullable', 'integer', 'min:0', 'max:180'],
            'tolerancia_salida' => ['nullable', 'integer', 'min:0', 'max:180'],
            'minutos_almuerzo' => ['nullable', 'integer', 'min:0', 'max:480'],
        ];
    }
}

