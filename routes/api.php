<?php

use App\Http\Controllers\apis\ApiMovilController;
use App\Http\Controllers\apis\Extractor\ExtractorController;
use App\Http\Controllers\CommandStatusController;
use App\Http\Controllers\InitializeConfigurationController;
use App\Http\Controllers\RecordEventsController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;



Route::get('/iclock/cdata', [InitializeConfigurationController::class, 'initialize_configuration']);
Route::post('/iclock/registry', [InitializeConfigurationController::class, 'registry']);
Route::get('/iclock/push', [InitializeConfigurationController::class, 'push']);

Route::post('/iclock/cdata', [RecordEventsController::class, 'record_events']);

Route::get('/iclock/getrequest', [CommandStatusController::class, 'command_status']);
Route::post('/iclock/devicecmd', [CommandStatusController::class, 'update_command_status']);

Route::get('/iclock/ping', function () {
    Log::info("iclock/ping");
    return 'OK';
});
Route::get('/iclock/deviceinfo', function () {
    Log::info("iclock/deviceinfo");
    return 'OK';
});


/* Route::get('/api/test',[apiMarcacionController::class,'prueba']); */
Route::post('/api/ultimaConexion', [ApiMovilController::class, 'ultimaConexion'])->middleware('apilogger');
Route::post('/api/RegistroDatosMovil', [ApiMovilController::class, 'RegistroDatosMovil'])->middleware('apilogger');
Route::post('/api/RegistrarMarcaciones', [ApiMovilController::class, 'RegistrarMarcaciones'])->middleware('apilogger');
Route::post('/api/almacenar_image', [ApiMovilController::class, 'almacenar_image'])->middleware('apilogger');

//rutas extractor
Route::post('/api/update-organizacion-puerto', [ExtractorController::class, 'update_organizacion_puerto'])->middleware('apilogger');
Route::post('/api/update-organizacion', [ExtractorController::class, 'update_organizacion'])->middleware('apilogger');
Route::post('/api/create-update-divice', [ExtractorController::class, 'create_update_divice'])->middleware('apilogger');
Route::post('/api/create-commands', [ExtractorController::class, 'create_comands'])->middleware('apilogger');
Route::post('/api/data-device', [ExtractorController::class, 'data_device'])->middleware('apilogger');
Route::post('/api/delete-image', [ExtractorController::class, 'delete_image'])->middleware('apilogger');
Route::post('/api/data-logs-jobs', [ExtractorController::class, 'data_logs_jobs'])->middleware('apilogger');
Route::post('/api/data-devices-type', [ExtractorController::class, 'data_devices_type'])->middleware('apilogger');
