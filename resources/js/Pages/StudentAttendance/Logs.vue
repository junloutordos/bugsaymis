<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppButton from '@/Components/AppButton.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'

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

const typeLabels = { in: 'Time In', out: 'Time Out' }

function typeBadgeColor(type) {
  return { in: 'green', out: 'amber' }[type] ?? 'slate'
}
</script>

<template>
  <Head title="Student Attendance Logs" />
  <AdminLayout title="Student Attendance Logs">
    <div class="space-y-5">

      <AppPageHeader title="Attendance Logs" subtitle="Immutable gate scan records — never edited or deleted">
        <template #actions>
          <AppButton as="a" :href="route('student-attendance.kiosk')">Open Kiosk</AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 w-full sm:w-auto">
          <AppInput v-model="filterDate" type="date" label="Date" @change="applyFilters" />
          <AppSelect v-model="filterType" label="Type" placeholder="All types" @change="applyFilters">
            <option value="in">Time In</option>
            <option value="out">Time Out</option>
          </AppSelect>
        </div>
        <template #actions>
          <AppButton
            v-if="filterDate || filterType || filterName"
            variant="secondary" size="sm" @click="clearFilters"
          >Clear filters</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!logs.data.length" :skeleton-cols="8">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Student</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Barcode</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Type</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Scan Time</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Gate</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Source</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Device</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Operator</th>
          </tr>
        </template>

        <tr v-for="log in logs.data" :key="log.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 font-medium text-slate-800">{{ studentName(log) }}</td>
          <td class="px-4 py-3 text-slate-500 font-mono text-xs">{{ log.raw_barcode }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="typeBadgeColor(log.type)">{{ typeLabels[log.type] ?? log.type }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ formatDateTime(log.scan_time) }}</td>
          <td class="px-4 py-3 text-slate-500">{{ log.gate_location ?? '—' }}</td>
          <td class="px-4 py-3 text-slate-400 text-xs capitalize">{{ log.source }}</td>
          <td class="px-4 py-3 text-slate-500 text-xs">{{ log.kiosk_device?.name ?? '—' }}</td>
          <td class="px-4 py-3 text-slate-500 text-xs">{{ log.recorded_by?.name ?? '—' }}</td>
        </tr>

        <template #mobileCard>
          <div v-for="log in logs.data" :key="log.id" class="p-4 space-y-1.5">
            <div class="flex items-start justify-between gap-2">
              <p class="font-medium text-slate-800 text-xs">{{ studentName(log) }}</p>
              <AppBadge :color="typeBadgeColor(log.type)">{{ typeLabels[log.type] ?? log.type }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500 font-mono">{{ log.raw_barcode }}</p>
            <p class="text-xs text-slate-500">{{ formatDateTime(log.scan_time) }} &middot; {{ log.gate_location ?? '—' }}</p>
            <p class="text-xs text-slate-400 capitalize">{{ log.source }}</p>
            <p v-if="log.kiosk_device || log.recorded_by" class="text-xs text-slate-400">
              {{ log.kiosk_device?.name ?? 'Unknown device' }} · {{ log.recorded_by?.name ?? 'Unknown operator' }}
            </p>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No attendance logs found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="logs.current_page"
            :total-pages="logs.last_page"
            :total="logs.total"
            @prev="goTo(logs.prev_page_url)"
            @next="goTo(logs.next_page_url)"
          />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>
