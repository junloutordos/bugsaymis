<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
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

// ── Search ────────────────────────────────────────────────────────────────────
const applySearch = () => {
  isLoading.value = true
  router.get(route('hr.leave-credits.initialize'), { search: search.value || undefined }, {
    preserveState: true, replace: true,
    only: ['employees', 'filters', 'specialChiefIds'],
    onFinish: () => { isLoading.value = false },
  })
}

const clearSearch = () => {
  search.value = ''
  isLoading.value = true
  router.get(route('hr.leave-credits.initialize'), {}, {
    preserveState: true, replace: true,
    only: ['employees', 'filters', 'specialChiefIds'],
    onFinish: () => { isLoading.value = false },
  })
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
// Non-Teaching → VL, SL, CTO, WL, FL, SPL
const visibleLeaveTypes = computed(() => {
  if (!selected.value) return []
  if (isTeaching.value && !isSpecialChief.value) {
    return (props.leaveTypes ?? []).filter(lt => lt.code === 'WL')
  }
  if (isSpecialChief.value) {
    return (props.leaveTypes ?? []).filter(lt => ['VL', 'SL', 'WL'].includes(lt.code))
  }
  return props.leaveTypes ?? []   // VL, SL, CTO, WL, FL, SPL
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
      return true   // Non-teaching: VL, SL, CTO, WL, FL, SPL
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

const categoryBadgeColor = (cat) => {
  if (!cat) return 'slate'
  return TEACHING_CATEGORIES.includes(cat) ? 'blue' : 'amber'
}
</script>

<template>
  <Head title="Initialize Leave Credits" />
  <AdminLayout title="Initialize Leave Credits">
    <div class="space-y-5">

      <AppPageHeader
        title="Initialize Leave Credits"
        subtitle="Set opening balances for employees migrating from manual or legacy records. Teaching: Wellness Leave + Service Credits. SSD/CID Division Chiefs (Teaching): VL · SL · Wellness Leave + Service Credits. Non-Teaching: VL · SL · CTO · Wellness Leave · Mandatory/Forced Leave · Special Privilege Leave."
      />

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">{{ $page.props.flash.success }}</div>
      <div v-if="$page.props.flash?.error"   class="bg-danger-50 border border-danger-100 text-danger-700 rounded-lg px-4 py-3 text-sm">{{ $page.props.flash.error }}</div>

      <!-- Search -->
      <AppFilterBar>
        <div class="relative flex-1">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input v-model="search" type="text" placeholder="Search by name or employee ID…"
                 @keydown.enter.prevent="applySearch"
                 class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <template #actions>
          <AppButton size="sm" :disabled="isLoading" @click="applySearch">Search</AppButton>
          <AppButton v-if="search" size="sm" variant="secondary" :disabled="isLoading" @click="clearSearch">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="pageData.length === 0" :skeleton-cols="4">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Employee</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Position</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Category</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Action</th>
          </tr>
        </template>

        <tr v-for="emp in pageData" :key="emp.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3">
            <p class="font-medium text-slate-800">{{ emp.name }}</p>
            <p class="text-xs text-slate-400">{{ emp.badge_id ?? '—' }}</p>
          </td>
          <td class="px-4 py-3 text-slate-600 text-sm">{{ emp.position ?? '—' }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="categoryBadgeColor(emp.emp_category)">{{ emp.emp_category ?? '—' }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <AppButton size="sm" @click="openModal(emp)">
              <CheckCircleIcon class="w-3.5 h-3.5" /> Set Opening Balance
            </AppButton>
          </td>
        </tr>

        <template #empty>
          <EmptyState title="No employees found." />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="curPage"
            :total-pages="totalPages"
            @prev="goToPage(curPage - 1)"
            @next="goToPage(curPage + 1)"
            @page="goToPage"
          />
        </template>
      </AppTable>
    </div>

    <!-- ── Opening Balance Modal ──────────────────────────────────────────────── -->
    <AppModal
      :show="showModal && !!selected"
      :title="selected ? `Opening Balance — ${selected.name}` : ''"
      size="lg"
      @close="closeModal"
    >
      <template v-if="selected">
        <div class="flex items-center gap-2 mb-5">
          <AppBadge :color="categoryBadgeColor(selected.emp_category)">{{ selected.emp_category }}</AppBadge>
          <span v-if="isTeaching && !isSpecialChief" class="text-xs text-blue-600 font-medium">· Wellness Leave + Service Credits</span>
          <span v-else-if="isSpecialChief" class="text-xs text-blue-600 font-medium">· VL · SL · Wellness Leave + Service Credits</span>
          <span v-else class="text-xs text-amber-600 font-medium">· VL · SL · CTO · Wellness Leave · FL · SPL</span>
        </div>

        <div class="space-y-5">
          <!-- Year -->
          <AppInput v-model.number="form.year" type="number" min="2000" max="2100" label="Year" />

          <!-- ── Leave Credit Balances ───────────────────────────────────────── -->
          <div>
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Leave Credit Balances</p>
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
          <AppTextarea
            v-model="form.remarks"
            rows="2" maxlength="500"
            label="Remarks / Source" required
            placeholder="e.g. Imported from HR records as of January 1, 2026"
          />

          <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
            <input v-model="form.force" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
            Override existing leave balances for this year
          </label>
        </div>
      </template>

      <template #footer>
        <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
        <AppButton :loading="isSubmitting" @click="submit">
          {{ isSubmitting ? 'Saving…' : 'Save Opening Balance' }}
        </AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
