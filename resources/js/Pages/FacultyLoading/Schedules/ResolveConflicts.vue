<template>
  <Head title="Resolve Adjusted Schedule Conflicts" />
  <AdminLayout title="Resolve Adjusted Schedule Conflicts">
    <div class="space-y-5">
      <AppPageHeader
        :title="`Resolve Conflicts — ${formattedEffectiveDate}`"
        subtitle="Manually correct a flagged entry's time before publishing. Regular classroom/faculty assignments are unaffected."
      >
        <template #actions>
          <AppButton variant="secondary" as="link" :href="route('faculty-loading.schedules.day-adjustments.index')">
            <ArrowLeftIcon class="h-4 w-4" /> Back to Adjustments
          </AppButton>
        </template>
      </AppPageHeader>

      <div v-if="loading" class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-500">
        Refreshing preview…
      </div>

      <AppCard v-if="warnings.length" padding="normal">
        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-amber-700">
          <ExclamationTriangleIcon class="h-5 w-5" /> {{ warnings.length }} possible conflict{{ warnings.length > 1 ? 's' : '' }} to review
        </div>
        <ul class="space-y-2">
          <li v-for="(warning, index) in warnings" :key="index" class="rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            {{ warning }}
          </li>
        </ul>
      </AppCard>

      <AppCard v-else padding="normal">
        <div class="flex items-center gap-2 text-sm font-semibold text-emerald-700">
          <CheckCircleIcon class="h-5 w-5" /> No conflicts detected in the current preview.
        </div>
      </AppCard>

      <AppCard v-for="grade in gradesWithEntries" :key="grade.grade_level" padding="none">
        <div class="border-b border-slate-100 px-4 py-3">
          <h3 class="text-sm font-semibold text-slate-700">Grade {{ grade.grade_level }}</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Section</th>
                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Subject</th>
                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Room</th>
                <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Time</th>
                <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <template v-for="section in grade.sections" :key="section.id">
                <tr v-for="entry in section.entries" :key="entry.id" :class="isFlagged(entry) ? 'bg-amber-50/60' : ''">
                  <td class="px-4 py-2 text-sm text-slate-700">{{ section.name }}</td>
                  <td class="px-4 py-2 text-sm text-slate-700">
                    {{ entry.subject?.name ?? entry.title ?? '—' }}
                    <span v-if="entry.manually_adjusted" class="ml-1 inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">Adjusted</span>
                  </td>
                  <td class="px-4 py-2 text-sm text-slate-600">{{ entry.classroom?.name ?? '—' }}</td>
                  <td class="px-4 py-2 text-sm text-slate-600">{{ entry.start_time }}–{{ entry.end_time }}</td>
                  <td class="w-px whitespace-nowrap px-4 py-2 text-right">
                    <div class="flex items-center justify-end gap-1">
                      <AppButton variant="ghost" size="sm" @click="openOverride(entry)">
                        <PencilSquareIcon class="h-4 w-4" /> Adjust time
                      </AppButton>
                      <AppIconButton v-if="entry.manually_adjusted" label="Remove override" variant="danger" @click="removeOverride(entry)">
                        <XMarkIcon class="h-4 w-4" />
                      </AppIconButton>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </AppCard>
    </div>

    <AppModal :show="showOverrideModal" title="Adjust class time" size="sm" @close="showOverrideModal = false">
      <div v-if="editingEntry" class="space-y-4">
        <p class="text-sm text-slate-600">
          {{ editingEntry.subject?.name ?? editingEntry.title }} — currently {{ editingEntry.start_time }}–{{ editingEntry.end_time }}
        </p>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">New start time</label>
            <input v-model="overrideForm.override_start_time" type="time"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">New end time</label>
            <input v-model="overrideForm.override_end_time" type="time"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
        <p v-if="overrideError" class="text-xs text-rose-600">{{ overrideError }}</p>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <AppButton variant="ghost" @click="showOverrideModal = false">Cancel</AppButton>
          <AppButton :loading="savingOverride" @click="saveOverride">Save</AppButton>
        </div>
      </template>
    </AppModal>
  </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppModal from '@/Components/AppModal.vue'
import {
  ArrowLeftIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  PencilSquareIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  adjustment: { type: Object, required: true },
  term: { type: Object, required: true },
  preview: { type: Object, required: true },
})

const currentPreview = ref(props.preview)
const loading = ref(false)
const showOverrideModal = ref(false)
const editingEntry = ref(null)
const overrideForm = ref({ override_start_time: '', override_end_time: '' })
const overrideError = ref('')
const savingOverride = ref(false)

const warnings = computed(() => currentPreview.value.conflict_warnings ?? [])
const gradesWithEntries = computed(() => (currentPreview.value.grades ?? []).filter(grade => grade.sections?.length))
const formattedEffectiveDate = computed(() =>
  new Date(`${props.adjustment.effective_date}T00:00:00`).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }))

function isFlagged(entry) {
  return Boolean(entry.manually_adjusted)
}

function openOverride(entry) {
  editingEntry.value = entry
  overrideForm.value = { override_start_time: entry.start_time, override_end_time: entry.end_time }
  overrideError.value = ''
  showOverrideModal.value = true
}

async function saveOverride() {
  savingOverride.value = true
  overrideError.value = ''
  try {
    const { data } = await axios.post(route('faculty-loading.schedules.day-adjustments.overrides.store', props.adjustment.id), {
      class_schedule_id: editingEntry.value.id,
      override_start_time: overrideForm.value.override_start_time,
      override_end_time: overrideForm.value.override_end_time,
    })
    currentPreview.value = data
    showOverrideModal.value = false
  } catch (error) {
    const errors = error.response?.data?.errors ?? {}
    overrideError.value = errors.override_end_time?.[0] ?? errors.override_start_time?.[0] ?? error.response?.data?.message ?? 'Unable to save this adjustment.'
  } finally {
    savingOverride.value = false
  }
}

async function removeOverride(entry) {
  loading.value = true
  try {
    const { data } = await axios.delete(route('faculty-loading.schedules.day-adjustments.overrides.destroy', [props.adjustment.id, entry.id]))
    currentPreview.value = data
  } finally {
    loading.value = false
  }
}
</script>
