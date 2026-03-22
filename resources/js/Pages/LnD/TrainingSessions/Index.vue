<script setup>
import { ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'

const props = defineProps({
  sessions: { type: Object, required: true },
  programs: { type: Array,  default: () => [] },
  filters:  { type: Object, default: () => ({}) },
})

// ── Filters ────────────────────────────────────────────────────────────────────
const programId = ref(props.filters?.program_id ?? '')
const status    = ref(props.filters?.status     ?? '')
const mode      = ref(props.filters?.mode       ?? '')
const from      = ref(props.filters?.from       ?? '')
const to        = ref(props.filters?.to         ?? '')
const isLoading = ref(false)

const applyFilters = () => {
  isLoading.value = true
  router.get(route('lnd.sessions.index'), {
    program_id: programId.value || undefined,
    status:     status.value    || undefined,
    mode:       mode.value      || undefined,
    from:       from.value      || undefined,
    to:         to.value        || undefined,
  }, {
    preserveState: true, replace: true,
    only: ['sessions', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

watch([programId, status, mode, from, to], () => applyFilters())

const goToPage = (p) => {
  isLoading.value = true
  router.get(route('lnd.sessions.index'), {
    program_id: programId.value || undefined,
    status: status.value || undefined,
    mode: mode.value || undefined,
    from: from.value || undefined,
    to: to.value || undefined,
    page: p,
  }, { preserveState: true, replace: true, only: ['sessions', 'filters'], onFinish: () => { isLoading.value = false } })
}

// ── Helpers ────────────────────────────────────────────────────────────────────
const statusColors = {
  scheduled: 'bg-blue-100 text-blue-700',
  ongoing:   'bg-yellow-100 text-yellow-700',
  completed: 'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-600',
}
const modeColors = {
  face_to_face: 'bg-indigo-100 text-indigo-700',
  online:       'bg-teal-100 text-teal-700',
  blended:      'bg-purple-100 text-purple-700',
}
const modeLabel = {
  face_to_face: 'Face-to-Face',
  online:       'Online',
  blended:      'Blended',
}

const fmt = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'
const fmtTime = (t) => {
  if (!t) return ''
  const [h, m] = t.split(':').map(Number)
  const ampm = h >= 12 ? 'PM' : 'AM'
  return `${h % 12 || 12}:${String(m).padStart(2,'0')} ${ampm}`
}

// ── Modal ──────────────────────────────────────────────────────────────────────
const showModal    = ref(false)
const editingItem  = ref(null)
const isSubmitting = ref(false)

const emptyForm = () => ({
  learning_program_id: '',
  session_date:        '',
  start_time:          '08:00',
  end_time:            '17:00',
  venue:               '',
  mode:                'face_to_face',
  facilitator:         '',
  max_participants:    '',
  status:              'scheduled',
  remarks:             '',
})
const form = ref(emptyForm())

const openCreate = () => {
  editingItem.value = null
  form.value = emptyForm()
  showModal.value = true
}

const openEdit = (s) => {
  editingItem.value = s
  form.value = {
    learning_program_id: s.learning_program_id ?? '',
    session_date:        s.session_date        ? s.session_date.substring(0, 10) : '',
    start_time:          s.start_time          ? s.start_time.substring(11, 16)  : '08:00',
    end_time:            s.end_time            ? s.end_time.substring(11, 16)    : '17:00',
    venue:               s.venue               ?? '',
    mode:                s.mode                ?? 'face_to_face',
    facilitator:         s.facilitator         ?? '',
    max_participants:    s.max_participants     ?? '',
    status:              s.status              ?? 'scheduled',
    remarks:             s.remarks             ?? '',
  }
  showModal.value = true
}

const closeModal = () => { showModal.value = false; editingItem.value = null }

const submit = () => {
  isSubmitting.value = true
  const url    = editingItem.value ? route('lnd.sessions.update', editingItem.value.id) : route('lnd.sessions.store')
  const method = editingItem.value ? 'put' : 'post'
  router[method](url, form.value, {
    preserveState: true,
    onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Saved', timer: 1400, showConfirmButton: false }) },
    onError: () => {},
    onFinish: () => { isSubmitting.value = false },
  })
}

const deleteSession = (s) => {
  Swal.fire({
    title: 'Delete session?',
    text: `Session on ${fmt(s.session_date)} will be removed.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    confirmButtonText: 'Delete',
  }).then(res => {
    if (!res.isConfirmed) return
    router.delete(route('lnd.sessions.destroy', s.id), {
      preserveState: true,
      onSuccess: () => Swal.fire({ icon: 'success', title: 'Deleted', timer: 1200, showConfirmButton: false }),
      onError: (errs) => Swal.fire({ icon: 'error', title: 'Error', text: Object.values(errs)[0] }),
    })
  })
}

const markComplete = (s) => {
  Swal.fire({
    title: 'Mark as Completed?',
    text: 'Attended participants will be set to completed.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#16a34a',
    confirmButtonText: 'Yes, complete it',
  }).then(res => {
    if (!res.isConfirmed) return
    router.post(route('lnd.sessions.complete', s.id), {}, {
      preserveState: true,
      onSuccess: () => Swal.fire({ icon: 'success', title: 'Session completed!', timer: 1400, showConfirmButton: false }),
    })
  })
}
</script>

<template>
  <AdminLayout title="Training Sessions">
    <Head title="Training Sessions" />

    <div class="p-6 space-y-5">

      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Training Sessions</h1>
          <p class="text-sm text-slate-500">Schedule and manage training sessions</p>
        </div>
        <button @click="openCreate"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          New Session
        </button>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
        <select v-model="programId"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 min-w-[200px]">
          <option value="">All Programs</option>
          <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.title }}</option>
        </select>
        <select v-model="status"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option value="">All Status</option>
          <option value="scheduled">Scheduled</option>
          <option value="ongoing">Ongoing</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <select v-model="mode"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option value="">All Modes</option>
          <option value="face_to_face">Face-to-Face</option>
          <option value="online">Online</option>
          <option value="blended">Blended</option>
        </select>
        <input v-model="from" type="date"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        <input v-model="to" type="date" :min="from"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        <button v-if="programId || status || mode || from || to"
          @click="programId=''; status=''; mode=''; from=''; to=''"
          class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          Clear
        </button>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div v-if="isLoading" class="py-16 text-center text-slate-400 text-sm">Loading…</div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Program</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Time</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Venue</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Mode</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Participants</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="sessions.data.length === 0">
                <td colspan="8" class="py-16 text-center text-slate-400 text-sm">No sessions found.</td>
              </tr>
              <tr v-for="s in sessions.data" :key="s.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-800">{{ s.program?.title ?? '—' }}</div>
                  <div v-if="s.facilitator" class="text-xs text-slate-500">{{ s.facilitator }}</div>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-700">{{ fmt(s.session_date) }}</td>
                <td class="px-4 py-3 whitespace-nowrap text-xs text-slate-600">
                  {{ fmtTime(s.start_time) }} – {{ fmtTime(s.end_time) }}
                </td>
                <td class="px-4 py-3 text-sm text-slate-700 max-w-[140px] truncate">{{ s.venue ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium', modeColors[s.mode] ?? 'bg-slate-100 text-slate-600']">
                    {{ modeLabel[s.mode] ?? s.mode }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span class="font-semibold text-indigo-600 text-sm">{{ s.participants_count }}</span>
                  <span v-if="s.max_participants" class="text-slate-400 text-sm"> / {{ s.max_participants }}</span>
                </td>
                <td class="px-4 py-3">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium', statusColors[s.status] ?? 'bg-slate-100 text-slate-600']">
                    {{ s.status.charAt(0).toUpperCase() + s.status.slice(1) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center justify-center gap-1 flex-wrap">
                    <a :href="route('lnd.sessions.show', s.id)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-indigo-600 transition-colors text-xs font-medium px-2">View</a>
                    <button @click="openEdit(s)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors text-xs font-medium px-2">Edit</button>
                    <button v-if="s.status !== 'completed' && s.status !== 'cancelled'"
                      @click="markComplete(s)"
                      class="p-1.5 rounded-lg hover:bg-emerald-50 text-slate-500 hover:text-emerald-600 transition-colors text-xs font-medium px-2">Complete</button>
                    <button @click="deleteSession(s)"
                      class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors text-xs font-medium px-2">Delete</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="sessions.last_page > 1"
        class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
        <span>Showing {{ sessions.from }}–{{ sessions.to }} of {{ sessions.total }}</span>
        <div class="flex gap-1">
          <button v-for="p in sessions.links" :key="p.label"
            @click="p.url && goToPage(new URL(p.url).searchParams.get('page'))"
            :disabled="!p.url"
            :class="['px-3 py-1 rounded border text-xs', p.active ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-200 hover:bg-slate-50 disabled:opacity-40 text-slate-600']"
            v-html="p.label" />
        </div>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="w-full max-w-xl max-h-[90vh] overflow-y-auto bg-white rounded-2xl shadow-xl">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">{{ editingItem ? 'Edit Session' : 'New Training Session' }}</h2>
            <button @click="closeModal"
              class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <form @submit.prevent="submit" class="px-6 py-5 space-y-4">

            <!-- Program -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Learning Program <span class="text-red-500">*</span></label>
              <select v-model="form.learning_program_id" required
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="">— Select program —</option>
                <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.title }}</option>
              </select>
            </div>

            <!-- Date -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Session Date <span class="text-red-500">*</span></label>
              <input v-model="form.session_date" type="date" required
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>

            <!-- Time -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Start Time <span class="text-red-500">*</span></label>
                <input v-model="form.start_time" type="time" required
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">End Time <span class="text-red-500">*</span></label>
                <input v-model="form.end_time" type="time" required
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
            </div>

            <!-- Mode + Status -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Mode <span class="text-red-500">*</span></label>
                <select v-model="form.mode" required
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                  <option value="face_to_face">Face-to-Face</option>
                  <option value="online">Online</option>
                  <option value="blended">Blended</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Status <span class="text-red-500">*</span></label>
                <select v-model="form.status" required
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                  <option value="scheduled">Scheduled</option>
                  <option value="ongoing">Ongoing</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>
            </div>

            <!-- Venue + Facilitator -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Venue</label>
              <input v-model="form.venue" type="text"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Facilitator</label>
                <input v-model="form.facilitator" type="text"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Max Participants</label>
                <input v-model="form.max_participants" type="number" min="1"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
            </div>

            <!-- Remarks -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
              <textarea v-model="form.remarks" rows="2"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 resize-none" />
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
              <button type="button" @click="closeModal"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                Cancel
              </button>
              <button type="submit" :disabled="isSubmitting"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
                {{ isSubmitting ? 'Saving…' : (editingItem ? 'Update' : 'Create') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
