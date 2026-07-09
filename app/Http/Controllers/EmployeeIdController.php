<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Office;
use App\Models\Pds;
use App\Models\PDSPersonalInfo;
use App\Models\User;
use App\Services\DigitalSignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EmployeeIdController extends Controller
{
    public function __construct(private readonly DigitalSignatureService $signatures) {}

    /**
     * The employee's own Digital ID card (CR-80 print layout). The QR encodes
     * only the public verify URL with an opaque per-user token — no PII.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();

        // Lazily issue the verification token on first render. forceFill —
        // the token must never be mass-assignable.
        if (empty($user->id_verification_token)) {
            $user->forceFill(['id_verification_token' => Str::random(48)])->save();
        }

        $verifyUrl = route('employee.verify', $user->id_verification_token);

        // QrCode::generate() returns HtmlString — cast before it reaches JSON.
        $qrSvg = (string) QrCode::format('svg')->size(240)->margin(0)->generate($verifyUrl);

        $division = $user->division_id ? Division::find($user->division_id) : null;
        $office   = $user->office_id ? Office::find($user->office_id) : null;

        $pdsInfo = null;
        $pdsId   = Pds::where('user_id', $user->id)->value('id');
        if ($pdsId) {
            $pdsInfo = PDSPersonalInfo::where('pds_id', $pdsId)
                ->first(['blood_type', 'tin_no', 'philhealth_no', 'pagibig_id_no', 'philsys_no']);
        }

        $ocd = User::whereHas('roles', fn ($q) => $q->where('name', 'OCD'))->first();

        return Inertia::render('Profile/IdCard', [
            'employee' => [
                'name'            => mb_strtoupper($user->name),
                'position'        => $user->position,
                'employee_no'     => $user->employee_no,
                'division'        => $division?->division_name,
                'office'          => $office?->name,
                'profile_picture' => $user->profile_picture,
                'is_active'       => $user->status !== 'inactive',
            ],
            'ids' => [
                'blood_type' => $pdsInfo?->blood_type,
                'tin'        => $pdsInfo?->tin_no,
                'philhealth' => $pdsInfo?->philhealth_no,
                'pagibig'    => $pdsInfo?->pagibig_id_no,
                'philsys'    => $pdsInfo?->philsys_no,
            ],
            'qr_svg'     => $qrSvg,
            'verify_url' => $verifyUrl,
            'ocd'        => [
                'name'          => $ocd?->name,
                'position'      => $ocd?->position ?? 'Campus Director',
                'signature_uri' => $ocd ? $this->signatures->getSignatureDataUri($ocd) : null,
            ],
        ]);
    }
}
