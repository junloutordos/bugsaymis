<?php

namespace App\Http\Controllers\DataManagement;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CampusController extends Controller
{
    private const MAX_LOGO_BYTES = 2 * 1024 * 1024; // 2MB, matches prior file-upload cap

    private const ALLOWED_LOGO_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
    ];

    public function index()
    {
        $campus = Campus::first(); // Get the single campus
        return Inertia::render('DataManagement/Campus/Index', [
            'campus' => $campus,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'year_established' => 'nullable|integer|min:1800|max:' . (date('Y') + 1),
            'address' => 'nullable|string',
            'telephone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'facebook' => 'nullable|string|max:255',
            'logo' => 'nullable|string', // base64 data URI
        ]);

        if ($request->filled('logo')) {
            $data['logo'] = $this->storeLogo($request->input('logo'));
        }

        Campus::create($data);

        return redirect()->route('campuses.index')->with('success', 'Campus created.');
    }

    public function update(Request $request, Campus $campus)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'year_established' => 'nullable|integer|min:1800|max:' . (date('Y') + 1),
            'address' => 'nullable|string',
            'telephone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'facebook' => 'nullable|string|max:255',
            'logo' => 'nullable|string', // base64 data URI
        ]);

        if ($request->filled('logo')) {
            // Delete old logo if exists
            if ($campus->logo && Storage::disk('s3')->exists($campus->logo)) {
                Storage::disk('s3')->delete($campus->logo);
            }
            $data['logo'] = $this->storeLogo($request->input('logo'));
        }

        $campus->update($data);

        return redirect()->route('campuses.index')->with('success', 'Campus updated.');
    }

    public function destroy(Campus $campus)
    {
        $campus->delete();
        return redirect()->route('campuses.index')->with('success', 'Campus deleted.');
    }

    /**
     * Decode a base64 logo data URI, enforce the same mime/size limits the
     * old multipart upload validated, and store it on S3.
     */
    private function storeLogo(string $dataUri): string
    {
        if (! preg_match('/^data:([^;]+);base64,(.+)$/', $dataUri, $m)) {
            throw ValidationException::withMessages(['logo' => 'Invalid logo format.']);
        }

        $mime = strtolower(trim($m[1]));
        if (! isset(self::ALLOWED_LOGO_MIMES[$mime])) {
            throw ValidationException::withMessages(['logo' => 'Logo must be a JPEG, PNG, or GIF image.']);
        }

        $binary = base64_decode($m[2], true);
        if ($binary === false) {
            throw ValidationException::withMessages(['logo' => 'Invalid logo data.']);
        }

        if (strlen($binary) > self::MAX_LOGO_BYTES) {
            throw ValidationException::withMessages(['logo' => 'Logo must be smaller than 2MB.']);
        }

        $path = 'campuses/' . uniqid() . '.' . self::ALLOWED_LOGO_MIMES[$mime];
        Storage::disk('s3')->put($path, $binary);

        return $path;
    }
}
