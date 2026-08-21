<?php

namespace App\Http\Controllers\Sos;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Sos\SosEscalationTier;
use App\Models\Sos\SosExternalContact;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SosSettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Sos/Settings', [
            'tiers'            => SosEscalationTier::with('role', 'users')->orderBy('alert_type')->orderBy('order')->get(),
            'externalContacts' => SosExternalContact::orderBy('name')->get(),
            'roles'            => Role::select('id', 'name')->get(),
            'users'            => User::employees()
                ->with('employeeProfile:id,user_id,mobile_number')
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeTier(Request $request)
    {
        $data = $request->validate([
            'alert_type'      => 'required|in:medical,security,fire_disaster,general',
            'order'           => 'required|integer|min:1',
            'role_id'         => 'nullable|exists:roles,id',
            'timeout_minutes' => 'nullable|integer|min:1',
            'channels'        => 'required|array|min:1',
            'channels.*'      => 'in:in_app,sms,email',
            'notify_external' => 'boolean',
            'user_ids'        => 'array',
            'user_ids.*'      => 'exists:users,id',
        ]);

        $tier = SosEscalationTier::create(collect($data)->except('user_ids')->all());
        $tier->users()->sync($data['user_ids'] ?? []);

        return back()->with('success', 'Escalation tier saved.');
    }

    public function updateTier(Request $request, SosEscalationTier $tier)
    {
        $data = $request->validate([
            'order'           => 'required|integer|min:1',
            'role_id'         => 'nullable|exists:roles,id',
            'timeout_minutes' => 'nullable|integer|min:1',
            'channels'        => 'required|array|min:1',
            'channels.*'      => 'in:in_app,sms,email',
            'notify_external' => 'boolean',
            'user_ids'        => 'array',
            'user_ids.*'      => 'exists:users,id',
        ]);

        $tier->update(collect($data)->except('user_ids')->all());
        $tier->users()->sync($data['user_ids'] ?? []);

        return back()->with('success', 'Escalation tier updated.');
    }

    public function destroyTier(SosEscalationTier $tier)
    {
        $tier->delete();
        return back()->with('success', 'Escalation tier removed.');
    }

    public function storeExternalContact(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'org'           => 'nullable|string|max:255',
            'phone'         => 'nullable|string|max:32',
            'email'         => 'nullable|email|max:255',
            'alert_types'   => 'required|array|min:1',
            'alert_types.*' => 'in:medical,security,fire_disaster,general',
            'channel'       => 'required|in:sms,email,both',
            'active'        => 'boolean',
        ]);

        SosExternalContact::create($data);
        return back()->with('success', 'External contact added.');
    }

    public function updateExternalContact(Request $request, SosExternalContact $contact)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'org'           => 'nullable|string|max:255',
            'phone'         => 'nullable|string|max:32',
            'email'         => 'nullable|email|max:255',
            'alert_types'   => 'required|array|min:1',
            'alert_types.*' => 'in:medical,security,fire_disaster,general',
            'channel'       => 'required|in:sms,email,both',
            'active'        => 'boolean',
        ]);

        $contact->update($data);
        return back()->with('success', 'External contact updated.');
    }

    public function destroyExternalContact(SosExternalContact $contact)
    {
        $contact->delete();
        return back()->with('success', 'External contact removed.');
    }

    public function updateResponderMobile(Request $request, User $user)
    {
        $data = $request->validate(['mobile_number' => 'nullable|string|max:20']);

        $profile = $user->employeeProfile;
        if (! $profile) {
            return back()->with('error', "{$user->name} has no employee profile yet — mobile number can't be set.");
        }

        $profile->update(['mobile_number' => $data['mobile_number']]);
        return back()->with('success', 'Mobile number updated.');
    }
}
