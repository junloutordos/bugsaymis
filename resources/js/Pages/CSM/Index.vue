<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowDownTrayIcon, EyeIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  responses:    Object,
  moduleLabels: Object,
  filters:      { type: Object, default: () => ({}) },
})

const search      = ref(props.filters.search      ?? '')
const module      = ref(props.filters.module      ?? '')
const clientType  = ref(props.filters.client_type ?? '')
const dateFrom    = ref(props.filters.date_from   ?? '')
const dateTo      = ref(props.filters.date_to     ?? '')
const month       = ref(props.filters.month       ?? '')

function applyFilters() {
  router.get(route('csm.list'), {
    search:       search.value       || undefined,
    module:       module.value       || undefined,
    client_type:  clientType.value   || undefined,
    date_from:    dateFrom.value     || undefined,
    date_to:      dateTo.value       || undefined,
    month:        month.value        || undefined,
  }, { preserveState: true, replace: true })
}

function buildExportUrl() {
  const p = new URLSearchParams()
  if (module.value)      p.set('module',       module.value)
  if (clientType.value)  p.set('client_type',  clientType.value)
  if (dateFrom.value)    p.set('date_from',     dateFrom.value)
  if (dateTo.value)      p.set('date_to',       dateTo.value)
  if (month.value)       p.set('month',         month.value)
  return route('csm.export') + (p.toString() ? '?' + p.toString() : '')
}

function adjectivalBadge(adj) {
  const map = {
    'Excellent':    'bg-emerald-100 text-emerald-700',
    'Very Good':    'bg-blue-100 text-blue-700',
    'Satisfactory': 'bg-violet-100 text-violet-700',
    'Fair':         'bg-amber-100 text-amber-700',
    'Poor':         'bg-red-100 text-red-600',
  }
  return map[adj] ?? 'bg-slate-100 text-slate-600'
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
}

const moduleOptions = Object.entries(props.moduleLabels).map(([value, label]) => ({ value, label }))
</script>

<template>
  <Head title="CSM Feedback List" />
  <AdminLayout title="CSM Feedback">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-slate-800">CSM Feedback</h1>
          <p class="text-sm text-slate-500 mt-0.5">All client satisfaction survey responses</p>
        </div>
        <div class="flex items-center gap-2">
          <a :href="route('csm.dashboard')"
            class="border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 px-3 py-2 rounded-lg text-sm font-medium transition-colors">
            Dashboard
          </a>
          <a :href="buildExportUrl()"
            class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            <ArrowDownTrayIcon class="h-4 w-4" /> Export Excel
          </a>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
          <input v-model="search" type="text" placeholder="Search…"
            @keydown.enter="applyFilters"
            class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 col-span-2 sm:col-span-1" />

          <select v-model="module" @change="applyFilters"
            class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option value="">All Modules</option>
            <option v-for="opt in moduleOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>

          <select v-model="clientType" @change="applyFilters"
            class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option value="">All Client Types</option>
            <option value="citizen">Citizen</option>
            <option value="business">Business</option>
            <option value="government">Government</option>
          </select>

          <input v-model="month" type="month" @change="applyFilters"
            class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />

          <input v-model="dateFrom" type="date" placeholder="From" @change="applyFilters"
            class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />

          <input v-model="dateTo" type="date" placeholder="To" @change="applyFilters"
            class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
        </div>
        <div class="mt-2 flex justify-end">
          <button @click="applyFilters"
            class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors">
            Apply
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Module</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Respondent</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Client Type</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Office Availed</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-amber-500 uppercase tracking-wide whitespace-nowrap">Avg SQD</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Adjectival</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="!responses.data?.length">
              <td colspan="8" class="py-16 text-center text-slate-400 text-sm">No CSM responses found.</td>
            </tr>
            <tr v-for="r in responses.data" :key="r.id" class="hover:bg-slate-50/60">
              <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap text-xs">{{ fmtDate(r.created_at) }}</td>
              <td class="px-4 py-2.5">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 text-[11px] font-medium whitespace-nowrap">
                  {{ r.module_label }}
                </span>
              </td>
              <td class="px-4 py-2.5 text-slate-700">{{ r.user?.name ?? '—' }}</td>
              <td class="px-4 py-2.5 text-slate-600 capitalize text-xs">{{ r.client_type }}</td>
              <td class="px-4 py-2.5 text-slate-600 text-xs">{{ r.office_availed }}</td>
              <td class="px-4 py-2.5 text-center font-semibold tabular-nums"
                :class="r.avg_sqd >= 4 ? 'text-emerald-600' : r.avg_sqd >= 3 ? 'text-amber-600' : 'text-red-500'">
                {{ r.avg_sqd?.toFixed(2) ?? '—' }}
              </td>
              <td class="px-4 py-2.5">
                <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium', adjectivalBadge(r.adjectival)]">
                  {{ r.adjectival }}
                </span>
              </td>
              <td class="px-4 py-2.5">
                <a :href="route('csm.show', r.id)"
                  class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 text-xs font-medium">
                  <EyeIcon class="h-3.5 w-3.5" /> View
                </a>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>{{ responses.from }}–{{ responses.to }} of {{ responses.total }}</span>
          <div class="flex items-center gap-2">
            <a v-if="responses.prev_page_url" :href="responses.prev_page_url"
              class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-sm">Prev</a>
            <a v-if="responses.next_page_url" :href="responses.next_page_url"
              class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-sm">Next</a>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
