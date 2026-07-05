<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import {
  CalendarDaysIcon,
  MapPinIcon,
  ClockIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClipboardDocumentCheckIcon,
  ClipboardDocumentListIcon,
  ArrowRightIcon,
  MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  activities: Array,
})

// ── Filters & pagination ──────────────────────────────────────────────────────

const search       = ref('')
const statusFilter = ref('all')   // all | yes | no
const evalFilter   = ref('all')   // all | yes | no
const page         = ref(1)
const PER_PAGE     = 15

const filtered = computed(() => {
  let list = props.activities ?? []

  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(a =>
      a.title?.toLowerCase().includes(q) ||
      a.venue?.toLowerCase().includes(q)
    )
  }

  if (statusFilter.value !== 'all') {
    list = list.filter(a => a.attended === statusFilter.value)
  }

  if (evalFilter.value !== 'all') {
    const want = evalFilter.value === 'yes'
    list = list.filter(a => a.evaluated === want)
  }

  return list
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed  = computed(() => {
  const start = (page.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

function resetPage() { page.value = 1 }
function setPage(n) { page.value = Math.max(1, Math.min(n, totalPages.value)) }

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatDate(d) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', {
    year: 'numeric', month: 'long', day: 'numeric',
  })
}

function dateRange(a) {
  if (!a.end_date || a.end_date === a.start_date) return formatDate(a.start_date)
  return `${formatDate(a.start_date)} – ${formatDate(a.end_date)}`
}

function attendanceColor(attended) {
  return attended === 'yes' ? 'green' : 'red'
}
</script>

<template>
  <Head title="My Activities" />
  <AdminLayout title="My Activities">
    <div class="space-y-5">

      <AppPageHeader title="My Activities" subtitle="Activities you have been enrolled in as a participant." />

      <!-- Filters -->
      <AppFilterBar>
        <div class="relative w-full sm:w-72">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input
            v-model="search"
            @input="resetPage"
            type="text"
            placeholder="Search by title or venue…"
            class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
        </div>

        <select
          v-model="statusFilter"
          @change="resetPage"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option value="all">All Status</option>
          <option value="yes">Present</option>
          <option value="no">Absent</option>
        </select>

        <select
          v-model="evalFilter"
          @change="resetPage"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option value="all">All Evaluation</option>
          <option value="yes">Evaluated</option>
          <option value="no">Not Evaluated</option>
        </select>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!displayed.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Activity</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Venue</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Evaluated</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Certificate</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="a in displayed" :key="a.id" class="hover:bg-slate-50/60">
          <!-- Title -->
          <td class="px-4 py-3 font-medium text-slate-800 max-w-xs">
            {{ a.title }}
          </td>

          <!-- Date -->
          <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
            <div class="flex items-center gap-1.5">
              <CalendarDaysIcon class="h-4 w-4 text-slate-400 shrink-0" />
              {{ dateRange(a) }}
            </div>
          </td>

          <!-- Venue -->
          <td class="px-4 py-3 text-slate-600">
            <div v-if="a.venue" class="flex items-center gap-1.5">
              <MapPinIcon class="h-4 w-4 text-slate-400 shrink-0" />
              {{ a.venue }}
            </div>
            <span v-else class="text-slate-400">—</span>
          </td>

          <!-- Attendance status -->
          <td class="px-4 py-3 text-center">
            <AppBadge :color="attendanceColor(a.attended)">
              <CheckCircleIcon v-if="a.attended === 'yes'" class="h-3.5 w-3.5 mr-1" />
              <XCircleIcon v-else class="h-3.5 w-3.5 mr-1" />
              {{ a.attended === 'yes' ? 'Present' : 'Absent' }}
            </AppBadge>
          </td>

          <!-- Evaluated -->
          <td class="px-4 py-3 text-center">
            <AppBadge v-if="a.evaluated" color="indigo">
              <ClipboardDocumentCheckIcon class="h-3.5 w-3.5 mr-1" /> Done
            </AppBadge>
            <a v-else-if="a.attended === 'yes'"
               :href="a.evaluate_url"
               class="inline-flex">
              <AppBadge color="amber">
                <ClipboardDocumentListIcon class="h-3.5 w-3.5 mr-1" /> Not Yet
              </AppBadge>
            </a>
            <span v-else class="text-slate-400 text-xs">—</span>
          </td>

          <!-- Certificate -->
          <td class="px-4 py-3 text-center">
            <AppBadge v-if="a.has_certificate" color="green">Ready</AppBadge>
            <span v-else class="text-slate-400 text-xs">—</span>
          </td>

          <!-- View -->
          <td class="px-4 py-3 text-right">
            <a :href="route('ams.my-activities.show', a.id)"
               class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 text-sm font-medium">
              View <ArrowRightIcon class="h-4 w-4" />
            </a>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="a in displayed" :key="a.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ a.title }}</p>
                <p class="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
                  <CalendarDaysIcon class="h-3.5 w-3.5 text-slate-400 shrink-0" /> {{ dateRange(a) }}
                </p>
                <p v-if="a.venue" class="text-xs text-slate-500 flex items-center gap-1">
                  <MapPinIcon class="h-3.5 w-3.5 text-slate-400 shrink-0" /> {{ a.venue }}
                </p>
              </div>
              <AppBadge :color="attendanceColor(a.attended)">
                {{ a.attended === 'yes' ? 'Present' : 'Absent' }}
              </AppBadge>
            </div>
            <div class="flex items-center justify-between pt-1">
              <div class="flex items-center gap-2">
                <AppBadge v-if="a.evaluated" color="indigo">Evaluated</AppBadge>
                <a v-else-if="a.attended === 'yes'" :href="a.evaluate_url" class="inline-flex">
                  <AppBadge color="amber">Not Yet Evaluated</AppBadge>
                </a>
                <AppBadge v-if="a.has_certificate" color="green">Certificate Ready</AppBadge>
              </div>
              <a :href="route('ams.my-activities.show', a.id)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View</a>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No activities found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="page"
            :total-pages="totalPages"
            :total="filtered.length"
            @prev="setPage(page - 1)"
            @next="setPage(page + 1)"
            @page="setPage($event)"
          />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>
