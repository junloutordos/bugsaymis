<script setup>
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'

const props = defineProps({
  year:         { type: Number, required: true },
  consolidated: { type: Object, default: () => ({}) },
})

const selectedYear = ref(props.year)
const currentYear  = new Date().getFullYear()
const yearOptions  = Array.from({ length: 6 }, (_, i) => currentYear - 2 + i)

watch(selectedYear, (y) => {
  router.get(route('lnd.tna.consolidation'), { year: y }, { preserveState: true, replace: true })
})

const levelColors = {
  none:         'bg-gray-100 text-gray-500',
  basic:        'bg-blue-100 text-blue-600',
  intermediate: 'bg-indigo-100 text-indigo-700',
  advanced:     'bg-purple-100 text-purple-700',
}
const sourceLabel = { self: 'Self', supervisor: 'Supervisor', hr: 'HR', ipcr: 'IPCR', spms: 'SPMS' }

function priorityBadgeColor (p) {
  const map = { high: 'red', medium: 'amber', low: 'green' }
  return map[p] ?? 'slate'
}

const entries = Object.entries(props.consolidated)
</script>

<template>
  <Head title="TNA Consolidation" />
  <AdminLayout title="TNA Consolidation">
    <div class="space-y-5">

      <AppPageHeader
        title="TNA Consolidation"
        :breadcrumb="[{ label: 'Training Needs', href: route('lnd.tna.index') }, { label: 'Consolidation' }]"
      >
        <template #actions>
          <select v-model="selectedYear"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
          </select>
        </template>
      </AppPageHeader>

      <!-- Summary Banner -->
      <AppCard>
        <p class="text-sm text-slate-600">
          Showing unaddressed training needs for <span class="font-semibold text-slate-800">{{ year }}</span>
          — grouped by competency area.
          <span class="font-semibold text-indigo-600">{{ entries.length }}</span> unique area(s).
        </p>
      </AppCard>

      <!-- Empty state -->
      <AppCard v-if="entries.length === 0">
        <p class="py-8 text-center text-slate-400 text-sm">No unaddressed training needs for {{ year }}.</p>
      </AppCard>

      <!-- Grouped Cards -->
      <div v-for="[area, needs] in entries" :key="area" class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
          <h3 class="font-semibold text-slate-800">{{ area }}</h3>
          <AppBadge color="indigo">{{ needs.length }} employee{{ needs.length !== 1 ? 's' : '' }}</AppBadge>
        </div>
        <AppTable :card="false" :skeleton-cols="6">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Employee</th>
              <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Gap</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Recommended Training</th>
              <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Priority</th>
              <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Source</th>
              <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            </tr>
          </template>

          <tr v-for="n in needs" :key="n.id" class="hover:bg-indigo-50/40">
            <td class="px-4 py-3 font-medium text-sm text-slate-800">{{ n.employee?.name ?? '—' }}</td>
            <td class="px-4 py-3 text-center">
              <div class="flex items-center justify-center gap-1 text-xs">
                <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[n.current_level]]">{{ n.current_level }}</span>
                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[n.target_level]]">{{ n.target_level }}</span>
              </div>
            </td>
            <td class="px-4 py-3 text-sm text-slate-700 max-w-[200px] truncate">{{ n.recommended_training ?? '—' }}</td>
            <td class="px-4 py-3 text-center">
              <AppBadge :color="priorityBadgeColor(n.priority_level)">{{ n.priority_level }}</AppBadge>
            </td>
            <td class="px-4 py-3 text-center text-xs text-slate-600">{{ sourceLabel[n.source] ?? n.source }}</td>
            <td class="px-4 py-3 text-center">
              <AppBadge color="slate">{{ n.status }}</AppBadge>
            </td>
          </tr>
        </AppTable>
      </div>

    </div>
  </AdminLayout>
</template>
