<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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

      <div>
        <h1 class="text-xl font-semibold text-slate-800">Manual Credit Adjustment</h1>
        <p class="text-sm text-slate-500 mt-0.5">Add or remove leave credits with a required audit remarks.</p>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm">{{ $page.props.flash.success }}</div>
      <div v-if="$page.props.flash?.error"   class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">{{ $page.props.flash.error }}</div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        <!-- Employee picker -->
        <div class="space-y-4">
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex gap-3">
            <div class="relative flex-1">
              <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input v-model="search" type="text" placeholder="Search employee…"
                     @keydown.enter.prevent="applySearch"
                     class="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <button @click="applySearch" :disabled="isLoading"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">Search</button>
            <button v-if="search" @click="clearSearch" :disabled="isLoading"
                    class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">Clear</button>
          </div>

          <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
              <thead class="bg-slate-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Employee</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Category</th>
                  <th class="px-4 py-3"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="emp in pageData" :key="emp.id"
                    :class="['hover:bg-slate-50/60 cursor-pointer', selectedUser?.id === emp.id ? 'bg-indigo-50' : '']"
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
                <tr v-if="pageData.length === 0">
                  <td colspan="3" class="py-10 text-center text-slate-400 text-sm">No employees found.</td>
                </tr>
              </tbody>
            </table>
            <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
              <span>Page {{ curPage }} of {{ totalPages }}</span>
              <div class="flex gap-2">
                <button @click="goToPage(curPage-1)" :disabled="curPage===1||isLoading" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-sm disabled:opacity-50">Prev</button>
                <button @click="goToPage(curPage+1)" :disabled="curPage===totalPages||isLoading" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-sm disabled:opacity-50">Next</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Adjustment panel -->
        <div class="space-y-4">

          <!-- Current balances -->
          <div v-if="selectedUser" class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
              <h2 class="text-sm font-semibold text-slate-800">Current Balances — {{ selectedUser.name }}</h2>
              <div class="flex items-center gap-2">
                <label class="text-xs text-slate-500">Year:</label>
                <input v-model.number="form.year" type="number" min="2000" max="2100"
                       @change="changeYear"
                       class="w-20 px-2 py-1 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>
            <div v-if="balanceSummary.length" class="grid grid-cols-2 gap-3">
              <div v-for="b in balanceSummary" :key="b.code"
                   class="rounded-lg bg-slate-50 border border-slate-100 p-3">
                <p class="text-xs font-semibold text-slate-500 uppercase">{{ b.code }}</p>
                <p class="text-2xl font-bold text-slate-800 mt-0.5">{{ b.balance }}</p>
                <p class="text-xs text-slate-400 mt-0.5">Earned: {{ b.earned }} · Used: {{ b.used }}</p>
              </div>
            </div>
            <p v-else class="text-sm text-slate-400 text-center py-4">No credit records for {{ form.year }}.</p>
          </div>

          <div v-if="!selectedUser" class="bg-white rounded-xl border border-slate-100 shadow-sm p-10 text-center text-slate-400 text-sm">
            Select an employee on the left to adjust their credits.
          </div>

          <!-- Adjustment form -->
          <div v-if="selectedUser" class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 space-y-4">
            <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
              <AdjustmentsHorizontalIcon class="w-4 h-4 text-indigo-600" /> New Adjustment
            </h2>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Leave Type</label>
              <select v-model="form.leave_type_code"
                      class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">— select —</option>
                <option v-for="lt in leaveTypes" :key="lt.code" :value="lt.code">{{ lt.code }} — {{ lt.name }}</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">
                Amount (days)
                <span class="text-slate-400 font-normal ml-1">Positive = add · Negative = deduct</span>
              </label>
              <input v-model="form.amount" type="number" step="0.5"
                     placeholder="e.g. 2.5 or -1"
                     class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Remarks <span class="text-red-500">*</span></label>
              <textarea v-model="form.remarks" rows="3" maxlength="500"
                        placeholder="Required: explain the reason for this adjustment…"
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
            </div>

            <button @click="submit" :disabled="isSubmitting"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">
              {{ isSubmitting ? 'Saving…' : 'Save Adjustment' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
