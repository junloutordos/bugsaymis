<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import {
  BuildingLibraryIcon,
  UsersIcon,
  BuildingOffice2Icon,
  ArrowDownTrayIcon,
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
    'Teaching':     'blue',
    'Non-Teaching': 'amber',
    'Contractual':  'purple',
    'COS':          'red',
    'JO':           'orange',
  }
  return map[c] ?? 'slate'
}
</script>

<template>
  <Head title="Org Structure — Reports" />
  <AdminLayout title="Org Structure Reports">
    <div class="space-y-6">

      <!-- Header -->
      <AppPageHeader
        title="Org Structure Report"
        :subtitle="`Generated ${fmtDate(generatedAt)} · ${totalCount} active employees`"
      >
        <template #actions>
          <AppButton as="a" :href="route('hr.org.export.units-csv')" variant="secondary" size="sm">
            <ArrowDownTrayIcon class="h-4 w-4" /> Units CSV
          </AppButton>
          <AppButton as="a" :href="route('hr.org.export.assignments-csv')" variant="secondary" size="sm">
            <ArrowDownTrayIcon class="h-4 w-4" /> Assignments CSV
          </AppButton>
          <AppButton as="a" :href="route('hr.org.index')" variant="ghost" size="sm">
            ← Org Chart
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Summary cards -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <AppCard :padded="false">
          <div class="px-5 py-4">
            <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Total Employees</p>
            <p class="text-3xl font-bold text-indigo-600 mt-1">{{ totalCount }}</p>
            <p class="text-xs text-slate-500 mt-0.5">active</p>
          </div>
        </AppCard>
        <AppCard :padded="false">
          <div class="px-5 py-4">
            <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">Divisions</p>
            <p class="text-3xl font-bold text-purple-600 mt-1">{{ roster.length }}</p>
            <p class="text-xs text-slate-500 mt-0.5">active</p>
          </div>
        </AppCard>
        <AppCard :padded="false">
          <div class="px-5 py-4">
            <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">By Category</p>
            <div class="mt-1 space-y-0.5">
              <div v-for="(count, cat) in byCategory" :key="cat" class="flex items-center justify-between gap-2">
                <span class="text-xs text-slate-600 truncate">{{ cat || 'Unset' }}</span>
                <span class="text-xs font-semibold text-slate-800">{{ count }}</span>
              </div>
            </div>
          </div>
        </AppCard>
        <AppCard :padded="false">
          <div class="px-5 py-4">
            <p class="text-xs text-slate-400 uppercase tracking-wide font-medium">By Sex</p>
            <div class="mt-1 space-y-0.5">
              <div v-for="(count, sex) in bySex" :key="sex" class="flex items-center justify-between gap-2">
                <span class="text-xs text-slate-600 capitalize">{{ sex || 'Unset' }}</span>
                <span class="text-xs font-semibold text-slate-800">{{ count }}</span>
              </div>
            </div>
          </div>
        </AppCard>
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
                <AppBadge color="indigo" class="ml-2 font-mono">{{ div.acronym }}</AppBadge>
                <p v-if="div.chief" class="text-xs text-slate-400 mt-0.5 truncate">
                  Chief: {{ div.chief }}<span v-if="div.chief_pos"> — {{ div.chief_pos }}</span>
                </p>
              </div>
            </div>
            <div class="flex items-center gap-3 shrink-0">
              <AppBadge color="indigo">
                {{ div.total }} employee{{ div.total !== 1 ? 's' : '' }}
              </AppBadge>
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
              <AppTable v-if="office.employees.length" :card="false">
                <template #head>
                  <tr>
                    <th class="px-5 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Name</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Badge ID</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Position</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Category</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Sex</th>
                  </tr>
                </template>
                <tr
                  v-for="emp in office.employees"
                  :key="emp.id"
                  class="hover:bg-slate-50/70 transition-colors"
                >
                  <td class="px-5 py-2.5 font-medium text-slate-800">{{ emp.name }}</td>
                  <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ emp.badge_id ?? '—' }}</td>
                  <td class="px-4 py-2.5 text-slate-600 text-xs">{{ emp.position ?? '—' }}</td>
                  <td class="px-4 py-2.5">
                    <AppBadge v-if="emp.emp_category" :color="categoryColor(emp.emp_category)">{{ emp.emp_category }}</AppBadge>
                    <span v-else class="text-xs text-slate-400">—</span>
                  </td>
                  <td class="px-4 py-2.5 text-xs text-slate-500 capitalize">{{ emp.sex ?? '—' }}</td>
                </tr>
              </AppTable>
              <p v-else class="px-5 py-3 text-xs text-slate-400 italic">No employees assigned to this office.</p>
            </div>

            <!-- Unassigned to any office -->
            <div v-if="div.unassigned.length" class="border-t border-warning-100">
              <div class="flex items-center gap-2 px-5 py-2.5 bg-warning-50">
                <UsersIcon class="h-4 w-4 text-warning-500 shrink-0" />
                <span class="text-xs font-semibold text-warning-700 uppercase tracking-wide">No Office/Unit Assigned</span>
                <span class="text-xs text-warning-500 ml-1">({{ div.unassigned.length }})</span>
              </div>
              <AppTable :card="false">
                <template #head><tr class="sr-only"><th /></tr></template>
                <tr
                  v-for="emp in div.unassigned"
                  :key="emp.id"
                  class="hover:bg-warning-50/40"
                >
                  <td class="px-5 py-2.5 font-medium text-slate-800 w-1/3">{{ emp.name }}</td>
                  <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ emp.badge_id ?? '—' }}</td>
                  <td class="px-4 py-2.5 text-slate-600 text-xs">{{ emp.position ?? '—' }}</td>
                  <td class="px-4 py-2.5">
                    <AppBadge v-if="emp.emp_category" :color="categoryColor(emp.emp_category)">{{ emp.emp_category }}</AppBadge>
                    <span v-else class="text-xs text-slate-400">—</span>
                  </td>
                  <td class="px-4 py-2.5 text-xs text-slate-500 capitalize">{{ emp.sex ?? '—' }}</td>
                </tr>
              </AppTable>
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
