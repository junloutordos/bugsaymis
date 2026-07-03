<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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
  upcoming:  'bg-slate-100 text-slate-600',
  ongoing:   'bg-green-100 text-green-700',
  completed: 'bg-indigo-50 text-indigo-600',
}
const STATUS_LABELS = { upcoming: 'Upcoming', ongoing: 'Ongoing', completed: 'Completed' }

function formatTrendMonth(ym) {
  const [y, m] = ym.split('-')
  return new Date(+y, +m - 1, 1).toLocaleDateString('en-PH', { month: 'short', year: '2-digit' })
}

const maxTrendCount = computed(() => Math.max(1, ...props.trend.map(t => t.count)))
</script>

<template>
  <Head title="Activity Monitoring" />
  <AdminLayout title="Activity Monitoring">

    <p class="text-sm text-slate-500 mb-6">All activities across the system and their evaluation analytics.</p>

    <!-- Stat tiles -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="flex items-center gap-2 text-slate-400 mb-1">
          <ChartBarIcon class="w-4 h-4" />
          <span class="text-xs font-semibold uppercase tracking-wide">Total Activities</span>
        </div>
        <p class="text-2xl font-bold text-slate-800">{{ stats.total_activities }}</p>
        <p class="text-xs text-slate-400 mt-1">
          <AcademicCapIcon class="w-3.5 h-3.5 inline -mt-0.5" /> {{ stats.by_type.training_workshop_seminar }} T/W/S
          &middot;
          <BuildingOffice2Icon class="w-3.5 h-3.5 inline -mt-0.5" /> {{ stats.by_type.in_house }} In-house
        </p>
      </div>

      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="flex items-center gap-2 text-slate-400 mb-1">
          <UsersIcon class="w-4 h-4" />
          <span class="text-xs font-semibold uppercase tracking-wide">Total Participants</span>
        </div>
        <p class="text-2xl font-bold text-slate-800">{{ stats.total_participants }}</p>
        <p class="text-xs text-slate-400 mt-1">{{ stats.total_evaluations }} evaluations submitted</p>
      </div>

      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="flex items-center gap-2 text-slate-400 mb-1">
          <ClipboardDocumentCheckIcon class="w-4 h-4" />
          <span class="text-xs font-semibold uppercase tracking-wide">Avg. Evaluation Completion</span>
        </div>
        <p class="text-2xl font-bold text-slate-800">
          {{ stats.avg_evaluation_completion_rate !== null ? stats.avg_evaluation_completion_rate + '%' : '—' }}
        </p>
        <p class="text-xs text-slate-400 mt-1">Among attended participants</p>
      </div>

      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="flex items-center gap-2 text-slate-400 mb-1">
          <ChartBarIcon class="w-4 h-4" />
          <span class="text-xs font-semibold uppercase tracking-wide">Avg. Satisfaction Score</span>
        </div>
        <p class="text-sm font-bold text-slate-800">
          In-house: {{ stats.avg_in_house_score ?? '—' }} / 5
        </p>
        <p class="text-sm font-bold text-slate-800">
          T/W/S: {{ stats.avg_tws_score ?? '—' }} / 5
        </p>
      </div>
    </div>

    <!-- Status breakdown -->
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div v-for="key in ['upcoming', 'ongoing', 'completed']" :key="key"
           class="bg-white rounded-xl border border-slate-200 p-4 text-center">
        <p class="text-xl font-bold text-slate-800">{{ stats.by_status[key] }}</p>
        <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-xs font-medium" :class="STATUS_STYLES[key]">
          {{ STATUS_LABELS[key] }}
        </span>
      </div>
    </div>

    <!-- Monthly trend -->
    <div v-if="trend.length" class="bg-white rounded-xl border border-slate-200 p-5 mb-6">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Activities Created — Last 12 Months</h2>
      <div class="flex items-end gap-2 h-32">
        <div v-for="t in trend" :key="t.month" class="flex-1 flex flex-col items-center justify-end h-full gap-1">
          <span class="text-xs text-slate-500 font-medium">{{ t.count }}</span>
          <div class="w-full bg-indigo-500 rounded-t"
               :style="{ height: Math.max(4, (t.count / maxTrendCount) * 100) + '%' }" />
          <span class="text-[10px] text-slate-400">{{ formatTrendMonth(t.month) }}</span>
        </div>
      </div>
    </div>

    <!-- Activities table -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-48">
          <MagnifyingGlassIcon class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input v-model="search" placeholder="Search activities…"
                 class="pl-8 rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
        </div>
        <select v-model="typeFilter" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="all">All Types</option>
          <option value="training_workshop_seminar">Training/Workshop/Seminar</option>
          <option value="in_house">In-house</option>
        </select>
        <select v-model="statusFilter" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="all">All Status</option>
          <option value="upcoming">Upcoming</option>
          <option value="ongoing">Ongoing</option>
          <option value="completed">Completed</option>
        </select>
        <span class="text-xs text-slate-400 ml-auto">{{ filtered.length }} result(s)</span>
      </div>

      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide bg-slate-50">
            <th class="text-left px-4 py-2">Title</th>
            <th class="text-center px-3 py-2">Type</th>
            <th class="text-left px-3 py-2">Dates</th>
            <th class="text-center px-3 py-2">Status</th>
            <th class="text-center px-3 py-2">Participants</th>
            <th class="text-center px-3 py-2">Evaluation Completion</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-for="a in displayed" :key="a.id" class="hover:bg-slate-50">
            <td class="px-4 py-3">
              <a :href="a.show_url" class="font-medium text-indigo-600 hover:underline">{{ a.title }}</a>
              <p class="text-xs text-slate-400">{{ a.creator ?? '—' }} &middot; {{ a.venue ?? '—' }}</p>
            </td>
            <td class="px-3 py-3 text-center">
              <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium"
                    :class="a.activity_type === 'training_workshop_seminar' ? 'bg-indigo-50 text-indigo-600' : 'bg-amber-50 text-amber-600'">
                {{ a.activity_type === 'training_workshop_seminar' ? 'T/W/S' : 'In-house' }}
              </span>
            </td>
            <td class="px-3 py-3 text-slate-600 text-xs">
              {{ formatDate(a.start_date) }}<span v-if="a.end_date !== a.start_date"> – {{ formatDate(a.end_date) }}</span>
            </td>
            <td class="px-3 py-3 text-center">
              <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium" :class="STATUS_STYLES[a.status]">
                {{ STATUS_LABELS[a.status] }}
              </span>
            </td>
            <td class="px-3 py-3 text-center text-slate-600">{{ a.attended_count }} / {{ a.participant_count }}</td>
            <td class="px-3 py-3 text-center text-slate-600">
              {{ a.evaluation_completion_rate !== null ? a.evaluation_completion_rate + '%' : '—' }}
              <span class="text-xs text-slate-400">({{ a.evaluation_count }})</span>
            </td>
          </tr>
          <tr v-if="!filtered.length">
            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400 italic">No activities match the current filters.</td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="px-4 py-3 border-t border-slate-100 flex items-center justify-between text-sm">
        <span class="text-slate-500 text-xs">Page {{ currentPage }} of {{ totalPages }}</span>
        <div class="flex items-center gap-1">
          <button @click="setPage(currentPage - 1)" :disabled="currentPage === 1"
                  class="px-2 py-1 rounded text-xs border border-slate-200 disabled:opacity-40 hover:bg-slate-50">‹</button>
          <template v-for="n in totalPages" :key="n">
            <button v-if="Math.abs(n - currentPage) <= 2"
                    @click="setPage(n)"
                    class="px-2.5 py-1 rounded text-xs border font-medium"
                    :class="n === currentPage ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-200 hover:bg-slate-50'">
              {{ n }}
            </button>
          </template>
          <button @click="setPage(currentPage + 1)" :disabled="currentPage === totalPages"
                  class="px-2 py-1 rounded text-xs border border-slate-200 disabled:opacity-40 hover:bg-slate-50">›</button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>
