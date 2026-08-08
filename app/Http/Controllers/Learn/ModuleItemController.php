<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Module;
use App\Models\Learn\ModuleItem;
use App\Models\Learn\Page;
use App\Services\Learn\CourseFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModuleItemController extends Controller
{
    public function __construct(private CourseFileService $files)
    {
    }

    /** POST /learn/modules/{module}/items/page */
    public function storePage(Request $request, Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'video_url' => 'nullable|url',
        ]);

        $page = Page::create($validated);
        $this->attachItem($module, $page);

        return back()->with('success', 'Page added.');
    }

    /** POST /learn/modules/{module}/items/file */
    public function storeFile(Request $request, Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'file_base64' => 'required|string',
        ]);

        $file = $this->files->upload($module->learn_course_id, $validated['title'], $validated['file_base64']);
        $this->attachItem($module, $file);

        return back()->with('success', 'File added.');
    }

    /** PATCH /learn/items/{item}/publish */
    public function togglePublish(ModuleItem $item)
    {
        $user = Auth::user();
        abort_unless($item->module->course->canEdit($user), 403);

        $item->update(['published_at' => $item->isPublished() ? null : now()]);

        return back()->with('success', $item->fresh()->isPublished() ? 'Item published.' : 'Item unpublished.');
    }

    /** PUT /learn/modules/{module}/items/reorder */
    public function reorder(Request $request, Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $validated = $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'integer|exists:learn_module_items,id',
        ]);

        foreach ($validated['item_ids'] as $position => $itemId) {
            ModuleItem::where('id', $itemId)->where('learn_module_id', $module->id)->update(['position' => $position]);
        }

        return back()->with('success', 'Items reordered.');
    }

    /** DELETE /learn/items/{item} */
    public function destroy(ModuleItem $item)
    {
        $user = Auth::user();
        abort_unless($item->module->course->canEdit($user), 403);

        $itemable = $item->itemable;
        $item->delete();
        $itemable?->delete();

        return back()->with('success', 'Item deleted.');
    }

    private function attachItem(Module $module, $itemable): ModuleItem
    {
        $position = (int) ($module->items()->max('position')) + 1;

        return $itemable->moduleItem()->create([
            'learn_module_id' => $module->id,
            'position' => $position,
        ]);
    }
}
