<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FacilityRequestFADMail extends Mailable
{
    use Queueable, SerializesModels;

    public $facilityRequest;
    public $approveUrl;
    public $declineUrl;

    public function __construct($facilityRequest, $approveUrl, $declineUrl = null)
    {
        $this->facilityRequest = $facilityRequest;
        $this->approveUrl = $approveUrl;
        $this->declineUrl = $declineUrl;
    }

    public function build()
    {
        // reuse venue formatting from existing GSU mail
        $venueDisplay = '—';
        try {
            $venue = $this->facilityRequest->venue ?? [];
            if (! is_array($venue) && $venue) {
                $decoded = @json_decode($venue, true);
                $venue = $decoded ?: [$venue];
            }

            if (! empty($venue)) {
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
            $venueDisplay = is_array($this->facilityRequest->venue) ? implode(', ', $this->facilityRequest->venue) : ($this->facilityRequest->venue ?? '—');
        }

        return $this->subject('FAD Chief Action Required: Facility Request')
            ->view('emails.facility_request_gsu_notification')
            ->with([
                'request' => $this->facilityRequest,
                'approveUrl' => $this->approveUrl,
                'declineUrl' => $this->declineUrl,
                'venueDisplay' => $venueDisplay,
            ]);
    }
}
