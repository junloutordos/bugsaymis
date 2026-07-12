<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import Swal from 'sweetalert2'
import { confirmAction, confirmDelete } from '@/Composables/useConfirm.js'
import { PlusIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  session: { type: Object, required: true },
})

// ── Helpers ────────────────────────────────────────────────────────────────────
const fmt = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' }) : '—'
const fmtTime = (t) => {
  if (!t) return ''
  const [h, m] = t.split(':').map(Number)
  const ampm = h >= 12 ? 'PM' : 'AM'
  return `${h % 12 || 12}:${String(m).padStart(2,'0')} ${ampm}`
}

const statusColors = {
  scheduled: 'blue',
  ongoing:   'amber',
  completed: 'green',
  cancelled: 'red',
}

const attendanceColors = {
  registered: 'slate',
  attended:   'green',
  absent:     'red',
  excused:    'amber',
}
const nominationColors = {
  nominated:    'slate',
  approved:     'green',
  disapproved:  'red',
}
const completionColors = {
  pending:       'slate',
  completed:     'green',
  not_completed: 'red',
}
const capitalize = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1) : s

const modeLabel = { face_to_face: 'Face-to-Face', online: 'Online', blended: 'Blended' }

const breadcrumb = computed(() => [
  { label: 'Sessions', href: route('lnd.sessions.index') },
  { label: fmt(props.session.session_date) },
])

// ── Nominate modal ─────────────────────────────────────────────────────────────
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
const removeParticipant = async (participant) => {
  const confirmed = await confirmDelete(`${participant.employee?.name} will be removed from this session.`)
  if (!confirmed) return
  router.delete(route('lnd.participants.destroy', participant.id), {
    preserveState: false,
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Removed', timer: 1200, showConfirmButton: false }),
    onError: (errs) => Swal.fire({ icon: 'error', title: 'Error', text: Object.values(errs)[0] }),
  })
}

