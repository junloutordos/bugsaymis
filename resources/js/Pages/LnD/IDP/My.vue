<script setup>
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { ChevronRightIcon } from '@heroicons/vue/24/outline'

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

const approvalLabel = {
  draft:     'Draft',
  submitted: 'Pending',
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
</script>

<template>
  <AdminLayout title="My IDP">
    <Head title="My IDP" />

    <div class="space-y-5">

      <AppPageHeader title="My Individual Development Plan" subtitle="Your personal development activities and targets">
        <template #actions>
          <select v-model="selectedYear"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
          </select>
        </template>
      </AppPageHeader>

      <!-- Empty -->
      <AppCard v-if="idps.length === 0" :padded="false">
        <EmptyState :title="`No IDP entries for ${year}.`" />
      </AppCard>

      <!-- IDP Cards -->
      <AppCard v-for="idp in idps" :key="idp.id" :padded="false">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50 flex items-start justify-between">
          <div>
            <h3 class="font-semibold text-slate-800">{{ idp.competency }}</h3>
            <p class="text-xs text-slate-500 mt-0.5">{{ interventionLabel[idp.intervention_type] ?? idp.intervention_type }}</p>
          </div>
          <div class="flex gap-2 shrink-0">
            <AppBadge :color="approvalBadgeColor(idp.approval_status)">{{ approvalLabel[idp.approval_status] ?? idp.approval_status }}</AppBadge>
            <AppBadge :color="statusBadgeColor(idp.status)"><span class="capitalize">{{ idp.status }}</span></AppBadge>
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
              <AppBadge :color="levelBadgeColor(idp.current_level)">{{ idp.current_level }}</AppBadge>
              <ChevronRightIcon class="w-3 h-3 text-slate-400" />
              <AppBadge :color="levelBadgeColor(idp.target_level)">{{ idp.target_level }}</AppBadge>
            </div>
          </div>
        </div>
        <div v-if="idp.supervisor_remarks" class="px-5 pb-4">
          <div class="rounded-lg bg-warning-50 border border-warning-100 p-3 text-sm text-warning-700">
            <span class="font-medium">Supervisor:</span> {{ idp.supervisor_remarks }}
          </div>
        </div>
        <div class="px-5 pb-4 flex justify-end">
          <AppButton as="a" size="sm" variant="ghost" :href="route('lnd.idp.show', idp.id)">View Details</AppButton>
        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>
