<script setup>
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  ChartBarIcon,
  UsersIcon,
  ExclamationTriangleIcon,
  TrophyIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  schoolYears:        Array,
  selectedSchoolYear: Number,
  totals:             Object,
  enrollmentByGrade:  Array,
  sectionStats:       Array,
  standingDist:       Object,
  honorsDist:         Object,
  atRisk:             Array,
  gwaBuckets:         Object,
})

const schoolYearId = ref(props.selectedSchoolYear)
watch(schoolYearId, (val) => {
  router.get(route('registrar.analytics.index'), { school_year_id: val }, { preserveState: true })
})

function pctBar(pct) {
  const w = Math.min(100, pct)
  const color = pct >= 95 ? '#dc2626' : pct >= 80 ? '#f59e0b' : '#4f46e5'
  return { width: w + '%', background: color }
}

const GWA_ORDER = ['1.00–1.25','1.26–1.50','1.51–1.75','1.76–2.00','2.01–2.50','2.51–3.00','> 3.00 (Failed)']
const GWA_COLORS = ['#15803d','#16a34a','#65a30d','#ca8a04','#f97316','#ef4444','#991b1b']

function orderedBuckets() {
  return GWA_ORDER.map((label, i) => ({
    label,
    count: props.gwaBuckets[label] ?? 0,
    color: GWA_COLORS[i],
  })).filter(b => b.count > 0)
}

const HONORS_ORDER = ['With Highest Honors', 'With High Honors', 'With Honors']
const HONORS_COLORS = { 'With Highest Honors':'#b45309','With High Honors':'#d97706','With Honors':'#f59e0b' }
</script>

