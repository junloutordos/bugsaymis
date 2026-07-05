<template>
  <Head title="Online Time Punches — Monitoring" />
  <AdminLayout title="Online Time Punches — Monitoring">
    <div class="space-y-5">

      <AppPageHeader title="Online Time Punches" subtitle="Monitor face-verified time punches across employees." />

      <!-- Filters -->
      <AppFilterBar>
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

        <template #actions>
          <AppButton size="sm" :loading="loading" @click="load">
            {{ loading ? 'Loading…' : 'Apply' }}
          </AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :loading="loading" :is-empty="!rows.length" :skeleton-cols="8">
        <template #head>
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
        </template>

        <tr v-for="row in rows" :key="row.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-slate-800">{{ row.user?.name }}</td>
          <td class="px-4 py-3 text-slate-600">{{ row.work_date }}</td>
          <td class="px-4 py-3 text-slate-600">{{ punchLabel(row.punch_type) }}</td>
          <td class="px-4 py-3 text-slate-600">{{ fmtTime(row.punched_at) }}</td>
          <td class="px-4 py-3 text-center text-slate-600">{{ row.liveness_confidence ?? '—' }}</td>
          <td class="px-4 py-3 text-center text-slate-600">{{ row.match_score ?? '—' }}</td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="statusBadgeColor(row.match_status)">{{ statusLabel(row.match_status) }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <a v-if="row.photo_url" :href="row.photo_url" target="_blank" class="text-indigo-600 hover:underline text-xs">View</a>
            <span v-else class="text-slate-300 text-xs">—</span>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="row in rows" :key="row.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ row.user?.name }}</p>
                <p class="text-xs text-slate-500">{{ row.work_date }} &middot; {{ punchLabel(row.punch_type) }} &middot; {{ fmtTime(row.punched_at) }}</p>
              </div>
              <AppBadge :color="statusBadgeColor(row.match_status)">{{ statusLabel(row.match_status) }}</AppBadge>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-500">
              <span>Liveness {{ row.liveness_confidence ?? '—' }} &middot; Match {{ row.match_score ?? '—' }}</span>
              <a v-if="row.photo_url" :href="row.photo_url" target="_blank" class="text-indigo-600 hover:underline">View photo</a>
              <span v-else class="text-slate-300">—</span>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No online time punches found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="meta?.current_page"
            :total-pages="meta?.last_page"
            :total="meta?.total"
            @prev="load(meta.current_page - 1)"
            @next="load(meta.current_page + 1)"
            @page="load($event)"
          />
        </template>
      </AppTable>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
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
    meta.value = { current_page: data.current_page, last_page: data.last_page, total: data.total }
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

function statusBadgeColor(status) {
  if (status === 'verified') return 'green'
  if (status === 'manual_review') return 'amber'
  return 'red'
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
