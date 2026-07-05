<script setup>
import { ref, computed, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { MagnifyingGlassIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
  applications: Array,
  schoolYears: Array,
  activeSyId: Number,
  filters: Object,
  myHall: String,
})

const flash = usePage().props.flash ?? {}

const search = ref(props.filters.search || '')
const status = ref(props.filters.status || '')
const syId   = ref(props.activeSyId)

const PER_PAGE = 15
const currentPage = ref(1)

const filtered = computed(() => {
  const lower = search.value.toLowerCase()
  return props.applications.filter(a =>
    (!lower || a.student_name.toLowerCase().includes(lower)) &&
    (!status.value || a.status === status.value)
  )
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))

const displayed = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

function applyFilters() {
  currentPage.value = 1
  router.get(route('rh.applications.index'), { school_year_id: syId.value, status: status.value, search: search.value }, { preserveState: true, replace: true })
}

const fmtDate = (d) => d
  ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
  : '—'

const statusColor = (s) => ({
  pending:   'amber',
  evaluated: 'blue',
  approved:  'green',
  rejected:  'red',
  waitlisted: 'slate',
}[s] || 'slate')

// ── New Application Modal ──────────────────────────────────────────────────
const showNew    = ref(false)
const submitting = ref(false)

const newForm = ref({
  student_id:            null,
  school_year_id:        props.activeSyId,
  preferred_hall:        props.myHall || 'BRH',
  grade_level:           '',
  home_province:         '',
  estimated_distance_km: '',
  scholarship_category:  '',
  foster_parent_name:    '',
  foster_parent_contact: '',
  foster_parent_address: '',
})

const studentQuery   = ref('')
const studentResults = ref([])
const selectedStudent = ref(null)
let searchTimer = null

watch(studentQuery, (q) => {
  clearTimeout(searchTimer)
  if (!q || q.length < 2) { studentResults.value = []; return }
  searchTimer = setTimeout(async () => {
    const { data } = await axios.get(route('rh.students.search'), { params: { q } })
    studentResults.value = data
  }, 300)
})

function selectStudent(s) {
  selectedStudent.value = s
  newForm.value.student_id = s.id
  newForm.value.preferred_hall = s.sex?.toLowerCase() === 'female' ? 'GRH' : (newForm.value.preferred_hall || 'BRH')
  studentQuery.value = s.name
  studentResults.value = []
}

function openNew() {
  Object.assign(newForm.value, {
    student_id: null, school_year_id: props.activeSyId,
    preferred_hall: props.myHall || 'BRH', grade_level: '',
    home_province: '', estimated_distance_km: '', scholarship_category: '',
    foster_parent_name: '', foster_parent_contact: '', foster_parent_address: '',
  })
  selectedStudent.value = null
  studentQuery.value = ''
  studentResults.value = []
  showNew.value = true
}

function submitNew() {
  submitting.value = true
  router.post(route('rh.applications.store'), newForm.value, {
    preserveScroll: true,
    onSuccess: () => { showNew.value = false },
    onFinish:  () => { submitting.value = false },
  })
}
</script>

