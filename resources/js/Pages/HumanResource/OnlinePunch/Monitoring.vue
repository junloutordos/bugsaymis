<template>
  <Head title="Online Time Punches — Monitoring" />
  <AdminLayout title="Online Time Punches — Monitoring">
    <div class="space-y-5">

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap gap-3 items-end">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Month</label>
          <input v-model="filterMonth" type="month"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
          <select v-model="filterStatus"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All</option>
            <option value="verified">Verified</option>
            <option value="manual_review">Under Review</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>

        <div class="flex-1 min-w-[160px]">
          <label class="block text-xs font-medium text-slate-600 mb-1">Search Employee</label>
          <input v-model="searchQuery" type="text" placeholder="Name…"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
        </div>

        <button @click="load" :disabled="loading"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          {{ loading ? 'Loading…' : 'Apply' }}
        </button>
      </div>

      <!-- Table card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div v-if="!rows.length && !loading" class="py-16 text-center text-slate-400 text-sm">
          No online time punches found.
        </div>

        <div v-else class="overflow-x-auto rounded-xl border border-slate-100">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Employee</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Punch</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Time</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Liveness</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Match Score</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Photo</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="row in rows" :key="row.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-slate-800">{{ row.user?.name }}</td>
                <td class="px-4 py-3 text-slate-600">{{ row.work_date }}</td>
                <td class="px-4 py-3 text-slate-600">{{ punchLabel(row.punch_type) }}</td>
                <td class="px-4 py-3 text-slate-600">{{ fmtTime(row.punched_at) }}</td>
                <td class="px-4 py-3 text-center text-slate-600">{{ row.liveness_confidence ?? '—' }}</td>
                <td class="px-4 py-3 text-center text-slate-600">{{ row.match_score ?? '—' }}</td>
                <td class="px-4 py-3 text-center">
                  <span class="inline-flex items-center gap-1 text-xs font-medium rounded-full px-2 py-0.5"
                        :class="statusPillClass(row.match_status)">
                    {{ statusLabel(row.match_status) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <a v-if="row.photo_url" :href="row.photo_url" target="_blank" class="text-indigo-600 hover:underline text-xs">View</a>
                  <span v-else class="text-slate-300 text-xs">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="meta && meta.last_page > 1" class="px-5 py-3 border-t border-slate-100 flex items-center justify-between text-sm text-slate-500">
          <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
          <div class="flex gap-2">
            <button @click="load(meta.current_page - 1)" :disabled="meta.current_page <= 1"
              class="px-3 py-1 rounded-lg border border-slate-200 hover:bg-slate-50 disabled:opacity-40 transition-colors">← Prev</button>
            <button @click="load(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page"
              class="px-3 py-1 rounded-lg border border-slate-200 hover:bg-slate-50 disabled:opacity-40 transition-colors">Next →</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'

const currentMonth = new Date().toISOString().slice(0, 7)
const filterMonth  = ref(currentMonth)
const filterStatus = ref('')
const searchQuery  = ref('')
const rows         = ref([])
const meta         = ref(null)
const loading      = ref(false)

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await axios.get(route('hr.online-punch.monitor'), {
      params: { month: filterMonth.value, status: filterStatus.value, search: searchQuery.value, page },
    })
    rows.value = data.data
    meta.value = { current_page: data.current_page, last_page: data.last_page }
  } finally {
    loading.value = false
  }
}

function punchLabel(type) {
  return { time_in_am: 'Time In AM', time_out_am: 'Time Out AM', time_in_pm: 'Time In PM', time_out_pm: 'Time Out PM' }[type] ?? type
}

function statusPillClass(status) {
  if (status === 'verified') return 'bg-emerald-100 text-emerald-700'
  if (status === 'manual_review') return 'bg-amber-100 text-amber-700'
  return 'bg-rose-100 text-rose-700'
}

function statusLabel(status) {
  if (status === 'verified') return 'Verified'
  if (status === 'manual_review') return 'Under Review'
  return 'Rejected'
}

function fmtTime(val) {
  if (!val) return '—'
  const d = new Date(String(val).replace(' ', 'T'))
  if (isNaN(d.getTime())) return '—'
  return d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' })
}

onMounted(() => load())
</script>
