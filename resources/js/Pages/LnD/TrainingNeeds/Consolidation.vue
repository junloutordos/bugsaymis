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

      <!-- Header -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <a :href="route('lnd.tna.index')"
            class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Training Needs
          </a>
          <span class="text-gray-300">/</span>
          <span class="text-sm font-medium text-gray-700">Consolidation</span>
        </div>
        <select v-model="selectedYear"
          class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-400 focus:outline-none">
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>

      <!-- Summary -->
      <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <p class="text-sm text-gray-600">
          Showing unaddressed training needs for <span class="font-semibold text-gray-800">{{ year }}</span>
          — grouped by competency area.
          <span class="font-semibold text-blue-600">{{ entries.length }}</span> unique area(s).
        </p>
      </div>

      <!-- Empty state -->
      <div v-if="entries.length === 0"
        class="rounded-xl border border-gray-200 bg-white p-12 shadow-sm text-center text-gray-400">
        No unaddressed training needs for {{ year }}.
      </div>

      <!-- Grouped cards -->
      <div v-for="[area, needs] in entries" :key="area"
        class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="flex items-center justify-between bg-gray-50 border-b px-5 py-3">
          <h3 class="font-semibold text-gray-800">{{ area }}</h3>
          <span class="inline-flex items-center rounded-full bg-blue-100 text-blue-700 px-2.5 py-0.5 text-xs font-medium">
            {{ needs.length }} employee{{ needs.length !== 1 ? 's' : '' }}
          </span>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
              <tr>
                <th class="px-4 py-2 text-left">Employee</th>
                <th class="px-4 py-2 text-center">Gap</th>
                <th class="px-4 py-2 text-left">Recommended Training</th>
                <th class="px-4 py-2 text-center">Priority</th>
                <th class="px-4 py-2 text-center">Source</th>
                <th class="px-4 py-2 text-center">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="n in needs" :key="n.id" class="hover:bg-gray-50">
                <td class="px-4 py-2 font-medium text-gray-800">{{ n.employee?.name ?? '—' }}</td>
                <td class="px-4 py-2 text-center">
                  <div class="flex items-center justify-center gap-1 text-xs">
                    <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[n.current_level]]">{{ n.current_level }}</span>
                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[n.target_level]]">{{ n.target_level }}</span>
                  </div>
                </td>
                <td class="px-4 py-2 text-gray-600 max-w-[200px] truncate">{{ n.recommended_training ?? '—' }}</td>
                <td class="px-4 py-2 text-center">
                  <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize', priorityColors[n.priority_level]]">
                    {{ n.priority_level }}
                  </span>
                </td>
                <td class="px-4 py-2 text-center text-xs text-gray-600">{{ sourceLabel[n.source] ?? n.source }}</td>
                <td class="px-4 py-2 text-center">
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize bg-gray-100 text-gray-600">
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
