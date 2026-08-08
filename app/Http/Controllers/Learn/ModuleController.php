<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Course;
use App\Models\Learn\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModuleController extends Controller
{
    /** POST /learn/{course}/modules */
    public function store(Request $request, Course $course)
    {
        $user = Auth::user();
        abort_unless($course->canEdit($user), 403);

        $validated = $request->validate(['title' => 'required|string|max:255']);
        $position = (int) ($course->modules()->max('position')) + 1;

        $course->modules()->create([
            'title' => $validated['title'],
            'position' => $position,
        ]);

        return back()->with('success', 'Module added.');
    }

    /** PUT /learn/modules/{module} */
    public function update(Request $request, Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $validated = $request->validate(['title' => 'required|string|max:255']);
        $module->update($validated);

        return back()->with('success', 'Module updated.');
    }

    /** PATCH /learn/modules/{module}/publish */
    public function togglePublish(Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $module->update(['published_at' => $module->isPublished() ? null : now()]);

        return back()->with('success', $module->fresh()->isPublished() ? 'Module published.' : 'Module unpublished.');
    }

    /** PUT /learn/{course}/modules/reorder */
    public function reorder(Request $request, Course $course)
    {
        $user = Auth::user();
        abort_unless($course->canEdit($user), 403);

        $validated = $request->validate([
            'module_ids' => 'required|array',
            'module_ids.*' => 'integer|exists:learn_modules,id',
        ]);

        foreach ($validated['module_ids'] as $position => $moduleId) {
            Module::where('id', $moduleId)->where('learn_course_id', $course->id)->update(['position' => $position]);
        }

        return back()->with('success', 'Modules reordered.');
    }

    /** DELETE /learn/modules/{module} */
    public function destroy(Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $module->delete();

        return back()->with('success', 'Module deleted.');
    }
}
