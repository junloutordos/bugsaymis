<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  BuildingLibraryIcon,
  UsersIcon,
  BuildingOffice2Icon,
  ArrowDownTrayIcon,
  PrinterIcon,
  ChartBarIcon,
  ChevronDownIcon,
  ChevronRightIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  roster:      { type: Array,  default: () => [] },
  totalCount:  { type: Number, default: 0 },
  byCategory:  { type: Object, default: () => ({}) },
  bySex:       { type: Object, default: () => ({}) },
  generatedAt: { type: String, default: '' },
})

// Expand/collapse state per division
const expanded = ref(
  Object.fromEntries(props.roster.map(d => [d.id, true]))
)
function toggle(id) {
  expanded.value[id] = !expanded.value[id]
}

// Search
const search = ref('')
const filtered = computed(() => {
  const q = search.value.toLowerCase().trim()
  if (!q) return props.roster

  return props.roster.map(div => {
    const offices = div.offices
      .map(o => ({
        ...o,
        employees: o.employees.filter(e =>
          e.name.toLowerCase().includes(q) ||
          (e.badge_id ?? '').toLowerCase().includes(q) ||
          (e.position ?? '').toLowerCase().includes(q)
        ),
      }))
      .filter(o => o.employees.length)

    const unassigned = div.unassigned.filter(e =>
      e.name.toLowerCase().includes(q) ||
      (e.badge_id ?? '').toLowerCase().includes(q) ||
      (e.position ?? '').toLowerCase().includes(q)
    )

    if (!offices.length && !unassigned.length) return null
    return { ...div, offices, unassigned }
  }).filter(Boolean)
})

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}

