<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'
import { CheckCircleIcon, QuestionMarkCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  batch:     Object,
  matched:   Array,
  probable:  Array,
  unmatched: Array,
  users:     Array,
})

const activeTab        = ref('matched')
const resolutions      = ref({})
const submitting       = ref(false)
const sendLoading      = ref(false)
const secondHalfDate   = ref('')
const secondHalfLoading= ref(false)

const isMonthly = computed(() =>
  Array.isArray(props.batch.disbursement_type)
    ? props.batch.disbursement_type.includes('monthly_salary')
    : props.batch.disbursement_type === 'monthly_salary'
)

const needsSecondHalf = computed(() =>
  isMonthly.value &&
  !props.batch.second_half_credit_date &&
  ['sending', 'completed'].includes(props.batch.status)
)

const tabs = computed(() => [
  { key: 'matched',   label: 'Matched',   count: props.matched.length,   icon: CheckCircleIcon,        color: 'text-emerald-600' },
  { key: 'probable',  label: 'Probable',  count: props.probable.length,  icon: QuestionMarkCircleIcon, color: 'text-amber-500' },
  { key: 'unmatched', label: 'Unmatched', count: props.unmatched.length, icon: XCircleIcon,            color: 'text-red-500' },
])

const fmt = (n) => Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const currentItems = computed(() => {
  if (activeTab.value === 'matched')   return props.matched
  if (activeTab.value === 'probable')  return props.probable
  return props.unmatched
})

function setResolution(itemId, userId, saveAlias = false) {
  resolutions.value[itemId] = { user_id: userId, save_alias: saveAlias }
}

function saveResolutions() {
  const list = Object.entries(resolutions.value).map(([id, v]) => ({
    item_id:    parseInt(id),
    user_id:    v.user_id,
    save_alias: v.save_alias,
  }))
  if (!list.length) return

  submitting.value = true
  router.post(route('payroll.cashier.resolve', props.batch.id), { resolutions: list }, {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Matches saved', timer: 1200, showConfirmButton: false }),
    onFinish:  () => { submitting.value = false },
  })
}

function sendSecondHalf() {
  if (!secondHalfDate.value) {
    Swal.fire({ icon: 'warning', title: 'Please select the 2nd half credit date.' })
    return
  }
  Swal.fire({
    title: 'Send 2nd half notifications?',
    text: `Employees will be notified that their 2nd half salary has been credited on ${secondHalfDate.value}.`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, notify',
    reverseButtons: true,
  }).then((res) => {
    if (!res.isConfirmed) return
    secondHalfLoading.value = true
    router.post(route('payroll.cashier.send-second-half', props.batch.id), {
      second_half_credit_date: secondHalfDate.value,
    }, {
      onSuccess: () => Swal.fire({ icon: 'success', title: '2nd half notifications queued!', timer: 1500, showConfirmButton: false }),
      onFinish:  () => { secondHalfLoading.value = false },
    })
  })
}

function sendAll() {
  Swal.fire({
    title: 'Send all payslips?',
    text: `This will email ${props.matched.length} matched employees.`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, send',
    reverseButtons: true,
  }).then((res) => {
    if (!res.isConfirmed) return
    sendLoading.value = true
    router.post(route('payroll.cashier.send', props.batch.id), {}, {
      onSuccess: () => {
        Swal.fire({ icon: 'success', title: 'Payslips queued!', text: 'Emails are being sent in the background.' })
        router.get(route('payroll.cashier.preview', props.batch.id))
      },
      onFinish: () => { sendLoading.value = false },
    })
  })
}
</script>

