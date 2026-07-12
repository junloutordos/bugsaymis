<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppCard from '@/Components/AppCard.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { MagnifyingGlassIcon, AdjustmentsHorizontalIcon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'

const props = defineProps({
  employees:      Object,
  leaveTypes:     Array,
  selectedUser:   Object,
  currentCredits: Object,
  filters:        Object,
  currentYear:    Number,
})

const search       = ref(props.filters?.search ?? '')
const isLoading    = ref(false)
const isSubmitting = ref(false)

const form = ref({
  user_id:         props.selectedUser?.id ?? null,
  year:            props.filters?.year ?? props.currentYear,
  leave_type_code: '',
  amount:          '',
  remarks:         '',
})

// If a user is already selected, keep form in sync when page reloads
watch(() => props.selectedUser, (u) => {
  if (u) form.value.user_id = u.id
})

const applySearch = () => {
  isLoading.value = true
  router.get(route('hr.leave-credits.adjust'), { search: search.value || undefined }, {
    preserveState: true, replace: true,
    only: ['employees', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

const clearSearch = () => {
  search.value = ''
  isLoading.value = true
  router.get(route('hr.leave-credits.adjust'), {}, {
    preserveState: true, replace: true,
    only: ['employees', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

const selectEmployee = (emp) => {
  isLoading.value = true
  form.value.user_id = emp.id
  router.get(route('hr.leave-credits.adjust'), {
    search: search.value || undefined,
    user_id: emp.id,
    year: form.value.year,
  }, {
    preserveState: true, replace: true,
    only: ['selectedUser', 'currentCredits', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

const changeYear = () => {
  if (!props.selectedUser) return
  isLoading.value = true
  router.get(route('hr.leave-credits.adjust'), {
    search: search.value || undefined,
    user_id: props.selectedUser.id,
    year: form.value.year,
  }, {
    preserveState: true, replace: true,
    only: ['currentCredits', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

const submit = () => {
  if (!form.value.user_id) return Swal.fire('Select Employee', 'Please select an employee first.', 'warning')
  if (!form.value.leave_type_code) return Swal.fire('Select Leave Type', 'Please select a leave type.', 'warning')
  if (!form.value.amount || Number(form.value.amount) === 0) return Swal.fire('Enter Amount', 'Amount cannot be 0.', 'warning')
  if (!form.value.remarks.trim()) return Swal.fire('Remarks Required', 'Please explain the reason for this adjustment.', 'warning')

  const direction = Number(form.value.amount) > 0 ? 'ADD' : 'DEDUCT'
  Swal.fire({
    title: `${direction} ${Math.abs(Number(form.value.amount))} day(s)?`,
    text: form.value.remarks,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, save',
    reverseButtons: true,
  }).then(r => {
    if (!r.isConfirmed) return
    isSubmitting.value = true
    router.post(route('hr.leave-credits.adjust.store'), form.value, {
      onSuccess: () => {
        Swal.fire('Saved!', 'Adjustment recorded.', 'success')
        form.value.leave_type_code = ''
        form.value.amount = ''
        form.value.remarks = ''
        // Reload current credits
        if (props.selectedUser) selectEmployee(props.selectedUser)
      },
      onError: (errors) => Swal.fire('Error', Object.values(errors)[0], 'error'),
      onFinish: () => { isSubmitting.value = false },
    })
  })
}

const pageData   = computed(() => props.employees?.data ?? [])
const totalPages = computed(() => props.employees?.last_page ?? 1)
const curPage    = computed(() => props.employees?.current_page ?? 1)

const goToPage = (p) => {
  isLoading.value = true
  router.get(route('hr.leave-credits.adjust'), {
    search: search.value || undefined,
    user_id: props.selectedUser?.id,
    year: form.value.year,
    page: p,
  }, {
    preserveState: true, replace: true,
    only: ['employees'],
    onFinish: () => { isLoading.value = false },
  })
}

const balanceSummary = computed(() => {
  if (!props.currentCredits) return []
  return Object.entries(props.currentCredits)
    .filter(([, v]) => !v.is_service_credit)
    .map(([code, v]) => ({ code, balance: v.balance, earned: v.earned, used: v.used }))
})
</script>

<template>
  <Head title="Adjust Leave Credits" />
  <AdminLayout title="Adjust Leave Credits">
    <div class="space-y-5">

      <AppPageHeader title="Adjust Leave Credits" subtitle="Add or remove leave credits with a required audit remarks." />

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">{{ $page.props.flash.success }}</div>
      <div v-if="$page.props.flash?.error"   class="bg-danger-50 border border-danger-100 text-danger-700 rounded-lg px-4 py-3 text-sm">{{ $page.props.flash.error }}</div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        <!-- Employee picker -->
        <div class="space-y-4">
          <AppFilterBar>
            <div class="relative flex-1">
              <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input v-model="search" type="text" placeholder="Search employee…"
                     @keydown.enter.prevent="applySearch"
                     class="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <template #actions>
              <AppButton size="sm" :disabled="isLoading" @click="applySearch">Search</AppButton>
              <AppButton v-if="search" size="sm" variant="secondary" :disabled="isLoading" @click="clearSearch">Clear</AppButton>
            </template>
          </AppFilterBar>

          <AppTable :is-empty="pageData.length === 0" :skeleton-cols="3">
            <template #head>
              <tr>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Employee</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Category</th>
                <th class="px-4 py-3"></th>
              </tr>
            </template>

            <tr v-for="emp in pageData" :key="emp.id"
                :class="['hover:bg-indigo-50/40 cursor-pointer', selectedUser?.id === emp.id ? 'bg-indigo-50' : '']"
                @click="selectEmployee(emp)">
              <td class="px-4 py-3">
                <p class="font-medium text-slate-800">{{ emp.name }}</p>
                <p class="text-xs text-slate-400">{{ emp.badge_id ?? '—' }}</p>
              </td>
              <td class="px-4 py-3 text-xs text-slate-500">{{ emp.emp_category ?? '—' }}</td>
              <td class="px-4 py-3 text-right">
                <span v-if="selectedUser?.id === emp.id" class="text-indigo-600 text-xs font-medium">Selected</span>
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

        <!-- Adjustment panel -->
        <div class="space-y-4">

          <!-- Current balances -->
          <AppCard v-if="selectedUser">
            <template #header>
              <div class="flex items-center justify-between gap-3 w-full">
                <h2 class="text-sm font-semibold text-slate-800">Current Balances — {{ selectedUser.name }}</h2>
                <div class="flex items-center gap-2">
                  <label class="text-xs text-slate-500">Year:</label>
                  <input v-model.number="form.year" type="number" min="2000" max="2100"
                         @change="changeYear"
                         class="w-20 px-2 py-1 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
              </div>
            </template>
            <div v-if="balanceSummary.length" class="grid grid-cols-2 gap-3">
              <div v-for="b in balanceSummary" :key="b.code"
                   class="rounded-lg bg-slate-50 border border-slate-100 p-3">
                <p class="text-xs font-semibold text-slate-500 uppercase">{{ b.code }}</p>
                <p class="text-2xl font-bold text-slate-800 mt-0.5">{{ b.balance }}</p>
                <p class="text-xs text-slate-400 mt-0.5">Earned: {{ b.earned }} · Used: {{ b.used }}</p>
              </div>
            </div>
            <p v-else class="text-sm text-slate-400 text-center py-4">No credit records for {{ form.year }}.</p>
          </AppCard>

          <AppCard v-if="!selectedUser">
            <EmptyState title="Select an employee on the left to adjust their credits." />
          </AppCard>

          <!-- Adjustment form -->
          <AppCard v-if="selectedUser">
            <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2 mb-4">
              <AdjustmentsHorizontalIcon class="w-4 h-4 text-indigo-600" /> New Adjustment
            </h2>

            <div class="space-y-4">
              <AppSelect v-model="form.leave_type_code" label="Leave Type" placeholder="— select —">
                <option v-for="lt in leaveTypes" :key="lt.code" :value="lt.code">{{ lt.code }} — {{ lt.name }}</option>
              </AppSelect>

              <AppInput
                v-model="form.amount"
                type="number" step="0.5"
                label="Amount (days) — positive = add, negative = deduct"
                placeholder="e.g. 2.5 or -1"
              />

              <AppTextarea
                v-model="form.remarks"
                rows="3" maxlength="500"
                label="Remarks" required
                placeholder="Required: explain the reason for this adjustment…"
              />

              <AppButton block :loading="isSubmitting" @click="submit">
                {{ isSubmitting ? 'Saving…' : 'Save Adjustment' }}
              </AppButton>
            </div>
          </AppCard>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