// ── Complete session ───────────────────────────────────────────────────────────
const completeSession = async () => {
  const confirmed = await confirmAction({
    title: 'Mark as Completed?',
    text: 'Attended participants will be set to completed.',
    confirmText: 'Yes, complete it',
  })
  if (!confirmed) return
  router.post(route('lnd.sessions.complete', props.session.id), {}, {
    preserveState: false,
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Session completed!', timer: 1400, showConfirmButton: false }),
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
  <Head :title="`Training Session · ${fmt(session.session_date)}`" />
  <AdminLayout :title="`Session — ${fmt(session.session_date)}`">
    <div class="space-y-5">

      <AppPageHeader :title="fmt(session.session_date)" :breadcrumb="breadcrumb">
        <template #actions>
          <AppButton v-if="session.status === 'completed'" as="link" variant="secondary"
            :href="route('lnd.sessions.evaluation-summary', session.id)">
            Evaluation Summary
          </AppButton>
          <AppButton v-if="session.status !== 'completed' && session.status !== 'cancelled'"
            variant="secondary" @click="openAttendance">
            Mark Attendance
          </AppButton>
          <AppButton v-if="session.status !== 'completed' && session.status !== 'cancelled'"
            variant="success" @click="completeSession">
            Complete Session
          </AppButton>
          <AppButton @click="openNominate">
            <PlusIcon class="h-4 w-4" />
            Nominate
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Info + Stats -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <!-- Session Details Card -->
        <AppCard title="Session Details">
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
              <AppBadge :color="statusColors[session.status] ?? 'slate'">{{ capitalize(session.status) }}</AppBadge>
            </dd>
            <dt class="text-slate-500">Max Participants</dt>
            <dd class="text-slate-700">{{ session.max_participants ?? 'Unlimited' }}</dd>
          </dl>
          <div v-if="session.remarks" class="mt-3 rounded-lg bg-slate-50 border border-slate-100 p-3 text-sm text-slate-600">
            {{ session.remarks }}
          </div>
        </AppCard>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 gap-4 content-start">
          <AppCard class="text-center">
            <div class="text-3xl font-bold text-indigo-600">{{ stats.total }}</div>
            <div class="text-xs text-slate-500 mt-1">Total Registered</div>
          </AppCard>
          <AppCard class="text-center">
            <div class="text-3xl font-bold text-success-600">{{ stats.approved }}</div>
            <div class="text-xs text-slate-500 mt-1">Approved</div>
          </AppCard>
          <AppCard class="text-center">
            <div class="text-3xl font-bold text-blue-600">{{ stats.attended }}</div>
            <div class="text-xs text-slate-500 mt-1">Attended</div>
          </AppCard>
          <AppCard class="text-center">
            <div class="text-3xl font-bold text-purple-600">{{ stats.withEval }}</div>
            <div class="text-xs text-slate-500 mt-1">With Evaluation</div>
          </AppCard>
        </div>
      </div>

      <!-- Participants Table -->
      <AppCard title="Participants" :padded="false">
      <AppTable :card="false" :is-empty="!session.participants?.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Employee</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Nomination</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Attendance</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Completion</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Certificate</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Evaluation</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="p in session.participants" :key="p.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3">
            <div class="font-medium text-slate-800">{{ p.employee?.name ?? '—' }}</div>
            <div class="text-xs text-slate-500">{{ p.employee?.email }}</div>
          </td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="nominationColors[p.nomination_status] ?? 'slate'">{{ capitalize(p.nomination_status) }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="attendanceColors[p.attendance_status] ?? 'slate'">{{ capitalize(p.attendance_status) }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="completionColors[p.completion_status] ?? 'slate'">{{ capitalize(p.completion_status.replace('_', ' ')) }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <span v-if="p.certificate_path" class="inline-flex items-center gap-1 text-xs text-success-600 font-medium">
              <CheckCircleIcon class="w-3.5 h-3.5" />
              Issued
            </span>
            <span v-else class="text-xs text-slate-400">—</span>
          </td>
          <td class="px-4 py-3 text-center">
            <Link v-if="p.evaluation"
              :href="route('lnd.evaluations.show', p.id)"
              class="text-xs font-medium text-indigo-600 hover:underline">View</Link>
            <Link v-else
              :href="route('lnd.evaluations.show', p.id)"
              class="text-xs font-medium text-slate-400 hover:text-indigo-500">+ Evaluate</Link>
          </td>
          <td class="px-4 py-3 text-center">
            <div class="flex items-center justify-center gap-1">
              <AppButton v-if="p.nomination_status === 'nominated'" size="sm" variant="success" @click="updateNomination(p, 'approved')">Approve</AppButton>
              <AppButton v-if="p.nomination_status === 'nominated'" size="sm" variant="danger" @click="updateNomination(p, 'disapproved')">Disapprove</AppButton>
              <AppButton v-if="p.nomination_status !== 'approved'" size="sm" variant="secondary" @click="removeParticipant(p)">Remove</AppButton>
            </div>
          </td>
        </tr>

        <template #empty>
          <EmptyState title="No participants yet" />
        </template>
      </AppTable>
      </AppCard>
    </div>

    <!-- Nominate Modal -->
    <AppModal :show="showNominateModal" title="Nominate Participants" @close="showNominateModal = false">
      <p class="text-sm text-slate-500 mb-4">Enter employee IDs to nominate (comma-separated):</p>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Employee IDs</label>
        <input
          type="text"
          placeholder="e.g. 5, 12, 47"
          @input="e => nominateEmployeeIds = e.target.value.split(',').map(v => parseInt(v.trim())).filter(Boolean)"
          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        <p class="mt-1 text-xs text-slate-400">Enter the user IDs of employees to nominate.</p>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showNominateModal = false">Cancel</AppButton>
        <AppButton :disabled="!nominateEmployeeIds.length" :loading="nominateLoading" @click="submitNominate">Nominate</AppButton>
      </template>
    </AppModal>

    <!-- Attendance Modal -->
    <AppModal :show="showAttendanceModal" title="Mark Attendance" size="lg" @close="showAttendanceModal = false">
      <div class="space-y-2">
        <div v-for="p in session.participants" :key="p.id"
          class="flex items-center justify-between gap-4 py-2 border-b border-slate-100 last:border-0">
          <span class="text-sm font-medium text-slate-800">{{ p.employee?.name }}</span>
          <div class="flex gap-2">
            <button v-for="opt in ['attended', 'absent', 'excused']" :key="opt"
              type="button"
              @click="attendanceMap[p.id] = opt"
              :class="[
                'rounded-lg px-3 py-1 text-xs font-medium capitalize transition-colors',
                attendanceMap[p.id] === opt
                  ? (opt === 'attended' ? 'bg-success-600 text-white' : opt === 'absent' ? 'bg-danger-600 text-white' : 'bg-warning-500 text-white')
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
      <template #footer>
        <AppButton variant="secondary" @click="showAttendanceModal = false">Cancel</AppButton>
        <AppButton @click="submitAttendance">Save Attendance</AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