<template>
  <Head title="Registrar Analytics" />
  <AdminLayout title="Registrar Analytics">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
      <div class="flex items-center gap-2">
        <ChartBarIcon class="w-6 h-6 text-indigo-600" />
        <h1 class="text-lg font-semibold text-slate-800">Registrar Analytics</h1>
      </div>

      <select
        v-model="schoolYearId"
        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
      >
        <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
          {{ sy.name }}{{ sy.is_current ? ' (Current)' : '' }}
        </option>
      </select>
    </div>

    <!-- Top totals -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
      <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
        <p class="text-xs text-slate-500 mb-1">Total Enrolled</p>
        <p class="text-3xl font-bold text-indigo-600">{{ totals.enrolled.toLocaleString('en-PH') }}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
        <p class="text-xs text-slate-500 mb-1">Dropped</p>
        <p class="text-3xl font-bold text-red-500">{{ totals.dropped.toLocaleString('en-PH') }}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
        <p class="text-xs text-slate-500 mb-1">Transferred Out</p>
        <p class="text-3xl font-bold text-amber-500">{{ totals.transferred.toLocaleString('en-PH') }}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
        <p class="text-xs text-slate-500 mb-1">Total (all statuses)</p>
        <p class="text-3xl font-bold text-slate-700">{{ totals.total.toLocaleString('en-PH') }}</p>
      </div>
    </div>

    <!-- Two-column grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">

      <!-- Enrollment by grade -->
      <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2">
          <UsersIcon class="w-4 h-4 text-indigo-600" />
          Enrollment by Grade Level
        </h2>
        <table class="w-full text-sm">
          <thead class="border-b border-slate-100">
            <tr>
              <th class="py-1 text-left text-xs text-slate-500">Grade</th>
              <th class="py-1 text-right text-xs text-slate-500">Enrolled</th>
              <th class="py-1 text-right text-xs text-slate-500">Dropped</th>
              <th class="py-1 text-right text-xs text-slate-500">Transferred</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="row in enrollmentByGrade" :key="row.grade">
              <td class="py-2 font-medium text-slate-800">{{ row.label }}</td>
              <td class="py-2 text-right text-green-700 font-semibold">{{ row.enrolled }}</td>
              <td class="py-2 text-right text-red-500">{{ row.dropped }}</td>
              <td class="py-2 text-right text-amber-500">{{ row.transferred }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Academic standing distribution -->
      <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Academic Standing Distribution</h2>
        <div
          v-if="Object.keys(standingDist).length === 0"
          class="text-slate-400 text-sm text-center py-6"
        >No standing data computed yet.</div>
        <div v-else class="space-y-2">
          <div
            v-for="(count, standing) in standingDist"
            :key="standing"
            class="flex items-center gap-3"
          >
            <span class="text-sm w-28 text-slate-700">{{ standing }}</span>
            <div class="flex-1 bg-slate-100 rounded-full h-4 overflow-hidden">
              <div
                class="h-4 rounded-full transition-all"
                :style="{ width: (totals.enrolled > 0 ? Math.round(count / totals.enrolled * 100) : 0) + '%', background: standing === 'Promoted' ? '#4f46e5' : standing === 'Retained' ? '#f59e0b' : standing === 'Excluded' ? '#dc2626' : '#64748b' }"
              />
            </div>
            <span class="text-sm font-semibold w-8 text-right">{{ count }}</span>
          </div>
        </div>
      </div>

    </div>

    <!-- Section utilisation -->
    <div class="bg-white rounded-xl border border-slate-200 p-5 mb-5">
      <h2 class="text-sm font-semibold text-slate-700 mb-3">Section Utilisation</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <div
          v-for="s in sectionStats"
          :key="s.id"
          class="border border-slate-100 rounded-lg p-3"
        >
          <div class="flex items-center justify-between mb-1.5">
            <p class="text-sm font-medium text-slate-700">Gr.{{ s.grade }} {{ s.name }}</p>
            <span class="text-xs text-slate-500">{{ s.enrolled }}/{{ s.capacity }}</span>
          </div>
          <div class="bg-slate-100 rounded-full h-2 overflow-hidden">
            <div class="h-2 rounded-full transition-all" :style="pctBar(s.pct)" />
          </div>
          <p class="text-xs text-slate-400 mt-1 text-right">{{ s.pct }}% capacity</p>
        </div>
        <div v-if="sectionStats.length === 0" class="text-slate-400 text-sm col-span-3 text-center py-4">
          No sections found for this school year.
        </div>
      </div>
    </div>

    <!-- Bottom row: GWA distribution + Honors + At-risk -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

      <!-- GWA distribution -->
      <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">GWA Distribution</h2>
        <div v-if="orderedBuckets().length === 0" class="text-slate-400 text-sm text-center py-6">
          No GWA data yet.
        </div>
        <div v-else class="space-y-2">
          <div v-for="b in orderedBuckets()" :key="b.label" class="flex items-center gap-2">
            <span class="text-xs w-28 text-slate-600">{{ b.label }}</span>
            <div class="flex-1 bg-slate-100 rounded-full h-3 overflow-hidden">
              <div
                class="h-3 rounded-full"
                :style="{ width: b.count + 'px', minWidth: '8px', maxWidth: '100%', background: b.color }"
              />
            </div>
            <span class="text-xs font-semibold w-6 text-right" :style="{ color: b.color }">{{ b.count }}</span>
          </div>
        </div>
      </div>

      <!-- Honors -->
      <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2">
          <TrophyIcon class="w-4 h-4 text-amber-500" />
          Honors Awardees
        </h2>
        <div v-if="Object.keys(honorsDist).length === 0" class="text-slate-400 text-sm text-center py-6">
          No honors data yet.
        </div>
        <div v-else class="space-y-3">
          <div v-for="honors in HONORS_ORDER" :key="honors">
            <div v-if="honorsDist[honors]" class="flex items-center gap-3">
              <div class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: HONORS_COLORS[honors] }" />
              <span class="text-sm text-slate-700 flex-1">{{ honors }}</span>
              <span class="text-lg font-bold" :style="{ color: HONORS_COLORS[honors] }">{{ honorsDist[honors] }}</span>
            </div>
          </div>
          <div class="border-t border-slate-100 pt-2 flex justify-between text-sm">
            <span class="text-slate-500">Total awardees</span>
            <span class="font-bold text-amber-700">{{ Object.values(honorsDist).reduce((a, b) => a + b, 0) }}</span>
          </div>
        </div>
      </div>

      <!-- At-risk students -->
      <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2">
          <ExclamationTriangleIcon class="w-4 h-4 text-red-500" />
          At-Risk (Failed Subjects)
        </h2>
        <div v-if="atRisk.length === 0" class="text-slate-400 text-sm text-center py-6">
          No at-risk data yet.
        </div>
        <div v-else class="space-y-1.5">
          <div
            v-for="row in atRisk"
            :key="row.student_id"
            class="flex items-center justify-between text-sm"
          >
            <span class="text-slate-600 tabular-nums">ID {{ row.student_id }}</span>
            <span
              :class="[
                'font-semibold px-2 py-0.5 rounded-full text-xs',
                row.failed_count >= 3 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'
              ]"
            >
              {{ row.failed_count }} failed
            </span>
          </div>
          <p class="text-xs text-slate-400 mt-2">Showing top 20 students by failed subject count.</p>
        </div>
      </div>

    </div>

  </AdminLayout>
</template>
