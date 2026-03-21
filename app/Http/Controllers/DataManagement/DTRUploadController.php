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
        // Validate before accessing uploaded files
        $request->validate([
            'files'   => 'nullable|array|max:20',
            'files.*' => 'file|mimes:txt,csv,dat,log|max:5120', // 5 MB per file, text-only types
            'file'    => 'nullable|file|mimes:txt,csv,dat,log|max:5120',
        ]);

        // accept multiple uploaded files (files[]), or a single file input 'file'
        $uploadedFiles = [];
        if ($request->hasFile('files')) {
            $uploadedFiles = $request->file('files');
        } elseif ($request->hasFile('file')) {
            $uploadedFiles = [$request->file('file')];
        }

        if (count($uploadedFiles) === 0) {
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

        // merge lines from all uploaded files
        $allLines = [];
        foreach ($uploadedFiles as $f) {
            try {
                $content = $f->get();
                $fileLines = preg_split('/\r\n|\n|\r/', trim($content));
                if (is_array($fileLines)) $allLines = array_merge($allLines, $fileLines);
            } catch (\Exception $e) {
                // skip unreadable file
            }
        }
        $lines = $allLines;
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
                        // If there's a matching data_parameters row for this date, seed the attendance_clean
                        $param = DB::table('data_parameters')->where('date', $d)->first();
                        if ($param) {
                            $upd = [];
                            // Only set when corresponding param field is not null/empty and attendance_clean currently empty
                            if (!empty($param->timein)) {
                                $upd['StartTime1'] = $param->type;
                            }
                            if (!empty($param->breakout)) {
                                $upd['StartTime2'] = $param->type;
                            }
                            if (!empty($param->breakin)) {
                                $upd['StartTime3'] = $param->type;
                            }
                            if (!empty($param->timeout)) {
                                $upd['StartTime4'] = $param->type;
                            }
                            if (!empty($upd)) {
                                // Only update fields that are still empty (we just inserted, so they are empty)
                                $upd['updated_at'] = now();
                                DB::table('attendance_clean')
                                    ->where('BadgeNumber', $badge)
                                    ->where('AttDate', $d)
                                    ->update($upd);
                            }
                        }
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
                elseif (isset($lower['att_type'])) $attType = (string)$lower['att_type'];

                // normalize attType to numeric code strings used in mapping
                $attTypeNorm = null;
                if ($attType !== null) {
                    $at = trim((string)$attType);
                    if (is_numeric($at)) {
                        $attTypeNorm = (string)(int)$at;
                    } else {
                        $c = strtoupper(substr($at, 0, 1));
                        // common device values: I (in), O (out), P (punch)
                        if ($c === 'I') $attTypeNorm = '0';
                        elseif ($c === 'O') $attTypeNorm = '3';
                        elseif ($c === 'P') $attTypeNorm = '0';
                        else $attTypeNorm = null;
                    }
                }

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

                if (!isset($colMap[$attTypeNorm])) {
                    // skip unknown types
                    continue;
                }

                $col = $colMap[$attTypeNorm];

                try {
                    // compute tardiness/undertime based on schedule before updating
                    $tardinessVal = null;
                    $undertimeVal = null;
                    try {
                        $dayName = \Carbon\Carbon::parse($attDate)->format('l'); // Monday, Tuesday, ...

                        // load schedule row for this badge (table name is `schedule`)
                        $schedule = DB::table('schedule')->where('badgeNumber', $badge)->first();
                        if ($schedule) {
                            // map full day name to schedule prefix
                            $lowerDay = strtolower($dayName);
                            $dayMap = [
                                'monday' => 'm',
                                'tuesday' => 't',
                                'wednesday' => 'w',
                                'thursday' => 'th',
                                'friday' => 'f',
                                'saturday' => 'sat',
                                'sunday' => 'sun',
                            ];
                            $prefix = $dayMap[$lowerDay] ?? null;

                            if ($prefix !== null) {
                                $schedCol = null;
                                // use normalized numeric attType code when selecting schedule column
                                switch ($attTypeNorm) {
                                    case '0': $schedCol = $prefix . '_timein'; break;
                                    case '1': $schedCol = $prefix . '_breakout'; break;
                                    case '2': $schedCol = $prefix . '_breakin'; break;
                                    case '3': $schedCol = $prefix . '_timeout'; break;
                                    default: $schedCol = null;
                                }

                                if ($schedCol && isset($schedule->{$schedCol}) && $schedule->{$schedCol}) {
                                    try {
                                        $schedTime = $schedule->{$schedCol};
                                        // parse schedule time
                                        $schedDT = \Carbon\Carbon::parse($attDate . ' ' . $schedTime);
                                        $attDT = \Carbon\Carbon::parse($attDate . ' ' . $attTime);

                                        // compute signed minute difference as float (att - sched)
                                        $diffSeconds = $attDT->getTimestamp() - $schedDT->getTimestamp();
                                        $diffMinutes = $diffSeconds / 60.0;

                                        // attTypeNorm is the normalized numeric code for mapping
                                        // attType 0 and 2: tardiness if attTime > scheduled
                                        if (in_array($attTypeNorm, ['0','2'])) {
                                            if ($diffMinutes > 0) $tardinessVal = $diffMinutes; else $tardinessVal = null;
                                        }
                                        // attType 1 and 3: undertime if attTime < scheduled
                                        if (in_array($attTypeNorm, ['1','3'])) {
                                            if ($diffMinutes < 0) $undertimeVal = abs($diffMinutes); else $undertimeVal = null;
                                        }
                                    } catch (\Exception $e) {
                                        // ignore parse errors, leave tardiness/undertime null
                                    }
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore and continue
                    }

                    // only update the time column if attendance_clean currently has no value for that column
                    $cleanRow = DB::table('attendance_clean')->where('BadgeNumber', $badge)->where('AttDate', $attDate)->first();
                    $updateData = ['updated_at' => now()];
                    if ($cleanRow) {
                        $currentVal = $cleanRow->{$col} ?? null;
                        if ($currentVal === null || $currentVal === '') {
                            $updateData[$col] = $attTime;
                        }
                    } else {
                        // if no clean row found, allow insert branch to set value
                        $updateData[$col] = $attTime;
                    }
                    // map tardiness/undertime columns to attendance_clean (use normalized attType)
                    $tardMap = ['0' => 'startTime1_tardiness', '2' => 'startTime3_tardiness'];
                    $underMap = ['1' => 'startTime2_undertime', '3' => 'startTime4_undertime'];
                    // only attach tardiness/undertime if we're also updating the main time column
                    if (isset($updateData[$col])) {
                        if (isset($tardMap[$attTypeNorm]) && $tardinessVal !== null) $updateData[$tardMap[$attTypeNorm]] = $tardinessVal;
                        if (isset($underMap[$attTypeNorm]) && $undertimeVal !== null) $updateData[$underMap[$attTypeNorm]] = $undertimeVal;
                    }

                    $updated = DB::table('attendance_clean')
                        ->where('BadgeNumber', $badge)
                        ->where('AttDate', $attDate)
                        ->update($updateData);

                    if ($updated) {
                        $updatedClean++;
                    } else {
                        // if update affected 0 rows, delete any existing and insert fresh row
                        // attempt to preserve any existing seeded values (e.g., from data_parameters)
                        $existing = DB::table('attendance_clean')->where('BadgeNumber', $badge)->where('AttDate', $attDate)->first();
                        // delete any existing row to reset
                        DB::table('attendance_clean')->where('BadgeNumber', $badge)->where('AttDate', $attDate)->delete();

                        // default values
                        $s1 = '';
                        $s2 = '';
                        $s3 = '';
                        $s4 = '';
                        $otin = '';
                        $otout = '';
                        $existingT1 = null; $existingT2 = null; $existingT3 = null; $existingT4 = null;

                        if ($existing) {
                            $s1 = $existing->StartTime1 ?? '';
                            $s2 = $existing->StartTime2 ?? '';
                            $s3 = $existing->StartTime3 ?? '';
                            $s4 = $existing->StartTime4 ?? '';
                            $otin = $existing->OTin ?? '';
                            $otout = $existing->OTout ?? '';
                            $existingT1 = $existing->startTime1_tardiness ?? null;
                            $existingT2 = $existing->startTime2_undertime ?? null;
                            $existingT3 = $existing->startTime3_tardiness ?? null;
                            $existingT4 = $existing->startTime4_undertime ?? null;
                        } else {
                            // fallback: check data_parameters for this date to seed values
                            $param = DB::table('data_parameters')->where('date', $attDate)->first();
                            if ($param) {
                                if (!empty($param->timein)) $s1 = $param->type;
                                if (!empty($param->breakout)) $s2 = $param->type;
                                if (!empty($param->breakin)) $s3 = $param->type;
                                if (!empty($param->timeout)) $s4 = $param->type;
                            }
                        }

                        // set the column for this attendance event only if the existing value is empty
                        $didSetMain = false;
                        if ($col === 'StartTime1' && ($s1 === null || $s1 === '')) { $s1 = $attTime; $didSetMain = true; }
                        if ($col === 'StartTime2' && ($s2 === null || $s2 === '')) { $s2 = $attTime; $didSetMain = true; }
                        if ($col === 'StartTime3' && ($s3 === null || $s3 === '')) { $s3 = $attTime; $didSetMain = true; }
                        if ($col === 'StartTime4' && ($s4 === null || $s4 === '')) { $s4 = $attTime; $didSetMain = true; }
                        if ($col === 'OTin' && ($otin === null || $otin === '')) { $otin = $attTime; $didSetMain = true; }
                        if ($col === 'OTout' && ($otout === null || $otout === '')) { $otout = $attTime; $didSetMain = true; }

                        $insertData = [
                            'BadgeNumber' => $badge,
                            'AttDate' => $attDate,
                            'StartTime1' => $s1,
                            'StartTime2' => $s2,
                            'StartTime3' => $s3,
                            'StartTime4' => $s4,
                            'OTin' => $otin,
                            'OTout' => $otout,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        // preserve existing computed minute columns when present
                        if ($existingT1 !== null) $insertData['startTime1_tardiness'] = $existingT1;
                        if ($existingT2 !== null) $insertData['startTime2_undertime'] = $existingT2;
                        if ($existingT3 !== null) $insertData['startTime3_tardiness'] = $existingT3;
                        if ($existingT4 !== null) $insertData['startTime4_undertime'] = $existingT4;

                        // include newly computed tardiness/undertime values when we actually set the main column
                        if ($didSetMain) {
                            if (isset($tardMap[$attTypeNorm]) && isset($tardinessVal) && $tardinessVal !== null) {
                                $insertData[$tardMap[$attTypeNorm]] = $tardinessVal;
                            }
                            if (isset($underMap[$attTypeNorm]) && isset($undertimeVal) && $undertimeVal !== null) {
                                $insertData[$underMap[$attTypeNorm]] = $undertimeVal;
                            }
                        }

                        DB::table('attendance_clean')->insert($insertData);
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
