<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  idp: { type: Object, required: true },
})

const fmt = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' }) : '—'

const approvalColors = { draft: 'bg-gray-100 text-gray-600', submitted: 'bg-yellow-100 text-yellow-700', approved: 'bg-green-100 text-green-700', returned: 'bg-red-100 text-red-600' }
const statusColors   = { planned: 'bg-blue-100 text-blue-700', ongoing: 'bg-yellow-100 text-yellow-700', completed: 'bg-green-100 text-green-700', deferred: 'bg-orange-100 text-orange-700', cancelled: 'bg-red-100 text-red-600' }
const levelColors    = { none: 'bg-gray-100 text-gray-500', basic: 'bg-blue-100 text-blue-600', intermediate: 'bg-indigo-100 text-indigo-700', advanced: 'bg-purple-100 text-purple-700' }
const interventionLabel = { training: 'Training', coaching: 'Coaching', assignment: 'Assignment', self_study: 'Self-Study', e_learning: 'E-Learning', other: 'Other' }
</script>

<template>
  <AdminLayout :title="`IDP — ${idp.competency}`">
    <Head :title="`IDP · ${idp.competency}`" />

    <div class="p-6 space-y-6">

      <!-- Back -->
      <div class="flex items-center gap-3">
        <a :href="route('lnd.idp.index')"
          class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          IDP List
        </a>
        <span class="text-gray-300">/</span>
        <span class="text-sm font-medium text-gray-700 truncate">{{ idp.competency }}</span>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

        <!-- Main details -->
        <div class="md:col-span-2 space-y-5">
          <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between mb-4">
              <h2 class="text-lg font-bold text-gray-800">{{ idp.competency }}</h2>
              <div class="flex gap-2">
                <span :class="['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium', approvalColors[idp.approval_status] ?? 'bg-gray-100 text-gray-600']">
                  {{ idp.approval_status === 'submitted' ? 'Pending Approval' : idp.approval_status.charAt(0).toUpperCase() + idp.approval_status.slice(1) }}
                </span>
                <span :class="['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize', statusColors[idp.status] ?? 'bg-gray-100 text-gray-600']">
                  {{ idp.status }}
                </span>
              </div>
            </div>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
              <dt class="text-gray-500">Employee</dt>
              <dd class="font-medium text-gray-800">{{ idp.employee?.name ?? '—' }}</dd>
              <dt class="text-gray-500">Supervisor</dt>
              <dd class="text-gray-800">{{ idp.supervisor?.name ?? '—' }}</dd>
              <dt class="text-gray-500">Year</dt>
              <dd class="text-gray-800">{{ idp.year }}</dd>
              <dt class="text-gray-500">Intervention</dt>
              <dd class="text-gray-800">{{ interventionLabel[idp.intervention_type] ?? idp.intervention_type }}</dd>
              <dt class="text-gray-500">Learning Program</dt>
              <dd class="text-gray-800">{{ idp.learning_program?.title ?? '—' }}</dd>
              <dt class="text-gray-500">Linked TNA</dt>
              <dd class="text-gray-800">{{ idp.training_need?.competency_area ?? '—' }}</dd>
              <dt class="text-gray-500">Competency Gap</dt>
              <dd class="text-center">
                <div class="inline-flex items-center gap-1 text-xs">
                  <span :class="['rounded px-2 py-0.5 font-medium', levelColors[idp.current_level]]">{{ idp.current_level }}</span>
                  <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                  <span :class="['rounded px-2 py-0.5 font-medium', levelColors[idp.target_level]]">{{ idp.target_level }}</span>
                </div>
              </dd>
              <dt class="text-gray-500">Timeline</dt>
              <dd class="text-gray-800">
                <template v-if="idp.timeline_start">{{ fmt(idp.timeline_start) }} – {{ fmt(idp.timeline_end) }}</template>
                <template v-else>—</template>
              </dd>
            </dl>
          </div>

          <!-- Development Activity -->
          <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="font-semibold text-gray-800 mb-2">Development Activity</h3>
            <p class="text-sm text-gray-700 whitespace-pre-line">{{ idp.development_activity ?? '—' }}</p>
          </div>
        </div>

        <!-- Remarks sidebar -->
        <div class="space-y-4">
          <div v-if="idp.supervisor_remarks" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Supervisor Remarks</h4>
            <p class="text-sm text-gray-700">{{ idp.supervisor_remarks }}</p>
            <p v-if="idp.approved_by" class="mt-2 text-xs text-gray-400">
              By {{ idp.approved_by?.name }} · {{ fmt(idp.approved_at) }}
            </p>
          </div>
          <div v-if="idp.employee_remarks" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Employee Remarks</h4>
            <p class="text-sm text-gray-700">{{ idp.employee_remarks }}</p>
          </div>
          <div v-if="!idp.supervisor_remarks && !idp.employee_remarks"
            class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-sm text-gray-400 text-center">
            No remarks yet.
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
