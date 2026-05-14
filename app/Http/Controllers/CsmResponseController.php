<?php

namespace App\Http\Controllers;

use App\Models\CsmResponse;
use App\Models\ITJobRequest;
use App\Models\ITJRTrackingLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CsmResponseController extends Controller
{
    // Maps the short frontend key → model class + post-CSM config
    private const MODULE_MAP = [
        'it-job-request'   => [
            'class'        => \App\Models\ITJobRequest::class,
            'requester_fk' => 'user_id',
            'post_status'  => 'Request Completed',
        ],
        'facility-request' => [
            'class'        => \App\Models\FacilityRequest::class,
            'requester_fk' => 'requestor_id',
            'post_status'  => 'Completed',
        ],
        'work-request'     => [
            'class'        => \App\Models\WorkRequest::class,
            'requester_fk' => 'requester_id',
            'post_status'  => 'Rated',
        ],
        'vehicle-request'  => [
            'class'        => \App\Models\VehicleRequest::class,
            'requester_fk' => 'requestor_id',
            'post_status'  => 'Completed',
        ],
        'service-request'  => [
            'class'        => \App\Models\ServiceRequest::class,
            'requester_fk' => 'requestor_id',
            'post_status'  => 'Completed',
        ],
        'messengerial-request' => [
            'class'        => \App\Models\MessengerialRequest::class,
            'requester_fk' => 'user_id',
            'post_status'  => 'Rated',
        ],
    ];

    public function store(Request $request)
    {
        $type = $request->input('respondable_type');

        if (! array_key_exists($type, self::MODULE_MAP)) {
            abort(422, 'Invalid request module type.');
        }

        $module = self::MODULE_MAP[$type];

        // Validate CSM payload
        $validated = $request->validate([
            'respondable_id'       => 'required|integer',
            'client_type'          => 'required|in:citizen,business,government',
            'sex'                  => 'nullable|in:male,female',
            'age'                  => 'nullable|integer|min:1|max:120',
            'region_of_residence'  => 'required|string|max:100',
            'date_of_transaction'  => 'required|date',
            'office_availed'       => 'required|string|max:100',
            'service_availed'      => 'required|array|min:1',
            'service_availed_other'=> 'nullable|string|max:255',
            'cc1'                  => 'required|integer|between:1,4',
            'cc2'                  => 'required_if:cc1_conditional,true|nullable|integer|between:1,5',
            'cc3'                  => 'required_if:cc1_conditional,true|nullable|integer|between:1,4',
            'sqd0'                 => 'required|integer|between:1,6',
            'sqd1'                 => 'required|integer|between:1,6',
            'sqd2'                 => 'required|integer|between:1,6',
            'sqd3'                 => 'required|integer|between:1,6',
            'sqd4'                 => 'required|integer|between:1,6',
            'sqd5'                 => 'required|integer|between:1,6',
            'sqd6'                 => 'required|integer|between:1,6',
            'sqd7'                 => 'required|integer|between:1,6',
            'sqd8'                 => 'required|integer|between:1,6',
            'suggestions'          => 'nullable|string|max:2000',
        ]);

        $modelClass = $module['class'];
        $model = $modelClass::findOrFail($validated['respondable_id']);

        // Ensure the current user owns this request
        $requesterFk = $module['requester_fk'];
        if ($model->{$requesterFk} !== Auth::id()) {
            abort(403, 'You are not the requester of this request.');
        }

        // Idempotency: prevent duplicate CSM submissions
        $exists = CsmResponse::where('respondable_type', $modelClass)
            ->where('respondable_id', $model->id)
            ->exists();

        if ($exists) {
            // Heal: CSM exists but status may not have been updated (transient DB issue on first submit)
            if ($model->status !== $module['post_status']) {
                $model->update(['status' => $module['post_status']]);
            }
            return back()->with('success', 'You have already submitted a satisfaction survey for this request.');
        }

        DB::transaction(function () use ($validated, $type, $module, $model, $modelClass) {
            $csm = CsmResponse::create([
                'respondable_type'     => $modelClass,
                'respondable_id'       => $model->id,
                'user_id'              => Auth::id(),
                'client_type'          => $validated['client_type'],
                'sex'                  => $validated['sex'] ?? null,
                'age'                  => $validated['age'] ?? null,
                'region_of_residence'  => $validated['region_of_residence'],
                'date_of_transaction'  => $validated['date_of_transaction'],
                'office_availed'       => $validated['office_availed'],
                'service_availed'      => $validated['service_availed'],
                'service_availed_other'=> $validated['service_availed_other'] ?? null,
                'cc1'                  => $validated['cc1'],
                'cc2'                  => in_array($validated['cc1'], [1, 2, 3]) ? ($validated['cc2'] ?? null) : null,
                'cc3'                  => in_array($validated['cc1'], [1, 2, 3]) ? ($validated['cc3'] ?? null) : null,
                'sqd0'                 => $validated['sqd0'],
                'sqd1'                 => $validated['sqd1'],
                'sqd2'                 => $validated['sqd2'],
                'sqd3'                 => $validated['sqd3'],
                'sqd4'                 => $validated['sqd4'],
                'sqd5'                 => $validated['sqd5'],
                'sqd6'                 => $validated['sqd6'],
                'sqd7'                 => $validated['sqd7'],
                'sqd8'                 => $validated['sqd8'],
                'suggestions'          => $validated['suggestions'] ?? null,
            ]);

            // Mark source request as completed and update rating (backward compat)
            $updates = [
                'status'   => $module['post_status'],
                'rated_at' => now(),
            ];

            // Derive 1–5 star rating from SQD avg for IT Job Request backward compat
            if ($type === 'it-job-request') {
                $updates['rating'] = $csm->derivedRating() ?? 3;

                ITJRTrackingLog::create([
                    'it_job_request_id' => $model->id,
                    'status'            => 'Request Completed',
                    'remarks'           => 'User completed the Client Satisfaction Survey.',
                    'updated_by'        => Auth::id(),
                ]);
            }

            $model->update($updates);
        });

        return back()->with('success', 'Thank you! Your Client Satisfaction Survey has been submitted.');
    }
}
