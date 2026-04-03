<script setup>
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  BuildingLibraryIcon,
  UsersIcon,
  BuildingOfficeIcon,
  ArrowDownTrayIcon,
  PrinterIcon,
  ChartBarIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  stats: { type: Object, required: true },
})

const TYPE_LABEL = {
  institution: 'Institution',
  division:    'Division',
  department:  'Department',
  section:     'Section',
  unit:        'Unit',
  office:      'Office',
  committee:   'Committee',
}

const TYPE_COLOR = {
  institution: 'bg-violet-100 text-violet-700',
  division:    'bg-blue-100 text-blue-700',
  department:  'bg-cyan-100 text-cyan-700',
  section:     'bg-teal-100 text-teal-700',
  unit:        'bg-emerald-100 text-emerald-700',
  office:      'bg-amber-100 text-amber-700',
  committee:   'bg-pink-100 text-pink-700',
}

const byTypeList = computed(() =>
  Object.entries(props.stats.by_type ?? {}).map(([type, data]) => ({
    type,
    label: TYPE_LABEL[type] ?? type,
    colorClass: TYPE_COLOR[type] ?? 'bg-slate-100 text-slate-600',
    ...data,
  })).sort((a, b) => b.count - a.count)
)

function openPrint() {
  window.open(route('hr.org.print'), '_blank')
}
</script>

<template>
  <Head title="Org Structure — Reports" />
  <AdminLayout title="Org Structure Reports">
    <div class="space-y-6">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
            <ChartBarIcon class="h-6 w-6 text-indigo-500" />
            Org Structure Reports
          </h1>
          <p class="text-sm text-slate-500 mt-0.5">Summary statistics and data exports.</p>
        </div>

        <!-- Export buttons -->
        <div class="flex items-center gap-2 flex-wrap">
          <button
            @click="openPrint"
            class="inline-flex items-center gap-1.5 text-sm px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-medium"
          >
            <PrinterIcon class="h-4 w-4" /> Print Chart
          </button>
          <a
            :href="route('hr.org.export.pdf')"
            target="_blank"
            class="inline-flex items-center gap-1.5 text-sm px-3 py-2 bg-red-50 hover:bg-red-100 text-red-700 rounded-lg font-medium"
          >
            <ArrowDownTrayIcon class="h-4 w-4" /> Download PDF
          </a>
          <a
            :href="route('hr.org.export.units-csv')"
            class="inline-flex items-center gap-1.5 text-sm px-3 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg font-medium"
          >
            <ArrowDownTrayIcon class="h-4 w-4" /> Units CSV
          </a>
          <a
            :href="route('hr.org.export.assignments-csv')"
            class="inline-flex items-center gap-1.5 text-sm px-3 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg font-medium"
          >
            <ArrowDownTrayIcon class="h-4 w-4" /> Assignments CSV
          </a>
        </div>
      </div>

      <!-- ── Summary cards ─────────────────────────────────────────────────────── -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-5 py-4">
          <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Total Units</p>
          <p class="text-3xl font-bold text-indigo-600 mt-1">{{ stats.total_units }}</p>
          <p class="text-xs text-slate-500 mt-0.5">{{ stats.active_units }} active</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-5 py-4">
          <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Employees Assigned</p>
          <p class="text-3xl font-bold text-emerald-600 mt-1">{{ stats.total_employees }}</p>
          <p class="text-xs text-slate-500 mt-0.5">active assignments</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-5 py-4">
          <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Root Units</p>
          <p class="text-3xl font-bold text-violet-600 mt-1">{{ stats.root_units }}</p>
          <p class="text-xs text-slate-500 mt-0.5">top-level institutions</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-5 py-4">
          <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Max Depth</p>
          <p class="text-3xl font-bold text-amber-600 mt-1">{{ stats.max_depth }}</p>
          <p class="text-xs text-slate-500 mt-0.5">hierarchy levels</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- ── By type ───────────────────────────────────────────────────────────── -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
          <div class="px-5 py-3 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
              <BuildingOfficeIcon class="h-4 w-4 text-indigo-400" /> Units by Type
            </h3>
          </div>
          <ul class="divide-y divide-slate-100">
            <li
              v-for="row in byTypeList"
              :key="row.type"
              class="px-5 py-3 flex items-center gap-3"
            >
              <span :class="['text-xs font-semibold px-2 py-0.5 rounded uppercase tracking-wide w-24 text-center', row.colorClass]">
                {{ row.label }}
              </span>
              <div class="flex-1">
                <!-- Bar -->
                <div class="w-full bg-slate-100 rounded-full h-1.5 mt-1">
                  <div
                    class="bg-indigo-400 h-1.5 rounded-full"
                    :style="{ width: stats.total_units > 0 ? (row.count / stats.total_units * 100) + '%' : '0%' }"
                  ></div>
                </div>
              </div>
              <span class="text-sm font-semibold text-slate-700 w-6 text-right">{{ row.count }}</span>
              <span class="text-xs text-slate-400 w-10 text-right">{{ row.employees }} emp.</span>
            </li>
          </ul>
        </div>

        <!-- ── Top units by employee count ─────────────────────────────────────── -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
          <div class="px-5 py-3 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
              <UsersIcon class="h-4 w-4 text-indigo-400" /> Most Staffed Units
            </h3>
          </div>
          <ul class="divide-y divide-slate-100">
            <li
              v-for="(u, i) in stats.top_units"
              :key="u.id"
              class="px-5 py-3 flex items-center gap-3"
            >
              <span class="text-xs text-slate-400 w-5 text-right font-mono">{{ i + 1 }}</span>
              <div class="flex-1 min-w-0">
                <a
                  :href="route('hr.org.units.show', u.id)"
                  class="text-sm font-medium text-slate-800 hover:text-indigo-600 truncate block"
                >
                  {{ u.name }}
                </a>
                <p class="text-xs text-slate-400 font-mono">{{ u.code }} · {{ u.type }}</p>
              </div>
              <span class="text-sm font-semibold text-indigo-600">{{ u.employee_count }}</span>
            </li>
            <li v-if="!stats.top_units?.length" class="px-5 py-8 text-center text-sm text-slate-400">
              No data yet.
            </li>
          </ul>
        </div>
      </div>

      <!-- ── Quick links ────────────────────────────────────────────────────────── -->
      <div class="flex flex-wrap gap-3">
        <a :href="route('hr.org.index')" class="text-sm text-indigo-600 hover:underline">← Back to Org Chart</a>
        <a :href="route('hr.org.versions.index')" class="text-sm text-indigo-600 hover:underline">Version History →</a>
      </div>

    </div>
  </AdminLayout>
</template>
