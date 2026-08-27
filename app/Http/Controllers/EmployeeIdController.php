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

        return Inertia::render('Profile/IdCard', $this->buildCardData($user, route('profile.edit')));
    }

    /**
     * Self-service digital ID — front-first flip-card view (screen only,
     * no print layout). Separate page from the CR-80 print view so the two
     * can evolve independently; shares the same underlying data.
     */
    public function digitalId(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/DigitalId', $this->buildCardData($user, route('profile.edit')));
    }

    /**
     * Assembles the ID card data shared by both the print view (show) and
     * the digital flip-card view (digitalId). $backRoute is the "Back"
     * link target, which differs between self-service and HR-print
     * contexts (see UserController::idCard).
     */
    private function buildCardData(User $user, string $backRoute): array
    {
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

        return [
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
            'back_route' => $backRoute,
            'print_route' => route('profile.id-card'),
            'ocd'        => [
                'name'          => $ocd ? $this->formatDirectorName($ocd) : null,
                'position'      => $ocd?->position ?? 'Campus Director',
                'signature_uri' => $ocd ? $this->signatures->getSignatureDataUri($ocd) : null,
            ],
        ];
    }

    /**
     * Formats the Campus Director's name as "FIRSTNAME M.I. LASTNAME, Post-
     * Nominal Title" for the ID card signature block. Stored name is in
     * filing order ("Lastname, Firstname M.I."); postnominal_title is
     * stored without its leading comma.
     */
    public static function formatDirectorName(User $director): string
    {
        $raw = $director->name ?? '';
        $commaIndex = strpos($raw, ',');

        $reading = $commaIndex === false
            ? $raw
            : trim(substr($raw, $commaIndex + 1)) . ' ' . trim(substr($raw, 0, $commaIndex));

        $reading = mb_strtoupper(trim($reading));

        return filled($director->postnominal_title)
            ? $reading . ', ' . $director->postnominal_title
            : $reading;
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
            self::isMeaningfulValue($info->residential_barangay) ? 'Brgy. ' . $info->residential_barangay : null,
            $info->residential_city,
            $info->residential_province,
        ], fn ($v) => self::isMeaningfulValue($v));

        return $parts ? implode(', ', $parts) : null;
    }

    /**
     * Excludes blank values and common "not applicable" placeholders (N/A,
     * NA, none, n.a.) that employees sometimes type into a PDS sub-field
     * instead of leaving it empty — these should never appear on the
     * printed ID card.
     */
    private static function isMeaningfulValue(?string $value): bool
    {
        if (! filled($value)) {
            return false;
        }

        $normalized = strtolower(str_replace('.', '', trim($value)));

        return ! in_array($normalized, ['n/a', 'na', 'none', 'n a'], true);
    }
}
