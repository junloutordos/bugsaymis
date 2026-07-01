<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { BanknotesIcon, MagnifyingGlassIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  fees:    Array,
  summary: Object,
  filters: Object,
  myHall:  String,
})

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December']

const month  = ref(props.filters.month)
const year   = ref(props.filters.year)
const isPaid = ref(props.filters.isPaid ?? '')
const search = ref(props.filters.search || '')

const showGenerate = ref(false)
const genMonth = ref(props.filters.month)
const genYear  = ref(props.filters.year)

const PER_PAGE = 15
const currentPage = ref(1)

const filtered = computed(() => {
  const lower = search.value.toLowerCase()
  return props.fees.filter(f =>
    !lower || f.student_name.toLowerCase().includes(lower)
  )
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed  = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

function applyFilters() {
  currentPage.value = 1
  router.get(route('rh.fees.index'), { month: month.value, year: year.value, is_paid: isPaid.value, search: search.value }, { preserveState: true, replace: true })
}

function markPaid(fee) {
  router.post(route('rh.fees.mark-paid', fee.id), {}, { preserveScroll: true })
}

function generate() {
  router.post(route('rh.fees.generate'), { month: genMonth.value, year: genYear.value }, {
    preserveScroll: true,
    onSuccess: () => { showGenerate.value = false },
  })
}

const fmt = (n) => Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'

const currentYears = computed(() => {
  const y = new Date().getFullYear()
  return [y - 1, y, y + 1]
})
</script>

<template>
  <Head title="Fee Ledger" />
  <AdminLayout title="Residence Hall">
    <div class="space-y-5">

      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Fee Ledger</h1>
          <p class="text-sm text-slate-500">Monthly lodging and appliance fees</p>
        </div>
        <button @click="showGenerate = true"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          <ArrowPathIcon class="w-4 h-4" /> Generate Month
        </button>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
          <p class="text-xs text-slate-500 mb-1">Total Dormers</p>
          <p class="text-2xl font-bold text-slate-800">{{ summary.total_interns }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
          <p class="text-xs text-slate-500 mb-1">Total Billed</p>
          <p class="text-lg font-bold text-slate-800">₱{{ fmt(summary.total_amount) }}</p>
        </div>
        <div class="bg-emerald-50 rounded-xl border border-emerald-100 shadow-sm p-4">
          <p class="text-xs text-emerald-600 mb-1">Collected</p>
          <p class="text-lg font-bold text-emerald-700">₱{{ fmt(summary.paid_amount) }}</p>
        </div>
        <div class="bg-amber-50 rounded-xl border border-amber-100 shadow-sm p-4">
          <p class="text-xs text-amber-600 mb-1">Unpaid</p>
          <p class="text-2xl font-bold text-amber-700">{{ summary.unpaid_count }}</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap gap-3 items-end">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Month</label>
          <select v-model.number="month" @change="applyFilters"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option v-for="(m, i) in MONTHS" :key="i + 1" :value="i + 1">{{ m }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Year</label>
          <select v-model.number="year" @change="applyFilters"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option v-for="y in currentYears" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Payment</label>
          <select v-model="isPaid" @change="applyFilters"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All</option>
            <option value="0">Unpaid</option>
            <option value="1">Paid</option>
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
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden md:table-cell">Hall / Room</th>
              <th class="text-right px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Lodging</th>
              <th class="text-right px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide hidden lg:table-cell">Appliance</th>
              <th class="text-right px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Total</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="f in displayed" :key="f.id" class="hover:bg-slate-50 transition-colors">
              <td class="px-4 py-3 font-medium text-slate-800">{{ f.student_name }}</td>
              <td class="px-4 py-3 text-slate-600 text-xs hidden md:table-cell">
                <span v-if="f.residence_hall"
                      :class="['px-1.5 py-0.5 rounded-full font-medium mr-1', f.residence_hall === 'BRH' ? 'bg-indigo-100 text-indigo-700' : 'bg-pink-100 text-pink-700']">
                  {{ f.residence_hall }}
                </span>
                Rm {{ f.room_number || '—' }}
              </td>
              <td class="px-4 py-3 text-right text-slate-600">₱{{ fmt(f.lodging_fee) }}</td>
              <td class="px-4 py-3 text-right text-slate-500 hidden lg:table-cell">₱{{ fmt(f.appliance_fee) }}</td>
              <td class="px-4 py-3 text-right font-semibold text-slate-800">₱{{ fmt(f.total_fee) }}</td>
              <td class="px-4 py-3">
                <div>
                  <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', f.is_paid ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700']">
                    {{ f.is_paid ? 'Paid' : 'Unpaid' }}
                  </span>
                  <p v-if="f.paid_at" class="text-xs text-slate-400 mt-0.5">{{ fmtDate(f.paid_at) }}</p>
                </div>
              </td>
              <td class="px-4 py-3">
                <button v-if="!f.is_paid" @click="markPaid(f)"
                        class="text-xs text-indigo-600 hover:underline font-medium">Mark Paid</button>
              </td>
            </tr>
            <tr v-if="!displayed.length">
              <td colspan="7" class="text-center py-12 text-slate-400 text-sm">
                No fee records for this period.
                <span class="block mt-1 text-xs">Use "Generate Month" to create fee records for all active dormers.</span>
              </td>
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

    </div>

    <!-- Generate Modal -->
    <div v-if="showGenerate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 p-6">
        <h3 class="text-base font-semibold text-slate-800 mb-1">Generate Monthly Fees</h3>
        <p class="text-sm text-slate-500 mb-4">Creates fee records for all active dormers in the selected month.</p>
        <div class="flex gap-3">
          <div class="flex-1">
            <label class="block text-xs font-medium text-slate-600 mb-1">Month</label>
            <select v-model.number="genMonth"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option v-for="(m, i) in MONTHS" :key="i + 1" :value="i + 1">{{ m }}</option>
            </select>
          </div>
          <div class="flex-1">
            <label class="block text-xs font-medium text-slate-600 mb-1">Year</label>
            <select v-model.number="genYear"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option v-for="y in currentYears" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>
        </div>
        <div class="flex gap-3 mt-5">
          <button @click="showGenerate = false"
                  class="flex-1 px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm hover:bg-slate-50">Cancel</button>
          <button @click="generate"
                  class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
            Generate
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>
