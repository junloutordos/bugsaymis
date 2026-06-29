<template>
  <Head title="My Faculty Load" />
  <AdminLayout title="My Faculty Load">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">My Faculty Load</h1>
          <p class="text-sm text-slate-500 mt-0.5">Your teaching load summary for the selected term</p>
        </div>
        <div class="flex items-center gap-2">
          <select v-model="selectedTermId" @change="applyFilter"
            class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 text-slate-700 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option v-for="t in terms" :key="t.id" :value="t.id">
              {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
            </option>
          </select>
          <button v-if="load" @click="printLoad"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm border border-slate-200 text-slate-600 hover:bg-slate-50 rounded-lg font-medium transition-colors"
            title="Print faculty load">
            <PrinterIcon class="h-4 w-4" /> Print
          </button>
        </div>
      </div>

      <!-- No load record -->
      <div v-if="!load" class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center">
        <DocumentTextIcon class="mx-auto h-12 w-12 text-slate-200 mb-3" />
        <p class="text-sm font-medium text-slate-500">No load record for this term</p>
        <p class="text-xs text-slate-400 mt-1">Your load record will appear here once schedules are assigned.</p>
      </div>

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
          <span :class="badgeClass" class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold shrink-0">
            {{ statusLabel }}
          </span>
        </div>

        <!-- Load breakdown -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
          <div v-for="item in breakdown" :key="item.label"
            class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 text-center">
            <p class="text-2xl font-bold text-slate-800">{{ item.value }}</p>
            <p class="text-xs text-slate-500 mt-0.5">{{ item.label }}</p>
          </div>
        </div>

        <!-- Assignments list -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
          <div class="px-4 py-3 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-700">Load Assignments</h2>
          </div>
          <div v-if="!load.assignments || load.assignments.length === 0" class="py-10 text-center">
            <p class="text-sm text-slate-400">No assignments recorded.</p>
          </div>
          <table v-else class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Assignment</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
                <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Units</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="a in load.assignments" :key="a.id" class="hover:bg-slate-50/50">
                <td class="px-4 py-3 text-slate-800">{{ assignmentLabel(a) }}</td>
                <td class="px-4 py-3">
                  <span :class="typeBadge(a.assignment_type)"
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                    {{ a.assignment_type }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center font-semibold text-slate-700">{{ a.load_units }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Overload computation card -->
        <div v-if="load.overload_computation" class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
          <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Overload Pay Computation</h2>
            <span :class="ocStatusClass(load.overload_computation.status)"
              class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
              {{ ocStatusLabel(load.overload_computation.status) }}
            </span>
          </div>
          <div class="px-4 py-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="text-center">
              <p class="text-xs text-slate-500 mb-1">Annual Rate</p>
              <p class="text-sm font-semibold text-slate-800">{{ phpFmt(load.overload_computation.annual_rate) }}</p>
            </div>
            <div class="text-center">
              <p class="text-xs text-slate-500 mb-1">PHTR</p>
              <p class="text-sm font-semibold text-emerald-700">{{ phpFmt(load.overload_computation.phtr) }}</p>
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
        </div>
      </template>

    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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
  underload: 'bg-amber-50 border-amber-200 text-amber-800',
  full_load: 'bg-emerald-50 border-emerald-200 text-emerald-800',
  overload:  'bg-red-50 border-red-200 text-red-800',
})[props.load?.load_status] ?? 'bg-slate-50 border-slate-200 text-slate-700')

const badgeClass = computed(() => ({
  underload: 'bg-amber-100 text-amber-700',
  full_load: 'bg-emerald-100 text-emerald-700',
  overload:  'bg-red-100 text-red-700',
})[props.load?.load_status] ?? 'bg-slate-100 text-slate-600')

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

function ocStatusClass(s) {
  return {
    for_approval: 'bg-amber-50 text-amber-700',
    approved:     'bg-emerald-50 text-emerald-700',
    paid:         'bg-indigo-50 text-indigo-700',
    rejected:     'bg-red-50 text-red-600',
    pending:      'bg-slate-50 text-slate-600',
  }[s] ?? 'bg-slate-50 text-slate-600'
}

function assignmentLabel(a) {
  if (a.assignment_type === 'teaching' && a.subject) {
    return a.section_name ? `${a.subject.name} (${a.section_name})` : a.subject.name
  }
  return a.display_label
}

function typeBadge(type) {
  return {
    teaching:    'bg-indigo-50 text-indigo-700',
    research:    'bg-violet-50 text-violet-700',
    admin:       'bg-blue-50 text-blue-700',
    cocurricular:'bg-teal-50 text-teal-700',
    committee:   'bg-orange-50 text-orange-700',
  }[type] ?? 'bg-slate-50 text-slate-600'
}
</script>
