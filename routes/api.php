<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UsuarioController;

use App\Http\Controllers\ClienteController;

use App\Http\Controllers\FacturaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ReporteGeneralController;
use App\Http\Controllers\DetalleFacturaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\FacturaCompraController;
use App\Http\Controllers\FacturaProductoController;
use App\Http\Controllers\FacturaReferenciaController;
use App\Http\Controllers\CabysController;

use App\Http\Controllers\EmpresaController;

use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\DescansoController;

use App\Http\Controllers\PoliticaEmpresaController;



Route::get('/asistencia', [AsistenciaController::class, 'index']);


Route::get('/usuarios/con-sucursal', [AuthController::class, 'usuariosConSucursal']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/asistencias/entrada', [AsistenciaController::class, 'marcarEntrada']);
    Route::post('/asistencias/salida', [AsistenciaController::class, 'marcarSalida']);
    Route::get('/asistencias/estado/{usuario_id}', [AsistenciaController::class, 'estadoActual']);
    Route::get('/asistencias/rango', [AsistenciaController::class, 'obtenerPorRango']);
});

// Rutas para Empresas
Route::get('/empresas', [EmpresaController::class, 'index']);
Route::post('/empresas', [EmpresaController::class, 'store']);
Route::get('/empresas/{id}', [EmpresaController::class, 'show']);
Route::put('/empresas/{id}', [EmpresaController::class, 'update']);
Route::delete('/empresas/{id}', [EmpresaController::class, 'destroy']);

use App\Http\Controllers\SucursalController;

Route::get('/sucursales', [SucursalController::class, 'index']);
Route::post('/sucursales', [SucursalController::class, 'store']);
Route::get('/sucursales/{id}', [SucursalController::class, 'show']);
Route::put('/sucursales/{id}', [SucursalController::class, 'update']);
Route::delete('/sucursales/{id}', [SucursalController::class, 'destroy']);

Route::get('/sucursales/con-usuarios', [SucursalController::class, 'sucursalesConUsuarios']);





Route::get('/reportes/all', [ReporteGeneralController::class, 'index']);

Route::get('/usuarios/all', [AuthController::class, 'index']);

// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->post('/update-Profile', [AuthController::class, 'updateProfile']);



// Usuarios
Route::get('/usuarios', [AuthController::class, 'index']);
Route::get('/usuarios/{id}', [AuthController::class, 'show']);
Route::put('/usuarios/{id}', [AuthController::class, 'updateProfile']);
Route::delete('/usuarios/{id}', [AuthController::class, 'deleteAccount']);

Route::put('/admin/usuarios/{id}', [AuthController::class, 'adminUpdateUser']);

Route::middleware('auth:sanctum')->delete('/delete-account', [AuthController::class, 'deleteAccount']);

Route::post('password/email', [AuthController::class, 'sendResetLinkEmail']);
Route::get('password/reset/{token}', [AuthController::class, 'showResetForm'])->name('password.reset');
Route::post('password/reset', [AuthController::class, 'reset']);





// =========================================================
// ===============   RUTAS DE PERMISOS     =================
// =========================================================

use App\Http\Controllers\PermisoController;

// Obtener permisos (admin ve todos, empleado ve los suyos)
Route::middleware('auth:sanctum')->get('/permisos', [PermisoController::class, 'index']);

// Crear permiso (empleado)
Route::middleware('auth:sanctum')->post('/permisos', [PermisoController::class, 'store']);

// Actualizar estado del permiso (solo admin)
Route::middleware('auth:sanctum')->put('/permisos/{permiso}/estado', [PermisoController::class, 'updateEstado']);

Route::middleware('auth:sanctum')->get('/permisos/pendientes-count', [PermisoController::class, 'pendientesCount']);

Route::middleware('auth:sanctum')->get('/permisos/{permiso}', [PermisoController::class, 'show']);
Route::middleware('auth:sanctum')->put('/permisos/{permiso}/cancelar', [PermisoController::class, 'cancelar']);
Route::middleware('auth:sanctum')->get('/permisos/resumen', [PermisoController::class, 'resumen']);



// =============================
//      DESCANSOS
// =============================
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/descansos', [DescansoController::class, 'index']);
    Route::post('/descansos/iniciar', [DescansoController::class, 'iniciar']);
    Route::put('/descansos/{descanso}/finalizar', [DescansoController::class, 'finalizar']);
    Route::put('/descansos/{descanso}/cancelar', [DescansoController::class, 'cancelar']);

});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('politicas-empresa', [PoliticaEmpresaController::class, 'show']);
    Route::put('politicas-empresa', [PoliticaEmpresaController::class, 'update']);
});
