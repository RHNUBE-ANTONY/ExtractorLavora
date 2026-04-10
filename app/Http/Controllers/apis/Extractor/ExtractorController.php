<?php

namespace App\Http\Controllers\apis\Extractor;


use App\Http\Controllers\Controller;
use App\Http\Controllers\DB\ConfiguracionDatabase;
use App\Models\biometrico_estado;
use App\Models\comandos_biometrico;
use App\Models\organizacion;
use App\Models\organizacion_puerto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExtractorController extends Controller
{
    public function create_update_divice(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'serie' => 'required',
                'organi_id' => 'required|exists:organizacion,organi_id',
            ],
            [
                'serie.required' => 'La serie del dispositivo es obligatoria.',
                'organi_id.required' => 'El ID de la organización es obligatorio.',
                'organi_id.exists' => 'La organización no existe en el sistema.',
            ]
        );
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
        $data = $request->all();
        $organi_id = $request->organi_id;
        // *-----------  CONEXION BD EXTRACTOR-------------------------------
        $environmentE = (new ConfiguracionDatabase($organi_id))->setBDD_Ex();
        $connectionE = $environmentE->conexion;
        $name_bdE = $environmentE->name;
        // *---------------------FIN CONEXION EXTRACTOR -------------------------*
        // Lógica para crear el dispositivo
        $connectionE->table('biometrico_estado')->updateOrInsert(
            [
                'organi_id' => $request->organi_id,
                'serie' => $request->serie,
            ],
            $data
        );
        DB::disconnect($name_bdE);
        return response()->json(
            ['message' => 'Dispositivo creado exitosamente'],
            200
        );
    }
    public function update_organizacion_puerto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'organi_id' => 'required|exists:organizacion,organi_id',
        ], [
            'organi_id.required' => 'El ID de la organización es obligatorio.',
            'organi_id.exists' => 'La organización no existe en el sistema.'
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
        $data = $request->all();
        $organi_id = $request->organi_id;
        // Lógica para crear el dispositivo
        organizacion_puerto::updateOrCreate(
            [
                'organi_id' => $organi_id,
            ],
            $data
        );
        return response()->json(
            ['message' => 'Puerto de organizacion actualizado exitosamente'],
            200
        );
    }
    public function update_organizacion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'organi_id' => 'required|exists:organizacion,organi_id',
        ], [
            'organi_id.required' => 'El ID de la organización es obligatorio.',
            'organi_id.exists' => 'La organización no existe en el sistema.'
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
        $data = $request->all();
        $organi_id = $request->organi_id;
        // Lógica para crear el dispositivo
        organizacion::updateOrCreate(
            [
                'organi_id' => $organi_id,
            ],
            $data
        );
        return response()->json(
            ['message' => 'Puerto de organizacion actualizado exitosamente'],
            200
        );
    }
    public function create_comands(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'organi_id' => 'required|exists:organizacion,organi_id',
            'commands' => 'required|array'
        ], [
            'organi_id.required' => 'El ID de la organización es obligatorio.',
            'organi_id.exists' => 'La organización no existe en el sistema.',
            'commands.required' => 'Los comandos son obligatorios.',
            'commands.array' => 'Los comandos deben ser un arreglo.'
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
        $data = $request->commands;
        $organi_id = $request->organi_id;
        // *-----------  CONEXION BD EXTRACTOR-------------------------------
        $environmentE =  new ConfiguracionDatabase($organi_id);
        // $connectionE = $environmentE->setBDD_Ex()->conexion;
        $name_bdE = $environmentE->setBDD_Ex()->name;
        // *---------------------FIN CONEXION EXTRACTOR -------------------------*
        foreach ($data as $comand) {
            comandos_biometrico::on($name_bdE)->create($comand);
        }
        DB::disconnect($name_bdE);
        return response()->json(
            ['message' => 'Comando creado exitosamente'],
            200
        );
    }
    public function data_device(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'organi_id' => 'required|exists:organizacion,organi_id',
            'serie' => 'required'
        ], [
            'organi_id.required' => 'El ID de la organización es obligatorio.',
            'organi_id.exists' => 'La organización no existe en el sistema.',
            'serie.required' => 'La serie del dispositivo es obligatoria.'
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
        $organi_id = $request->organi_id;
        // *-----------  CONEXION BD EXTRACTOR-------------------------------
        $environmentE =  new ConfiguracionDatabase($organi_id);
        // $connectionE = $environmentE->setBDD_Ex()->conexion;
        $name_bdE = $environmentE->setBDD_Ex()->name;
        // *---------------------FIN CONEXION EXTRACTOR -------------------------*
        $device = biometrico_estado::on($name_bdE)
            ->select(
                DB::raw('IF(algoritmo_huella IS NULL,10,algoritmo_huella) as algoritmo_huella'),
                'algoritmo_facial',
                'n_puertas'
            )
            ->where('organi_id', $organi_id)
            ->where('serie', $request->serie)->first();
        DB::disconnect($name_bdE);
        return response()->json(
            [
                'result' => true,
                'device' => $device
            ],
            200
        );
    }
    public function delete_image(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image_path' => 'required|string',
        ], [
            'image_path.required' => 'La ruta de la imagen es obligatoria.',
            'image_path.string' => 'La ruta de la imagen debe ser una cadena de texto.',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
        $image_path =  public_path() . $request->image_path;
        // Lógica para eliminar la imagen
        if (file_exists($image_path)) {
            unlink($image_path);
            return response()->json(
                ['message' => 'Imagen eliminada exitosamente'],
                200
            );
        } else {
            return response()->json(
                ['error' => 'La imagen no existe en la ruta especificada'],
                404
            );
        }
    }
    public function data_logs_jobs(Request $request)
    {
        $start = $request->start;
        $limite = $request->limite;
        $organis_ids = $request->organis_ids; // Recibir el array de organi_id desde la solicitud
        $onlyError = $request->onlyError; // Recibir el valor de onlyError desde la solicitud
        $query = DB::table('jobs_logs as lj')
            ->join('organizacion as o', 'lj.organi_id', '=', 'o.organi_id')
            ->select(
                'o.organi_ruc',
                'o.organi_razonSocial',
                'fecha_inicio',
                'fecha_fin'
            )
            ->whereIn('lj.organi_id', $organis_ids) // Filtrar por el array de organi_id
            ->when($onlyError == 1, function ($query) {
                $query->whereNull('fecha_fin'); // Si onlyError es true, filtrar solo los registros con fecha_fin nula
            })
            ->orderBy('fecha_inicio', 'desc');

        $total_registros = $query->get()->count();

        if (!is_negative_number(intval($limite))) {
            $query->skip($start)
                ->take($limite);
        }
        $fecha_actual = Carbon::now('America/Lima');

        $logs_jobs = $query->get()->each(function ($item) use ($fecha_actual) {
            if (empty($item->fecha_fin)) {
                //si la diferencia de la fecha actual y la fecha de inicio es mayor a 20 minutos, entonces el proceso esta en proceso, caso contrario se finalizo
                $fecha_inicio = Carbon::parse($item->fecha_inicio);
                $diferencia_minutos = $fecha_actual->diffInMinutes($fecha_inicio);
                if ($diferencia_minutos <= 20) {
                    $item->status = '<span class="badge badge-warning">En proceso</span>';
                } else {
                    $item->status = '<span class="badge badge-danger">Error</span>';
                }
            } else {
                $item->status = '<span class="badge badge-success">Finalizado</span>';
            }
        })->toArray();
        return response()->json(
            [
                'result' => true,
                'total' => $total_registros,
                'logs_jobs' => $logs_jobs
            ],
            200
        );
    }
    public function data_devices_type(Request $request)
    {
        $organi_id = $request->organi_id;
        $tipoProceso = $request->tipoProceso;
        // *-----------  CONEXION BD EXTRACTOR-------------------------------
        $environmentE = (new ConfiguracionDatabase($organi_id))->setBDD_Ex();
        // $connectionE = $environmentE->conexion;
        $name_bdE = $environmentE->name;
        // *---------------------FIN CONEXION EXTRACTOR -------------------------*
        $actual = Carbon::now('America/Lima');
        $actualMedia = Carbon::now('America/Lima')->subSeconds(180);
        $devices_type = biometrico_estado::on($name_bdE)
            ->where('organi_id', $organi_id)
            ->where('tipoProceso', $tipoProceso)
            ->whereBetween(DB::raw('(ultima_conexion)'), [$actualMedia, $actual])
            ->pluck('serie');

        DB::disconnect($name_bdE);
        return response()->json(
            [
                'result' => true,
                'devices_type' => $devices_type
            ],
            200
        );
    }
}
