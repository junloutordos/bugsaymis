<?php

namespace App\Http\Controllers\HumanResource;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\User;

class DateParametersController extends Controller
{
    public function index()
    {
        $params = DB::table('data_parameters')->orderBy('date')->get();
        return Inertia::render('HumanResource/DateParameters/Index', [
            'params' => $params,
        ]);
    }

    public function store(Request $request)
    {
        // normalize time fields (accept HH:MM from the browser and convert to HH:MM:00)
        $input = $request->only(['type','description','date','timein','breakout','breakin','timeout']);
        foreach (['timein','breakout','breakin','timeout'] as $t) {
            if (!empty($input[$t]) && preg_match('/^\d{2}:\d{2}$/', $input[$t])) {
                $input[$t] = $input[$t] . ':00';
            }
        }

        $validator = \Illuminate\Support\Facades\Validator::make($input, [
            'type' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'date' => 'nullable|date',
            'timein' => 'nullable|date_format:H:i:s',
            'breakout' => 'nullable|date_format:H:i:s',
            'breakin' => 'nullable|date_format:H:i:s',
            'timeout' => 'nullable|date_format:H:i:s',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $id = DB::table('data_parameters')->insertGetId(array_merge($data, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $row = DB::table('data_parameters')->where('id', $id)->first();
        return response()->json(['row' => $row], 201);
    }

    public function update(Request $request, $id)
    {
        $input = $request->only(['type','description','date','timein','breakout','breakin','timeout']);
        foreach (['timein','breakout','breakin','timeout'] as $t) {
            if (!empty($input[$t]) && preg_match('/^\d{2}:\d{2}$/', $input[$t])) {
                $input[$t] = $input[$t] . ':00';
            }
        }

        $validator = \Illuminate\Support\Facades\Validator::make($input, [
            'type' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'date' => 'nullable|date',
            'timein' => 'nullable|date_format:H:i:s',
            'breakout' => 'nullable|date_format:H:i:s',
            'breakin' => 'nullable|date_format:H:i:s',
            'timeout' => 'nullable|date_format:H:i:s',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        DB::table('data_parameters')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));
        $row = DB::table('data_parameters')->where('id', $id)->first();
        return response()->json(['row' => $row]);
    }

    public function destroy($id)
    {
        DB::table('data_parameters')->where('id', $id)->delete();
        return response()->json(['deleted' => true]);
    }
}