function categoryColor(c) {
  const map = {
    'Teaching':     'bg-blue-100 text-blue-700',
    'Non-Teaching': 'bg-amber-100 text-amber-700',
    'Contractual':  'bg-violet-100 text-violet-700',
    'COS':          'bg-pink-100 text-pink-700',
    'JO':           'bg-orange-100 text-orange-700',
  }
  return map[c] ?? 'bg-slate-100 text-slate-500'
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
            Org Structure Report
          </h1>
          <p class="text-xs text-slate-400 mt-0.5">
            Generated {{ fmtDate(generatedAt) }} &bull; {{ totalCount }} active employees
          </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
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
          <a
            :href="route('hr.org.index')"
            class="inline-flex items-center gap-1.5 text-sm px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg font-medium"
          >
            ← Org Chart
          </a>
        </div>
      </div>

      <!-- Summary cards -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-5 py-4">
          <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Total Employees</p>
          <p class="text-3xl font-bold text-indigo-600 mt-1">{{ totalCount }}</p>
          <p class="text-xs text-slate-500 mt-0.5">active</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-5 py-4">
          <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Divisions</p>
          <p class="text-3xl font-bold text-violet-600 mt-1">{{ roster.length }}</p>
          <p class="text-xs text-slate-500 mt-0.5">active</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-5 py-4">
          <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">By Category</p>
          <div class="mt-1 space-y-0.5">
            <div v-for="(count, cat) in byCategory" :key="cat" class="flex items-center justify-between gap-2">
              <span class="text-xs text-slate-600 truncate">{{ cat || 'Unset' }}</span>
              <span class="text-xs font-semibold text-slate-800">{{ count }}</span>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm px-5 py-4">
          <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">By Sex</p>
          <div class="mt-1 space-y-0.5">
            <div v-for="(count, sex) in bySex" :key="sex" class="flex items-center justify-between gap-2">
              <span class="text-xs text-slate-600 capitalize">{{ sex || 'Unset' }}</span>
              <span class="text-xs font-semibold text-slate-800">{{ count }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Search -->
      <div class="flex items-center gap-3">
        <input
          v-model="search"
          type="search"
          placeholder="Search employee, badge ID, or position…"
          class="w-full max-w-sm border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
        />
        <span v-if="search" class="text-xs text-slate-400">
          Showing results in {{ filtered.length }} division(s)
        </span>
      </div>

      <!-- Division → Office → Employees -->
      <div class="space-y-4">
        <div
          v-for="div in filtered"
          :key="div.id"
          class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden"
        >
          <!-- Division header -->
          <button
            @click="toggle(div.id)"
            class="w-full flex items-center justify-between gap-3 px-5 py-4 text-left hover:bg-slate-50 transition-colors"
          >
            <div class="flex items-center gap-3 min-w-0">
              <BuildingLibraryIcon class="h-5 w-5 text-indigo-400 shrink-0" />
              <div class="min-w-0">
                <span class="text-sm font-semibold text-slate-800">{{ div.name }}</span>
                <span class="ml-2 text-xs font-mono text-indigo-500 bg-indigo-50 px-2 py-0.5 rounded">{{ div.acronym }}</span>
                <p v-if="div.chief" class="text-xs text-slate-400 mt-0.5 truncate">
                  Chief: {{ div.chief }}<span v-if="div.chief_pos"> — {{ div.chief_pos }}</span>
                </p>
              </div>
            </div>
            <div class="flex items-center gap-3 shrink-0">
              <span class="text-xs bg-indigo-100 text-indigo-700 px-2.5 py-1 rounded-full font-semibold">
                {{ div.total }} employee{{ div.total !== 1 ? 's' : '' }}
              </span>
              <ChevronDownIcon v-if="expanded[div.id]" class="h-4 w-4 text-slate-400" />
              <ChevronRightIcon v-else class="h-4 w-4 text-slate-400" />
            </div>
          </button>

          <!-- Offices & employees -->
          <div v-show="expanded[div.id]">

            <!-- Per-office table -->
            <div
              v-for="office in div.offices"
              :key="office.id"
              class="border-t border-slate-100"
            >
              <!-- Office sub-header -->
              <div class="flex items-center gap-2 px-5 py-2.5 bg-slate-50">
                <BuildingOffice2Icon class="h-4 w-4 text-slate-400 shrink-0" />
                <span class="text-xs font-semibold text-slate-600 uppercase tracking-wide">{{ office.name }}</span>
                <span class="text-xs text-slate-400 ml-1">({{ office.employees.length }})</span>
              </div>

              <!-- Employee rows -->
              <table v-if="office.employees.length" class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                  <tr>
                    <th class="px-5 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Name</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Badge ID</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Position</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Category</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Sex</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr
                    v-for="emp in office.employees"
                    :key="emp.id"
                    class="hover:bg-slate-50/70 transition-colors"
                  >
                    <td class="px-5 py-2.5 font-medium text-slate-800">{{ emp.name }}</td>
                    <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ emp.badge_id ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-slate-600 text-xs">{{ emp.position ?? '—' }}</td>
                    <td class="px-4 py-2.5">
                      <span v-if="emp.emp_category" :class="['text-xs px-2 py-0.5 rounded-full font-medium', categoryColor(emp.emp_category)]">
                        {{ emp.emp_category }}
                      </span>
                      <span v-else class="text-xs text-slate-400">—</span>
                    </td>
                    <td class="px-4 py-2.5 text-xs text-slate-500 capitalize">{{ emp.sex ?? '—' }}</td>
                  </tr>
                </tbody>
              </table>
              <p v-else class="px-5 py-3 text-xs text-slate-400 italic">No employees assigned to this office.</p>
            </div>

            <!-- Unassigned to any office -->
            <div v-if="div.unassigned.length" class="border-t border-amber-100">
              <div class="flex items-center gap-2 px-5 py-2.5 bg-amber-50">
                <UsersIcon class="h-4 w-4 text-amber-400 shrink-0" />
                <span class="text-xs font-semibold text-amber-700 uppercase tracking-wide">No Office/Unit Assigned</span>
                <span class="text-xs text-amber-500 ml-1">({{ div.unassigned.length }})</span>
              </div>
              <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-50">
                  <tr
                    v-for="emp in div.unassigned"
                    :key="emp.id"
                    class="hover:bg-amber-50/40"
                  >
                    <td class="px-5 py-2.5 font-medium text-slate-800 w-1/3">{{ emp.name }}</td>
                    <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ emp.badge_id ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-slate-600 text-xs">{{ emp.position ?? '—' }}</td>
                    <td class="px-4 py-2.5">
                      <span v-if="emp.emp_category" :class="['text-xs px-2 py-0.5 rounded-full font-medium', categoryColor(emp.emp_category)]">
                        {{ emp.emp_category }}
                      </span>
                      <span v-else class="text-xs text-slate-400">—</span>
                    </td>
                    <td class="px-4 py-2.5 text-xs text-slate-500 capitalize">{{ emp.sex ?? '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Division total footer -->
            <div class="border-t border-slate-100 px-5 py-2.5 bg-slate-50 flex justify-end">
              <span class="text-xs text-slate-500 font-medium">
                Total for {{ div.acronym }}: {{ div.total }} employee{{ div.total !== 1 ? 's' : '' }}
              </span>
            </div>
          </div>
        </div>

        <p v-if="!filtered.length" class="text-center text-sm text-slate-400 py-12">
          No matching employees found.
        </p>
      </div>

    </div>
  </AdminLayout>
</template>
