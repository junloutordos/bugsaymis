<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { FunnelIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline'

const page = usePage()
const logs    = computed(() => page.props.logs    ?? { data: [], current_page: 1, last_page: 1 })
const filters = computed(() => page.props.filters ?? {})

// ── Filter state (initialise from server-returned filters) ────────────────────
const filterDate = ref(filters.value.date       ?? '')
const filterType = ref(filters.value.type       ?? '')
const filterName = ref(filters.value.student_id ?? '')

function applyFilters() {
  router.get(route('student-attendance.logs.index'), {
    date:       filterDate.value || undefined,
    type:       filterType.value || undefined,
    student_id: filterName.value || undefined,
  }, { preserveState: true, replace: true })
}

function clearFilters() {
  filterDate.value = ''
  filterType.value = ''
  filterName.value = ''
  router.get(route('student-attendance.logs.index'), {}, { replace: true })
}

// ── Pagination ────────────────────────────────────────────────────────────────
function goTo(url) {
  if (url) window.location.href = url
}

// ── Formatters ────────────────────────────────────────────────────────────────
function formatDateTime(v) {
  if (!v) return '—'
  return new Date(v).toLocaleString('en-PH', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true,
  })
}

function studentName(log) {
  if (!log.student) return '—'
  const s = log.student
  return `${s.lastname ?? ''}, ${s.firstname ?? ''}`.trim().replace(/^,\s*/, '')
}

const typeColors = {
  in:  'bg-emerald-100 text-emerald-700',
  out: 'bg-amber-100 text-amber-700',
}
const typeLabels = { in: 'Time In', out: 'Time Out' }
</script>

<template>
  <Head title="Student Attendance Logs" />
  <AdminLayout title="Student Attendance Logs">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
      <div>
        <h1 class="text-xl font-semibold text-slate-800">Attendance Logs</h1>
        <p class="text-sm text-slate-500 mt-0.5">Immutable gate scan records — never edited or deleted</p>
      </div>
      <a
        :href="route('student-attendance.kiosk')"
        class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium"
      >
        Open Kiosk
      </a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm mb-4">
      <div class="px-5 py-4 flex flex-wrap items-end gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Date</label>
          <input
            v-model="filterDate"
            type="date"
            @change="applyFilters"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Type</label>
          <select
            v-model="filterType"
            @change="applyFilters"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option value="">All types</option>
            <option value="in">Time In</option>
            <option value="out">Time Out</option>
          </select>
        </div>
        <button
          v-if="filterDate || filterType || filterName"
          @click="clearFilters"
          class="text-sm text-slate-500 hover:text-slate-700 px-3 py-2 rounded-lg hover:bg-slate-50 border border-slate-200"
        >
          Clear filters
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Student</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Barcode</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Scan Time</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Gate</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Source</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="log in logs.data" :key="log.id" class="hover:bg-slate-50/60">
              <td class="px-4 py-3 font-medium text-slate-800">{{ studentName(log) }}</td>
              <td class="px-4 py-3 text-slate-500 font-mono text-xs">{{ log.raw_barcode }}</td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                  :class="typeColors[log.type] ?? 'bg-slate-100 text-slate-600'"
                >
                  {{ typeLabels[log.type] ?? log.type }}
                </span>
              </td>
              <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ formatDateTime(log.scan_time) }}</td>
              <td class="px-4 py-3 text-slate-500">{{ log.gate_location ?? '—' }}</td>
              <td class="px-4 py-3 text-slate-400 text-xs capitalize">{{ log.source }}</td>
            </tr>
            <tr v-if="logs.data.length === 0">
              <td colspan="6" class="py-16 text-center text-slate-400">No attendance logs found</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
        <button
          @click="goTo(logs.prev_page_url)"
          :disabled="!logs.prev_page_url"
          class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-40"
        >Prev</button>
        <span>Page {{ logs.current_page }} of {{ logs.last_page }}</span>
        <button
          @click="goTo(logs.next_page_url)"
          :disabled="!logs.next_page_url"
          class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-40"
        >Next</button>
      </div>
    </div>

  </AdminLayout>
</template>
