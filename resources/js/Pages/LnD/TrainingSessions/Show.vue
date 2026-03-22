<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'

const props = defineProps({
  session: { type: Object, required: true },
})

const page = usePage()

// ── Helpers ────────────────────────────────────────────────────────────────────
const fmt = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' }) : '—'
const fmtTime = (t) => {
  if (!t) return ''
  const [h, m] = t.split(':').map(Number)
  const ampm = h >= 12 ? 'PM' : 'AM'
  return `${h % 12 || 12}:${String(m).padStart(2,'0')} ${ampm}`
}

const statusColors = {
  scheduled: 'bg-blue-100 text-blue-700',
  ongoing:   'bg-yellow-100 text-yellow-700',
  completed: 'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-600',
}
const modeLabel = { face_to_face: 'Face-to-Face', online: 'Online', blended: 'Blended' }

const attendanceColors = {
  registered: 'bg-gray-100 text-gray-600',
  attended:   'bg-green-100 text-green-700',
  absent:     'bg-red-100 text-red-600',
  excused:    'bg-yellow-100 text-yellow-700',
}
const nominationColors = {
  nominated:    'bg-gray-100 text-gray-600',
  approved:     'bg-green-100 text-green-700',
  disapproved:  'bg-red-100 text-red-600',
}
const completionColors = {
  pending:       'bg-gray-100 text-gray-600',
  completed:     'bg-green-100 text-green-700',
  not_completed: 'bg-red-100 text-red-600',
}

// ── Nominatate modal ───────────────────────────────────────────────────────────
const showNominateModal = ref(false)
const nominateEmployeeIds = ref([])
const nominateLoading = ref(false)

// Employees not yet in session (would need a prop — for now free text search is omitted)
const openNominate = () => {
  nominateEmployeeIds.value = []
  showNominateModal.value = true
}
const submitNominate = () => {
  nominateLoading.value = true
  router.post(route('lnd.participants.store', props.session.id), {
    employee_ids: nominateEmployeeIds.value,
  }, {
    preserveState: false,
    onSuccess: () => { showNominateModal.value = false; Swal.fire({ icon: 'success', title: 'Nominated', timer: 1400, showConfirmButton: false }) },
    onError: (errs) => Swal.fire({ icon: 'error', title: 'Error', text: Object.values(errs)[0] }),
    onFinish: () => { nominateLoading.value = false },
  })
}

// ── Attendance ─────────────────────────────────────────────────────────────────
const showAttendanceModal = ref(false)
const attendanceMap = ref({})

const openAttendance = () => {
  attendanceMap.value = {}
  props.session.participants.forEach(p => {
    attendanceMap.value[p.id] = p.attendance_status
  })
  showAttendanceModal.value = true
}

const submitAttendance = () => {
  const attendance = Object.entries(attendanceMap.value).map(([participant_id, status]) => ({
    participant_id: Number(participant_id),
    status,
  }))
  router.post(route('lnd.sessions.attendance', props.session.id), { attendance }, {
    preserveState: false,
    onSuccess: () => {
      showAttendanceModal.value = false
      Swal.fire({ icon: 'success', title: 'Attendance saved', timer: 1400, showConfirmButton: false })
    },
    onError: (errs) => Swal.fire({ icon: 'error', title: 'Error', text: Object.values(errs)[0] }),
  })
}

// ── Approve / Disapprove nomination ───────────────────────────────────────────
const updateNomination = (participant, action) => {
  router.patch(route('lnd.participants.nomination', participant.id), { action }, {
    preserveState: false,
    onSuccess: () => Swal.fire({ icon: 'success', title: action === 'approved' ? 'Approved' : 'Disapproved', timer: 1200, showConfirmButton: false }),
    onError: (errs) => Swal.fire({ icon: 'error', title: 'Error', text: Object.values(errs)[0] }),
  })
}

