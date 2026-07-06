<template>
  <Head title="Faculty Load Report" />
  <AdminLayout title="Faculty Load Report">
    <div class="space-y-5">

      <AppPageHeader title="Faculty Load Report" subtitle="Load summary per faculty for the selected academic term">
        <template #actions>
          <AppButton as="a" :href="exportUrl" target="_blank" variant="secondary">
            <ArrowDownTrayIcon class="h-4 w-4" /> Export CSV
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <div class="flex-1 min-w-[180px]">
          <AppSelect v-model="filters.term_id" label="Academic Term" placeholder="All Terms" @change="applyFilters">
            <option v-for="t in terms" :key="t.id" :value="t.id">
              {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
            </option>
          </AppSelect>
        </div>
        <div class="w-40">
          <AppSelect v-model="filters.status" label="Load Status" placeholder="All Statuses" @change="applyFilters">
            <option value="underload">Underload</option>
            <option value="full_load">Full Load</option>
            <option value="overload">Overload</option>
          </AppSelect>
        </div>
        <div class="flex-1 min-w-[180px]">
          <AppInput v-model="filters.search" label="Search Faculty" placeholder="Name..." @input="debouncedApply" />
        </div>
        <template #actions>
          <AppButton v-if="hasActiveFilter" size="sm" variant="secondary" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Summary cards -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <AppCard :padded="false">
          <div class="px-4 py-3 text-center">
            <p class="text-2xl font-bold text-slate-800">{{ summary.total }}</p>
            <p class="text-xs text-slate-500 mt-0.5">Total Faculty</p>
          </div>
        </AppCard>
        <AppCard :padded="false">
          <div class="px-4 py-3 text-center">
            <p class="text-2xl font-bold text-danger-600">{{ summary.overload }}</p>
            <p class="text-xs text-slate-500 mt-0.5">Overload</p>
          </div>
        </AppCard>
        <AppCard :padded="false">
          <div class="px-4 py-3 text-center">
            <p class="text-2xl font-bold text-success-600">{{ summary.full_load }}</p>
            <p class="text-xs text-slate-500 mt-0.5">Full Load</p>
          </div>
        </AppCard>
        <AppCard :padded="false">
          <div class="px-4 py-3 text-center">
            <p class="text-2xl font-bold text-warning-500">{{ summary.underload }}</p>
            <p class="text-xs text-slate-500 mt-0.5">Underload</p>
          </div>
        </AppCard>
      </div>

      <!-- Table -->
      <AppTable :is-empty="!loads.length" :skeleton-cols="12">
        <template #head>
          <tr>
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide w-8">#</th>
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Faculty</th>
            <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">SG</th>
            <th class="px-4 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Teaching</th>
            <th class="px-4 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Research</th>
            <th class="px-4 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Admin</th>
            <th class="px-4 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Co-Curr.</th>
            <th class="px-4 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Cmte.</th>
            <th class="px-4 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Total</th>
            <th class="px-4 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Overload</th>
            <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">OL Approved</th>
          </tr>
        </template>

        <tr v-for="(l, i) in loads" :key="l.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-2.5 text-slate-400 text-xs">{{ i + 1 }}</td>
          <td class="px-4 py-2.5">
            <p class="font-medium text-slate-800">{{ l.faculty_name }}</p>
            <p v-if="l.position" class="text-xs text-slate-400">{{ l.position }}</p>
          </td>
          <td class="px-4 py-2.5 text-center text-slate-600">{{ l.salary_grade ?? '—' }}</td>
          <td class="px-4 py-2.5 text-right text-slate-600">{{ l.teaching_units }}</td>
          <td class="px-4 py-2.5 text-right text-slate-600">{{ l.research_units }}</td>
          <td class="px-4 py-2.5 text-right text-slate-600">{{ l.admin_units }}</td>
          <td class="px-4 py-2.5 text-right text-slate-600">{{ l.cocurricular_units }}</td>
          <td class="px-4 py-2.5 text-right text-slate-600">{{ l.committee_units }}</td>
          <td class="px-4 py-2.5 text-right font-semibold text-slate-800">{{ l.total_units }}</td>
          <td class="px-4 py-2.5 text-right">
            <span v-if="l.overload_units > 0" class="text-danger-600 font-medium">+{{ l.overload_units }}</span>
            <span v-else class="text-slate-300">—</span>
          </td>
          <td class="px-4 py-2.5 text-center">
            <AppBadge :color="statusColor(l.load_status)">{{ statusLabel(l.load_status) }}</AppBadge>
          </td>
          <td class="px-4 py-2.5 text-center">
            <span v-if="l.load_status === 'overload'">
              <CheckCircleIcon v-if="l.overload_approved" class="h-4 w-4 text-success-500 mx-auto" />
              <span v-else class="text-xs text-warning-500 font-medium">Pending</span>
            </span>
            <span v-else class="text-slate-300 text-xs">—</span>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="(l, i) in loads" :key="l.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ l.faculty_name }}</p>
                <p v-if="l.position" class="text-xs text-slate-400">{{ l.position }}</p>
              </div>
              <AppBadge :color="statusColor(l.load_status)">{{ statusLabel(l.load_status) }}</AppBadge>
            </div>
            <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-500">
              <span>SG {{ l.salary_grade ?? '—' }}</span>
              <span>Total {{ l.total_units }} units</span>
              <span>Teaching {{ l.teaching_units }} · Research {{ l.research_units }}</span>
              <span>Admin {{ l.admin_units }} · Co-Curr {{ l.cocurricular_units }} · Cmte {{ l.committee_units }}</span>
            </div>
            <div class="flex justify-between text-xs pt-1">
              <span v-if="l.overload_units > 0" class="text-danger-600 font-medium">+{{ l.overload_units }} overload</span>
              <span v-else class="text-slate-300">No overload</span>
              <span v-if="l.load_status === 'overload'">
                <CheckCircleIcon v-if="l.overload_approved" class="h-4 w-4 text-success-500" />
                <span v-else class="text-warning-500 font-medium">Pending approval</span>
              </span>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No load records found" subtitle="Try adjusting the filters above." :icon="TableCellsIcon" />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppInput from '@/Components/AppInput.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { ArrowDownTrayIcon, CheckCircleIcon, TableCellsIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  loads:       { type: Array,  default: () => [] },
  terms:       { type: Array,  default: () => [] },
  currentTerm: { type: Object, default: null },
  filters:     { type: Object, default: () => ({}) },
  summary:     { type: Object, default: () => ({ total: 0, overload: 0, full_load: 0, underload: 0 }) },
})

