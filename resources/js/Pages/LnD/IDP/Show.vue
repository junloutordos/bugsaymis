<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppBreadcrumb from '@/Components/AppBreadcrumb.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { ChevronRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  idp: { type: Object, required: true },
})

const fmt = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' }) : '—'

const approvalLabel = {
  draft:     'Draft',
  submitted: 'Pending Approval',
  approved:  'Approved',
  returned:  'Returned',
}

function approvalBadgeColor(status) {
  const map = { draft: 'slate', submitted: 'amber', approved: 'green', returned: 'red' }
  return map[status] ?? 'slate'
}
function statusBadgeColor(status) {
  const map = { planned: 'blue', ongoing: 'amber', completed: 'green', deferred: 'orange', cancelled: 'red' }
  return map[status] ?? 'slate'
}
function levelBadgeColor(level) {
  const map = { none: 'slate', basic: 'blue', intermediate: 'indigo', advanced: 'purple' }
  return map[level] ?? 'slate'
}

const interventionLabel = { training: 'Training', coaching: 'Coaching', assignment: 'Assignment', self_study: 'Self-Study', e_learning: 'E-Learning', other: 'Other' }

const breadcrumbItems = computed(() => [
  { label: 'IDP List', href: route('lnd.idp.index') },
  { label: props.idp.competency },
])
</script>

<template>
  <AdminLayout :title="`IDP — ${idp.competency}`">
    <Head :title="`IDP · ${idp.competency}`" />

    <div class="space-y-5">

      <AppBreadcrumb :items="breadcrumbItems" />

      <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

        <!-- Main details -->
        <div class="md:col-span-2 space-y-5">
          <AppCard :padded="false">
            <div class="px-5 py-4 border-b border-slate-100 flex items-start justify-between">
              <h2 class="text-xl font-semibold text-slate-800">{{ idp.competency }}</h2>
              <div class="flex gap-2 shrink-0">
                <AppBadge :color="approvalBadgeColor(idp.approval_status)">{{ approvalLabel[idp.approval_status] ?? idp.approval_status }}</AppBadge>
                <AppBadge :color="statusBadgeColor(idp.status)"><span class="capitalize">{{ idp.status }}</span></AppBadge>
              </div>
            </div>
            <div class="p-5">
              <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <dt class="text-slate-500">Employee</dt>
                <dd class="font-medium text-slate-800">{{ idp.employee?.name ?? '—' }}</dd>
                <dt class="text-slate-500">Supervisor</dt>
                <dd class="text-slate-800">{{ idp.supervisor?.name ?? '—' }}</dd>
                <dt class="text-slate-500">Year</dt>
                <dd class="text-slate-800">{{ idp.year }}</dd>
                <dt class="text-slate-500">Intervention</dt>
                <dd class="text-slate-800">{{ interventionLabel[idp.intervention_type] ?? idp.intervention_type }}</dd>
                <dt class="text-slate-500">Learning Program</dt>
                <dd class="text-slate-800">{{ idp.learning_program?.title ?? '—' }}</dd>
                <dt class="text-slate-500">Linked TNA</dt>
                <dd class="text-slate-800">{{ idp.training_need?.competency_area ?? '—' }}</dd>
                <dt class="text-slate-500">Competency Gap</dt>
                <dd>
                  <div class="inline-flex items-center gap-1 text-xs">
                    <AppBadge :color="levelBadgeColor(idp.current_level)">{{ idp.current_level }}</AppBadge>
                    <ChevronRightIcon class="w-3 h-3 text-slate-400" />
                    <AppBadge :color="levelBadgeColor(idp.target_level)">{{ idp.target_level }}</AppBadge>
                  </div>
                </dd>
                <dt class="text-slate-500">Timeline</dt>
                <dd class="text-slate-800">
                  <template v-if="idp.timeline_start">{{ fmt(idp.timeline_start) }} – {{ fmt(idp.timeline_end) }}</template>
                  <template v-else>—</template>
                </dd>
              </dl>
            </div>
          </AppCard>

          <!-- Development Activity -->
          <AppCard title="Development Activity">
            <p class="text-sm text-slate-700 whitespace-pre-line">{{ idp.development_activity ?? '—' }}</p>
          </AppCard>
        </div>

        <!-- Remarks Sidebar -->
        <div class="space-y-4">
          <AppCard v-if="idp.supervisor_remarks">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Supervisor Remarks</h4>
            <p class="text-sm text-slate-700">{{ idp.supervisor_remarks }}</p>
            <p v-if="idp.approved_by" class="mt-2 text-xs text-slate-400">
              By {{ idp.approved_by?.name }} · {{ fmt(idp.approved_at) }}
            </p>
          </AppCard>
          <AppCard v-if="idp.employee_remarks">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Employee Remarks</h4>
            <p class="text-sm text-slate-700">{{ idp.employee_remarks }}</p>
          </AppCard>
          <AppCard v-if="!idp.supervisor_remarks && !idp.employee_remarks" :padded="false">
            <EmptyState title="No remarks yet." />
          </AppCard>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
