<template>
  <Head title="My Load Assignments" />
  <AdminLayout title="My Load Assignments">
    <div class="space-y-5">

      <AppPageHeader title="My Load Assignments" subtitle="Your teaching load summary for the selected term">
        <template #actions>
          <AppSelect v-model="selectedTermId" :show-blank="false" class="min-w-[200px]" @change="applyFilter">
            <option v-for="t in terms" :key="t.id" :value="t.id">
              {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
            </option>
          </AppSelect>
          <AppButton v-if="load" variant="secondary" size="sm" @click="printLoad">
            <PrinterIcon class="h-4 w-4" /> Print
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- No load record -->
      <AppCard v-if="!load" :padded="false">
        <EmptyState title="No load record for this term" subtitle="Your load record will appear here once schedules are assigned." :icon="DocumentTextIcon" />
      </AppCard>

      <template v-else>
        <!-- Status banner -->
        <div :class="bannerClass" class="rounded-xl border px-5 py-4 flex items-center justify-between gap-4">
          <div>
            <p class="font-semibold text-base">{{ statusLabel }} — {{ load.total_units }} / {{ load.full_load_threshold }} units</p>
            <p class="text-sm mt-0.5 opacity-80">
              <template v-if="load.load_status === 'underload'">
                You are {{ (load.full_load_threshold - load.total_units).toFixed(2) }} unit(s) below full load.
              </template>
              <template v-else-if="load.load_status === 'overload'">
                You have {{ load.overload_units }} overload unit(s).
                <span v-if="load.overload_approved" class="font-medium"> Overload approved.</span>
                <span v-else class="font-medium"> Pending Campus Director approval.</span>
              </template>
              <template v-else>
                You have reached full load.
              </template>
            </p>
          </div>
          <AppBadge :color="statusColor" class="!text-sm !px-3 !py-1">{{ statusLabel }}</AppBadge>
        </div>

        <!-- Load breakdown -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
          <AppCard v-for="item in breakdown" :key="item.label" :padded="false">
            <div class="p-4 text-center">
              <p class="text-2xl font-bold text-slate-800">{{ item.value }}</p>
              <p class="text-xs text-slate-500 mt-0.5">{{ item.label }}</p>
            </div>
          </AppCard>
        </div>

        <!-- Assignments list -->
        <AppCard title="Load Assignments" :padded="false">
          <AppTable :is-empty="!load.assignments || load.assignments.length === 0" :skeleton-cols="3" :card="false">
            <template #head>
              <tr>
                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Assignment</th>
                <th class="px-4 py-2.5 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Type</th>
                <th class="px-4 py-2.5 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Units</th>
              </tr>
            </template>

            <tr v-for="a in load.assignments" :key="a.id" class="hover:bg-indigo-50/40">
              <td class="px-4 py-3 text-slate-800">{{ assignmentLabel(a) }}</td>
              <td class="px-4 py-3">
                <AppBadge :color="typeColor(a.assignment_type)">{{ a.assignment_type }}</AppBadge>
              </td>
              <td class="px-4 py-3 text-center font-semibold text-slate-700">{{ a.load_units }}</td>
            </tr>

            <template #mobileCard>
              <div v-for="a in load.assignments" :key="a.id" class="p-4 flex items-center justify-between gap-2">
                <div>
                  <p class="text-sm text-slate-800">{{ assignmentLabel(a) }}</p>
                  <AppBadge :color="typeColor(a.assignment_type)">{{ a.assignment_type }}</AppBadge>
                </div>
                <span class="font-semibold text-slate-700 text-sm">{{ a.load_units }} units</span>
              </div>
            </template>

            <template #empty>
              <p class="text-sm text-slate-400">No assignments recorded.</p>
            </template>
          </AppTable>
        </AppCard>

        <!-- Overload computation card -->
        <AppCard v-if="load.overload_computation" title="Overload Pay Computation" :padded="false">
          <template #header>
            <AppBadge :color="ocStatusColor(load.overload_computation.status)">{{ ocStatusLabel(load.overload_computation.status) }}</AppBadge>
          </template>
          <div class="px-4 py-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="text-center">
              <p class="text-xs text-slate-500 mb-1">Annual Rate</p>
              <p class="text-sm font-semibold text-slate-800">{{ phpFmt(load.overload_computation.annual_rate) }}</p>
            </div>
            <div class="text-center">
              <p class="text-xs text-slate-500 mb-1">PHTR</p>
              <p class="text-sm font-semibold text-success-700">{{ phpFmt(load.overload_computation.phtr) }}</p>
            </div>
            <div class="text-center">
              <p class="text-xs text-slate-500 mb-1">Hours × Weeks</p>
              <p class="text-sm font-semibold text-slate-800">{{ load.overload_computation.overload_hours }} × {{ load.overload_computation.term_weeks }}</p>
            </div>
            <div class="text-center">
              <p class="text-xs text-slate-500 mb-1">Total Overload Pay</p>
              <p class="text-base font-bold text-indigo-700">{{ phpFmt(load.overload_computation.total_overload_pay) }}</p>
            </div>
          </div>
          <p v-if="load.overload_computation.approved_at"
            class="px-4 pb-3 text-xs text-slate-400">
            Approved on {{ load.overload_computation.approved_at }}
          </p>
        </AppCard>
      </template>

    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { DocumentTextIcon, PrinterIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  load:        { type: Object, default: null },
  terms:       { type: Array,  default: () => [] },
  currentTerm: { type: Object, default: null },
  filters:     { type: Object, default: () => ({}) },
})

