<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import Swal from 'sweetalert2'
import { confirmAction, confirmDelete } from '@/Composables/useConfirm.js'
import { PlusIcon } from '@heroicons/vue/24/outline'

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

function clearFilters () {
  programId.value = ''
  status.value = ''
  mode.value = ''
  from.value = ''
  to.value = ''
}

// ── Helpers ────────────────────────────────────────────────────────────────────
const statusColors = {
  scheduled: 'blue',
  ongoing:   'amber',
  completed: 'green',
  cancelled: 'red',
}
const modeColors = {
  face_to_face: 'indigo',
  online:       'purple',
  blended:      'purple',
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

const deleteSession = async (s) => {
  const confirmed = await confirmDelete(`Session on ${fmt(s.session_date)} will be removed.`)
  if (!confirmed) return
  router.delete(route('lnd.sessions.destroy', s.id), {
    preserveState: true,
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Deleted', timer: 1200, showConfirmButton: false }),
    onError: (errs) => Swal.fire({ icon: 'error', title: 'Error', text: Object.values(errs)[0] }),
  })
}

const markComplete = async (s) => {
  const confirmed = await confirmAction({
    title: 'Mark as Completed?',
    text: 'Attended participants will be set to completed.',
    confirmText: 'Yes, complete it',
  })
  if (!confirmed) return
  router.post(route('lnd.sessions.complete', s.id), {}, {
    preserveState: true,
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Session completed!', timer: 1400, showConfirmButton: false }),
  })
}
</script>

<template>
  <Head title="Training Sessions" />
  <AdminLayout title="Training Sessions">
    <div class="space-y-5">

      <AppPageHeader title="Training Sessions" subtitle="Schedule and manage training sessions">
        <template #actions>
          <AppButton @click="openCreate">
            <PlusIcon class="h-4 w-4" />
            New Session
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <select v-model="programId"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 min-w-[200px]">
          <option value="">All Programs</option>
          <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.title }}</option>
        </select>
        <select v-model="status"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Status</option>
          <option value="scheduled">Scheduled</option>
          <option value="ongoing">Ongoing</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <select v-model="mode"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Modes</option>
          <option value="face_to_face">Face-to-Face</option>
          <option value="online">Online</option>
          <option value="blended">Blended</option>
        </select>
        <input v-model="from" type="date"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        <input v-model="to" type="date" :min="from"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />

        <template #actions>
          <AppButton v-if="programId || status || mode || from || to" size="sm" variant="secondary" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :loading="isLoading" :is-empty="!sessions.data.length" :skeleton-cols="8">
        <template #head>
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
        </template>

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
            <AppBadge :color="modeColors[s.mode] ?? 'slate'">{{ modeLabel[s.mode] ?? s.mode }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <span class="font-semibold text-indigo-600 text-sm">{{ s.participants_count }}</span>
            <span v-if="s.max_participants" class="text-slate-400 text-sm"> / {{ s.max_participants }}</span>
          </td>
          <td class="px-4 py-3">
            <AppBadge :color="statusColors[s.status] ?? 'slate'">{{ s.status.charAt(0).toUpperCase() + s.status.slice(1) }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <div class="flex items-center justify-center gap-1 flex-wrap">
              <Link :href="route('lnd.sessions.show', s.id)" class="text-xs text-slate-500 hover:text-indigo-600 font-medium px-2 py-1.5">View</Link>
              <AppButton size="sm" variant="secondary" @click="openEdit(s)">Edit</AppButton>
              <AppButton v-if="s.status !== 'completed' && s.status !== 'cancelled'" size="sm" variant="success" @click="markComplete(s)">Complete</AppButton>
              <AppButton size="sm" variant="danger" @click="deleteSession(s)">Delete</AppButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="s in sessions.data" :key="s.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ s.program?.title ?? '—' }}</p>
                <p v-if="s.facilitator" class="text-xs text-slate-500">{{ s.facilitator }}</p>
                <p class="text-xs text-slate-400">{{ fmt(s.session_date) }} &middot; {{ fmtTime(s.start_time) }} – {{ fmtTime(s.end_time) }}</p>
              </div>
              <AppBadge :color="statusColors[s.status] ?? 'slate'">{{ s.status.charAt(0).toUpperCase() + s.status.slice(1) }}</AppBadge>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-500">
              <AppBadge :color="modeColors[s.mode] ?? 'slate'">{{ modeLabel[s.mode] ?? s.mode }}</AppBadge>
              <span>{{ s.participants_count }}<span v-if="s.max_participants"> / {{ s.max_participants }}</span> participants</span>
            </div>
            <div class="flex items-center gap-2 pt-1 flex-wrap">
              <Link :href="route('lnd.sessions.show', s.id)" class="text-xs text-slate-500 hover:text-indigo-600 font-medium">View</Link>
              <AppButton size="sm" variant="secondary" @click="openEdit(s)">Edit</AppButton>
              <AppButton v-if="s.status !== 'completed' && s.status !== 'cancelled'" size="sm" variant="success" @click="markComplete(s)">Complete</AppButton>
              <AppButton size="sm" variant="danger" @click="deleteSession(s)">Delete</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No sessions found" />
        </template>

        <template #footer>
          <PaginationControl :links="sessions.links" :current-page="sessions.current_page" :total-pages="sessions.last_page" :total="sessions.total" />
        </template>
      </AppTable>

    </div>

    <!-- Create / Edit Modal -->
    <AppModal :show="showModal" :title="editingItem ? 'Edit Session' : 'New Training Session'" @close="closeModal">
      <form @submit.prevent="submit" class="space-y-4">

        <!-- Program -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Learning Program <span class="text-danger-600">*</span></label>
          <select v-model="form.learning_program_id" required
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">— Select program —</option>
            <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.title }}</option>
          </select>
        </div>

        <!-- Date -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Session Date <span class="text-danger-600">*</span></label>
          <input v-model="form.session_date" type="date" required
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>

        <!-- Time -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Start Time <span class="text-danger-600">*</span></label>
            <input v-model="form.start_time" type="time" required
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">End Time <span class="text-danger-600">*</span></label>
            <input v-model="form.end_time" type="time" required
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>

        <!-- Mode + Status -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Mode <span class="text-danger-600">*</span></label>
            <select v-model="form.mode" required
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="face_to_face">Face-to-Face</option>
              <option value="online">Online</option>
              <option value="blended">Blended</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Status <span class="text-danger-600">*</span></label>
            <select v-model="form.status" required
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
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
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Facilitator</label>
            <input v-model="form.facilitator" type="text"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Max Participants</label>
            <input v-model="form.max_participants" type="number" min="1"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>

        <!-- Remarks -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
          <textarea v-model="form.remarks" rows="2"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
          <AppButton type="button" variant="secondary" @click="closeModal">Cancel</AppButton>
          <AppButton type="submit" :loading="isSubmitting">{{ editingItem ? 'Update' : 'Create' }}</AppButton>
        </div>
      </form>
    </AppModal>
  </AdminLayout>
</template>
