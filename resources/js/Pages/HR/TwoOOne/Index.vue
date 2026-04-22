<template>
  <Head title="201 Files" />
  <AdminLayout title="201 Files">
    <div class="space-y-5">

      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Employee 201 Files</h1>
          <p class="text-sm text-slate-500 mt-0.5">CSC PRIME-HRM — {{ employees.length }} employee{{ employees.length !== 1 ? 's' : '' }}</p>
        </div>
        <!-- Search -->
        <div class="relative w-full sm:w-72">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none" />
          <input
            v-model="search"
            type="text"
            placeholder="Search employee…"
            class="w-full pl-9 pr-4 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400"
          />
        </div>
      </div>

      <!-- Legend -->
      <div class="flex flex-wrap gap-4 text-xs text-slate-500">
        <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full bg-emerald-500"></span> Complete (all required folders present)</span>
        <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full bg-amber-400"></span> Partial (some required folders missing)</span>
        <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full bg-red-400"></span> Empty (no documents on file)</span>
      </div>

      <!-- Employee Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                <th class="px-4 py-3 text-left">Employee</th>
                <th class="px-4 py-3 text-left hidden md:table-cell">Position / Division</th>
                <th class="px-4 py-3 text-center">Completeness</th>
                <th class="px-4 py-3 text-center hidden lg:table-cell" v-for="cat in requiredCategories" :key="cat.value" :title="cat.label">
                  Folder {{ cat.folder }}
                </th>
                <th class="px-4 py-3 text-center">Total Docs</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-if="filtered.length === 0">
                <td :colspan="5 + requiredCategories.length" class="py-16 text-center text-slate-400 text-sm">
                  <UserCircleIcon class="mx-auto h-10 w-10 text-slate-200 mb-2" />
                  No employees found.
                </td>
              </tr>
              <tr v-for="emp in filtered" :key="emp.id" class="hover:bg-slate-50/60 transition-colors">
                <!-- Employee Name -->
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2.5">
                    <span :class="statusDot(emp)" class="shrink-0 w-2.5 h-2.5 rounded-full"></span>
                    <div>
                      <p class="font-medium text-slate-800">{{ emp.name }}</p>
                      <p v-if="emp.employee_no" class="text-xs text-slate-400">EMP# {{ emp.employee_no }}</p>
                    </div>
                  </div>
                </td>
                <!-- Position -->
                <td class="px-4 py-3 hidden md:table-cell">
                  <p class="text-slate-700">{{ emp.position || '—' }}</p>
                  <p v-if="emp.division" class="text-xs text-slate-400">{{ emp.division }}</p>
                </td>
                <!-- Completeness bar -->
                <td class="px-4 py-3 text-center">
                  <div class="flex flex-col items-center gap-1">
                    <span :class="completenessColor(emp.completeness)" class="text-xs font-semibold tabular-nums">
                      {{ emp.completeness }}%
                    </span>
                    <div class="w-20 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                      <div
                        :class="completenessBarColor(emp.completeness)"
                        class="h-full rounded-full transition-all"
                        :style="{ width: emp.completeness + '%' }"
                      ></div>
                    </div>
                    <span class="text-[10px] text-slate-400">{{ emp.completed_required }}/{{ emp.total_required }} req.</span>
                  </div>
                </td>
                <!-- Per-required-folder indicator -->
                <td v-for="cat in requiredCategories" :key="cat.value" class="px-4 py-3 text-center hidden lg:table-cell">
                  <span
                    :title="cat.label + ': ' + (emp.counts[cat.value] ?? 0) + ' doc(s)'"
                    :class="emp.counts[cat.value] ? 'bg-emerald-100 text-emerald-700' : 'bg-red-50 text-red-400'"
                    class="inline-flex items-center justify-center w-6 h-6 rounded-full text-[10px] font-bold"
                  >
                    {{ emp.counts[cat.value] ?? 0 }}
                  </span>
                </td>
                <!-- Total docs -->
                <td class="px-4 py-3 text-center">
                  <span class="inline-flex items-center justify-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                    {{ emp.total_docs }}
                  </span>
                </td>
                <!-- Action -->
                <td class="px-4 py-3 text-right">
                  <a
                    :href="route('hr.employees.documents.index', emp.id)"
                    class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 border border-indigo-200 hover:border-indigo-400 rounded-lg px-2.5 py-1.5 transition-colors whitespace-nowrap"
                  >
                    <FolderOpenIcon class="h-3.5 w-3.5" />
                    Open 201
                  </a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- CSC PRIME-HRM Folder Reference -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-700">CSC PRIME-HRM 201 File Folder Reference</h2>
          <p class="text-xs text-slate-400 mt-0.5">Based on CSC Memorandum Circular No. 3, s. 2001</p>
        </div>
        <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <div
            v-for="cat in categories"
            :key="cat.value"
            class="flex gap-3 p-3 rounded-lg border"
            :class="cat.required ? 'border-indigo-100 bg-indigo-50/40' : 'border-slate-100 bg-slate-50/40'"
          >
            <div class="shrink-0 flex items-center justify-center w-8 h-8 rounded-lg text-xs font-bold"
              :class="cat.required ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-500'">
              {{ cat.folder }}
            </div>
            <div class="min-w-0">
              <p class="text-xs font-semibold text-slate-700 flex items-center gap-1">
                {{ cat.label }}
                <span v-if="cat.required" class="inline-flex items-center rounded-full bg-indigo-100 px-1.5 py-0 text-[9px] font-bold text-indigo-600 uppercase tracking-wide">
                  Required
                </span>
              </p>
              <p class="text-[11px] text-slate-400 mt-0.5 leading-tight">{{ cat.description }}</p>
            </div>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  MagnifyingGlassIcon,
  FolderOpenIcon,
  UserCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  employees:  Array,
  categories: Array,
  canManage:  Boolean,
})

const search = ref('')

const requiredCategories = computed(() => props.categories.filter(c => c.required))

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.employees
  return props.employees.filter(e =>
    e.name?.toLowerCase().includes(q) ||
    e.position?.toLowerCase().includes(q) ||
    e.division?.toLowerCase().includes(q) ||
    e.employee_no?.toLowerCase().includes(q)
  )
})

function statusDot(emp) {
  if (emp.total_docs === 0) return 'bg-red-400'
  if (emp.completeness >= 100) return 'bg-emerald-500'
  return 'bg-amber-400'
}

function completenessColor(pct) {
  if (pct >= 100) return 'text-emerald-600'
  if (pct >= 50)  return 'text-amber-500'
  return 'text-red-500'
}

function completenessBarColor(pct) {
  if (pct >= 100) return 'bg-emerald-500'
  if (pct >= 50)  return 'bg-amber-400'
  return 'bg-red-400'
}
</script>
