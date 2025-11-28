<?php

namespace App\Http\Controllers;

use App\Models\Mensaje;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ChatController extends Controller
{
    /**
     * LISTA DE CONTACTOS DEL USUARIO
     * - Admin: ve todos los usuarios de su empresa excepto él mismo
     * - Empleado: ve todos los usuarios de su empresa excepto él mismo
     * (Chat libre entre todos)
     */
    public function conversaciones()
    {
        $user = auth()->user();

        $usuarios = Usuario::where('empresa_id', $user->empresa_id)
            ->where('id', '!=', $user->id)
            ->select('id', 'nombre', 'email', 'role', 'sucursal_id')
            ->orderBy('nombre')
            ->get();

        return response()->json($usuarios);
    }


    /**
     * OBTENER TODOS LOS MENSAJES ENTRE DOS USUARIOS
     */
    public function mensajes(Request $request)
    {
        $request->validate([
            'usuario_id' => 'required|integer'
        ]);

        $user = auth()->user();
        $otro = Usuario::find($request->usuario_id);

        if (!$otro || $otro->empresa_id !== $user->empresa_id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $mensajes = Mensaje::entreUsuarios($user->id, $otro->id)
            ->visiblesPara($user->id)
            ->get();

        return response()->json($mensajes);
    }


    /**
     * ENVIAR MENSAJE ENTRE USUARIOS
     */
    public function enviar(Request $request)
    {
        $request->validate([
            'destinatario_id' => 'required|integer',
            'contenido'       => 'required|string|max:1500',
        ]);

        $user = auth()->user();

        $destinatario = Usuario::find($request->destinatario_id);

        if (!$destinatario || $destinatario->empresa_id !== $user->empresa_id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $msg = Mensaje::create([
            'empresa_id'      => $user->empresa_id,
            'sucursal_id'     => $user->sucursal_id,
            'remitente_id'    => $user->id,
            'destinatario_id' => $destinatario->id,
            'contenido'       => $request->contenido,
            'tipo'            => 'texto',
            'leido'           => false,
        ]);

        return response()->json($msg);
    }


    /**
     * MARCAR MENSAJE COMO LEÍDO
     */
    public function marcarLeido($id)
    {
        $user = auth()->user();
        $msg = Mensaje::find($id);

        if (!$msg) {
            return response()->json(['error' => 'No encontrado'], 404);
        }

        if ($msg->destinatario_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $msg->update([
            'leido' => true,
            'leido_en' => Carbon::now(),
        ]);

        return response()->json(['ok' => true]);
    }


    /**
     * ELIMINAR SOLO PARA EL USUARIO (WhatsApp style)
     */
    public function eliminarSoloParaMi($id)
    {
        $user = auth()->user();
        $msg = Mensaje::find($id);

        if (!$msg) {
            return response()->json(['error' => 'No encontrado'], 404);
        }

        if ($msg->remitente_id == $user->id) {
            $msg->eliminado_para_remitente = Carbon::now();
        }

        if ($msg->destinatario_id == $user->id) {
            $msg->eliminado_para_destinatario = Carbon::now();
        }

        $msg->save();

        return response()->json(['ok' => true]);
    }
}
