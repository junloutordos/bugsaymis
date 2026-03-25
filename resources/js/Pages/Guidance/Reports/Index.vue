<template>
  <Head title="Guidance Transaction Report" />
  <AdminLayout title="Guidance Transaction Report">
    <template #default>
      <div class="space-y-5">

        <!-- Page header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <h1 class="text-xl font-semibold text-slate-800">Transaction Report</h1>
            <p class="text-sm text-slate-500">Filter and export guidance consultation records</p>
          </div>
          <!-- sub-nav -->
          <div class="flex items-center gap-2 text-sm">
            <Link href="/guidance/dashboard" class="px-3 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition-colors">Dashboard</Link>
            <Link href="/guidance/consultations" class="px-3 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition-colors">Consultations</Link>
            <Link href="/guidance/session-reports" class="px-3 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition-colors">Session Reports</Link>
            <span class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-medium">Reports</span>
          </div>
        </div>

        <!-- Filter bar -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Date From</label>
              <input v-model="f.from" type="date" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Date To</label>
              <input v-model="f.to" type="date" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
              <select v-model="f.status" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Statuses</option>
                <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Concern</label>
              <select v-model="f.concern" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Concerns</option>
                <option value="Academic">Academic</option>
                <option value="Behavior">Behavior</option>
                <option value="Personal / Social">Personal / Social</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Type</label>
              <select v-model="f.type" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Types</option>
                <option value="student">Student (Self)</option>
                <option value="referred">Referred</option>
              </select>
            </div>
          </div>
          <div class="flex gap-2 mt-3">
            <button @click="applyFilters" class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
              Apply Filters
            </button>
            <button @click="resetFilters" class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50 transition-colors">
              Reset
            </button>
            <button @click="printReport" class="ml-auto inline-flex items-center gap-1.5 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
              Print / Export
            </button>
          </div>
        </div>

        <!-- Summary pills -->
        <div class="flex flex-wrap gap-2 text-xs">
          <span class="bg-slate-100 text-slate-600 px-3 py-1 rounded-full font-medium">Total Records: {{ filtered.length }}</span>
          <span v-for="(cnt, label) in summaryByStatus" :key="label" class="bg-indigo-50 text-indigo-700 px-3 py-1 rounded-full font-medium">{{ label }}: {{ cnt }}</span>
        </div>

        <!-- Table -->
        <div id="report-table" class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Date Filed</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Client</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Concern</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Assigned</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Referred By</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-if="!filtered.length">
                <td colspan="8" class="px-4 py-8 text-center text-slate-400">No records found for the selected filters.</td>
              </tr>
              <tr v-for="(row, i) in filtered" :key="row.id" class="hover:bg-slate-50 transition-colors">
                <td class="px-4 py-2.5 text-slate-500">{{ i + 1 }}</td>
                <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap">{{ fmtDate(row.created_at) }}</td>
                <td class="px-4 py-2.5 font-medium text-slate-800">{{ row.requestor_name ?? '—' }}</td>
                <td class="px-4 py-2.5 text-slate-600">{{ row.concern ?? '—' }}</td>
                <td class="px-4 py-2.5">
                  <span :class="row.consultation_type === 'referred' ? 'bg-orange-50 text-orange-700' : 'bg-blue-50 text-blue-700'" class="text-xs font-medium px-2 py-0.5 rounded-full">
                    {{ row.consultation_type === 'referred' ? 'Referred' : 'Self-request' }}
                  </span>
                </td>
                <td class="px-4 py-2.5">
                  <span :class="statusBadge(row.status)" class="text-xs font-medium px-2 py-0.5 rounded-full">{{ row.status }}</span>
                </td>
                <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap">{{ row.date_time_assigned ? fmtDate(row.date_time_assigned) : '—' }}</td>
                <td class="px-4 py-2.5 text-slate-600">{{ row.referred_by_name ?? '—' }}</td>
              </tr>
            </tbody>
            <tfoot v-if="filtered.length" class="bg-slate-50">
              <tr>
                <td colspan="8" class="px-4 py-2 text-right text-xs text-slate-500 font-medium">
                  Total: {{ filtered.length }} record(s)
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

      </div>
    </template>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  records: Array,
  filters: Object,
})

const statuses = ['pending', 'scheduled', 'For Follow-up', 'For Monitoring', 'Done Intervention', 'Refer to School Psychologist']

const f = ref({
  from:    props.filters?.from    ?? '',
  to:      props.filters?.to      ?? '',
  status:  props.filters?.status  ?? '',
  concern: props.filters?.concern ?? '',
  type:    props.filters?.type    ?? '',
})

// Client-side filter of the already-server-filtered records (for instant UX)
const filtered = computed(() => props.records ?? [])

const summaryByStatus = computed(() => {
  const counts = {}
  filtered.value.forEach(r => {
    const s = r.status ?? 'Unknown'
    counts[s] = (counts[s] ?? 0) + 1
  })
  return counts
})

function applyFilters() {
  const params = {}
  if (f.value.from)    params.from    = f.value.from
  if (f.value.to)      params.to      = f.value.to
  if (f.value.status)  params.status  = f.value.status
  if (f.value.concern) params.concern = f.value.concern
  if (f.value.type)    params.type    = f.value.type
  router.get('/guidance/reports', params, { preserveState: true })
}

function resetFilters() {
  f.value = { from: '', to: '', status: '', concern: '', type: '' }
  router.get('/guidance/reports', {}, { preserveState: true })
}

function printReport() {
  window.print()
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
}

function statusBadge(s) {
  const map = {
    pending:                        'bg-amber-50 text-amber-700',
    scheduled:                      'bg-blue-50 text-blue-700',
    'For Follow-up':                'bg-purple-50 text-purple-700',
    'For Monitoring':               'bg-teal-50 text-teal-700',
    'Done Intervention':            'bg-emerald-50 text-emerald-700',
    'Refer to School Psychologist': 'bg-rose-50 text-rose-700',
  }
  return map[s] ?? 'bg-slate-100 text-slate-600'
}
</script>

<style>
@media print {
  /* hide sidebar and header, show only table */
  nav, aside, header, .sidebar, [data-sidebar], button, a { display: none !important; }
  #report-table { box-shadow: none !important; border: 1px solid #ccc !important; }
  body { background: #fff !important; }
}
</style>
