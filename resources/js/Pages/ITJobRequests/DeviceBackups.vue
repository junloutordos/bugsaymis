<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppModal from '@/Components/AppModal.vue'
import {
  CloudArrowUpIcon,
  FolderOpenIcon,
  PlayIcon,
  PencilSquareIcon,
  TrashIcon,
  MagnifyingGlassIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({ devices: Array })

const page = usePage()
const canManage = computed(() =>
  (page.props.auth?.user?.permissions || []).includes('it.equipment.manage')
)

const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']

// ── Filters + pagination ────────────────────────────────────────────────────
const search = ref('')
const scheduleFilter = ref('all') // all | scheduled | unscheduled

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return props.devices.filter((d) => {
    if (scheduleFilter.value === 'scheduled' && !d.schedule) return false
    if (scheduleFilter.value === 'unscheduled' && d.schedule) return false
    if (!q) return true
    return [d.hostname, d.equipment_label, String(d.equipment_id)]
      .filter(Boolean)
      .some((v) => v.toLowerCase().includes(q))
  })
})

const PER_PAGE = 15
const currentPage = ref(1)
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

// ── Formatting ──────────────────────────────────────────────────────────────
function formatBytes(bytes) {
  if (!bytes) return '0 MB'
  const mb = bytes / (1024 * 1024)
  if (mb < 1024) return `${mb.toLocaleString('en-PH', { maximumFractionDigits: 1 })} MB`
  return `${(mb / 1024).toLocaleString('en-PH', { maximumFractionDigits: 2 })} GB`
}

function formatDateTime(value) {
  if (!value) return '—'
  const d = new Date(value)
  return (
    d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) +
    ' ' +
    d.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' })
  )
}

function scheduleLabel(schedule) {
  if (!schedule) return 'Not scheduled'
  const hour = `${schedule.preferred_hour}:00`
  return schedule.frequency === 'weekly'
    ? `Weekly · ${DAY_NAMES[schedule.weekly_day ?? 0]} ${hour}`
    : `Daily · ${hour}`
}

const RUN_BADGES = {
  completed: 'bg-emerald-50 text-emerald-700',
  completed_with_errors: 'bg-amber-50 text-amber-700',
  failed: 'bg-red-50 text-red-700',
  running: 'bg-indigo-50 text-indigo-700',
  dispatched: 'bg-sky-50 text-sky-700',
}

const RUN_LABELS = {
  completed: 'Completed',
  completed_with_errors: 'Completed with errors',
  failed: 'Failed',
  running: 'Running',
  dispatched: 'Dispatched',
}

// ── Add / edit modal ────────────────────────────────────────────────────────
const showModal = ref(false)
const editingDevice = ref(null) // device whose schedule is being edited; null = adding

const form = useForm({
  device_id: null,
  frequency: 'daily',
  weekly_day: 1,
  preferred_hour: 12,
  include_downloads: false,
  max_file_mb: 100,
  enabled: true,
})

const unscheduledDevices = computed(() => props.devices.filter((d) => !d.schedule))

function openAdd() {
  editingDevice.value = null
  form.reset()
  form.clearErrors()
  showModal.value = true
}

function openEdit(device) {
  editingDevice.value = device
  form.clearErrors()
  form.device_id = device.id
  form.frequency = device.schedule.frequency
  form.weekly_day = device.schedule.weekly_day ?? 1
  form.preferred_hour = device.schedule.preferred_hour
  form.include_downloads = device.schedule.include_downloads
  form.max_file_mb = device.schedule.max_file_mb
  form.enabled = device.schedule.enabled
  showModal.value = true
}

function submitSchedule() {
  const options = { preserveScroll: true, onSuccess: () => (showModal.value = false) }
  if (editingDevice.value) {
    form.put(route('atlas-sentinel.backups.update', editingDevice.value.schedule.id), options)
  } else {
    form.post(route('atlas-sentinel.backups.store'), options)
  }
}

// ── Row actions ─────────────────────────────────────────────────────────────
function runNow(device) {
  router.post(route('atlas-sentinel.backups.run-now', device.schedule.id), {}, { preserveScroll: true })
}

function toggleEnabled(device) {
  router.put(
    route('atlas-sentinel.backups.update', device.schedule.id),
    { ...device.schedule, enabled: !device.schedule.enabled },
    { preserveScroll: true }
  )
}

const removingDevice = ref(null)

function confirmRemove() {
  router.delete(route('atlas-sentinel.backups.destroy', removingDevice.value.schedule.id), {
    preserveScroll: true,
    onSuccess: () => (removingDevice.value = null),
  })
}

