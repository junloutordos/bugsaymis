<template>
  <Head title="Faculty Vacancies" />
  <AdminLayout title="Faculty Vacancies">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Faculty Vacancies</h1>
          <p class="text-sm text-slate-500 mt-0.5">Track open faculty slots per academic unit and term</p>
        </div>
        <button @click="openForm()"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm shrink-0">
          <PlusIcon class="h-4 w-4" /> Open Vacancy
        </button>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" /> {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.errors?.error" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <ExclamationCircleIcon class="h-4 w-4 shrink-0" /> {{ $page.props.errors.error }}
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-3 items-center">
        <select v-model="filterYear" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
          <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">S.Y. {{ sy.name }}</option>
        </select>
        <select v-model="filterUnit" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
          <option value="">All Units</option>
          <option v-for="u in academicUnits" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
        <select v-model="filterStatus" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
          <option value="">All Statuses</option>
          <option value="open">Open</option>
          <option value="filled">Filled</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      <!-- Summary chips -->
      <div class="flex flex-wrap gap-2">
        <div v-for="u in academicUnits" :key="u.id"
          v-if="openCounts[u.id]"
          class="text-xs bg-amber-50 border border-amber-200 text-amber-700 px-3 py-1 rounded-full font-medium">
          {{ u.code }}: {{ openCounts[u.id] }} open
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-100">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Term</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Position</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Subject</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Filled By</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-if="vacancies.length === 0">
              <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-400">No vacancies found.</td>
            </tr>
            <tr v-for="v in vacancies" :key="v.id" class="hover:bg-slate-50/50">
              <td class="px-4 py-3 font-medium text-slate-800">{{ v.academic_unit?.name ?? '—' }}</td>
              <td class="px-4 py-3 text-slate-500">{{ v.academic_term?.name ?? 'Full Year' }}</td>
              <td class="px-4 py-3 text-slate-600">{{ v.sst_position?.code ?? '—' }}</td>
              <td class="px-4 py-3 text-slate-600">{{ v.subject?.name ?? '—' }}</td>
              <td class="px-4 py-3">
                <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                  :class="statusClass(v.status)">
                  {{ v.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-slate-500 text-xs">
                {{ v.filled_by?.name ?? '—' }}
                <span v-if="v.filled_at" class="text-slate-400"> · {{ fmtDate(v.filled_at) }}</span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <button v-if="v.status === 'open'" @click="openFillModal(v)"
                    class="text-xs text-emerald-600 hover:text-emerald-800 border border-emerald-200 hover:bg-emerald-50 px-2 py-1 rounded transition-colors font-medium">
                    Fill
                  </button>
                  <button v-if="v.status === 'open'" @click="cancelVacancy(v)"
                    class="text-xs text-amber-600 hover:text-amber-800 border border-amber-200 hover:bg-amber-50 px-2 py-1 rounded transition-colors font-medium">
                    Cancel
                  </button>
                  <button v-if="v.status !== 'filled'" @click="deleteVacancy(v)"
                    class="p-1.5 text-slate-400 hover:text-red-600 rounded">
                    <TrashIcon class="h-4 w-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Open Vacancy Modal -->
    <div v-if="formModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
        <h2 class="text-lg font-semibold text-slate-800">Open Vacancy</h2>
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Academic Unit <span class="text-red-500">*</span></label>
            <select v-model="vacForm.academic_unit_id"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
              <option v-for="u in academicUnits" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Notes</label>
            <textarea v-model="vacForm.notes" rows="2" placeholder="Context or requirements for this vacancy"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
          </div>
        </div>
        <div class="flex justify-end gap-3 pt-1">
          <button @click="formModal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="saveVacancy" :disabled="vacForm.processing"
            class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg font-medium">
            Open Vacancy
          </button>
        </div>
      </div>
    </div>

    <!-- Fill Modal -->
    <div v-if="fillModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 space-y-4">
        <h2 class="text-lg font-semibold text-slate-800">Fill Vacancy</h2>
        <p class="text-sm text-slate-500">Select the faculty member who will fill this slot.</p>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Faculty Member <span class="text-red-500">*</span></label>
          <input v-model="fillSearch" type="text" placeholder="Type to search faculty..."
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none mb-2" />
          <p v-if="fillForm.errors.user_id" class="text-red-500 text-xs mt-1">{{ fillForm.errors.user_id }}</p>
          <p class="text-xs text-slate-400 mt-1">Selected: {{ fillSelectedName || '—' }}</p>
        </div>
        <div class="flex justify-end gap-3 pt-1">
          <button @click="fillModal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="submitFill" :disabled="fillForm.processing || !fillForm.user_id"
            class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-lg font-medium">
            Confirm Fill
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { CheckCircleIcon, ExclamationCircleIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  vacancies:      { type: Array,  default: () => [] },
  schoolYears:    { type: Array,  default: () => [] },
  academicUnits:  { type: Array,  default: () => [] },
  openCounts:     { type: Object, default: () => ({}) },
  selectedYearId: { type: Number, default: null },
  filters:        { type: Object, default: () => ({}) },
})

const filterYear   = ref(props.selectedYearId)
const filterUnit   = ref(props.filters.academic_unit_id ?? '')
const filterStatus = ref(props.filters.status ?? '')

function applyFilters() {
  router.get(route('faculty-loading.vacancies.index'), {
    school_year_id:   filterYear.value,
    academic_unit_id: filterUnit.value || undefined,
    status:           filterStatus.value || undefined,
  }, { preserveScroll: true, preserveState: true })
}

// ── Open Vacancy ──────────────────────────────────────────────────────────────
const formModal = ref(false)
const vacForm   = useForm({ school_year_id: props.selectedYearId, academic_unit_id: null, notes: '' })

function openForm() { vacForm.reset(); vacForm.school_year_id = props.selectedYearId; formModal.value = true }
function saveVacancy() {
  vacForm.post(route('faculty-loading.vacancies.store'), { onSuccess: () => { formModal.value = false } })
}

// ── Fill ──────────────────────────────────────────────────────────────────────
const fillModal        = ref(false)
const fillTargetId     = ref(null)
const fillSearch       = ref('')
const fillForm         = useForm({ user_id: null })
const fillSelectedName = ref('')

function openFillModal(v) {
  fillTargetId.value = v.id; fillForm.reset(); fillSearch.value = ''; fillSelectedName.value = ''
  fillModal.value = true
}
function submitFill() {
  fillForm.post(route('faculty-loading.vacancies.fill', fillTargetId.value), { onSuccess: () => { fillModal.value = false } })
}

// ── Cancel / Delete ───────────────────────────────────────────────────────────
function cancelVacancy(v) {
  if (! confirm('Cancel this vacancy?')) return
  useForm({}).post(route('faculty-loading.vacancies.cancel', v.id))
}
function deleteVacancy(v) {
  if (! confirm('Delete this vacancy?')) return
  useForm({}).delete(route('faculty-loading.vacancies.destroy', v.id))
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const STATUS_CLASSES = { open: 'bg-amber-100 text-amber-700', filled: 'bg-emerald-100 text-emerald-700', cancelled: 'bg-slate-100 text-slate-500' }
const statusClass = (s) => STATUS_CLASSES[s] ?? 'bg-slate-100 text-slate-500'
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
</script>
