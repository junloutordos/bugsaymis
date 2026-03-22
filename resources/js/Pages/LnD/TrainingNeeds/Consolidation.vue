<script setup>
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

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

const priorityColors = {
  high:   'bg-red-100 text-red-700',
  medium: 'bg-yellow-100 text-yellow-700',
  low:    'bg-green-100 text-green-700',
}
const levelColors = {
  none:         'bg-gray-100 text-gray-500',
  basic:        'bg-blue-100 text-blue-600',
  intermediate: 'bg-indigo-100 text-indigo-700',
  advanced:     'bg-purple-100 text-purple-700',
}
const sourceLabel = { self: 'Self', supervisor: 'Supervisor', hr: 'HR', ipcr: 'IPCR', spms: 'SPMS' }

const entries = Object.entries(props.consolidated)
</script>

<template>
  <AdminLayout title="TNA Consolidation">
    <Head title="TNA Consolidation" />

    <div class="p-6 space-y-5">

      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div class="flex items-center gap-2 text-sm">
          <a :href="route('lnd.tna.index')"
            class="inline-flex items-center gap-1 text-slate-500 hover:text-slate-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Training Needs
          </a>
          <span class="text-slate-300">/</span>
          <span class="font-medium text-slate-700">Consolidation</span>
        </div>
        <select v-model="selectedYear"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>

      <!-- Summary Banner -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
        <p class="text-sm text-slate-600">
          Showing unaddressed training needs for <span class="font-semibold text-slate-800">{{ year }}</span>
          — grouped by competency area.
          <span class="font-semibold text-indigo-600">{{ entries.length }}</span> unique area(s).
        </p>
      </div>

      <!-- Empty state -->
      <div v-if="entries.length === 0"
        class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center text-slate-400 text-sm">
        No unaddressed training needs for {{ year }}.
      </div>

      <!-- Grouped Cards -->
      <div v-for="[area, needs] in entries" :key="area"
        class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
          <h3 class="font-semibold text-slate-800">{{ area }}</h3>
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-indigo-50 text-indigo-700">
            {{ needs.length }} employee{{ needs.length !== 1 ? 's' : '' }}
          </span>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Employee</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Gap</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Recommended Training</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Priority</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Source</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="n in needs" :key="n.id" class="hover:bg-slate-50/60">
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
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium capitalize', priorityColors[n.priority_level]]">
                    {{ n.priority_level }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center text-xs text-slate-600">{{ sourceLabel[n.source] ?? n.source }}</td>
                <td class="px-4 py-3 text-center">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium capitalize bg-slate-100 text-slate-600">
                    {{ n.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
