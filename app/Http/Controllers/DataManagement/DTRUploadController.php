<?php

namespace App\Http\Controllers\DataManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class DTRUploadController extends Controller
{
    public function store(Request $request)
    {
        $file = $request->file('file');
        if (!$file) {
            return response()->json(['message' => 'No file uploaded'], 422);
        }

        // optional date range filtering from the form
        $startInput = $request->input('start_date');
        $endInput = $request->input('end_date');
        $startDateObj = null;
        $endDateObj = null;
        try {
            if ($startInput) $startDateObj = Carbon::parse($startInput)->startOfDay();
            if ($endInput) $endDateObj = Carbon::parse($endInput)->endOfDay();
            if ($startDateObj && $endDateObj && $startDateObj->gt($endDateObj)) {
                return response()->json(['message' => 'Start date must be before or equal to end date'], 422);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid date format for start/end date'], 422);
        }

        $content = $file->get();
        $lines = preg_split('/\r\n|\n|\r/', trim($content));
        $insertRows = [];
        $errors = [];
        $skippedByCategory = 0;

        $selectedCategory = $request->input('category');

        foreach ($lines as $lnIndex => $line) {
            $line = trim($line);
            if ($line === '') continue;

            // Try CSV first
            if (strpos($line, ',') !== false) {
                $parts = array_map('trim', str_getcsv($line));
            } else {
                $parts = preg_split('/\s+/', $line);
            }

            $badge = null;
            $date = null;
            $time = null;
            $type = null;

            if (count($parts) >= 5) {
                // prefer column 5 for attType (index 4)
                $badge = $parts[0];
                $date = $parts[1];
                $time = $parts[2];
                $type = $parts[4];
            } elseif (count($parts) === 4) {
                // fallback to column 4 if no column 5
                $badge = $parts[0];
                $date = $parts[1];
                $time = $parts[2];
                $type = $parts[3];
            } elseif (count($parts) === 3) {
                $badge = $parts[0];
                $date = $parts[1];
                $time = $parts[2];
            } else {
                // fallback to regex extraction
                if (preg_match('/(\d{3,})/', $line, $m)) {
                    $badge = $m[1];
                }
                if (preg_match('/(\d{4}-\d{2}-\d{2}|\d{8}|\d{2}\/\d{2}\/\d{4})/', $line, $m2)) {
                    $date = $m2[1];
                }
                if (preg_match('/(\d{2}:\d{2}(?::\d{2})?|\d{6})/', $line, $m3)) {
                    $time = $m3[1];
                }
                if (preg_match('/\b(IN|OUT|I|O|P|0|1|2)\b/i', $line, $m4)) {
                    $type = strtoupper($m4[1]);
                }
            }

            // Normalize values
            $badge = $badge ? preg_replace('/[^0-9]/', '', $badge) : null;

            // Normalize date
            $dateFormatted = null;
            if ($date) {
                $date = trim($date);
                try {
                    if (preg_match('/^\d{8}$/', $date)) {
                        $dateFormatted = Carbon::createFromFormat('Ymd', $date)->format('Y-m-d');
                    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                        $dateFormatted = Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d');
                    } elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
                        $dateFormatted = Carbon::createFromFormat('m/d/Y', $date)->format('Y-m-d');
                    } elseif (preg_match('/^\d{6}$/', $date)) {
                        // HHMMSS as date fallback (unlikely)
                        $dateFormatted = null;
                    } else {
                        // try generic parse
                        $dateFormatted = Carbon::parse($date)->format('Y-m-d');
                    }
                } catch (\Exception $e) {
                    $dateFormatted = null;
                }
            }

            // Normalize time
            $timeFormatted = null;
            if ($time) {
                $time = trim($time);
                try {
                    if (preg_match('/^\d{6}$/', $time)) {
                        $timeFormatted = Carbon::createFromFormat('His', $time)->format('H:i:s');
                    } else {
                        $timeFormatted = Carbon::parse($time)->format('H:i:s');
                    }
                } catch (\Exception $e) {
                    $timeFormatted = null;
                }
            }

            if (!$badge || !$dateFormatted || !$timeFormatted) {
                $errors[] = ['line' => $lnIndex + 1, 'content' => $line];
                continue;
            }

            // preserve literal '0' value (PHP treats "0" as falsy),
            // otherwise use first character uppercased, default to 'I'
            if ($type !== null && $type !== '') {
                $type = strtoupper(substr($type, 0, 1));
            } else {
                $type = 'I';
            }

            // check user category if provided
            if ($selectedCategory && strtolower($selectedCategory) !== 'all category' && strtolower($selectedCategory) !== 'all') {
                $empCategory = DB::table('users')->where('badge_id', $badge)->value('emp_category');
                if (!$empCategory) {
                    $errors[] = ['line' => $lnIndex + 1, 'content' => $line, 'reason' => 'user_not_found'];
                    continue;
                }
                if ($empCategory !== $selectedCategory) {
                    $skippedByCategory++;
                    continue;
                }
            }

            // apply date range filter if provided
            if ($startDateObj || $endDateObj) {
                try {
                    $rowDate = Carbon::createFromFormat('Y-m-d', $dateFormatted)->startOfDay();
                } catch (\Exception $e) {
                    $errors[] = ['line' => $lnIndex + 1, 'content' => $line];
                    continue;
                }
                if ($startDateObj && $rowDate->lt($startDateObj)) {
                    continue;
                }
                if ($endDateObj && $rowDate->gt($endDateObj)) {
                    continue;
                }
            }

            $insertRows[] = [
                'BadgeNumber' => $badge,
                'AttDate' => $dateFormatted,
                'AttTime' => $timeFormatted,
                'attType' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (count($insertRows) === 0) {
            return response()->json(['inserted' => 0, 'errors' => $errors, 'skipped_by_category' => $skippedByCategory], 422);
        }

        DB::beginTransaction();
        try {
            // insert in chunks to avoid large single insert
            $chunks = array_chunk($insertRows, 500);
            foreach ($chunks as $c) {
                DB::table('attendance')->insert($c);
            }
            DB::commit();
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(['message' => 'Insert failed', 'error' => $ex->getMessage()], 500);
        }

        // Post-process: build/seed attendance_clean and map times
        $badges = array_values(array_unique(array_map(function ($r) { return $r['BadgeNumber']; }, $insertRows)));

        // determine date range to populate attendance_clean
        if (!$startDateObj || !$endDateObj) {
            // derive from inserted rows
            $dates = array_values(array_unique(array_map(function ($r) { return $r['AttDate']; }, $insertRows)));
            sort($dates);
            $startDateObj = isset($dates[0]) ? Carbon::createFromFormat('Y-m-d', $dates[0])->startOfDay() : $startDateObj;
            $endDateObj = isset($dates[count($dates)-1]) ? Carbon::createFromFormat('Y-m-d', $dates[count($dates)-1])->endOfDay() : $endDateObj;
        }

        $createdClean = 0;
        $updatedClean = 0;
        $processedAttendance = 0;
        $cleanErrors = [];

        try {
            // create attendance_clean rows for every badge x date in range
            if ($startDateObj && $endDateObj) {
                foreach ($badges as $badge) {
                    $cursor = $startDateObj->copy();
                    while ($cursor->lte($endDateObj)) {
                        $d = $cursor->format('Y-m-d');
                        // delete any existing row for this badge/date to avoid duplicates
                        DB::table('attendance_clean')
                            ->where('BadgeNumber', $badge)
                            ->where('AttDate', $d)
                            ->delete();

                        // insert with empty strings to avoid NOT NULL constraint issues
                        DB::table('attendance_clean')->insert([
                            'BadgeNumber' => $badge,
                            'AttDate' => $d,
                            'StartTime1' => '',
                            'StartTime2' => '',
                            'StartTime3' => '',
                            'StartTime4' => '',
                            'OTin' => '',
                            'OTout' => '',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $createdClean++;
                        $cursor->addDay();
                    }
                }
            }

            // now fetch relevant attendance rows to map into clean table
            $query = DB::table('attendance')->whereIn('BadgeNumber', $badges);
            if ($startDateObj && $endDateObj) {
                $query->whereBetween('AttDate', [$startDateObj->format('Y-m-d'), $endDateObj->format('Y-m-d')]);
            }
            $attRows = $query->get();

            foreach ($attRows as $att) {
                $processedAttendance++;
                // normalize column names to lowercase keys to avoid undefined property errors
                $vars = (array) $att;
                $lower = [];
                foreach ($vars as $k => $v) {
                    $lower[strtolower($k)] = $v;
                }

                $badge = $lower['badgenumber'] ?? $lower['badge'] ?? $lower['badge_id'] ?? null;
                $attDate = $lower['attdate'] ?? $lower['att_date'] ?? null;
                $attTime = $lower['atttime'] ?? $lower['atttime'] ?? $lower['att_time'] ?? null;
                $attType = null;
                if (isset($lower['atttype'])) $attType = (string)$lower['atttype'];
                elseif (isset($lower['atttype'])) $attType = (string)$lower['atttype'];
                elseif (isset($lower['att_type'])) $attType = (string)$lower['att_type'];

                if (!$badge || !$attDate || !$attTime || $attType === null) {
                    // skip malformed rows
                    continue;
                }

                // map attType to attendance_clean column
                $colMap = [
                    '0' => 'StartTime1',
                    '1' => 'StartTime2',
                    '2' => 'StartTime3',
                    '3' => 'StartTime4',
                    '4' => 'OTin',
                    '5' => 'OTout',
                ];

                if (!isset($colMap[$attType])) {
                    // skip unknown types
                    continue;
                }

                $col = $colMap[$attType];

                try {
                    $updated = DB::table('attendance_clean')
                        ->where('BadgeNumber', $badge)
                        ->where('AttDate', $attDate)
                        ->update([$col => $attTime, 'updated_at' => now()]);

                    if ($updated) {
                        $updatedClean++;
                    } else {
                        // if update affected 0 rows, delete any existing and insert fresh row
                        DB::table('attendance_clean')
                            ->where('BadgeNumber', $badge)
                            ->where('AttDate', $attDate)
                            ->delete();

                        DB::table('attendance_clean')->insert([
                            'BadgeNumber' => $badge,
                            'AttDate' => $attDate,
                            'StartTime1' => ($col === 'StartTime1') ? $attTime : '',
                            'StartTime2' => ($col === 'StartTime2') ? $attTime : '',
                            'StartTime3' => ($col === 'StartTime3') ? $attTime : '',
                            'StartTime4' => ($col === 'StartTime4') ? $attTime : '',
                            'OTin' => ($col === 'OTin') ? $attTime : '',
                            'OTout' => ($col === 'OTout') ? $attTime : '',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $createdClean++;
                    }
                } catch (\Exception $e) {
                    $cleanErrors[] = ['badge' => $badge, 'attDate' => $attDate, 'exception' => $e->getMessage()];
                    // continue processing others
                }
            }
        } catch (\Exception $e) {
            // log and return partial success
            return response()->json([
                'inserted' => count($insertRows),
                'errors' => $errors,
                'skipped_by_category' => $skippedByCategory,
                'clean_created' => $createdClean,
                'clean_updated' => $updatedClean,
                'processed_attendance' => $processedAttendance,
                'clean_errors' => $cleanErrors,
                'message' => 'Error processing attendance_clean: ' . $e->getMessage()
            ], 500);
        }

            // truncate raw attendance table now that processing is complete
            try {
                DB::table('attendance')->truncate();
            } catch (\Exception $e) {
                // non-fatal: include error info in response
                $cleanErrors[] = ['truncate_attendance_error' => $e->getMessage()];
            }

        return response()->json([
            'inserted' => count($insertRows),
            'errors' => $errors,
            'skipped_by_category' => $skippedByCategory,
            'clean_created' => $createdClean,
            'clean_updated' => $updatedClean,
            'processed_attendance' => $processedAttendance,
            'clean_errors' => $cleanErrors
        ]);
    }
}