const filters = ref({
  term_id: props.filters.term_id ?? props.currentTerm?.id ?? '',
  status:  props.filters.status ?? '',
  search:  props.filters.search ?? '',
})

const hasActiveFilter = computed(() =>
  filters.value.term_id !== (props.currentTerm?.id ?? '') ||
  filters.value.status !== '' ||
  filters.value.search !== ''
)

const exportUrl = computed(() => {
  const p = new URLSearchParams()
  if (filters.value.term_id) p.set('term_id', filters.value.term_id)
  if (filters.value.status)  p.set('status',  filters.value.status)
  if (filters.value.search)  p.set('search',  filters.value.search)
  return route('faculty-loading.reports.loads.export') + (p.toString() ? '?' + p.toString() : '')
})

function applyFilters() {
  router.get(route('faculty-loading.reports.loads'), {
    term_id: filters.value.term_id || undefined,
    status:  filters.value.status  || undefined,
    search:  filters.value.search  || undefined,
  }, { preserveState: true, replace: true })
}

let debounceTimer = null
function debouncedApply() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 400)
}

function clearFilters() {
  filters.value = { term_id: props.currentTerm?.id ?? '', status: '', search: '' }
  applyFilters()
}

function statusLabel(s) {
  return { underload: 'Underload', full_load: 'Full Load', overload: 'Overload' }[s] ?? s
}

function statusColor(s) {
  return {
    underload: 'amber',
    full_load: 'green',
    overload:  'red',
  }[s] ?? 'slate'
}
</script>
