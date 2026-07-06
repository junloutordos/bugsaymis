<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import {
  CpuChipIcon,
  SparklesIcon,
  ScaleIcon,
  ExclamationTriangleIcon,
  ClockIcon,
  CalendarIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  currentTerm:  { type: Object,  default: null },
  loadSummary:  { type: Object,  default: null },
  recentJobs:   { type: Array,   default: () => [] },
  schoolYears:  { type: Array,   default: () => [] },
})

const lastJob = computed(() => props.recentJobs?.[0] ?? null)

const flaggedFaculty = computed(() =>
  (props.loadSummary?.faculty_profiles ?? []).filter(f => f.flags_count > 0)
)

function fmtDateTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-PH', {
    month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function jobStatusClass(status) {
  const map = {
    completed: 'green',
    running:   'blue',
    failed:    'red',
    pending:   'slate',
  }
  return map[status] ?? map.pending
}
</script>

<template>
  <Head title="AI Optimization Dashboard" />
  <AdminLayout title="AI Optimization Dashboard">
    <div class="space-y-6">

      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
            <CpuChipIcon class="h-5 w-5 text-indigo-500" />
            AI Optimization Dashboard
          </h1>
          <p class="text-sm text-slate-500 mt-0.5">
            Central hub for AI-assisted scheduling and workload optimization.
            <span v-if="currentTerm" class="text-indigo-500 font-medium">
              — {{ currentTerm.full_label }}
            </span>
          </p>
        </div>
      </div>

      <!-- ── Status Overview ─────────────────────────────────────────── -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <!-- Faculty total -->
        <AppCard class="text-center">
          <p class="text-2xl font-bold text-slate-700">{{ loadSummary?.faculty_count ?? '—' }}</p>
          <p class="text-xs text-slate-500 mt-1">Faculty</p>
        </AppCard>
        <!-- Overloaded -->
        <AppCard class="text-center" :class="(loadSummary?.overloaded ?? 0) > 0 ? 'border-danger-100 bg-danger-50/30' : ''">
          <p class="text-2xl font-bold" :class="(loadSummary?.overloaded ?? 0) > 0 ? 'text-danger-600' : 'text-slate-400'">
            {{ loadSummary?.overloaded ?? '—' }}
          </p>
          <p class="text-xs text-slate-500 mt-1">Overloaded</p>
        </AppCard>
        <!-- Underloaded -->
        <AppCard class="text-center" :class="(loadSummary?.underloaded ?? 0) > 0 ? 'border-warning-100 bg-warning-50/30' : ''">
          <p class="text-2xl font-bold" :class="(loadSummary?.underloaded ?? 0) > 0 ? 'text-warning-600' : 'text-slate-400'">
            {{ loadSummary?.underloaded ?? '—' }}
          </p>
          <p class="text-xs text-slate-500 mt-1">Underloaded</p>
        </AppCard>
        <!-- Imbalance score -->
        <AppCard class="text-center">
          <p class="text-2xl font-bold"
            :class="(loadSummary?.imbalance_score ?? 0) === 0 ? 'text-success-600'
              : (loadSummary?.imbalance_score ?? 0) < 3 ? 'text-warning-600' : 'text-danger-600'">
            {{ loadSummary?.imbalance_score ?? '—' }}
          </p>
          <p class="text-xs text-slate-500 mt-1">Imbalance Score</p>
        </AppCard>
      </div>

      <!-- ── Main Tool Cards ──────────────────────────────────────────── -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        <!-- AI Schedule Generator card -->
        <AppCard :padded="false" class="overflow-hidden flex flex-col">
          <div class="px-5 py-4 bg-gradient-to-br from-indigo-50 to-purple-50 border-b border-slate-100">
            <div class="flex items-start justify-between">
              <div>
                <div class="flex items-center gap-2 mb-1">
                  <SparklesIcon class="h-5 w-5 text-indigo-500" />
                  <h2 class="text-sm font-semibold text-slate-800">AI Timetable Generator</h2>
                  <AppBadge color="indigo">Phase 9</AppBadge>
                </div>
                <p class="text-xs text-slate-500">
                  Automatically generate conflict-free class schedules using a Genetic Algorithm.
                </p>
              </div>
            </div>
          </div>

          <!-- Last job summary -->
          <div class="px-5 py-3 flex-1">
            <template v-if="lastJob">
              <p class="text-xs text-slate-400 mb-2">Last generation run:</p>
              <div class="grid grid-cols-3 gap-3 text-center">
                <div>
                  <p class="text-lg font-bold" :class="lastJob.hard_conflicts === 0 ? 'text-success-600' : 'text-danger-600'">
                    {{ lastJob.hard_conflicts }}
                  </p>
                  <p class="text-xs text-slate-400">Conflicts</p>
                </div>
                <div>
                  <p class="text-lg font-bold text-indigo-600">{{ lastJob.schedules_generated }}</p>
                  <p class="text-xs text-slate-400">Slots</p>
                </div>
                <div>
                  <p class="text-lg font-bold text-slate-600">{{ lastJob.duration_seconds ?? '—' }}s</p>
                  <p class="text-xs text-slate-400">Run Time</p>
                </div>
              </div>
              <p class="text-xs text-slate-400 mt-2">
                {{ fmtDateTime(lastJob.created_at) }} by {{ lastJob.created_by_name }}
              </p>
            </template>
            <div v-else class="py-4 text-center text-slate-400 text-xs">
              No schedule generation runs yet.
            </div>
          </div>

          <!-- Action -->
          <div class="px-5 py-3 border-t border-slate-100 bg-slate-50/50">
            <AppButton as="link" :href="route('faculty-loading.auto-schedule.index')">
              <SparklesIcon class="h-4 w-4" />
              Open AI Schedule Generator
            </AppButton>
          </div>
        </AppCard>

        <!-- Load Balancing card -->
        <AppCard :padded="false" class="overflow-hidden flex flex-col">
          <div class="px-5 py-4 bg-gradient-to-br from-emerald-50 to-teal-50 border-b border-slate-100">
            <div class="flex items-start justify-between">
              <div>
                <div class="flex items-center gap-2 mb-1">
                  <ScaleIcon class="h-5 w-5 text-emerald-600" />
                  <h2 class="text-sm font-semibold text-slate-800">Load Balancing Engine</h2>
                  <AppBadge color="green">Phase 10</AppBadge>
                </div>
                <p class="text-xs text-slate-500">
                  Detect overloaded/underloaded faculty and get intelligent rebalancing suggestions.
                </p>
              </div>
            </div>
          </div>

          <!-- Mini load distribution -->
          <div class="px-5 py-3 flex-1">
            <template v-if="loadSummary?.faculty_profiles?.length">
              <p class="text-xs text-slate-400 mb-2">
                Current load distribution — Avg: <strong>{{ loadSummary.average_load }} u</strong>,
                Std Dev: <strong>± {{ loadSummary.std_deviation }}</strong>
              </p>
              <div class="space-y-1.5">
                <div v-for="f in loadSummary.faculty_profiles.slice(0, 6)" :key="f.faculty_name"
                  class="flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full shrink-0"
                    :class="f.load_status === 'overload' ? 'bg-danger-500' : f.load_status === 'underload' ? 'bg-warning-500' : 'bg-success-500'" />
                  <span class="text-xs text-slate-600 truncate w-32">{{ f.faculty_name }}</span>
                  <div class="flex-1 bg-slate-100 rounded-full h-2 relative overflow-hidden">
                    <div class="absolute inset-y-0 w-px bg-slate-400 z-10"
                      :style="{ left: ((18 / 24) * 100) + '%' }" />
                    <div class="h-full rounded-full"
                      :class="f.load_status === 'overload' ? 'bg-danger-500' : f.load_status === 'underload' ? 'bg-warning-500' : 'bg-success-500'"
                      :style="{ width: Math.min(100, (f.total_units / 24) * 100) + '%' }" />
                  </div>
                  <span class="text-xs tabular-nums w-10 text-right font-medium"
                    :class="f.load_status === 'overload' ? 'text-danger-600' : f.load_status === 'underload' ? 'text-warning-600' : 'text-success-600'">
                    {{ f.total_units }} u
                  </span>
                  <ExclamationTriangleIcon v-if="f.flags_count > 0"
                    class="h-3.5 w-3.5 text-warning-500 shrink-0" />
                </div>
                <p v-if="loadSummary.faculty_profiles.length > 6"
                  class="text-xs text-slate-400 text-right">
                  + {{ loadSummary.faculty_profiles.length - 6 }} more
                </p>
              </div>
            </template>
            <div v-else class="py-4 text-center text-slate-400 text-xs">
              No faculty load data for the current term.
            </div>
          </div>

          <!-- Action -->
          <div class="px-5 py-3 border-t border-slate-100 bg-slate-50/50">
            <AppButton as="link" :href="route('faculty-loading.load-balance.index')">
              <ScaleIcon class="h-4 w-4" />
              Open Load Balancing
            </AppButton>
          </div>
        </AppCard>
      </div>

      <!-- ── Imbalance Indicators ────────────────────────────────────── -->
      <div v-if="flaggedFaculty.length"
        class="bg-warning-50 border border-warning-100 rounded-xl px-5 py-4">
        <div class="flex items-start gap-3">
          <ExclamationTriangleIcon class="h-5 w-5 text-warning-500 mt-0.5 shrink-0" />
          <div class="flex-1">
            <p class="text-sm font-semibold text-warning-700 mb-2">
              {{ flaggedFaculty.length }} faculty member(s) need attention
            </p>
            <div class="flex flex-wrap gap-2">
              <span v-for="f in flaggedFaculty" :key="f.faculty_name"
                class="inline-flex items-center gap-1.5 text-xs bg-white border border-warning-100 text-warning-700 px-2.5 py-1 rounded-full shadow-sm">
                <span class="w-1.5 h-1.5 rounded-full"
                  :class="f.load_status === 'overload' ? 'bg-danger-500' : 'bg-warning-500'" />
                {{ f.faculty_name }}
                <span class="font-semibold">{{ f.total_units }}u</span>
              </span>
            </div>
            <div class="mt-3">
              <Link :href="route('faculty-loading.load-balance.index')"
                class="text-xs text-warning-700 hover:text-warning-700/80 font-medium underline">
                View rebalancing suggestions →
              </Link>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Recent AI Jobs ──────────────────────────────────────────── -->
      <AppCard :padded="false">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
            <ClockIcon class="h-4 w-4 text-slate-400" />
            Recent Schedule Generation Runs
          </h2>
          <Link :href="route('faculty-loading.auto-schedule.index')"
            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
            View all →
          </Link>
        </div>

        <AppTable :card="false" :is-empty="!recentJobs.length" :skeleton-cols="6">
          <template #head>
            <tr>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Term</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Run at</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Slots</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Conflicts</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Fitness</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            </tr>
          </template>

          <tr v-for="job in recentJobs" :key="job.id" class="hover:bg-slate-50/50">
            <td class="px-4 py-2.5 text-slate-700">{{ job.academic_term_label }}</td>
            <td class="px-4 py-2.5 text-xs text-slate-500">
              {{ fmtDateTime(job.created_at) }}
              <span class="text-slate-400"> · {{ job.created_by_name }}</span>
            </td>
            <td class="px-4 py-2.5 text-center text-slate-600">{{ job.schedules_generated }}</td>
            <td class="px-4 py-2.5 text-center">
              <span :class="job.hard_conflicts === 0 ? 'text-success-600' : 'text-danger-600'"
                class="font-semibold text-xs">{{ job.hard_conflicts }}</span>
            </td>
            <td class="px-4 py-2.5 text-center text-xs text-slate-500 tabular-nums">
              {{ job.fitness_score?.toLocaleString() ?? '—' }}
            </td>
            <td class="px-4 py-2.5 text-center">
              <AppBadge :color="jobStatusClass(job.status)">{{ job.status }}</AppBadge>
            </td>
          </tr>

          <template #mobileCard>
            <div v-for="job in recentJobs" :key="job.id" class="p-4 space-y-1.5">
              <div class="flex items-start justify-between gap-2">
                <p class="font-medium text-slate-800">{{ job.academic_term_label }}</p>
                <AppBadge :color="jobStatusClass(job.status)">{{ job.status }}</AppBadge>
              </div>
              <p class="text-xs text-slate-500">
                {{ fmtDateTime(job.created_at) }} · {{ job.created_by_name }}
              </p>
              <p class="text-xs text-slate-500">
                {{ job.schedules_generated }} slots ·
                <span :class="job.hard_conflicts === 0 ? 'text-success-600' : 'text-danger-600'" class="font-semibold">{{ job.hard_conflicts }} conflicts</span>
                · fitness {{ job.fitness_score?.toLocaleString() ?? '—' }}
              </p>
            </div>
          </template>

          <template #empty>
            <EmptyState title="No generation runs yet" subtitle="Start by opening the AI Schedule Generator." />
          </template>
        </AppTable>
      </AppCard>

      <!-- ── How It Works ────────────────────────────────────────────── -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <AppCard>
          <div class="flex items-center gap-2 mb-2">
            <span class="w-6 h-6 bg-indigo-100 text-indigo-700 rounded-full text-xs font-bold flex items-center justify-center shrink-0">1</span>
            <p class="text-sm font-semibold text-slate-700">Generate Schedule</p>
          </div>
          <p class="text-xs text-slate-500 leading-relaxed">
            The Genetic Algorithm reads all teaching assignments and automatically assigns each to a
            conflict-free day, time, and classroom.
          </p>
          <Link :href="route('faculty-loading.auto-schedule.index')"
            class="mt-3 inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
            <SparklesIcon class="h-3.5 w-3.5" /> Open Generator
          </Link>
        </AppCard>

        <AppCard>
          <div class="flex items-center gap-2 mb-2">
            <span class="w-6 h-6 bg-success-100 text-success-700 rounded-full text-xs font-bold flex items-center justify-center shrink-0">2</span>
            <p class="text-sm font-semibold text-slate-700">Balance Workloads</p>
          </div>
          <p class="text-xs text-slate-500 leading-relaxed">
            The Load Balancing Engine detects overloaded and underloaded faculty and suggests
            transfers or swaps to reach the 18-unit threshold.
          </p>
          <Link :href="route('faculty-loading.load-balance.index')"
            class="mt-3 inline-flex items-center gap-1 text-xs text-success-600 hover:text-success-700 font-medium">
            <ScaleIcon class="h-3.5 w-3.5" /> Open Balancer
          </Link>
        </AppCard>

        <AppCard>
          <div class="flex items-center gap-2 mb-2">
            <span class="w-6 h-6 bg-warning-100 text-warning-700 rounded-full text-xs font-bold flex items-center justify-center shrink-0">3</span>
            <p class="text-sm font-semibold text-slate-700">Review &amp; Approve</p>
          </div>
          <p class="text-xs text-slate-500 leading-relaxed">
            All AI-generated schedules are saved as <em>tentative</em>. CID reviews, adjusts if
            needed, then activates them in the Schedules module.
          </p>
          <Link :href="route('faculty-loading.schedules.index')"
            class="mt-3 inline-flex items-center gap-1 text-xs text-warning-600 hover:text-warning-700 font-medium">
            <CalendarIcon class="h-3.5 w-3.5" /> Open Schedules
          </Link>
        </AppCard>
      </div>

    </div>
  </AdminLayout>
</template>
