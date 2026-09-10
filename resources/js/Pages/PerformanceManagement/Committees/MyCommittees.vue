<template>
  <Head title="My Committees" />
  <AdminLayout title="My Committees">
    <div class="space-y-5">
      <AppPageHeader title="My Committees" subtitle="Every committee you belong to, across all terms, with your role, load, and rating status." />

      <AppTable :is-empty="assignments.length === 0" :skeleton-cols="5">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Committee</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Term</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Role</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Load Units</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Rating</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="a in assignments" :key="a.assignment_id" class="hover:bg-slate-50/50">
          <td class="px-4 py-3">
            <p class="font-medium text-slate-800">{{ a.committee_name }}</p>
            <p v-if="a.committee_code" class="text-xs text-slate-400 font-mono">{{ a.committee_code }}</p>
            <AppBadge v-if="a.is_revoked" color="red" class="mt-1">Revoked</AppBadge>
          </td>
          <td class="px-4 py-3 text-slate-700">{{ a.term?.label ?? '—' }}</td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="roleBadge(a.role)">
              <StarIcon v-if="a.is_chairperson" class="h-3 w-3" />
              {{ roleLabel(a.role) }}
            </AppBadge>
          </td>
          <td class="px-4 py-3 text-center font-semibold text-slate-700">{{ a.load_units }}</td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="statusBadge(a.status)">{{ a.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <span v-if="a.rating_status?.row_average !== null && a.rating_status?.row_average !== undefined" class="font-semibold text-slate-800">
              {{ a.rating_status.row_average }}
            </span>
            <span v-else-if="a.rating_status" class="text-xs text-slate-400 italic">{{ a.rating_status.ipcr_status }}</span>
            <span v-else class="text-xs text-slate-400">—</span>
          </td>
          <td class="px-4 py-3 text-right">
            <AppButton v-if="a.committee_id" as="link" variant="ghost" size="sm" :href="route('pm-committees.show', a.committee_id)">
              <ArrowRightIcon class="h-4 w-4" />
            </AppButton>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="a in assignments" :key="a.assignment_id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ a.committee_name }}</p>
                <p class="text-xs text-slate-400">{{ a.term?.label ?? '—' }}</p>
              </div>
              <AppBadge :color="statusBadge(a.status)">{{ a.status }}</AppBadge>
            </div>
            <div class="flex items-center gap-2">
              <AppBadge :color="roleBadge(a.role)">
                <StarIcon v-if="a.is_chairperson" class="h-3 w-3" />
                {{ roleLabel(a.role) }}
              </AppBadge>
              <span class="text-xs text-slate-500">{{ a.load_units }} unit(s)</span>
            </div>
            <div class="pt-1">
              <AppButton v-if="a.committee_id" as="link" size="sm" :href="route('pm-committees.show', a.committee_id)">View</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="You are not currently a member of any committee." :icon="UserGroupIcon" />
        </template>
      </AppTable>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { ArrowRightIcon, StarIcon, UserGroupIcon } from '@heroicons/vue/24/outline'

defineProps({
  assignments: { type: Array, default: () => [] },
  authUser:    { type: Object, default: null },
})

const roles = [
  { value: 'member',      label: 'Member' },
  { value: 'secretary',   label: 'Secretary' },
  { value: 'co_chair',    label: 'Co-Chairperson' },
  { value: 'chairperson', label: 'Chairperson' },
]

function roleBadge(role) {
  return { chairperson: 'amber', co_chair: 'amber', secretary: 'blue', member: 'slate' }[role] ?? 'slate'
}
function roleLabel(role) { return roles.find(r => r.value === role)?.label ?? role }
function statusBadge(status) { return { active: 'green', inactive: 'slate' }[status] ?? 'slate' }
</script>
