<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\RubricTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RubricTemplateController extends Controller
{
    /** PUT /learn/rubric-templates/{template} */
    public function update(Request $request, RubricTemplate $template)
    {
        abort_unless($template->user_id === Auth::id(), 403);

        $validated = $request->validate(['name' => 'required|string|max:255']);
        $template->update($validated);

        return back()->with('success', 'Template renamed.');
    }

    /** DELETE /learn/rubric-templates/{template} */
    public function destroy(RubricTemplate $template)
    {
        abort_unless($template->user_id === Auth::id(), 403);

        $template->delete();

        return back()->with('success', 'Template deleted.');
    }
}
