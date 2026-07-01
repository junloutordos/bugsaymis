<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
  interns: Array,
  schoolYears: Array,
  activeSyId: Number,
  filters: Object,
  myHall: String,
})

const search   = ref(props.filters.search || '')
const statusF  = ref(props.filters.status || 'active')
const syId     = ref(props.activeSyId)
const showAdd  = ref(false)

const PER_PAGE = 15
const currentPage = ref(1)

const filtered = computed(() => {
  const lower = search.value.toLowerCase()
  return props.interns.filter(i =>
    !lower || i.student_name.toLowerCase().includes(lower)
  )
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

function applyFilters() {
  currentPage.value = 1
  router.get(route('rh.interns.index'),
    { school_year_id: syId.value, status: statusF.value, search: search.value },
    { preserveState: true, replace: true })
}

// Student search for Add form
const studentQuery = ref('')
const studentResults = ref([])
const selectedStudent = ref(null)
let searchTimeout = null

async function searchStudents() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(async () => {
    if (studentQuery.value.length < 2) { studentResults.value = []; return }
    const { data } = await axios.get(route('rh.students.search'), { params: { q: studentQuery.value } })
    studentResults.value = data
  }, 300)
}

function selectStudent(s) {
  selectedStudent.value = s
  studentQuery.value = s.name
  studentResults.value = []
  addForm.student_id = s.id
}

// Rooms will be loaded from the current interns list for SY (simplified)
const addForm = useForm({
  student_id: null,
  school_year_id: props.activeSyId,
  rh_room_id: '',
  bed_number: '',
  check_in_date: '',
  lodging_fee_monthly: 0,
  contract_start: '',
  contract_end: '',
})

function submitAdd() {
  addForm.post(route('rh.interns.store'), {
    preserveScroll: true,
    onSuccess: () => { showAdd.value = false; addForm.reset(); selectedStudent.value = null; studentQuery.value = '' },
  })
}

const fmtDate = (d) => d
  ? new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
  : '—'

const statusClass = (s) => ({
  active:       'bg-emerald-100 text-emerald-700',
  checked_out:  'bg-slate-100 text-slate-600',
  suspended:    'bg-amber-100 text-amber-700',
  terminated:   'bg-rose-100 text-rose-700',
}[s] || 'bg-slate-100 text-slate-600')

const hallBadge = (h) => h === 'BRH'
  ? 'bg-indigo-100 text-indigo-700'
  : 'bg-pink-100 text-pink-700'
</script>

<template>
  <Head title="Dormer Roster" />
  <AdminLayout title="Residence Hall">
    <div class="space-y-5">

      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Dormer Roster</h1>
          <p class="text-sm text-slate-500">Active dormitory residents</p>
        </div>
        <button @click="showAdd = true"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          <PlusIcon class="w-4 h-4" /> Enroll Dormer
        </button>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap gap-3 items-end">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">School Year</label>
          <select v-model="syId" @change="applyFilters"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
              SY {{ sy.name }}{{ sy.is_current ? ' (Current)' : '' }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
          <select v-model="statusF" @change="applyFilters"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="suspended">Suspended</option>
            <option value="checked_out">Checked Out</option>
            <option value="terminated">Terminated</option>
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
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Hall</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Room</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Bed</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Check-in</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="intern in displayed" :key="intern.id" class="hover:bg-slate-50 transition-colors">
              <td class="px-4 py-3 font-medium text-slate-800">{{ intern.student_name }}</td>
              <td class="px-4 py-3">
                <span v-if="intern.room" :class="['text-xs px-2 py-0.5 rounded-full font-medium', hallBadge(intern.room.residence_hall)]">
                  {{ intern.room.residence_hall }}
                </span>
                <span v-else class="text-slate-400 text-xs">—</span>
              </td>
              <td class="px-4 py-3 text-slate-600">{{ intern.room?.room_number || '—' }}</td>
              <td class="px-4 py-3 text-slate-600">{{ intern.bed_number || '—' }}</td>
              <td class="px-4 py-3 text-slate-500 text-xs">{{ fmtDate(intern.check_in_date) }}</td>
              <td class="px-4 py-3">
                <span :class="['text-xs px-2 py-0.5 rounded-full font-medium capitalize', statusClass(intern.status)]">
                  {{ intern.status.replace('_', ' ') }}
                </span>
              </td>
              <td class="px-4 py-3">
                <Link :href="route('rh.interns.show', intern.id)"
                      class="text-xs text-indigo-600 hover:underline font-medium">View</Link>
              </td>
            </tr>
            <tr v-if="!displayed.length">
              <td colspan="7" class="text-center py-12 text-slate-400 text-sm">No dormers found.</td>
            </tr>
          </tbody>
        </table>

        <PaginationControl
          v-if="totalPages > 1"
          :current-page="currentPage"
          :total-pages="totalPages"
          @prev="currentPage--"
          @next="currentPage++"
          @page="currentPage = $event"
        />
      </div>

      <!-- Add Intern Modal -->
      <div v-if="showAdd" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 p-6 max-h-[90vh] overflow-y-auto">
          <h3 class="text-base font-semibold text-slate-800 mb-4">Enroll New Dormer</h3>
          <div class="space-y-3">

            <!-- Student search -->
            <div class="relative">
              <label class="block text-xs font-medium text-slate-600 mb-1">Student</label>
              <input v-model="studentQuery" @input="searchStudents" type="text"
                     placeholder="Type name or PISAY ID…"
                     class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              <ul v-if="studentResults.length"
                  class="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                <li v-for="s in studentResults" :key="s.id"
                    @click="selectStudent(s)"
                    class="px-3 py-2 text-sm text-slate-700 hover:bg-indigo-50 cursor-pointer">
                  {{ s.name }}
                  <span v-if="s.pisay" class="text-slate-400 text-xs ml-2">{{ s.pisay }}</span>
                </li>
              </ul>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">School Year</label>
                <select v-model="addForm.school_year_id"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">SY {{ sy.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Room</label>
                <input v-model.number="addForm.rh_room_id" type="number" placeholder="Room ID"
                       class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Bed No.</label>
                <input v-model="addForm.bed_number" type="text" placeholder="e.g. A1"
                       class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Monthly Lodging Fee (₱)</label>
                <input v-model.number="addForm.lodging_fee_monthly" type="number" min="0"
                       class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Check-in Date</label>
                <input v-model="addForm.check_in_date" type="date"
                       class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Contract Start</label>
                <input v-model="addForm.contract_start" type="date"
                       class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Contract End</label>
              <input v-model="addForm.contract_end" type="date"
                     class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

          </div>
          <div class="flex gap-3 mt-5">
            <button @click="showAdd = false; addForm.reset(); selectedStudent = null; studentQuery = ''"
                    class="flex-1 px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm hover:bg-slate-50">
              Cancel
            </button>
            <button @click="submitAdd" :disabled="!addForm.student_id || !addForm.rh_room_id || addForm.processing"
                    class="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium">
              Enroll Dormer
            </button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
