<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use Illuminate\Support\Facades\URL;

class WorkRequestCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $workRequest;
    public $approveUrl;
    public $declineUrl;
    public $skilledUsers;
    public $approveWithAssign;
    public $gsuId;

    public function __construct($workRequest, $approveUrl = null, $declineUrl = null, $gsuId = null)
    {
        $this->workRequest = $workRequest;
        $this->approveUrl = $approveUrl;
        $this->declineUrl = $declineUrl;
        $this->gsuId = $gsuId;
        // load skilled users for assignment dropdown in the email
        $this->skilledUsers = User::select('id','name','position')
                                ->where('position', 'like', '%Skilled%')
                                ->get();

        // prepare per-user signed approve links that include assigned_user_id
        $this->approveWithAssign = [];
        foreach ($this->skilledUsers as $u) {
            // if no gsu id is supplied we cannot create a signed link for that recipient
            if ($this->gsuId) {
                $this->approveWithAssign[$u->id] = URL::signedRoute('work-requests.gsu.approve', [
                    'workRequest' => $this->workRequest->id,
                    'gsu' => $this->gsuId,
                    'assigned_user_id' => $u->id,
                ], now()->addDays(7));
            } else {
                $this->approveWithAssign[$u->id] = null;
            }
        }
    }

    public function build()
    {
        return $this->subject('Work Request Approval')
                    ->view('emails.work_request_created')
                    ->with([
                        'request' => $this->workRequest,
                        'approveUrl' => $this->approveUrl,
                        'declineUrl' => $this->declineUrl,
                        'skilledUsers' => $this->skilledUsers,
                        'approveWithAssign' => $this->approveWithAssign,
                    ]);
    }
}
