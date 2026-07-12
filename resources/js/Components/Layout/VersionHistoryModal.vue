<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppButton from '@/Components/AppButton.vue'
import AppModal from '@/Components/AppModal.vue'
import { PencilSquareIcon } from '@heroicons/vue/24/outline'

defineProps({
  show:       { type: Boolean, default: false },
  appVersion: { type: Object, required: true },
  isAdmin:   { type: Boolean, default: false },
})

const emit = defineEmits(['close'])

const CHANGE_GROUPS = [
  { key: 'features',     label: 'New Features', dot: 'bg-indigo-400' },
  { key: 'fixes',        label: 'Fixes',        dot: 'bg-slate-400' },
  { key: 'improvements', label: 'Improvements', dot: 'bg-slate-300' },
]

function hasChanges(entry) {
  return CHANGE_GROUPS.some((g) => entry.changes?.[g.key]?.length)
}

const editingEntry = ref(null)
const editForm = useForm({
  remarks: '',
  features: '',
  fixes: '',
  improvements: '',
})

function openEditModal(entry) {
  editingEntry.value = entry
  editForm.clearErrors()
  editForm.remarks = entry.remarks ?? ''
  editForm.features = (entry.changes?.features ?? []).join('\n')
  editForm.fixes = (entry.changes?.fixes ?? []).join('\n')
  editForm.improvements = (entry.changes?.improvements ?? []).join('\n')
}

function toLines(text) {
  return text.split('\n').map((l) => l.trim()).filter(Boolean)
}

function submitEdit() {
  editForm
    .transform((data) => ({
      remarks: data.remarks,
      changes: {
        features: toLines(data.features),
        fixes: toLines(data.fixes),
        improvements: toLines(data.improvements),
      },
    }))
    .patch(route('app-versions.update', editingEntry.value.id), {
      preserveScroll: true,
      onSuccess: () => { editingEntry.value = null },
    })
}
</script>

<template>
  <AppModal
    :show="show"
    title="Version History"
    :subtitle="`Current version: v${appVersion.current}`"
    size="lg"
    @close="emit('close')"
  >
    <div class="space-y-4">
      <div v-for="entry in appVersion.history" :key="entry.version" class="flex gap-4">
        <div class="flex flex-col items-center">
          <div
            class="mt-1.5 h-2.5 w-2.5 rounded-full"
            :class="entry.version === appVersion.current ? 'bg-indigo-500' : 'bg-slate-300'"
          />
          <div class="mt-1 w-px flex-1 bg-slate-200" />
        </div>
        <div class="flex-1 pb-4">
          <div class="mb-1 flex items-center gap-2">
            <span class="text-sm font-semibold text-slate-800">v{{ entry.version }}</span>
            <span
              v-if="entry.version === appVersion.current"
              class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs text-indigo-700"
            >
              Latest
            </span>
            <button
              v-if="isAdmin"
              type="button"
              class="rounded p-0.5 text-slate-400 transition-colors hover:text-indigo-600"
              title="Edit changelog"
              @click="openEditModal(entry)"
            >
              <PencilSquareIcon class="h-4 w-4" />
            </button>
            <span class="ml-auto text-xs text-slate-400">{{ entry.date }}</span>
          </div>

          <template v-if="hasChanges(entry)">
            <div v-for="group in CHANGE_GROUPS" :key="group.key">
              <template v-if="entry.changes?.[group.key]?.length">
                <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                  {{ group.label }}
                </p>
                <ul class="mt-1 space-y-1">
                  <li
                    v-for="(item, i) in entry.changes[group.key]"
                    :key="i"
                    class="flex items-start gap-2 text-sm text-slate-600"
                  >
                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full" :class="group.dot" />
                    <span>{{ item }}</span>
                  </li>
                </ul>
              </template>
            </div>
          </template>
          <p v-else class="text-sm text-slate-600">{{ entry.remarks }}</p>
        </div>
      </div>

      <p v-if="!appVersion.history?.length" class="py-4 text-center text-sm text-slate-400">No history yet.</p>
    </div>
  </AppModal>

  <AppModal
    :show="!!editingEntry"
    :title="`Edit Changelog — v${editingEntry?.version}`"
    size="md"
    @close="editingEntry = null"
  >
    <form id="version-edit-form" class="space-y-4" @submit.prevent="submitEdit">
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">
          Summary <span class="text-red-500">*</span>
        </label>
        <input
          v-model="editForm.remarks"
          type="text"
          required
          placeholder="Short summary of this release..."
          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
        <p v-if="editForm.errors.remarks" class="mt-1 text-xs text-red-500">{{ editForm.errors.remarks }}</p>
      </div>
      <div v-for="group in CHANGE_GROUPS" :key="group.key">
        <label class="mb-1 block text-xs font-medium text-slate-600">
          {{ group.label }} <span class="font-normal text-slate-400">(one per line)</span>
        </label>
        <textarea
          v-model="editForm[group.key]"
          rows="3"
          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
      </div>
      <p class="text-xs text-slate-400">
        Entries are generated automatically from each release — edits here only polish the wording shown to users.
      </p>
    </form>

    <template #footer>
      <AppButton variant="secondary" @click="editingEntry = null">Cancel</AppButton>
      <AppButton type="submit" form="version-edit-form" :loading="editForm.processing">Save</AppButton>
    </template>
  </AppModal>
</template>
