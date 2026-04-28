<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { MagnifyingGlassIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'

const props = defineProps({
  employees:       Object,
  leaveTypes:      Array,   // VL, SL, CTO, WL from backend
  filters:         Object,
  specialChiefIds: Array,   // teaching division chiefs of SSD/CID who also earn VL+SL
})

const search    = ref(props.filters?.search ?? '')
const isLoading = ref(false)
let debounce    = null

// ── Search ────────────────────────────────────────────────────────────────────
const applySearch = (immediate = false) => {
  clearTimeout(debounce)
  const go = () => {
    isLoading.value = true
    router.get(route('hr.leave-credits.initialize'), { search: search.value || undefined }, {
      preserveState: true, replace: true,
      only: ['employees', 'filters', 'specialChiefIds'],
      onFinish: () => { isLoading.value = false },
    })
  }
  immediate ? go() : (debounce = setTimeout(go, 400))
}

// ── Modal ─────────────────────────────────────────────────────────────────────
const showModal    = ref(false)
const selected     = ref(null)
const isSubmitting = ref(false)

const form = ref({
  user_id:    null,
  year:       new Date().getFullYear(),
  balances:   [],
  remarks:    '',
  force:      false,
  sc_balance: '',   // Service credit opening balance in days (Teaching only)
})

// Teaching categories — must match LeaveCreditService::TEACHING_CATEGORIES exactly
const TEACHING_CATEGORIES = ['Plantilla Teaching', 'COS Teaching']

// Is the selected employee Teaching?
const isTeaching = computed(() =>
  TEACHING_CATEGORIES.includes(selected.value?.emp_category ?? '')
)

// Is the selected employee a special SSD/CID division chief (teaching but earns VL+SL too)?
const isSpecialChief = computed(() =>
  isTeaching.value && (props.specialChiefIds ?? []).includes(selected.value?.id)
)

// Visible leave types per employee category:
// Teaching (regular) → WL only (Service Credits tracked separately)
// Teaching (special chief, SSD/CID) → VL, SL, WL (+ SC row for Service Credits)
// Non-Teaching → VL, SL, CTO, WL
const visibleLeaveTypes = computed(() => {
  if (!selected.value) return []
  if (isTeaching.value && !isSpecialChief.value) {
    return (props.leaveTypes ?? []).filter(lt => lt.code === 'WL')
  }
  if (isSpecialChief.value) {
    return (props.leaveTypes ?? []).filter(lt => ['VL', 'SL', 'WL'].includes(lt.code))
  }
  return props.leaveTypes ?? []   // VL, SL, CTO, WL
})

const openModal = (emp) => {
  selected.value = emp
  form.value = {
    user_id:    emp.id,
    year:       new Date().getFullYear(),
    remarks:    '',
    force:      false,
    sc_balance: '',
    balances:   [],
  }
  // Build balances based on employee type
  const teaching      = TEACHING_CATEGORIES.includes(emp.emp_category ?? '')
  const specialChief  = teaching && (props.specialChiefIds ?? []).includes(emp.id)
  form.value.balances = (props.leaveTypes ?? [])
    .filter(lt => {
      if (teaching && !specialChief) return lt.code === 'WL'
      if (specialChief)              return ['VL', 'SL', 'WL'].includes(lt.code)
      return true   // Non-teaching: VL, SL, CTO, WL
    })
    .map(lt => ({ leave_type_code: lt.code, label: lt.name, amount: '' }))
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
  selected.value  = null
}

const submit = async () => {
  if (!form.value.remarks.trim()) {
    Swal.fire('Remarks Required', 'Enter the source or reason for this initialization.', 'warning')
    return
  }

  const filled = form.value.balances.filter(b => b.amount !== '' && Number(b.amount) > 0)
  const hasScDate = form.value.sc_service_date?.trim()

  if (!filled.length && !hasScDate) {
    Swal.fire('Nothing to Save', 'Enter at least one leave balance or a service credit record.', 'warning')
    return
  }

  isSubmitting.value = true
  router.post(route('hr.leave-credits.initialize.store'), form.value, {
    onSuccess: () => {
      Swal.fire('Saved!', 'Opening balances recorded successfully.', 'success')
      closeModal()
    },
    onError: (errors) => {
      Swal.fire('Error', Object.values(errors)[0], 'error')
    },
    onFinish: () => { isSubmitting.value = false },
  })
}

// ── Table helpers ─────────────────────────────────────────────────────────────
const pageData   = computed(() => props.employees?.data ?? [])
const totalPages = computed(() => props.employees?.last_page ?? 1)
const curPage    = computed(() => props.employees?.current_page ?? 1)

const goToPage = (p) => {
  isLoading.value = true
  router.get(route('hr.leave-credits.initialize'), { search: search.value || undefined, page: p }, {
    preserveState: true, replace: true,
    only: ['employees', 'filters', 'specialChiefIds'],
    onFinish: () => { isLoading.value = false },
  })
}

const categoryBadge = (cat) => {
  if (!cat) return 'bg-slate-100 text-slate-600'
  return TEACHING_CATEGORIES.includes(cat)
    ? 'bg-blue-100 text-blue-700'
    : 'bg-amber-100 text-amber-700'
}
</script>

<template>
  <Head title="Initialize Leave Credits" />
  <AdminLayout title="Initialize Leave Credits">
    <div class="space-y-5">

      <div>
        <h1 class="text-xl font-semibold text-slate-800">Initialize Leave Credits</h1>
        <p class="text-sm text-slate-500 mt-0.5">
          Set opening balances for employees migrating from manual or legacy records.
          Teaching: Wellness Leave + Service Credits.
          SSD/CID Division Chiefs (Teaching): VL · SL · Wellness Leave + Service Credits.
          Non-Teaching: VL · SL · CTO · Wellness Leave.
        </p>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm">{{ $page.props.flash.success }}</div>
      <div v-if="$page.props.flash?.error"   class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">{{ $page.props.flash.error }}</div>

      <!-- Search -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex gap-3">
        <div class="relative flex-1">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input v-model="search" type="text" placeholder="Search by name or employee ID…"
                 @keydown.enter.prevent="applySearch(true)"
                 @input="applySearch(false)"
                 class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <button @click="applySearch(true)" :disabled="isLoading"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">
          Search
        </button>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Employee</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Position</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Category</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="emp in pageData" :key="emp.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3">
                  <p class="font-medium text-slate-800">{{ emp.name }}</p>
                  <p class="text-xs text-slate-400">{{ emp.badge_id ?? '—' }}</p>
                </td>
                <td class="px-4 py-3 text-slate-600 text-sm">{{ emp.position ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded text-xs font-medium', categoryBadge(emp.emp_category)]">
                    {{ emp.emp_category ?? '—' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <button @click="openModal(emp)"
                          class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                    <CheckCircleIcon class="w-3.5 h-3.5" /> Set Opening Balance
                  </button>
                </td>
              </tr>
              <tr v-if="pageData.length === 0">
                <td colspan="4" class="py-16 text-center text-slate-400 text-sm">No employees found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ curPage }} of {{ totalPages }}</span>
          <div class="flex gap-2">
            <button @click="goToPage(curPage - 1)" :disabled="curPage === 1 || isLoading"
                    class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-sm font-medium disabled:opacity-50">Prev</button>
            <button @click="goToPage(curPage + 1)" :disabled="curPage === totalPages || isLoading"
                    class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-sm font-medium disabled:opacity-50">Next</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Opening Balance Modal ──────────────────────────────────────────────── -->
    <div v-if="showModal && selected" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col">

        <!-- Header -->
        <div class="px-6 py-4 border-b border-slate-100 flex items-start justify-between shrink-0">
          <div>
            <h2 class="text-base font-semibold text-slate-800">Opening Balance — {{ selected.name }}</h2>
            <div class="flex items-center gap-2 mt-1">
              <span :class="['inline-flex items-center px-2 py-0.5 rounded text-xs font-medium', categoryBadge(selected.emp_category)]">
                {{ selected.emp_category }}
              </span>
              <span v-if="isTeaching && !isSpecialChief" class="text-xs text-blue-600 font-medium">· Wellness Leave + Service Credits</span>
              <span v-else-if="isSpecialChief" class="text-xs text-blue-600 font-medium">· VL · SL · Wellness Leave + Service Credits</span>
              <span v-else class="text-xs text-amber-600 font-medium">· VL · SL · CTO · Wellness Leave</span>
            </div>
          </div>
          <button @click="closeModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 text-lg leading-none">&times;</button>
        </div>

        <div class="overflow-y-auto flex-1 px-6 py-5 space-y-5">

          <!-- Year -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Year</label>
            <input v-model.number="form.year" type="number" min="2000" max="2100"
                   class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>

          <!-- ── Leave Credit Balances ───────────────────────────────────────── -->
          <div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Leave Credit Balances</p>
            <div class="space-y-2">
              <div v-for="(b, i) in form.balances" :key="b.leave_type_code"
                   class="flex items-center gap-3">
                <div class="w-40 shrink-0">
                  <p class="text-sm font-medium text-slate-700">{{ b.leave_type_code }}</p>
                  <p class="text-xs text-slate-400">{{ b.label }}</p>
                </div>
                <input v-model.number="form.balances[i].amount" type="number" min="0" step="0.5"
                       placeholder="0"
                       class="flex-1 px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <!-- Service Credits row (Teaching only) — same style as leave type rows -->
              <div v-if="isTeaching" class="flex items-center gap-3">
                <div class="w-40 shrink-0">
                  <p class="text-sm font-medium text-slate-700">SC</p>
                  <p class="text-xs text-slate-400">Service Credits</p>
                </div>
                <input v-model.number="form.sc_balance" type="number" min="0" step="0.5"
                       placeholder="0"
                       class="flex-1 px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>
          </div>

          <!-- ── Shared Remarks & Force override ──────────────────────────── -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">
              Remarks / Source <span class="text-red-500">*</span>
            </label>
            <textarea v-model="form.remarks" rows="2" maxlength="500"
                      placeholder="e.g. Imported from HR records as of January 1, 2026"
                      class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
          </div>

          <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
            <input v-model="form.force" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
            Override existing leave balances for this year
          </label>

        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2 shrink-0">
          <button @click="closeModal"
                  class="px-4 py-2 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-sm font-medium">
            Cancel
          </button>
          <button @click="submit" :disabled="isSubmitting"
                  class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition-colors disabled:opacity-50">
            {{ isSubmitting ? 'Saving…' : 'Save Opening Balance' }}
          </button>
        </div>

      </div>
    </div>
  </AdminLayout>
</template>
