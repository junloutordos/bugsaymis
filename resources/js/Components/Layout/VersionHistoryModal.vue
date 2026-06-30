<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppButton from '@/Components/AppButton.vue'
import AppInput from '@/Components/AppInput.vue'
import AppModal from '@/Components/AppModal.vue'

defineProps({
  show:       { type: Boolean, default: false },
  appVersion: { type: Object, required: true },
  isAdmin:   { type: Boolean, default: false },
})

const emit = defineEmits(['close'])

const showAddVersionModal = ref(false)
const versionForm = useForm({
  version:    '',
  date:       new Date().toISOString().slice(0, 10),
  remarks:    '',
  is_current: true,
})

function openAddVersionModal() {
  versionForm.reset()
  versionForm.date = new Date().toISOString().slice(0, 10)
  versionForm.is_current = true
  showAddVersionModal.value = true
}

function submitVersion() {
  versionForm.post(route('app-versions.store'), {
    preserveScroll: true,
    onSuccess: () => {
      showAddVersionModal.value = false
      versionForm.reset()
    },
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
            <span class="ml-auto text-xs text-slate-400">{{ entry.date }}</span>
          </div>
          <p class="text-sm text-slate-600">{{ entry.remarks }}</p>
        </div>
      </div>

      <p v-if="!appVersion.history?.length" class="py-4 text-center text-sm text-slate-400">No history yet.</p>
    </div>

    <template v-if="isAdmin" #footer>
      <AppButton size="sm" @click="openAddVersionModal">Add New Version</AppButton>
    </template>
  </AppModal>

  <AppModal
    :show="showAddVersionModal"
    title="Add New Version"
    size="md"
    @close="showAddVersionModal = false"
  >
    <form id="version-form" class="space-y-4" @submit.prevent="submitVersion">
      <AppInput
        v-model="versionForm.version"
        label="Version"
        placeholder="e.g. 1.2.0"
        :required="true"
        :error="versionForm.errors.version"
      />
      <AppInput
        v-model="versionForm.date"
        label="Release Date"
        type="date"
        :required="true"
        :error="versionForm.errors.date"
      />
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">
          Remarks / Changelog <span class="text-red-500">*</span>
        </label>
        <textarea
          v-model="versionForm.remarks"
          rows="4"
          required
          placeholder="Describe what changed in this version..."
          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
        <p v-if="versionForm.errors.remarks" class="mt-1 text-xs text-red-500">{{ versionForm.errors.remarks }}</p>
      </div>
      <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
        <input v-model="versionForm.is_current" type="checkbox" class="rounded border-slate-300 text-indigo-600" />
        Set as current version
      </label>
    </form>

    <template #footer>
      <AppButton variant="secondary" @click="showAddVersionModal = false">Cancel</AppButton>
      <AppButton type="submit" form="version-form" :loading="versionForm.processing">Save</AppButton>
    </template>
  </AppModal>
</template>
