<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  year:         { type: Number, required: true },
  years:        { type: Array,  default: () => [] },
  kpis:         { type: Object, default: () => ({}) },
  utilization:  { type: Array,  default: () => [] },
  fill_rate:    { type: Array,  default: () => [] },
  time_to_hire: { type: Array,  default: () => [] },
  pipeline:     { type: Array,  default: () => [] },
})

const selectedYear = ref(props.year)

const changeYear = () => {
  router.get(route('recruitment.reports.index'), { year: selectedYear.value }, { preserveState: false })
}

// ── Pipeline chart helpers ─────────────────────────────────────────────────
const pipelineMax = computed(() =>
  Math.max(...props.pipeline.map(m => m.submitted), 1)
)

const barPct = (val) => Math.round((val / pipelineMax.value) * 100)

// ── Stage color helpers ────────────────────────────────────────────────────
const fillColor = (rate) => {
  if (rate >= 75) return 'bg-emerald-500'
  if (rate >= 40) return 'bg-amber-400'
  return 'bg-red-400'
}

const fillText = (rate) => {
  if (rate >= 75) return 'text-emerald-700'
  if (rate >= 40) return 'text-amber-700'
  return 'text-red-600'
}
</script>

<template>
  <Head title="Recruitment Reports" />
  <AdminLayout title="Recruitment Reports">
    <div class="space-y-6 max-w-6xl mx-auto">

      <!-- Year selector / header row -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Recruitment Dashboard</h1>
        <div class="flex items-center gap-2">
          <label class="text-xs font-medium text-slate-600">Year:</label>
          <select v-model="selectedYear" @change="changeYear"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
      </div>

      <!-- KPI Cards -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-indigo-600">{{ kpis.total_applications?.toLocaleString() ?? 0 }}</p>
          <p class="text-xs text-slate-500 mt-1">Applications ({{ year }})</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-emerald-600">{{ kpis.total_placements?.toLocaleString() ?? 0 }}</p>
          <p class="text-xs text-slate-500 mt-1">Placements ({{ year }})</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-indigo-600">{{ kpis.total_applicants?.toLocaleString() ?? 0 }}</p>
          <p class="text-xs text-slate-500 mt-1">Total Applicants (all-time)</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-amber-600">{{ kpis.active_vacancies?.toLocaleString() ?? 0 }}</p>
          <p class="text-xs text-slate-500 mt-1">Active Vacancies</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
          <p class="text-2xl font-bold text-slate-700">{{ kpis.open_positions?.toLocaleString() ?? 0 }}</p>
          <p class="text-xs text-slate-500 mt-1">Open Positions</p>
        </div>
      </div>

      <!-- Row 1: Pipeline + Fill Rate -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Monthly Pipeline -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6">
          <h3 class="text-sm font-semibold text-slate-700 mb-4">Monthly Application Pipeline — {{ year }}</h3>
          <div class="space-y-3">
            <div v-for="m in pipeline" :key="m.month">
              <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
                <span class="w-8 font-semibold text-slate-600">{{ m.month }}</span>
                <span>{{ m.submitted }} submitted · {{ m.placed }} placed · {{ m.rejected }} rejected</span>
              </div>
              <div class="flex gap-1 h-2.5">
                <div class="bg-indigo-500 rounded-sm transition-all duration-300"
                     :style="{ width: barPct(m.submitted) + '%' }" :title="`${m.submitted} submitted`" />
                <div class="bg-emerald-500 rounded-sm transition-all duration-300"
                     :style="{ width: barPct(m.placed) + '%' }" :title="`${m.placed} placed`" />
                <div class="bg-red-400 rounded-sm transition-all duration-300"
                     :style="{ width: barPct(m.rejected) + '%' }" :title="`${m.rejected} rejected`" />
              </div>
            </div>
          </div>
          <div class="flex gap-4 mt-4 text-xs text-slate-500">
            <span class="flex items-center gap-1"><span class="w-3 h-3 bg-indigo-500 rounded-sm inline-block" /> Submitted</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 bg-emerald-500 rounded-sm inline-block" /> Placed</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 bg-red-400 rounded-sm inline-block" /> Rejected</span>
          </div>
        </div>

        <!-- Vacancy Fill Rate -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6">
          <h3 class="text-sm font-semibold text-slate-700 mb-4">Vacancy Fill Rate by Type — {{ year }}</h3>
          <div v-if="fill_rate.length" class="space-y-4">
            <div v-for="r in fill_rate" :key="r.type">
              <div class="flex items-center justify-between text-sm mb-1">
                <span class="font-medium text-slate-700 truncate max-w-[60%]">{{ r.type }}</span>
                <span class="font-bold" :class="fillText(r.fill_rate)">{{ r.fill_rate }}%</span>
              </div>
              <div class="w-full bg-slate-100 rounded-full h-2.5">
                <div class="h-2.5 rounded-full transition-all duration-500"
                     :class="fillColor(r.fill_rate)"
                     :style="{ width: r.fill_rate + '%' }" />
              </div>
              <div class="text-xs text-slate-400 mt-0.5">
                {{ r.filled }} / {{ r.total }} vacancies filled · {{ r.open }} open
              </div>
            </div>
          </div>
          <div v-else class="py-16 text-center text-slate-400 text-sm">
            No vacancy data for {{ year }}.
          </div>
        </div>

      </div>

      <!-- Row 2: Plantilla Utilization + Time-to-Hire -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Plantilla Utilization -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6">
          <h3 class="text-sm font-semibold text-slate-700 mb-4">Plantilla Utilization</h3>
          <div v-if="utilization.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
              <thead class="bg-slate-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Total</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Filled</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Vacant</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Fill %</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="u in utilization" :key="u.name" class="hover:bg-slate-50/60">
                  <td class="px-4 py-3 text-sm font-medium text-slate-700">{{ u.name }}</td>
                  <td class="px-4 py-3 text-sm text-right text-slate-600">{{ u.total }}</td>
                  <td class="px-4 py-3 text-sm text-right text-emerald-700 font-semibold">{{ u.filled }}</td>
                  <td class="px-4 py-3 text-sm text-right text-amber-600">{{ u.total - u.filled }}</td>
                  <td class="px-4 py-3 text-sm text-right font-bold" :class="fillText(u.fill_rate)">{{ u.fill_rate }}%</td>
                </tr>
              </tbody>
              <tfoot class="border-t border-slate-100">
                <tr class="text-xs text-slate-500">
                  <td class="px-4 pt-2 font-bold">Total</td>
                  <td class="px-4 pt-2 text-right font-bold">{{ utilization.reduce((s, u) => s + u.total, 0) }}</td>
                  <td class="px-4 pt-2 text-right font-bold text-emerald-700">{{ utilization.reduce((s, u) => s + u.filled, 0) }}</td>
                  <td class="px-4 pt-2 text-right font-bold text-amber-600">
                    {{ utilization.reduce((s, u) => s + (u.total - u.filled), 0) }}
                  </td>
                  <td class="px-4 pt-2 text-right font-bold">
                    {{
                      utilization.reduce((s, u) => s + u.total, 0) > 0
                        ? Math.round(utilization.reduce((s, u) => s + u.filled, 0) / utilization.reduce((s, u) => s + u.total, 0) * 100)
                        : 0
                    }}%
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
          <div v-else class="py-16 text-center text-slate-400 text-sm">No position data available.</div>
        </div>

        <!-- Time to Hire -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6">
          <h3 class="text-sm font-semibold text-slate-700 mb-4">Time-to-Hire (Days) — {{ year }}</h3>
          <div v-if="time_to_hire.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
              <thead class="bg-slate-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Placements</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Avg Days</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Min</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Max</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="t in time_to_hire" :key="t.type" class="hover:bg-slate-50/60">
                  <td class="px-4 py-3 text-sm font-medium text-slate-700">{{ t.type }}</td>
                  <td class="px-4 py-3 text-sm text-right text-slate-600">{{ t.placements }}</td>
                  <td class="px-4 py-3 text-sm text-right font-bold"
                      :class="t.avg_days <= 30 ? 'text-emerald-700' : t.avg_days <= 60 ? 'text-amber-700' : 'text-red-600'">
                    {{ t.avg_days }}
                  </td>
                  <td class="px-4 py-3 text-sm text-right text-slate-500">{{ t.min_days }}</td>
                  <td class="px-4 py-3 text-sm text-right text-slate-500">{{ t.max_days }}</td>
                </tr>
              </tbody>
            </table>
            <p class="mt-3 text-xs text-slate-400">Days from application submission to confirmed placement.</p>
          </div>
          <div v-else class="py-16 text-center text-slate-400 text-sm">
            No placement data for {{ year }}.
          </div>
        </div>

      </div>

    </div>
  </AdminLayout>
</template>
