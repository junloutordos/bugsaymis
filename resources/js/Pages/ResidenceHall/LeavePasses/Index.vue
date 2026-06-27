<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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
function logDepart(pass) {
  if (!confirm(`Log departure for ${pass.student_name}?`)) return
  router.post(route('rh.leave-passes.depart', pass.id), {}, { preserveScroll: true })
}

function logReturn(pass) {
  if (!confirm(`Log return for ${pass.student_name}?`)) return
  router.post(route('rh.leave-passes.return', pass.id), {}, { preserveScroll: true })
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const fmtDate = (d) => d
  ? new Date(d).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
  : '—'

const statusClass = (s) => ({
  pending:  'bg-amber-100 text-amber-700',
  approved: 'bg-sky-100 text-sky-700',
  rejected: 'bg-rose-100 text-rose-600',
  departed: 'bg-indigo-100 text-indigo-700',
  returned: 'bg-emerald-100 text-emerald-700',
  overdue:  'bg-rose-100 text-rose-700',
}[s] || 'bg-slate-100 text-slate-600')

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

      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Leave Passes</h1>
          <p class="text-sm text-slate-500">F-RHU-07 — Intern leave pass management</p>
        </div>
        <button @click="showAdd = true"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          <PlusIcon class="w-4 h-4" /> New Pass
        </button>
      </div>

      <!-- Overdue Banner -->
      <div v-if="overdue.length" class="bg-rose-50 border border-rose-200 rounded-xl p-4">
        <div class="flex items-center gap-2 mb-2">
          <ClockIcon class="w-5 h-5 text-rose-600" />
          <h3 class="text-sm font-semibold text-rose-800">{{ overdue.length }} Overdue Return{{ overdue.length > 1 ? 's' : '' }}</h3>
        </div>
        <div class="space-y-1">
          <div v-for="p in overdue" :key="p.id" class="flex items-center justify-between text-sm">
            <span class="text-rose-700 font-medium">{{ p.student_name }}</span>
            <span class="text-rose-600 text-xs">Expected: {{ fmtDate(p.expected_return_at) }}</span>
            <button @click="logReturn(p)"
                    class="text-xs text-white bg-rose-600 hover:bg-rose-700 px-2 py-1 rounded-md">
              Log Return
            </button>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap gap-3 items-end">
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
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50">
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Student</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden md:table-cell">Hall</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Purpose</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden lg:table-cell">Expected Return</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="p in displayed" :key="p.id"
                :class="['hover:bg-slate-50 transition-colors', p.status === 'overdue' ? 'bg-rose-50' : '']">
              <td class="px-4 py-3 font-medium text-slate-800">{{ p.student_name }}</td>
              <td class="px-4 py-3 hidden md:table-cell">
                <span v-if="p.residence_hall"
                      :class="['text-xs px-2 py-0.5 rounded-full font-medium', p.residence_hall === 'BRH' ? 'bg-indigo-100 text-indigo-700' : 'bg-pink-100 text-pink-700']">
                  {{ p.residence_hall }}
                </span>
              </td>
              <td class="px-4 py-3 text-slate-600">{{ purposeLabel(p.purpose) }}</td>
              <td class="px-4 py-3 text-slate-500 text-xs hidden lg:table-cell">{{ fmtDate(p.expected_return_at) }}</td>
              <td class="px-4 py-3">
                <span :class="['text-xs px-2 py-0.5 rounded-full font-medium capitalize', statusClass(p.status)]">
                  {{ p.status }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-1">
                  <!-- Approve/Reject if pending -->
                  <template v-if="p.status === 'pending'">
                    <button @click="openApprove(p, 'approve')"
                            class="text-xs text-emerald-600 hover:underline font-medium">Approve</button>
                    <span class="text-slate-300">·</span>
                    <button @click="openApprove(p, 'reject')"
                            class="text-xs text-rose-600 hover:underline font-medium">Reject</button>
                  </template>
                  <!-- Guard: log departure if approved -->
                  <button v-if="p.status === 'approved'" @click="logDepart(p)"
                          class="text-xs text-indigo-600 hover:underline font-medium">Log Out</button>
                  <!-- Guard: log return if departed -->
                  <button v-if="p.status === 'departed'" @click="logReturn(p)"
                          class="text-xs text-emerald-600 hover:underline font-medium">Log In</button>
                </div>
              </td>
            </tr>
            <tr v-if="!displayed.length">
              <td colspan="6" class="text-center py-12 text-slate-400 text-sm">No leave passes found.</td>
            </tr>
          </tbody>
        </table>
        <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100">
          <p class="text-xs text-slate-500">Page {{ currentPage }} of {{ totalPages }}</p>
          <div class="flex gap-2">
            <button @click="currentPage--" :disabled="currentPage <= 1"
                    class="px-3 py-1.5 rounded-lg text-xs border border-slate-200 text-slate-600 disabled:opacity-40 hover:bg-slate-50">Prev</button>
            <button @click="currentPage++" :disabled="currentPage >= totalPages"
                    class="px-3 py-1.5 rounded-lg text-xs border border-slate-200 text-slate-600 disabled:opacity-40 hover:bg-slate-50">Next</button>
          </div>
        </div>
      </div>

    </div>

    <!-- Add Pass Modal -->
    <div v-if="showAdd" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <h3 class="text-base font-semibold text-slate-800 mb-4">New Leave Pass</h3>
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Intern ID</label>
            <input v-model.number="addForm.rh_intern_id" type="number" placeholder="Enter intern record ID"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <p class="text-xs text-slate-400 mt-1">Find the intern ID from the Intern Roster.</p>
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
        <div class="flex gap-3 mt-5">
          <button @click="showAdd = false; addForm.reset(); studentQuery = ''"
                  class="flex-1 px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm hover:bg-slate-50">Cancel</button>
          <button @click="submitAdd" :disabled="!addForm.rh_intern_id || !addForm.destination || addForm.processing"
                  class="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium">
            Issue Pass
          </button>
        </div>
      </div>
    </div>

    <!-- Approve Modal -->
    <div v-if="showApprove" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="text-base font-semibold text-slate-800 mb-1">
          {{ approveAction === 'approve' ? 'Approve' : 'Reject' }} Leave Pass
        </h3>
        <p class="text-sm text-slate-500 mb-4">{{ showApprove.student_name }} — {{ purposeLabel(showApprove.purpose) }}</p>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Remarks (optional)</label>
          <textarea v-model="approveRemarks" rows="2"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
        </div>
        <div class="flex gap-3 mt-4">
          <button @click="showApprove = null"
                  class="flex-1 px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm hover:bg-slate-50">Cancel</button>
          <button @click="submitApprove"
                  :class="['flex-1 text-white px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center justify-center gap-2',
                    approveAction === 'approve' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-rose-600 hover:bg-rose-700']">
            <CheckCircleIcon v-if="approveAction === 'approve'" class="w-4 h-4" />
            <XCircleIcon v-else class="w-4 h-4" />
            {{ approveAction === 'approve' ? 'Approve' : 'Reject' }}
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>