// ── Last-run errors drill-down ──────────────────────────────────────────────
const errorsDevice = ref(null)
</script>

<template>
  <Head title="Device Backups" />
  <AdminLayout title="Device Backups">
    <div class="space-y-5">
      <!-- Header -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="flex items-center gap-2 text-lg font-semibold text-slate-800">
            <CloudArrowUpIcon class="h-6 w-6 text-indigo-600" />
            Atlas Sentinel — Document Backups
          </h1>
          <p class="mt-1 text-sm text-slate-500">
            Office documents (Word, PowerPoint, Excel, PDF) from scheduled devices are backed up to
            Google Drive, one folder per device. Only devices added to the schedule sync.
          </p>
        </div>
        <button
          v-if="canManage"
          @click="openAdd"
          class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium"
        >
          Add device to schedule
        </button>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap items-center gap-3">
        <div class="relative">
          <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input
            v-model="search"
            @input="currentPage = 1"
            type="text"
            placeholder="Search hostname or equipment…"
            class="rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-72"
          />
        </div>
        <select
          v-model="scheduleFilter"
          @change="currentPage = 1"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option value="all">All devices</option>
          <option value="scheduled">On schedule</option>
          <option value="unscheduled">Not scheduled</option>
        </select>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Device</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Schedule</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Last backup</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Backed up</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="device in displayed" :key="device.id" class="hover:bg-slate-50">
              <td class="px-4 py-3">
                <div class="font-medium text-slate-800">{{ device.hostname || `Device #${device.id}` }}</div>
                <div class="text-xs text-slate-500">
                  #{{ device.equipment_id }}<span v-if="device.equipment_label"> · {{ device.equipment_label }}</span>
                </div>
              </td>
              <td class="px-4 py-3">
                <span
                  v-if="device.schedule"
                  :class="[
                    'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                    device.schedule.enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500',
                  ]"
                >
                  {{ device.schedule.enabled ? scheduleLabel(device.schedule) : 'Paused' }}
                </span>
                <span v-else class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">
                  Not scheduled
                </span>
                <div v-if="device.schedule?.run_requested_at" class="mt-1 text-xs text-sky-600">
                  Manual run queued
                </div>
              </td>
              <td class="px-4 py-3">
                <template v-if="device.last_run">
                  <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', RUN_BADGES[device.last_run.status] || 'bg-slate-100 text-slate-600']">
                    {{ RUN_LABELS[device.last_run.status] || device.last_run.status }}
                  </span>
                  <div class="mt-1 text-xs text-slate-500">
                    {{ formatDateTime(device.last_run.finished_at || device.last_run.dispatched_at) }}
                    <template v-if="device.last_run.files_uploaded"> · {{ device.last_run.files_uploaded }} uploaded</template>
                  </div>
                  <button
                    v-if="device.last_run.errors?.length"
                    @click="errorsDevice = device"
                    class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-amber-600 hover:text-amber-700"
                  >
                    <ExclamationTriangleIcon class="h-3.5 w-3.5" />
                    {{ device.last_run.errors.length }} error(s)
                  </button>
                </template>
                <span v-else class="text-xs text-slate-400">Never</span>
              </td>
              <td class="px-4 py-3">
                <div class="text-slate-700">{{ device.backed_up_files.toLocaleString('en-PH') }} files</div>
                <div class="text-xs text-slate-500">{{ formatBytes(device.backed_up_bytes) }}</div>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-1.5">
                  <a
                    v-if="device.drive_folder_url"
                    :href="device.drive_folder_url"
                    target="_blank"
                    rel="noopener"
                    title="Open Drive folder"
                    class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-indigo-600"
                  >
                    <FolderOpenIcon class="h-4 w-4" />
                  </a>
                  <template v-if="canManage && device.schedule">
                    <button
                      v-if="device.schedule.enabled && !device.schedule.run_requested_at"
                      @click="runNow(device)"
                      title="Back up now"
                      class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-indigo-600"
                    >
                      <PlayIcon class="h-4 w-4" />
                    </button>
                    <button
                      @click="openEdit(device)"
                      title="Edit schedule"
                      class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-indigo-600"
                    >
                      <PencilSquareIcon class="h-4 w-4" />
                    </button>
                    <button
                      @click="toggleEnabled(device)"
                      :title="device.schedule.enabled ? 'Pause backups' : 'Resume backups'"
                      class="rounded-lg px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-100"
                    >
                      {{ device.schedule.enabled ? 'Pause' : 'Resume' }}
                    </button>
                    <button
                      @click="removingDevice = device"
                      title="Remove from schedule"
                      class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                    >
                      <TrashIcon class="h-4 w-4" />
                    </button>
                  </template>
                </div>
              </td>
            </tr>
            <tr v-if="displayed.length === 0">
              <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-400">
                No devices match your filters.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex items-center justify-between text-sm text-slate-500">
        <span>Page {{ currentPage }} of {{ totalPages }} · {{ filtered.length }} device(s)</span>
        <div class="flex gap-2">
          <button
            :disabled="currentPage === 1"
            @click="currentPage--"
            class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 disabled:opacity-40"
          >
            Previous
          </button>
          <button
            :disabled="currentPage === totalPages"
            @click="currentPage++"
            class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 disabled:opacity-40"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Add / edit schedule modal -->
    <AppModal
      :show="showModal"
      :title="editingDevice ? `Edit schedule — ${editingDevice.hostname || '#' + editingDevice.id}` : 'Add device to backup schedule'"
      size="lg"
      @close="showModal = false"
    >
      <form class="space-y-4" @submit.prevent="submitSchedule">
        <div v-if="!editingDevice">
          <label class="mb-1 block text-sm font-medium text-slate-700">Device</label>
          <select
            v-model="form.device_id"
            required
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
          >
            <option :value="null" disabled>Select an enrolled device…</option>
            <option v-for="d in unscheduledDevices" :key="d.id" :value="d.id">
              {{ d.hostname || `Device #${d.id}` }} — #{{ d.equipment_id }}{{ d.equipment_label ? ` · ${d.equipment_label}` : '' }}
            </option>
          </select>
          <p v-if="form.errors.device_id" class="mt-1 text-xs text-red-600">{{ form.errors.device_id }}</p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Frequency</label>
            <select
              v-model="form.frequency"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
            >
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
            </select>
          </div>
          <div v-if="form.frequency === 'weekly'">
            <label class="mb-1 block text-sm font-medium text-slate-700">Day of week</label>
            <select
              v-model.number="form.weekly_day"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
            >
              <option v-for="(name, i) in DAY_NAMES" :key="i" :value="i">{{ name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Start after (hour)</label>
            <select
              v-model.number="form.preferred_hour"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
            >
              <option v-for="h in 24" :key="h - 1" :value="h - 1">{{ h - 1 }}:00</option>
            </select>
            <p class="mt-1 text-xs text-slate-400">The run starts on the device's first check-in after this hour.</p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Max file size (MB)</label>
            <input
              v-model.number="form.max_file_mb"
              type="number"
              min="1"
              max="2048"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
            />
            <p v-if="form.errors.max_file_mb" class="mt-1 text-xs text-red-600">{{ form.errors.max_file_mb }}</p>
          </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-700">
          <input v-model="form.include_downloads" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
          Also back up the Downloads folder
        </label>
        <p class="text-xs text-slate-400">
          Documents and Desktop of every user profile are always included. Collected types:
          doc, docx, ppt, pptx, xls, xlsx, pdf.
        </p>

        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
          <button
            type="button"
            @click="showModal = false"
            class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="form.processing"
            class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-50"
          >
            {{ editingDevice ? 'Save changes' : 'Add to schedule' }}
          </button>
        </div>
      </form>
    </AppModal>

    <!-- Remove confirmation -->
    <AppModal :show="!!removingDevice" title="Remove from backup schedule" size="md" @close="removingDevice = null">
      <p class="text-sm text-slate-600">
        <strong>{{ removingDevice?.hostname || `Device #${removingDevice?.id}` }}</strong> will stop backing up.
        Files already in Google Drive are kept.
      </p>
      <div class="mt-5 flex justify-end gap-2">
        <button
          @click="removingDevice = null"
          class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
        >
          Cancel
        </button>
        <button
          @click="confirmRemove"
          class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
        >
          Remove
        </button>
      </div>
    </AppModal>

    <!-- Last-run errors -->
    <AppModal
      :show="!!errorsDevice"
      :title="`Last run errors — ${errorsDevice?.hostname || ''}`"
      size="2xl"
      @close="errorsDevice = null"
    >
      <ul class="max-h-96 space-y-2 overflow-y-auto text-sm text-slate-700">
        <li v-for="(err, i) in errorsDevice?.last_run?.errors || []" :key="i" class="rounded-lg bg-amber-50 px-3 py-2">
          {{ err }}
        </li>
      </ul>
    </AppModal>
  </AdminLayout>
</template>
