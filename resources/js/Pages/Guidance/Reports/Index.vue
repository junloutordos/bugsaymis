<template>
  <Head title="Guidance Transaction Report" />
  <AdminLayout title="Guidance Transaction Report">
    <template #default>
      <div class="space-y-5">

        <AppPageHeader title="Transaction Report" subtitle="Filter and export guidance consultation records" />

        <!-- sub-nav -->
        <div class="flex items-center gap-2 text-sm flex-wrap">
          <Link href="/guidance/dashboard" class="px-3 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition-colors">Dashboard</Link>
          <Link href="/guidance/consultations" class="px-3 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition-colors">Consultations</Link>
          <Link href="/guidance/session-reports" class="px-3 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition-colors">Session Reports</Link>
          <span class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-medium">Reports</span>
        </div>

        <!-- Filter bar -->
        <AppFilterBar>
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

          <template #actions>
            <AppButton @click="applyFilters">Apply Filters</AppButton>
            <AppButton variant="secondary" @click="resetFilters">Reset</AppButton>
            <AppButton variant="secondary" @click="printReport">Print / Export</AppButton>
          </template>
        </AppFilterBar>

        <!-- Summary pills -->
        <div class="flex flex-wrap gap-2 text-xs">
          <AppBadge color="slate">Total Records: {{ filtered.length }}</AppBadge>
          <AppBadge v-for="(cnt, label) in summaryByStatus" :key="label" color="indigo">{{ label }}: {{ cnt }}</AppBadge>
        </div>

        <!-- Table -->
        <div id="report-table">
          <AppTable :is-empty="!filtered.length" :skeleton-cols="8">
            <template #head>
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
            </template>

            <tr v-for="(row, i) in filtered" :key="row.id" class="hover:bg-slate-50 transition-colors">
              <td class="px-4 py-2.5 text-slate-500">{{ i + 1 }}</td>
              <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap">{{ fmtDate(row.created_at) }}</td>
              <td class="px-4 py-2.5 font-medium text-slate-800">{{ row.requestor_name ?? '—' }}</td>
              <td class="px-4 py-2.5 text-slate-600">{{ row.concern ?? '—' }}</td>
              <td class="px-4 py-2.5">
                <AppBadge :color="row.consultation_type === 'referred' ? 'orange' : 'blue'">
                  {{ row.consultation_type === 'referred' ? 'Referred' : 'Self-request' }}
                </AppBadge>
              </td>
              <td class="px-4 py-2.5">
                <AppBadge :color="statusBadgeColor(row.status)">{{ row.status }}</AppBadge>
              </td>
              <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap">{{ row.date_time_assigned ? fmtDate(row.date_time_assigned) : '—' }}</td>
              <td class="px-4 py-2.5 text-slate-600">{{ row.referred_by_name ?? '—' }}</td>
            </tr>

            <template #mobileCard>
              <div v-for="(row, i) in filtered" :key="row.id" class="p-4 space-y-2">
                <div class="flex items-start justify-between gap-2">
                  <div>
                    <p class="text-xs text-slate-400">#{{ i + 1 }} &middot; {{ fmtDate(row.created_at) }}</p>
                    <p class="font-medium text-slate-800">{{ row.requestor_name ?? '—' }}</p>
                    <p class="text-xs text-slate-500">{{ row.concern ?? '—' }}</p>
                  </div>
                  <AppBadge :color="statusBadgeColor(row.status)">{{ row.status }}</AppBadge>
                </div>
                <div class="flex items-center gap-2">
                  <AppBadge :color="row.consultation_type === 'referred' ? 'orange' : 'blue'">
                    {{ row.consultation_type === 'referred' ? 'Referred' : 'Self-request' }}
                  </AppBadge>
                  <span class="text-xs text-slate-400">Referred by {{ row.referred_by_name ?? '—' }}</span>
                </div>
                <p class="text-xs text-slate-400">Assigned {{ row.date_time_assigned ? fmtDate(row.date_time_assigned) : '—' }}</p>
              </div>
            </template>

            <template #empty>
              <EmptyState title="No records found for the selected filters." />
            </template>

            <template #footer>
              <p v-if="filtered.length" class="px-4 py-2 text-right text-xs text-slate-500 font-medium">
                Total: {{ filtered.length }} record(s)
              </p>
            </template>
          </AppTable>
        </div>

      </div>
    </template>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'

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

function statusBadgeColor(s) {
  const map = {
    pending:                        'amber',
    scheduled:                      'blue',
    'For Follow-up':                'purple',
    'For Monitoring':               'blue',
    'Done Intervention':            'green',
    'Refer to School Psychologist': 'red',
  }
  return map[s] ?? 'slate'
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