<template>
  <Head title="RH Applications" />
  <AdminLayout title="Residence Hall">
    <div class="space-y-5">
      <!-- Flash -->
      <div v-if="flash.success" class="flex items-center gap-2 bg-success-50 border border-success-100 rounded-xl px-4 py-3 text-sm text-success-700">
        {{ flash.success }}
      </div>
      <div v-if="flash.error" class="flex items-center gap-2 bg-danger-50 border border-danger-100 rounded-xl px-4 py-3 text-sm text-danger-700">
        {{ flash.error }}
      </div>

      <AppPageHeader title="Accommodation Applications" subtitle="SSM 5.1 — Evaluation and approval of RH applications">
        <template #actions>
          <AppButton @click="openNew">
            <PlusIcon class="w-4 h-4" />
            New Application
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <div class="flex-1 min-w-[180px]">
          <label class="block text-xs font-medium text-slate-600 mb-1">School Year</label>
          <select v-model="syId" @change="applyFilters"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
              SY {{ sy.name }}{{ sy.is_current ? ' (Current)' : '' }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
          <select v-model="status" @change="applyFilters"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="evaluated">Evaluated</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="waitlisted">Waitlisted</option>
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
      <AppTable :is-empty="!displayed.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Student</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Grade</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Hall</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Province</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Filed</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="app in displayed" :key="app.id" class="hover:bg-slate-50 transition-colors">
          <td class="px-4 py-3 font-medium text-slate-800">{{ app.student_name }}</td>
          <td class="px-4 py-3 text-slate-600">{{ app.grade_level || '—' }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="app.preferred_hall === 'BRH' ? 'indigo' : 'purple'">{{ app.preferred_hall }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-slate-600">{{ app.home_province || '—' }}</td>
          <td class="px-4 py-3 text-slate-500 text-xs">{{ fmtDate(app.created_at) }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusColor(app.status)" class="capitalize">{{ app.status }}</AppBadge>
          </td>
          <td class="px-4 py-3">
            <Link :href="route('rh.applications.show', app.id)"
                  class="text-xs text-indigo-600 hover:underline font-medium">Review</Link>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="app in displayed" :key="app.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ app.student_name }}</p>
                <p class="text-xs text-slate-500">Grade {{ app.grade_level || '—' }} &middot; {{ app.home_province || '—' }}</p>
              </div>
              <AppBadge :color="statusColor(app.status)" class="capitalize">{{ app.status }}</AppBadge>
            </div>
            <div class="flex items-center justify-between">
              <AppBadge :color="app.preferred_hall === 'BRH' ? 'indigo' : 'purple'">{{ app.preferred_hall }}</AppBadge>
              <span class="text-xs text-slate-400">Filed {{ fmtDate(app.created_at) }}</span>
            </div>
            <Link :href="route('rh.applications.show', app.id)" class="text-xs text-indigo-600 hover:underline font-medium">Review</Link>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No applications found" />
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

    <!-- New Application Modal -->
    <AppModal :show="showNew" title="New Application" size="lg" @close="showNew = false">
        <!-- Student search -->
        <div class="relative">
          <label class="block text-xs font-medium text-slate-600 mb-1">Student <span class="text-danger-500">*</span></label>
          <input v-model="studentQuery" type="text" placeholder="Search by name or PISAY ID…"
                 class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          <ul v-if="studentResults.length" class="absolute z-10 mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg max-h-48 overflow-y-auto text-sm">
            <li v-for="s in studentResults" :key="s.id"
                @click="selectStudent(s)"
                class="px-3 py-2 hover:bg-indigo-50 cursor-pointer">
              {{ s.name }}
              <span v-if="s.pisay" class="text-slate-400 ml-1 text-xs">{{ s.pisay }}</span>
            </li>
          </ul>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <!-- School Year -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">School Year <span class="text-danger-500">*</span></label>
            <select v-model="newForm.school_year_id"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
                SY {{ sy.name }}{{ sy.is_current ? ' (Current)' : '' }}
              </option>
            </select>
          </div>

          <!-- Grade Level -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Grade Level</label>
            <input v-model="newForm.grade_level" type="text" placeholder="e.g. Grade 7"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>

          <!-- Preferred Hall -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Preferred Hall <span class="text-danger-500">*</span></label>
            <select v-model="newForm.preferred_hall"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="BRH">BRH — Boys</option>
              <option value="GRH">GRH — Girls</option>
            </select>
          </div>

          <!-- Home Province -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Home Province</label>
            <input v-model="newForm.home_province" type="text" placeholder="e.g. Agusan del Norte"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>

          <!-- Distance -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Distance (km)</label>
            <input v-model.number="newForm.estimated_distance_km" type="number" min="0" placeholder="e.g. 120"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>

          <!-- Scholarship Category -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Scholarship Category</label>
            <input v-model="newForm.scholarship_category" type="text" placeholder="e.g. Full Merit"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>

        <!-- Foster Parent -->
        <div class="border border-slate-100 rounded-xl p-4 space-y-3 bg-slate-50">
          <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Foster Parent Information</p>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Full Name</label>
            <input v-model="newForm.foster_parent_name" type="text" placeholder="Last Name, First Name"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Contact Number</label>
              <input v-model="newForm.foster_parent_contact" type="text" placeholder="09XXXXXXXXX"
                     class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Address</label>
              <input v-model="newForm.foster_parent_address" type="text" placeholder="Complete address"
                     class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
          </div>
        </div>

      <template #footer>
        <div class="flex gap-3 w-full">
          <AppButton block variant="secondary" @click="showNew = false">Cancel</AppButton>
          <AppButton block :disabled="!newForm.student_id || !newForm.preferred_hall || !newForm.school_year_id" :loading="submitting" @click="submitNew">
            Record Application
          </AppButton>
        </div>
      </template>
    </AppModal>

  </AdminLayout>
</template>