const selectedTermId = ref(props.filters.term_id ?? props.currentTerm?.id)

function applyFilter() {
  router.get(route('faculty-loading.my-load'), { term_id: selectedTermId.value }, { preserveState: true })
}

function printLoad() {
  window.open(route('faculty-loading.my-load.print', { term_id: selectedTermId.value }), '_blank')
}

const statusLabel = computed(() => ({
  underload: 'Underload', full_load: 'Full Load', overload: 'Overload',
})[props.load?.load_status] ?? '—')

const bannerClass = computed(() => ({
  underload: 'bg-warning-50 border-warning-100 text-warning-700',
  full_load: 'bg-success-50 border-success-100 text-success-700',
  overload:  'bg-danger-50 border-danger-100 text-danger-600',
})[props.load?.load_status] ?? 'bg-slate-50 border-slate-200 text-slate-700')

const statusColor = computed(() => ({
  underload: 'amber',
  full_load: 'green',
  overload:  'red',
})[props.load?.load_status] ?? 'slate')

const breakdown = computed(() => {
  if (! props.load) return []
  return [
    { label: 'Teaching',     value: props.load.teaching_units },
    { label: 'Research',     value: props.load.research_units },
    { label: 'Admin',        value: props.load.admin_units },
    { label: 'Co-curricular',value: props.load.cocurricular_units },
    { label: 'Committee',    value: props.load.committee_units },
  ]
})

function phpFmt(val) {
  if (val == null) return '—'
  return '₱' + Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function ocStatusLabel(s) {
  return { for_approval: 'For Approval', approved: 'Approved', paid: 'Paid', rejected: 'Rejected', pending: 'Pending' }[s] ?? s
}

function ocStatusColor(s) {
  return {
    for_approval: 'amber',
    approved:     'green',
    paid:         'indigo',
    rejected:     'red',
    pending:      'slate',
  }[s] ?? 'slate'
}

function assignmentLabel(a) {
  if (a.assignment_type === 'teaching' && a.subject) {
    return a.section_name ? `${a.subject.name} (${a.section_name})` : a.subject.name
  }
  return a.display_label
}

function typeColor(type) {
  return {
    teaching:    'indigo',
    research:    'purple',
    admin:       'blue',
    cocurricular:'green',
    committee:   'orange',
  }[type] ?? 'slate'
}
</script>
