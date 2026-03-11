<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Role;
use Carbon\Carbon;

class GuidanceKioskController extends Controller
{
    public function index(Request $request)
    {
        // fetch guidance personnel for kiosk dropdown
        $guidanceRole = Role::whereRaw('LOWER(name) = ?', ['guidance'])->first();
        $guidanceUsers = User::where(function($q) use ($guidanceRole) {
            if ($guidanceRole) $q->whereRaw('FIND_IN_SET(?, role_id)', [$guidanceRole->id]);
            $q->orWhereRaw('LOWER(position) LIKE ?', ['%guidance%']);
            $q->orWhereRaw('LOWER(position) LIKE ?', ['%counselor%']);
        })->orderBy('name')->get(['id','name']);

        return Inertia::render('Guidance/Kiosk', [
            'guidanceUsers' => $guidanceUsers,
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            // Require PISAY and accept either dashed (AA-AAAA-AAA) or 9 alnum chars
            'pisay' => ['required', 'string', 'max:255', 'regex:/^([A-Za-z0-9]{2}-[A-Za-z0-9]{4}-[A-Za-z0-9]{3}|[A-Za-z0-9]{9})$/'],
            'concerns' => 'required|array|min:1',
            'concerns.*' => 'string|max:255',
            'description' => 'nullable|string|max:4000',
            'date_time_preferred' => 'required|date',
            'assigned_personnel' => 'nullable|integer|exists:users,id',
        ];

        $data = $request->validate($rules);

        DB::beginTransaction();
        try {
            Log::info('GuidanceKiosk store', ['keys' => array_keys($request->all())]);

            $studentId = null;
            if (!empty($data['pisay'])) {
                $cols = collect(DB::select("SHOW COLUMNS FROM students"))->map(fn($c) => $c->Field)->all();
                $pisayCandidates = ['pisaysystemid','pisaysystemID','pisaysystem_id','pisay_system_id','pisay_id','pisayid'];
                $pisayCol = null;
                foreach ($pisayCandidates as $c) {
                    if (in_array($c, $cols)) { $pisayCol = $c; break; }
                }

                $student = null;
                if ($pisayCol) {
                    $student = DB::table('students')->where($pisayCol, $data['pisay'])->first();
                    if (! $student) {
                        $clean = preg_replace('/[^A-Za-z0-9]/', '', $data['pisay']);
                        $student = DB::table('students')->where($pisayCol, $clean)->first();
                    }
                } else {
                    $clean = preg_replace('/[^A-Za-z0-9]/', '', $data['pisay']);
                    $student = DB::table('students')->whereRaw('LOWER(CONCAT_WS(" ", '.implode(', ', array_map(fn($c)=>"`$c`", $cols)).')) LIKE ?', ["%{$data['pisay']}%"])->first();
                    if (! $student) {
                        $student = DB::table('students')->whereRaw('LOWER(CONCAT_WS(" ", '.implode(', ', array_map(fn($c)=>"`$c`", $cols)).')) LIKE ?', ["%{$clean}%"])->first();
                    }
                }

                $studentId = $student?->id ?? null;
                if (empty($studentId)) {
                    DB::rollBack();
                    throw ValidationException::withMessages(['pisay' => ['PISAY ID not found']]);
                }
            }

            $now = Carbon::now()->toDateTimeString();

            $assembledConcern = is_array($data['concerns']) ? implode(', ', $data['concerns']) : (string) ($data['concerns'] ?? '');

            $insert = [
                'requestor_id' => $studentId,
                'consultation_type' => 'student',
                'concern' => $assembledConcern,
                'description' => $data['description'] ?? null,
                'status' => 'pending',
                'date_time_preferred' => isset($data['date_time_preferred']) ? Carbon::parse($data['date_time_preferred'])->toDateTimeString() : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // If assigned_personnel (user id) provided, store the user id
            if (!empty($data['assigned_personnel'])) {
                $insert['assigned_personnel'] = $data['assigned_personnel'];
            }

            $insertedId = DB::table('guidance_consultations')->insertGetId($insert);

            // load the inserted row for email payload
            $consult = DB::table('guidance_consultations')->where('id', $insertedId)->first();

            // Prepare requestor info (prefer student details when available)
            $requestor = [
                'name' => null,
                'sex' => null,
                'grade_level' => null,
                'section' => null,
                'office' => null,
            ];

            if (!empty($consult->requestor_id)) {
                $student = DB::table('students')->where('id', $consult->requestor_id)->first();
                if ($student) {
                    $lastnameCols = ['lastname', 'last_name', 'lname'];
                    $firstnameCols = ['firstname', 'first_name', 'fname'];
                    $last = null;
                    $first = null;
                    foreach ($lastnameCols as $c) { if (isset($student->$c) && $student->$c !== null && $student->$c !== '') { $last = $student->$c; break; } }
                    foreach ($firstnameCols as $c) { if (isset($student->$c) && $student->$c !== null && $student->$c !== '') { $first = $student->$c; break; } }

                    if ($last || $first) {
                        $requestor['name'] = $last && $first ? ($last . ', ' . $first) : trim(($last ?? '') . ' ' . ($first ?? ''));
                    } else {
                        $requestor['name'] = $student->name ?? null;
                    }

                    $requestor['sex'] = $student->sex ?? null;

                    $sectionStudent = DB::table('section_students')->where('studentid', $student->id)->orderByDesc('id')->first();
                    if ($sectionStudent) {
                        $levelId = $sectionStudent->levelid ?? null;
                        if ($levelId) $requestor['grade_level'] = 'Grade ' . $levelId;
                        $section = DB::table('sections')->where('id', $sectionStudent->sectionid)->first();
                        if ($section) {
                            $sectionName = null;
                            foreach (['sectionname','section_name','name','section'] as $col) {
                                if (isset($section->$col) && $section->$col !== null && $section->$col !== '') { $sectionName = $section->$col; break; }
                            }
                            $requestor['section'] = $sectionName ?? null;
                        }
                    }
                } else {
                    $userRow = DB::table('users')->where('id', $consult->requestor_id)->first();
                    if ($userRow) {
                        $requestor['name'] = $userRow->name ?? null;
                        $requestor['office'] = $userRow->office ?? null;
                    }
                }
            }

            // Notify guidance staff using a similar format to clinic emails
            try {
                $guidanceRole = Role::whereRaw('LOWER(name) = ?', ['guidance'])->first();
                $guidanceRecipients = User::where(function($q) use ($guidanceRole) {
                    if ($guidanceRole) $q->whereRaw('FIND_IN_SET(?, role_id)', [$guidanceRole->id]);
                    $q->orWhereRaw('LOWER(position) LIKE ?', ['%guidance%']);
                    $q->orWhereRaw('LOWER(position) LIKE ?', ['%counselor%']);
                })->pluck('email')->filter()->unique();

                foreach ($guidanceRecipients as $email) {
                    try {
                        Mail::send('emails.guidance.consultation_logged', ['consult' => $consult, 'requestor' => (object) $requestor], function ($m) use ($email) {
                            $m->to($email)->subject('New Guidance Consultation Scheduled');
                        });
                        Log::info('Sent guidance consultation email to: '.$email);
                    } catch (\Throwable $e) {
                        Log::error('Failed to send guidance consultation email to '.$email.': '.$e->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Failed to prepare/send guidance consultation emails: '.$e->getMessage());
            }

            DB::commit();
            return redirect()->route('guidance.kiosk')->with('success', 'Guidance consultation scheduled.');
        } catch (\Exception $e) {
            DB::rollBack();
            if ($e instanceof ValidationException) {
                throw $e;
            }
            Log::error('GuidanceKiosk store failed', ['exception' => $e->getMessage()]);
            return back()->with('error', 'Failed to schedule guidance consultation.');
        }
    }
}