<template>
  <Head title="Payroll Preview" />
  <AdminLayout title="Payroll Preview">
    <div>
      <!-- Batch header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Preview — {{ batch.payroll_no }}</h1>
          <p class="text-sm text-slate-500 mt-0.5">
            Period: {{ batch.period_start }} to {{ batch.period_end }} &nbsp;|&nbsp;
            Net Total: ₱ {{ fmt(batch.totals_net) }}
          </p>
        </div>
        <div class="flex gap-2">
          <a :href="route('payroll.cashier.audit-csv', batch.id)"
             class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            Download CSV
          </a>
          <button @click="sendAll" :disabled="sendLoading || !matched.length"
                  class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
            {{ sendLoading ? 'Queuing…' : `Send ${matched.length} Payslips` }}
          </button>
        </div>
      </div>

      <!-- 2nd Half Notification Panel -->
      <div v-if="needsSecondHalf"
           class="mb-6 bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="flex-1">
          <p class="text-sm font-semibold text-amber-800">2nd Half Not Yet Notified</p>
          <p class="text-xs text-amber-700 mt-0.5">
            Set the ATM credit date and click <strong>Notify 2nd Half</strong> to send notifications to all matched employees.
          </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <input v-model="secondHalfDate" type="date"
                 class="rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400" />
          <button @click="sendSecondHalf" :disabled="secondHalfLoading || !secondHalfDate"
                  class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 whitespace-nowrap">
            {{ secondHalfLoading ? 'Queuing…' : 'Notify 2nd Half' }}
          </button>
        </div>
      </div>

      <!-- Tabs -->
      <div class="flex gap-1 mb-4 bg-white rounded-xl border border-slate-100 shadow-sm p-1 w-fit">
        <button v-for="tab in tabs" :key="tab.key"
                @click="activeTab = tab.key"
                :class="['flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                         activeTab === tab.key ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-50']">
          <component :is="tab.icon" :class="['w-4 h-4', activeTab === tab.key ? 'text-white' : tab.color]" />
          {{ tab.label }}
          <span :class="['text-xs rounded-full px-1.5 py-0.5 font-semibold',
                         activeTab === tab.key ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600']">
            {{ tab.count }}
          </span>
        </button>
      </div>

      <!-- Save resolutions button (probable/unmatched) -->
      <div v-if="activeTab !== 'matched' && Object.keys(resolutions).length" class="mb-3 flex justify-end">
        <button @click="saveResolutions" :disabled="submitting"
                class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
          {{ submitting ? 'Saving…' : 'Save Matches' }}
        </button>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">#</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Excel Name</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Matched User</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Position</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Basic</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Net Pay</th>
              <th v-if="activeTab !== 'matched'" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Assign to</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="item in currentItems" :key="item.id" class="hover:bg-slate-50/60">
              <td class="px-4 py-3 text-slate-500 text-xs">{{ item.excel_row_number }}</td>
              <td class="px-4 py-3 font-medium text-slate-800">{{ item.employee_name_raw }}</td>
              <td class="px-4 py-3 text-slate-600 text-xs">
                {{ item.employee?.name ?? (resolutions[item.id] ? users.find(u=>u.id===resolutions[item.id].user_id)?.name : '—') }}
              </td>
              <td class="px-4 py-3 text-slate-600 text-xs">{{ item.position || '—' }}</td>
              <td class="px-4 py-3 text-right text-slate-700">{{ fmt(item.basic_salary) }}</td>
              <td class="px-4 py-3 text-right font-semibold text-slate-800">{{ fmt(item.net_pay) }}</td>
              <td v-if="activeTab !== 'matched'" class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <select @change="(e) => setResolution(item.id, parseInt(e.target.value))"
                          class="w-48 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">— Select employee —</option>
                    <option v-for="u in users" :key="u.id" :value="u.id"
                            :selected="item.matched_user_id === u.id || resolutions[item.id]?.user_id === u.id">
                      {{ u.name }} {{ u.employee_no ? `(${u.employee_no})` : '' }}
                    </option>
                  </select>
                  <label v-if="resolutions[item.id]?.user_id" class="flex items-center gap-1 text-xs text-slate-600 whitespace-nowrap">
                    <input type="checkbox" @change="(e) => setResolution(item.id, resolutions[item.id].user_id, e.target.checked)"
                           class="rounded" />
                    Save alias
                  </label>
                </div>
              </td>
            </tr>
            <tr v-if="!currentItems.length">
              <td :colspan="activeTab !== 'matched' ? 7 : 6" class="py-12 text-center text-slate-400 text-sm">
                No {{ activeTab }} rows.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>
