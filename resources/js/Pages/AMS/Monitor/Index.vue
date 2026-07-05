<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import {
  ChartBarIcon,
  UsersIcon,
  ClipboardDocumentCheckIcon,
  AcademicCapIcon,
  BuildingOffice2Icon,
  MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  stats:      Object,
  trend:      Array,
  activities: Array,
})

// ── Filters + pagination ─────────────────────────────────────────────────────

const PER_PAGE      = 15
const search         = ref('')
const typeFilter     = ref('all')    // 'all' | 'in_house' | 'training_workshop_seminar'
const statusFilter   = ref('all')    // 'all' | 'upcoming' | 'ongoing' | 'completed'
const currentPage    = ref(1)

const filtered = computed(() => {
  const q = search.value.toLowerCase()
  return props.activities.filter(a => {
    if (q && !a.title.toLowerCase().includes(q)) return false
    if (typeFilter.value !== 'all' && a.activity_type !== typeFilter.value) return false
    if (statusFilter.value !== 'all' && a.status !== statusFilter.value) return false
    return true
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed  = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

function setPage(n) { currentPage.value = Math.max(1, Math.min(n, totalPages.value)) }

// ── Helpers ──────────────────────────────────────────────────────────────────

function formatDate(d) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

const STATUS_STYLES = {
  upcoming:  'slate',
  ongoing:   'green',
  completed: 'indigo',
}
const STATUS_LABELS = { upcoming: 'Upcoming', ongoing: 'Ongoing', completed: 'Completed' }

function typeColor(type) {
  return type === 'training_workshop_seminar' ? 'indigo' : 'amber'
}
function typeLabel(type) {
  return type === 'training_workshop_seminar' ? 'T/W/S' : 'In-house'
}

function formatTrendMonth(ym) {
  const [y, m] = ym.split('-')
  return new Date(+y, +m - 1, 1).toLocaleDateString('en-PH', { month: 'short', year: '2-digit' })
}

const maxTrendCount = computed(() => Math.max(1, ...props.trend.map(t => t.count)))
</script>

<template>
  <Head title="Activity Monitoring" />
  <AdminLayout title="Activity Monitoring">
    <div class="space-y-5">

      <AppPageHeader title="Activity Monitoring" subtitle="All activities across the system and their evaluation analytics." />

      <!-- Stat tiles -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <AppCard>
          <div class="flex items-center gap-2 text-slate-400 mb-1">
            <ChartBarIcon class="h-4 w-4" />
            <span class="text-xs font-semibold uppercase tracking-wide">Total Activities</span>
          </div>
          <p class="text-2xl font-bold text-slate-800">{{ stats.total_activities }}</p>
          <p class="text-xs text-slate-400 mt-1">
            <AcademicCapIcon class="h-3.5 w-3.5 inline -mt-0.5" /> {{ stats.by_type.training_workshop_seminar }} T/W/S
            &middot;
            <BuildingOffice2Icon class="h-3.5 w-3.5 inline -mt-0.5" /> {{ stats.by_type.in_house }} In-house
          </p>
        </AppCard>

        <AppCard>
          <div class="flex items-center gap-2 text-slate-400 mb-1">
            <UsersIcon class="h-4 w-4" />
            <span class="text-xs font-semibold uppercase tracking-wide">Total Participants</span>
          </div>
          <p class="text-2xl font-bold text-slate-800">{{ stats.total_participants }}</p>
          <p class="text-xs text-slate-400 mt-1">{{ stats.total_evaluations }} evaluations submitted</p>
        </AppCard>

        <AppCard>
          <div class="flex items-center gap-2 text-slate-400 mb-1">
            <ClipboardDocumentCheckIcon class="h-4 w-4" />
            <span class="text-xs font-semibold uppercase tracking-wide">Avg. Evaluation Completion</span>
          </div>
          <p class="text-2xl font-bold text-slate-800">
            {{ stats.avg_evaluation_completion_rate !== null ? stats.avg_evaluation_completion_rate + '%' : '—' }}
          </p>
          <p class="text-xs text-slate-400 mt-1">Among attended participants</p>
        </AppCard>

        <AppCard>
          <div class="flex items-center gap-2 text-slate-400 mb-1">
            <ChartBarIcon class="h-4 w-4" />
            <span class="text-xs font-semibold uppercase tracking-wide">Avg. Satisfaction Score</span>
          </div>
          <p class="text-sm font-bold text-slate-800">
            In-house: {{ stats.avg_in_house_score ?? '—' }} / 5
          </p>
          <p class="text-sm font-bold text-slate-800">
            T/W/S: {{ stats.avg_tws_score ?? '—' }} / 5
          </p>
        </AppCard>
      </div>

      <!-- Status breakdown -->
      <div class="grid grid-cols-3 gap-4">
        <AppCard v-for="key in ['upcoming', 'ongoing', 'completed']" :key="key" class="text-center">
          <p class="text-xl font-bold text-slate-800">{{ stats.by_status[key] }}</p>
          <AppBadge :color="STATUS_STYLES[key]" class="mt-1">{{ STATUS_LABELS[key] }}</AppBadge>
        </AppCard>
      </div>

      <!-- Monthly trend -->
      <AppCard v-if="trend.length" title="Activities Created — Last 12 Months">
        <div class="flex items-end gap-2 h-32">
          <div v-for="t in trend" :key="t.month" class="flex-1 flex flex-col items-center justify-end h-full gap-1">
            <span class="text-xs text-slate-500 font-medium">{{ t.count }}</span>
            <div class="w-full bg-indigo-500 rounded-t"
                 :style="{ height: Math.max(4, (t.count / maxTrendCount) * 100) + '%' }" />
            <span class="text-[10px] text-slate-400">{{ formatTrendMonth(t.month) }}</span>
          </div>
        </div>
      </AppCard>

      <!-- Filters -->
      <AppFilterBar :result-label="`${filtered.length} result(s)`">
        <div class="relative w-full sm:w-72">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input v-model="search" placeholder="Search activities…"
                 class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <select v-model="typeFilter" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="all">All Types</option>
          <option value="training_workshop_seminar">Training/Workshop/Seminar</option>
          <option value="in_house">In-house</option>
        </select>
        <select v-model="statusFilter" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="all">All Status</option>
          <option value="upcoming">Upcoming</option>
          <option value="ongoing">Ongoing</option>
          <option value="completed">Completed</option>
        </select>
      </AppFilterBar>

      <!-- Activities table -->
      <AppTable :is-empty="!filtered.length" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Title</th>
            <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
            <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Dates</th>
            <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Participants</th>
            <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Evaluation Completion</th>
          </tr>
        </template>

        <tr v-for="a in displayed" :key="a.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3">
            <a :href="a.show_url" class="font-medium text-indigo-600 hover:underline">{{ a.title }}</a>
            <p class="text-xs text-slate-400">{{ a.creator ?? '—' }} &middot; {{ a.venue ?? '—' }}</p>
          </td>
          <td class="px-3 py-3 text-center">
            <AppBadge :color="typeColor(a.activity_type)">{{ typeLabel(a.activity_type) }}</AppBadge>
          </td>
          <td class="px-3 py-3 text-slate-600 text-xs">
            {{ formatDate(a.start_date) }}<span v-if="a.end_date !== a.start_date"> – {{ formatDate(a.end_date) }}</span>
          </td>
          <td class="px-3 py-3 text-center">
            <AppBadge :color="STATUS_STYLES[a.status]">{{ STATUS_LABELS[a.status] }}</AppBadge>
          </td>
          <td class="px-3 py-3 text-center text-slate-600">{{ a.attended_count }} / {{ a.participant_count }}</td>
          <td class="px-3 py-3 text-center text-slate-600">
            {{ a.evaluation_completion_rate !== null ? a.evaluation_completion_rate + '%' : '—' }}
            <span class="text-xs text-slate-400">({{ a.evaluation_count }})</span>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="a in displayed" :key="a.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <a :href="a.show_url" class="font-medium text-indigo-600 hover:underline text-sm">{{ a.title }}</a>
                <p class="text-xs text-slate-400">{{ a.creator ?? '—' }} &middot; {{ a.venue ?? '—' }}</p>
              </div>
              <AppBadge :color="STATUS_STYLES[a.status]">{{ STATUS_LABELS[a.status] }}</AppBadge>
            </div>
            <div class="flex items-center gap-2 text-xs text-slate-500">
              <AppBadge :color="typeColor(a.activity_type)">{{ typeLabel(a.activity_type) }}</AppBadge>
              <span>{{ formatDate(a.start_date) }}<span v-if="a.end_date !== a.start_date"> – {{ formatDate(a.end_date) }}</span></span>
            </div>
            <div class="flex justify-between text-xs text-slate-500">
              <span>{{ a.attended_count }} / {{ a.participant_count }} participants</span>
              <span>{{ a.evaluation_completion_rate !== null ? a.evaluation_completion_rate + '%' : '—' }} ({{ a.evaluation_count }})</span>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No activities match the current filters." />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            :total="filtered.length"
            @prev="setPage(currentPage - 1)"
            @next="setPage(currentPage + 1)"
            @page="setPage($event)"
          />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>
