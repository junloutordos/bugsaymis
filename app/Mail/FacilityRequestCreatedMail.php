<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FacilityRequestCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $facilityRequest;
    public $approveUrl;
    public $declineUrl;

    public function __construct($facilityRequest, $approveUrl = null, $declineUrl = null)
    {
        $this->facilityRequest = $facilityRequest;
        $this->approveUrl = $approveUrl;
        $this->declineUrl = $declineUrl;
    }

    public function build()
    {
        // Prepare venue display: if venue contains facility IDs, convert to names
        $venueDisplay = '—';
        try {
            $venue = $this->facilityRequest->venue ?? [];
            if (! is_array($venue) && $venue) {
                $decoded = @json_decode($venue, true);
                $venue = $decoded ?: [$venue];
            }

            if (! empty($venue)) {
                // attempt to resolve facility names if numeric ids present
                $ids = array_filter($venue, fn($v) => is_numeric($v));
                if (count($ids)) {
                    $names = \App\Models\Facility::whereIn('id', $ids)->pluck('name', 'id')->toArray();
                    $lines = [];
                    foreach ($venue as $v) {
                        $lines[] = $names[$v] ?? $v;
                    }
                    $venueDisplay = implode(', ', $lines);
                } else {
                    $venueDisplay = implode(', ', $venue);
                }
            }
        } catch (\Throwable $e) {
            // fallback: keep original value
            $venueDisplay = is_array($this->facilityRequest->venue) ? implode(', ', $this->facilityRequest->venue) : ($this->facilityRequest->venue ?? '—');
        }

        return $this->subject('Facility Request Approval')
                    ->view('emails.facility_request_created')
                    ->with([
                        'request' => $this->facilityRequest,
                        'approveUrl' => $this->approveUrl,
                        'declineUrl' => $this->declineUrl,
                        'venueDisplay' => $venueDisplay,
                    ]);
    }
}