// ── Remove participant ─────────────────────────────────────────────────────────
const removeParticipant = (participant) => {
  Swal.fire({
    title: 'Remove participant?',
    text: `${participant.employee?.name} will be removed from this session.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    confirmButtonText: 'Remove',
  }).then(res => {
    if (!res.isConfirmed) return
    router.delete(route('lnd.participants.destroy', participant.id), {
      preserveState: false,
      onSuccess: () => Swal.fire({ icon: 'success', title: 'Removed', timer: 1200, showConfirmButton: false }),
      onError: (errs) => Swal.fire({ icon: 'error', title: 'Error', text: Object.values(errs)[0] }),
    })
  })
}

// ── Complete session ───────────────────────────────────────────────────────────
const completeSession = () => {
  Swal.fire({
    title: 'Mark as Completed?',
    text: 'Attended participants will be set to completed.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#16a34a',
    confirmButtonText: 'Yes, complete it',
  }).then(res => {
    if (!res.isConfirmed) return
    router.post(route('lnd.sessions.complete', props.session.id), {}, {
      preserveState: false,
      onSuccess: () => Swal.fire({ icon: 'success', title: 'Session completed!', timer: 1400, showConfirmButton: false }),
    })
  })
}

// ── Stats ──────────────────────────────────────────────────────────────────────
const stats = computed(() => {
  const total    = props.session.participants?.length ?? 0
  const attended = props.session.participants?.filter(p => p.attendance_status === 'attended').length ?? 0
  const approved = props.session.participants?.filter(p => p.nomination_status === 'approved').length ?? 0
  const withEval = props.session.participants?.filter(p => p.evaluation).length ?? 0
  return { total, attended, approved, withEval }
})
</script>

<template>
  <AdminLayout :title="`Session — ${fmt(session.session_date)}`">
    <Head :title="`Training Session · ${fmt(session.session_date)}`" />

    <div class="p-6 space-y-5">

      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div class="flex items-center gap-2 text-sm">
          <a :href="route('lnd.sessions.index')"
            class="inline-flex items-center gap-1 text-slate-500 hover:text-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Sessions
          </a>
          <span class="text-slate-300">/</span>
          <span class="font-medium text-slate-700">{{ fmt(session.session_date) }}</span>
        </div>
        <div class="flex flex-wrap gap-2">
          <a v-if="session.status === 'completed'"
            :href="route('lnd.sessions.evaluation-summary', session.id)"
            class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            Evaluation Summary
          </a>
          <button v-if="session.status !== 'completed' && session.status !== 'cancelled'"
            @click="openAttendance"
            class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            Mark Attendance
          </button>
          <button v-if="session.status !== 'completed' && session.status !== 'cancelled'"
            @click="completeSession"
            class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            Complete Session
          </button>
          <button @click="openNominate"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nominate
          </button>
        </div>
      </div>

      <!-- Info + Stats -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <!-- Session Details Card -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
          <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-base font-semibold text-slate-800">Session Details</h2>
          </div>
          <div class="p-5 space-y-3">
            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
              <dt class="text-slate-500">Program</dt>
              <dd class="font-medium text-slate-800">{{ session.program?.title ?? '—' }}</dd>
              <dt class="text-slate-500">Date</dt>
              <dd class="text-slate-700">{{ fmt(session.session_date) }}</dd>
              <dt class="text-slate-500">Time</dt>
              <dd class="text-slate-700">{{ fmtTime(session.start_time?.substring(11,16)) }} – {{ fmtTime(session.end_time?.substring(11,16)) }}</dd>
              <dt class="text-slate-500">Venue</dt>
              <dd class="text-slate-700">{{ session.venue ?? '—' }}</dd>
              <dt class="text-slate-500">Mode</dt>
              <dd class="text-slate-700">{{ modeLabel[session.mode] ?? session.mode }}</dd>
              <dt class="text-slate-500">Facilitator</dt>
              <dd class="text-slate-700">{{ session.facilitator ?? '—' }}</dd>
              <dt class="text-slate-500">Status</dt>
              <dd>
                <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium', statusColors[session.status] ?? 'bg-slate-100 text-slate-600']">
                  {{ session.status.charAt(0).toUpperCase() + session.status.slice(1) }}
                </span>
              </dd>
              <dt class="text-slate-500">Max Participants</dt>
              <dd class="text-slate-700">{{ session.max_participants ?? 'Unlimited' }}</dd>
            </dl>
            <div v-if="session.remarks" class="rounded-lg bg-slate-50 border border-slate-100 p-3 text-sm text-slate-600">
              {{ session.remarks }}
            </div>
          </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 gap-4 content-start">
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
            <div class="text-3xl font-bold text-indigo-600">{{ stats.total }}</div>
            <div class="text-xs text-slate-500 mt-1">Total Registered</div>
          </div>
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
            <div class="text-3xl font-bold text-emerald-600">{{ stats.approved }}</div>
            <div class="text-xs text-slate-500 mt-1">Approved</div>
          </div>
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
            <div class="text-3xl font-bold text-blue-600">{{ stats.attended }}</div>
            <div class="text-xs text-slate-500 mt-1">Attended</div>
          </div>
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
            <div class="text-3xl font-bold text-purple-600">{{ stats.withEval }}</div>
            <div class="text-xs text-slate-500 mt-1">With Evaluation</div>
          </div>
        </div>
      </div>

      <!-- Participants Table Card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-base font-semibold text-slate-800">Participants</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Employee</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Nomination</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Attendance</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Completion</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Certificate</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Evaluation</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!session.participants?.length">
                <td colspan="7" class="py-16 text-center text-slate-400 text-sm">No participants yet.</td>
              </tr>
              <tr v-for="p in session.participants" :key="p.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-800">{{ p.employee?.name ?? '—' }}</div>
                  <div class="text-xs text-slate-500">{{ p.employee?.email }}</div>
                </td>
                <td class="px-4 py-3 text-center">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium', nominationColors[p.nomination_status] ?? 'bg-slate-100 text-slate-600']">
                    {{ p.nomination_status.charAt(0).toUpperCase() + p.nomination_status.slice(1) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium', attendanceColors[p.attendance_status] ?? 'bg-slate-100 text-slate-600']">
                    {{ p.attendance_status.charAt(0).toUpperCase() + p.attendance_status.slice(1) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium', completionColors[p.completion_status] ?? 'bg-slate-100 text-slate-600']">
                    {{ p.completion_status.replace('_', ' ').charAt(0).toUpperCase() + p.completion_status.replace('_',' ').slice(1) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span v-if="p.certificate_path" class="inline-flex items-center gap-1 text-xs text-emerald-600 font-medium">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Issued
                  </span>
                  <span v-else class="text-xs text-slate-400">—</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <a v-if="p.evaluation"
                    :href="route('lnd.evaluations.show', p.id)"
                    class="text-xs font-medium text-indigo-600 hover:underline">View</a>
                  <a v-else
                    :href="route('lnd.evaluations.show', p.id)"
                    class="text-xs font-medium text-slate-400 hover:text-indigo-500">+ Evaluate</a>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <button v-if="p.nomination_status === 'nominated'"
                      @click="updateNomination(p, 'approved')"
                      class="p-1.5 rounded-lg hover:bg-emerald-50 text-slate-500 hover:text-emerald-600 transition-colors text-xs font-medium px-2">Approve</button>
                    <button v-if="p.nomination_status === 'nominated'"
                      @click="updateNomination(p, 'disapproved')"
                      class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors text-xs font-medium px-2">Disapprove</button>
                    <button v-if="p.nomination_status !== 'approved'"
                      @click="removeParticipant(p)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors text-xs font-medium px-2">Remove</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Nominate Modal -->
    <Teleport to="body">
      <div v-if="showNominateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-xl">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Nominate Participants</h2>
            <button @click="showNominateModal = false"
              class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="px-6 py-5 space-y-4">
            <p class="text-sm text-slate-500">Enter employee IDs to nominate (comma-separated):</p>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Employee IDs</label>
              <input
                type="text"
                placeholder="e.g. 5, 12, 47"
                @input="e => nominateEmployeeIds = e.target.value.split(',').map(v => parseInt(v.trim())).filter(Boolean)"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p class="mt-1 text-xs text-slate-400">Enter the user IDs of employees to nominate.</p>
            </div>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click="showNominateModal = false"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
              Cancel
            </button>
            <button @click="submitNominate" :disabled="nominateLoading || !nominateEmployeeIds.length"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
              {{ nominateLoading ? 'Nominating…' : 'Nominate' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Attendance Modal -->
    <Teleport to="body">
      <div v-if="showAttendanceModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="w-full max-w-lg max-h-[85vh] overflow-y-auto bg-white rounded-2xl shadow-xl">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Mark Attendance</h2>
            <button @click="showAttendanceModal = false"
              class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="px-6 py-5 space-y-2">
            <div v-for="p in session.participants" :key="p.id"
              class="flex items-center justify-between gap-4 py-2 border-b border-slate-100 last:border-0">
              <span class="text-sm font-medium text-slate-800">{{ p.employee?.name }}</span>
              <div class="flex gap-2">
                <button v-for="opt in ['attended', 'absent', 'excused']" :key="opt"
                  @click="attendanceMap[p.id] = opt"
                  :class="[
                    'rounded-lg px-3 py-1 text-xs font-medium capitalize transition-colors',
                    attendanceMap[p.id] === opt
                      ? (opt === 'attended' ? 'bg-emerald-600 text-white' : opt === 'absent' ? 'bg-red-600 text-white' : 'bg-amber-500 text-white')
                      : 'border border-slate-200 text-slate-600 hover:bg-slate-50'
                  ]">
                  {{ opt }}
                </button>
              </div>
            </div>
            <div v-if="!session.participants?.length" class="py-8 text-center text-slate-400 text-sm">
              No participants to mark.
            </div>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click="showAttendanceModal = false"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
              Cancel
            </button>
            <button @click="submitAttendance"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
              Save Attendance
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
