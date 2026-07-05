<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { MagnifyingGlassIcon, ArrowUpTrayIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppButton from '@/Components/AppButton.vue'
import EmptyState from '@/Components/EmptyState.vue'

const props = defineProps({
  employees:  Array,
  leaveTypes: Array,
  filters:    Object,
})

const search       = ref(props.filters?.search ?? '')
const selected     = ref(null)

const form = useForm({
  user_id:        null,
  leave_type_id:  '',
  date_from:      '',
  date_to:        '',
  days_applied:   '',
  is_without_pay: false,
  legacy_ref:     '',
})

// ── Employee search ─────────────────────────────────────────────────────────

const applySearch = () => {
  router.get(route('hr.leave.legacy-import.index'), { search: search.value || undefined }, {
    preserveState: true,
    replace: true,
    only: ['employees', 'filters'],
  })
}

const clearSearch = () => {
  search.value = ''
  router.get(route('hr.leave.legacy-import.index'), {}, {
    preserveState: true,
    replace: true,
    only: ['employees', 'filters'],
  })
}

const selectEmployee = (emp) => {
  selected.value       = emp
  form.user_id         = emp.id
  // reset leave fields when employee changes
  form.leave_type_id   = ''
  form.date_from       = ''
  form.date_to         = ''
  form.days_applied    = ''
  form.is_without_pay  = false
  form.legacy_ref      = ''
}

// ── Auto-compute working days when dates change ─────────────────────────────
// Simple weekday count (Mon–Fri); HR can override manually

const countWorkdays = (from, to) => {
  if (!from || !to) return ''
  let count = 0
  const d   = new Date(from)
  const end = new Date(to)
  while (d <= end) {
    const day = d.getDay()
    if (day !== 0 && day !== 6) count++
    d.setDate(d.getDate() + 1)
  }
  return count > 0 ? count : ''
}

watch([() => form.date_from, () => form.date_to], ([f, t]) => {
  if (f && t && t >= f) {
    form.days_applied = countWorkdays(f, t)
  }
})

// ── Submit ──────────────────────────────────────────────────────────────────

const submit = () => {
  form.post(route('hr.leave.legacy-import.store'), {
    preserveScroll: true,
    onSuccess: () => {
      selected.value       = null
      form.reset()
    },
  })
}
</script>

<template>
  <Head title="Import Legacy Leave" />
  <AdminLayout title="Import Legacy Leave">
    <div class="max-w-4xl mx-auto space-y-6">

      <!-- Header -->
      <AppPageHeader title="Import Legacy Leave" subtitle="Record approved leave from the previous system so it appears in DTR." />

      <div class="rounded-lg bg-warning-50 border border-warning-100 px-4 py-3 text-xs text-warning-700">
        Credits are <strong>not re-deducted</strong> — the old system already handled that.
        After importing, re-run DTR generation for the affected date range.
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

        <!-- Left: Employee picker -->
        <AppCard title="1. Select Employee">
          <div class="space-y-4">
            <!-- Search -->
            <div class="relative">
              <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
              <input
                v-model="search"
                @keydown.enter.prevent="applySearch"
                type="text"
                placeholder="Search by name or badge ID…"
                class="rounded-lg border border-slate-200 bg-white pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
              />
            </div>
            <div class="flex gap-2">
              <AppButton size="sm" @click="applySearch">Search</AppButton>
              <AppButton v-if="search" size="sm" variant="secondary" @click="clearSearch">Clear</AppButton>
            </div>

            <!-- Employee list -->
            <ul class="divide-y divide-slate-100 max-h-72 overflow-y-auto rounded-lg border border-slate-100">
              <li v-if="employees.length === 0" class="px-4 py-6">
                <EmptyState title="No employees found" />
              </li>
              <li
                v-for="emp in employees"
                :key="emp.id"
                @click="selectEmployee(emp)"
                class="flex items-center justify-between px-4 py-3 cursor-pointer hover:bg-indigo-50 transition-colors"
                :class="selected?.id === emp.id ? 'bg-indigo-50 border-l-2 border-indigo-500' : ''"
              >
                <div>
                  <p class="text-sm font-medium text-slate-800">{{ emp.name }}</p>
                  <p class="text-xs text-slate-500">{{ emp.badge_id ?? '—' }} · {{ emp.emp_category }}</p>
                </div>
                <CheckCircleIcon v-if="selected?.id === emp.id" class="h-5 w-5 text-indigo-500 flex-shrink-0" />
              </li>
            </ul>
          </div>
        </AppCard>

        <!-- Right: Leave details form -->
        <AppCard title="2. Leave Details">
          <!-- No employee selected state -->
          <EmptyState v-if="!selected" title="Select an employee on the left to fill in leave details." />

          <div v-else class="space-y-4">
            <!-- Selected employee pill -->
            <div class="flex items-center gap-2 rounded-lg bg-indigo-50 border border-indigo-100 px-3 py-2">
              <CheckCircleIcon class="h-4 w-4 text-indigo-500 flex-shrink-0" />
              <span class="text-sm font-medium text-indigo-700">{{ selected.name }}</span>
            </div>

            <!-- Leave type -->
            <AppSelect v-model="form.leave_type_id" label="Leave Type" required placeholder="Select leave type…" :error="form.errors.leave_type_id">
              <option v-for="lt in leaveTypes" :key="lt.id" :value="lt.id">
                {{ lt.code }} — {{ lt.name }}
              </option>
            </AppSelect>

            <!-- Date range -->
            <div class="grid grid-cols-2 gap-3">
              <AppInput v-model="form.date_from" type="date" label="Date From" required :error="form.errors.date_from" />
              <AppInput v-model="form.date_to" type="date" label="Date To" required :min="form.date_from" :error="form.errors.date_to" />
            </div>

            <!-- Days applied -->
            <div>
              <AppInput
                v-model="form.days_applied"
                type="number" min="0.5" step="0.5" placeholder="e.g. 1"
                label="Days Applied" required :error="form.errors.days_applied"
              />
              <p class="text-xs text-slate-400 mt-1">Auto-computed from the date range (weekdays only). Adjust if needed.</p>
            </div>

            <!-- Legacy reference number -->
            <div>
              <AppInput v-model="form.legacy_ref" type="text" placeholder="e.g. LV-2024-00123" label="Legacy Reference No." />
              <p class="text-xs text-slate-400 mt-1">Control number or reference from the old system (optional).</p>
            </div>

            <!-- Without pay -->
            <label class="flex items-center gap-2 cursor-pointer text-sm text-slate-700">
              <input
                v-model="form.is_without_pay"
                type="checkbox"
                class="accent-indigo-600 h-4 w-4"
              />
              Mark as Leave Without Pay (LWOP)
            </label>

            <!-- Reminder banner -->
            <div class="rounded-lg bg-warning-50 border border-warning-100 px-4 py-3 text-xs text-warning-700 leading-relaxed">
              <strong>After importing:</strong> go to HR &rsaquo; DTR Management, select this employee, and
              re-run DTR generation for the leave date range so the records reflect
              <em>On Leave</em>.
            </div>

            <!-- Submit -->
            <AppButton
              block
              @click="submit"
              :disabled="form.processing || !form.user_id || !form.leave_type_id || !form.date_from || !form.date_to || !form.days_applied"
              :loading="form.processing"
            >
              <ArrowUpTrayIcon class="h-4 w-4" />
              {{ form.processing ? 'Importing…' : 'Import Legacy Leave' }}
            </AppButton>
          </div>
        </AppCard>
      </div>

    </div>
  </AdminLayout>
</template>
