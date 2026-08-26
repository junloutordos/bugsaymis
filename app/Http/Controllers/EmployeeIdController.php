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
                ->first([
                    'date_of_birth',
                    'residential_house', 'residential_street', 'residential_subdivision',
                    'residential_barangay', 'residential_city', 'residential_province', 'residential_zip_code',
                ]);
        }

        $profile = $user->employeeProfile;

        $ocd = User::whereHas('roles', fn ($q) => $q->where('name', 'OCD'))->first();

        return Inertia::render('Profile/IdCard', [
            'employee' => [
                'name'              => mb_strtoupper($user->name),
                'position'          => $user->position,
                'employee_no'       => $user->employee_idno_new,
                'division'          => $division?->division_name,
                'office'            => $office?->name,
                'profile_picture'   => $user->profile_picture,
                'is_active'         => $user->status !== 'inactive',
                'date_of_birth'     => $pdsInfo?->date_of_birth ? \Carbon\Carbon::parse($pdsInfo->date_of_birth)->format('F j, Y') : null,
                'residential_address' => $this->formatAddress($pdsInfo),
            ],
            'emergency' => [
                'contact_name'    => $profile?->emergency_contact_name,
                'contact_phone'   => $profile?->emergency_contact_phone,
                'contact_address' => $profile?->emergency_contact_address,
            ],
            'qr_svg'     => $qrSvg,
            'verify_url' => $verifyUrl,
            'back_route' => route('profile.edit'),
            'ocd'        => [
                'name'          => $ocd?->name,
                'position'      => $ocd?->position ?? 'Campus Director',
                'signature_uri' => $ocd ? $this->signatures->getSignatureDataUri($ocd) : null,
            ],
        ]);
    }

    /**
     * Joins the PDS's structured residential fields into a single display
     * string for the ID card. Shared with UserController::idCard.
     */
    public static function formatAddress(?PDSPersonalInfo $info): ?string
    {
        if (! $info) {
            return null;
        }

        $parts = array_filter([
            $info->residential_house,
            $info->residential_street,
            $info->residential_subdivision,
            filled($info->residential_barangay) ? 'Brgy. ' . $info->residential_barangay : null,
            $info->residential_city,
            $info->residential_province,
        ], fn ($v) => filled($v));

        return $parts ? implode(', ', $parts) : null;
    }
}
