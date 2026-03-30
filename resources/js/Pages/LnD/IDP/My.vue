<script setup>
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  idps: { type: Array,  default: () => [] },
  year: { type: Number, required: true },
})

const selectedYear = ref(props.year)
const currentYear  = new Date().getFullYear()
const yearOptions  = Array.from({ length: 6 }, (_, i) => currentYear - 2 + i)

watch(selectedYear, (y) => {
  router.get(route('lnd.my-idp'), { year: y }, { preserveState: true, replace: true })
})

const fmt = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'
const approvalColors = { draft: 'bg-gray-100 text-gray-600', submitted: 'bg-yellow-100 text-yellow-700', approved: 'bg-green-100 text-green-700', returned: 'bg-red-100 text-red-600' }
const statusColors   = { planned: 'bg-blue-100 text-blue-700', ongoing: 'bg-yellow-100 text-yellow-700', completed: 'bg-green-100 text-green-700', deferred: 'bg-orange-100 text-orange-700', cancelled: 'bg-red-100 text-red-600' }
const levelColors    = { none: 'bg-gray-100 text-gray-500', basic: 'bg-blue-100 text-blue-600', intermediate: 'bg-indigo-100 text-indigo-700', advanced: 'bg-purple-100 text-purple-700' }
const interventionLabel = { training: 'Training', coaching: 'Coaching', assignment: 'Assignment', self_study: 'Self-Study', e_learning: 'E-Learning', other: 'Other' }
</script>

<template>
  <AdminLayout title="My IDP">
    <Head title="My IDP" />

    <div class="p-6 space-y-5">

      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">My Individual Development Plan</h1>
          <p class="text-sm text-slate-500">Your personal development activities and targets</p>
        </div>
        <select v-model="selectedYear"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>

      <!-- Empty -->
      <div v-if="idps.length === 0"
        class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center text-slate-400 text-sm">
        No IDP entries for {{ year }}.
      </div>

      <!-- IDP Cards -->
      <div v-for="idp in idps" :key="idp.id"
        class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50 flex items-start justify-between">
          <div>
            <h3 class="font-semibold text-slate-800">{{ idp.competency }}</h3>
            <p class="text-xs text-slate-500 mt-0.5">{{ interventionLabel[idp.intervention_type] ?? idp.intervention_type }}</p>
          </div>
          <div class="flex gap-2 shrink-0">
            <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium', approvalColors[idp.approval_status] ?? 'bg-slate-100 text-slate-600']">
              {{ idp.approval_status === 'submitted' ? 'Pending' : idp.approval_status }}
            </span>
            <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium capitalize', statusColors[idp.status] ?? 'bg-slate-100 text-slate-600']">
              {{ idp.status }}
            </span>
          </div>
        </div>
        <div class="p-5 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div>
            <div class="text-xs text-slate-500 mb-1">Learning Program</div>
            <div class="text-slate-800">{{ idp.learning_program?.title ?? '—' }}</div>
          </div>
          <div>
            <div class="text-xs text-slate-500 mb-1">Supervisor</div>
            <div class="text-slate-800">{{ idp.supervisor?.name ?? '—' }}</div>
          </div>
          <div>
            <div class="text-xs text-slate-500 mb-1">Timeline</div>
            <div class="text-slate-800 text-xs">
              <template v-if="idp.timeline_start">{{ fmt(idp.timeline_start) }}<br>to {{ fmt(idp.timeline_end) }}</template>
              <template v-else>—</template>
            </div>
          </div>
          <div>
            <div class="text-xs text-slate-500 mb-1">Gap</div>
            <div class="flex items-center gap-1 text-xs">
              <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[idp.current_level]]">{{ idp.current_level }}</span>
              <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
              <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[idp.target_level]]">{{ idp.target_level }}</span>
            </div>
          </div>
        </div>
        <div v-if="idp.supervisor_remarks" class="px-5 pb-4">
          <div class="rounded-lg bg-amber-50 border border-amber-100 p-3 text-sm text-amber-800">
            <span class="font-medium">Supervisor:</span> {{ idp.supervisor_remarks }}
          </div>
        </div>
        <div class="px-5 pb-4 flex justify-end">
          <a :href="route('lnd.idp.show', idp.id)"
            class="text-xs font-medium text-indigo-600 hover:underline">View Details</a>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
