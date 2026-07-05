<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { confirmAction } from '@/Composables/useConfirm.js'
import {
  MagnifyingGlassIcon, PlusIcon, ClockIcon,
  CheckCircleIcon, XCircleIcon,
} from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
  passes:  Array,
  filters: Object,
  myHall:  String,
})

const search   = ref(props.filters.search || '')
const statusF  = ref(props.filters.status || '')
const showAdd  = ref(false)
const showApprove = ref(null) // pass object
const approveAction = ref('approve')
const approveRemarks = ref('')

const PER_PAGE = 15
const currentPage = ref(1)

const filtered = computed(() => {
  const lower = search.value.toLowerCase()
  return props.passes.filter(p =>
    (!lower || p.student_name.toLowerCase().includes(lower)) &&
    (!statusF.value || p.status === statusF.value)
  )
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed  = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

const overdue = computed(() =>
  props.passes.filter(p => p.status === 'departed' && p.expected_return_at && new Date(p.expected_return_at) < new Date())
)

function applyFilters() {
  currentPage.value = 1
  router.get(route('rh.leave-passes.index'), { status: statusF.value, search: search.value }, { preserveState: true, replace: true })
}

// ── Add form ──────────────────────────────────────────────────────────────────
const studentQuery = ref('')
const studentResults = ref([])
let searchTimeout = null

async function searchStudents() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(async () => {
    if (studentQuery.value.length < 2) { studentResults.value = []; return }
    const { data } = await axios.get(route('rh.students.search'), { params: { q: studentQuery.value } })
    studentResults.value = data
  }, 300)
}

const addForm = useForm({
  rh_intern_id:         '',
  purpose:              'go_home',
  destination:          '',
  with_companion:       false,
  companion_name:       '',
  companion_contact:    '',
  expected_return_at:   '',
  student_signature_at: '',
})

// Intern search (search all interns in hall)
const internResults = ref([])
let internTimeout = null

async function searchInterns() {
  clearTimeout(internTimeout)
  internTimeout = setTimeout(async () => {
    if (studentQuery.value.length < 2) { internResults.value = []; return }
    // use student search → cross-ref interns from passes list or a dedicated endpoint
    const { data } = await axios.get(route('rh.students.search'), { params: { q: studentQuery.value } })
    studentResults.value = data
  }, 300)
}

function selectStudent(s) {
  studentQuery.value = s.name
  studentResults.value = []
  // We need rh_intern_id — for now show a note that user must type intern ID
  addForm.rh_intern_id = ''
  selectedStudentForAdd.value = s
}

const selectedStudentForAdd = ref(null)

function submitAdd() {
  addForm.post(route('rh.leave-passes.store'), {
    preserveScroll: true,
    onSuccess: () => { showAdd.value = false; addForm.reset(); studentQuery.value = ''; selectedStudentForAdd.value = null },
  })
}

// ── Approve ───────────────────────────────────────────────────────────────────
function openApprove(pass, action) {
  showApprove.value = pass
  approveAction.value = action
  approveRemarks.value = ''
}

function submitApprove() {
  router.post(route('rh.leave-passes.approve', showApprove.value.id), {
    action: approveAction.value,
    remarks: approveRemarks.value,
  }, {
    preserveScroll: true,
    onSuccess: () => { showApprove.value = null },
  })
}

// ── Guard actions ─────────────────────────────────────────────────────────────
async function logDepart(pass) {
  const confirmed = await confirmAction({ title: 'Log departure?', text: `Log departure for ${pass.student_name}?`, confirmText: 'Log Out' })
  if (!confirmed) return
  router.post(route('rh.leave-passes.depart', pass.id), {}, { preserveScroll: true })
}

async function logReturn(pass) {
  const confirmed = await confirmAction({ title: 'Log return?', text: `Log return for ${pass.student_name}?`, confirmText: 'Log In' })
  if (!confirmed) return
  router.post(route('rh.leave-passes.return', pass.id), {}, { preserveScroll: true })
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const fmtDate = (d) => d
  ? new Date(d).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
  : '—'

const statusColor = (s) => ({
  pending:  'amber',
  approved: 'blue',
  rejected: 'red',
  departed: 'indigo',
  returned: 'green',
  overdue:  'red',
}[s] || 'slate')

const purposeLabel = (p) => ({
  go_home:          'Go Home',
  school_activity:  'School Activity',
  other:            'Other',
}[p] || p)
</script>

<template>
  <Head title="Leave Passes" />
  <AdminLayout title="Residence Hall">
    <div class="space-y-5">

      <AppPageHeader title="Leave Passes" subtitle="F-RHU-07 — Dormer leave pass management">
        <template #actions>
          <AppButton @click="showAdd = true">
            <PlusIcon class="w-4 h-4" /> New Pass
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Overdue Banner -->
      <div v-if="overdue.length" class="bg-danger-50 border border-danger-100 rounded-xl p-4">
        <div class="flex items-center gap-2 mb-2">
          <ClockIcon class="w-5 h-5 text-danger-600" />
          <h3 class="text-sm font-semibold text-danger-700">{{ overdue.length }} Overdue Return{{ overdue.length > 1 ? 's' : '' }}</h3>
        </div>
        <div class="space-y-1">
          <div v-for="p in overdue" :key="p.id" class="flex items-center justify-between text-sm">
            <span class="text-danger-600 font-medium">{{ p.student_name }}</span>
            <span class="text-danger-500 text-xs">Expected: {{ fmtDate(p.expected_return_at) }}</span>
            <AppButton size="sm" variant="danger" @click="logReturn(p)">Log Return</AppButton>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <AppFilterBar>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
          <select v-model="statusF" @change="applyFilters"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="departed">Departed</option>
            <option value="returned">Returned</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
        <div class="flex-1 min-w-[200px]">
          <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
          <div class="relative">
            <MagnifyingGlassIcon class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input v-model="search" @input="currentPage = 1" type="text" placeholder="Student name…"
                   class="w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!displayed.length" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Student</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden md:table-cell">Hall</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Purpose</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden lg:table-cell">Expected Return</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="p in displayed" :key="p.id"
            :class="['hover:bg-slate-50 transition-colors', p.status === 'overdue' ? 'bg-danger-50' : '']">
          <td class="px-4 py-3 font-medium text-slate-800">{{ p.student_name }}</td>
          <td class="px-4 py-3 hidden md:table-cell">
            <AppBadge v-if="p.residence_hall" :color="p.residence_hall === 'BRH' ? 'indigo' : 'purple'">{{ p.residence_hall }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-slate-600">{{ purposeLabel(p.purpose) }}</td>
          <td class="px-4 py-3 text-slate-500 text-xs hidden lg:table-cell">{{ fmtDate(p.expected_return_at) }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusColor(p.status)" class="capitalize">{{ p.status }}</AppBadge>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1">
              <!-- Approve/Reject if pending -->
              <template v-if="p.status === 'pending'">
                <AppButton size="sm" variant="success" @click="openApprove(p, 'approve')">Approve</AppButton>
                <AppButton size="sm" variant="danger" @click="openApprove(p, 'reject')">Reject</AppButton>
              </template>
              <!-- Guard: log departure if approved -->
              <AppButton v-if="p.status === 'approved'" size="sm" variant="ghost" @click="logDepart(p)">Log Out</AppButton>
              <!-- Guard: log return if departed -->
              <AppButton v-if="p.status === 'departed'" size="sm" variant="success" @click="logReturn(p)">Log In</AppButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="p in displayed" :key="p.id" class="p-4 space-y-2" :class="p.status === 'overdue' ? 'bg-danger-50' : ''">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ p.student_name }}</p>
                <AppBadge v-if="p.residence_hall" :color="p.residence_hall === 'BRH' ? 'indigo' : 'purple'">{{ p.residence_hall }}</AppBadge>
              </div>
              <AppBadge :color="statusColor(p.status)" class="capitalize">{{ p.status }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">{{ purposeLabel(p.purpose) }} &middot; Expected {{ fmtDate(p.expected_return_at) }}</p>
            <div class="flex items-center gap-2 pt-1 flex-wrap">
              <template v-if="p.status === 'pending'">
                <AppButton size="sm" variant="success" @click="openApprove(p, 'approve')">Approve</AppButton>
                <AppButton size="sm" variant="danger" @click="openApprove(p, 'reject')">Reject</AppButton>
              </template>
              <AppButton v-if="p.status === 'approved'" size="sm" variant="ghost" @click="logDepart(p)">Log Out</AppButton>
              <AppButton v-if="p.status === 'departed'" size="sm" variant="success" @click="logReturn(p)">Log In</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No leave passes found" />
        </template>

        <template #footer>
          <PaginationControl
            v-if="totalPages > 1"
            :current-page="currentPage"
            :total-pages="totalPages"
            @prev="currentPage--"
            @next="currentPage++"
            @page="currentPage = $event"
          />
        </template>
      </AppTable>

    </div>

    <!-- Add Pass Modal -->
    <AppModal :show="showAdd" title="New Leave Pass" size="lg" @close="showAdd = false; addForm.reset(); studentQuery = ''">
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Dormer ID</label>
            <input v-model.number="addForm.rh_intern_id" type="number" placeholder="Enter dormer record ID"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <p class="text-xs text-slate-400 mt-1">Find the dormer ID from the Dormer Roster.</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Purpose</label>
            <select v-model="addForm.purpose"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="go_home">Go Home</option>
              <option value="school_activity">School Activity</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Destination</label>
            <input v-model="addForm.destination" type="text"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div class="flex items-center gap-2">
            <input v-model="addForm.with_companion" type="checkbox" id="with_companion" class="rounded" />
            <label for="with_companion" class="text-sm text-slate-700">With Companion</label>
          </div>
          <div v-if="addForm.with_companion" class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Companion Name</label>
              <input v-model="addForm.companion_name" type="text"
                     class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Companion Contact</label>
              <input v-model="addForm.companion_contact" type="text"
                     class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Expected Return</label>
              <input v-model="addForm.expected_return_at" type="datetime-local"
                     class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Student Signature Date</label>
              <input v-model="addForm.student_signature_at" type="datetime-local"
                     class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
          </div>
        </div>

      <template #footer>
        <div class="flex gap-3 w-full">
          <AppButton block variant="secondary" @click="showAdd = false; addForm.reset(); studentQuery = ''">Cancel</AppButton>
          <AppButton block :disabled="!addForm.rh_intern_id || !addForm.destination" :loading="addForm.processing" @click="submitAdd">
            Issue Pass
          </AppButton>
        </div>
      </template>
    </AppModal>

    <!-- Approve Modal -->
    <AppModal :show="!!showApprove" :title="`${approveAction === 'approve' ? 'Approve' : 'Reject'} Leave Pass`"
              :subtitle="showApprove ? `${showApprove.student_name} — ${purposeLabel(showApprove.purpose)}` : ''"
              size="md" @close="showApprove = null">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Remarks (optional)</label>
        <textarea v-model="approveRemarks" rows="2"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
      </div>

      <template #footer>
        <div class="flex gap-3 w-full">
          <AppButton block variant="secondary" @click="showApprove = null">Cancel</AppButton>
          <AppButton block :variant="approveAction === 'approve' ? 'success' : 'danger'" @click="submitApprove">
            <CheckCircleIcon v-if="approveAction === 'approve'" class="w-4 h-4" />
            <XCircleIcon v-else class="w-4 h-4" />
            {{ approveAction === 'approve' ? 'Approve' : 'Reject' }}
          </AppButton>
        </div>
      </template>
    </AppModal>

  </AdminLayout>
</template>
