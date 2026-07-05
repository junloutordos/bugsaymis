<script setup>
import { ref, computed } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
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

function adjectivalColor(adj) {
  const map = {
    'Excellent':    'green',
    'Very Good':    'blue',
    'Satisfactory': 'purple',
    'Fair':         'amber',
    'Poor':         'red',
  }
  return map[adj] ?? 'slate'
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

      <AppPageHeader title="CSM Feedback" subtitle="All client satisfaction survey responses">
        <template #actions>
          <AppButton as="link" variant="secondary" :href="route('csm.dashboard')">Dashboard</AppButton>
          <AppButton as="a" variant="success" :href="buildExportUrl()">
            <ArrowDownTrayIcon class="h-4 w-4" /> Export Excel
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <input v-model="search" type="text" placeholder="Search…"
          @keydown.enter="applyFilters"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />

        <select v-model="module" @change="applyFilters"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Modules</option>
          <option v-for="opt in moduleOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>

        <select v-model="clientType" @change="applyFilters"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Client Types</option>
          <option value="citizen">Citizen</option>
          <option value="business">Business</option>
          <option value="government">Government</option>
        </select>

        <input v-model="month" type="month" @change="applyFilters"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />

        <input v-model="dateFrom" type="date" placeholder="From" @change="applyFilters"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />

        <input v-model="dateTo" type="date" placeholder="To" @change="applyFilters"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />

        <template #actions>
          <AppButton size="sm" @click="applyFilters">Apply</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!responses.data?.length" :skeleton-cols="8">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Module</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Respondent</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Client Type</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Office Availed</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Avg SQD</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Adjectival</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="r in responses.data" :key="r.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap text-xs">{{ fmtDate(r.created_at) }}</td>
          <td class="px-4 py-2.5">
            <AppBadge color="indigo">{{ r.module_label }}</AppBadge>
          </td>
          <td class="px-4 py-2.5 text-slate-700">{{ r.user?.name ?? '—' }}</td>
          <td class="px-4 py-2.5 text-slate-600 capitalize text-xs">{{ r.client_type }}</td>
          <td class="px-4 py-2.5 text-slate-600 text-xs">{{ r.office_availed }}</td>
          <td class="px-4 py-2.5 text-center font-semibold tabular-nums"
            :class="r.avg_sqd >= 4 ? 'text-success-700' : r.avg_sqd >= 3 ? 'text-warning-600' : 'text-danger-600'">
            {{ r.avg_sqd?.toFixed(2) ?? '—' }}
          </td>
          <td class="px-4 py-2.5">
            <AppBadge :color="adjectivalColor(r.adjectival)">{{ r.adjectival }}</AppBadge>
          </td>
          <td class="px-4 py-2.5">
            <Link :href="route('csm.show', r.id)"
              class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 text-xs font-medium">
              <EyeIcon class="h-3.5 w-3.5" /> View
            </Link>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="r in responses.data" :key="r.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ r.user?.name ?? '—' }}</p>
                <p class="text-xs text-slate-500">{{ fmtDate(r.created_at) }} &middot; <span class="capitalize">{{ r.client_type }}</span></p>
              </div>
              <AppBadge :color="adjectivalColor(r.adjectival)">{{ r.adjectival }}</AppBadge>
            </div>
            <AppBadge color="indigo">{{ r.module_label }}</AppBadge>
            <p class="text-xs text-slate-500">{{ r.office_availed }}</p>
            <div class="flex items-center justify-between pt-1">
              <span class="text-xs font-semibold tabular-nums"
                :class="r.avg_sqd >= 4 ? 'text-success-700' : r.avg_sqd >= 3 ? 'text-warning-600' : 'text-danger-600'">
                Avg SQD {{ r.avg_sqd?.toFixed(2) ?? '—' }}
              </span>
              <Link :href="route('csm.show', r.id)" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 text-xs font-medium">
                <EyeIcon class="h-3.5 w-3.5" /> View
              </Link>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No CSM responses found" />
        </template>

        <template #footer>
          <PaginationControl
            :links="responses.links"
            :current-page="responses.current_page"
            :total-pages="responses.last_page"
            :total="responses.total"
          />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>
