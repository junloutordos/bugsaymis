<template>
  <AdminLayout title="Awards">
    <div class="space-y-6">
      <AppPageHeader title="Recorded Awards" />

      <AppTable :is-empty="!rewards.data.length" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Awardee</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Award</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Incentive</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Value</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Recorded By</th>
          </tr>
        </template>

        <tr v-for="r in rewards.data" :key="r.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm font-medium text-slate-800">
            {{ r.nomination?.nominee?.name }}
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ r.nomination?.reward_type?.name }}</td>
          <td class="px-4 py-3 text-sm text-slate-700 capitalize">
            {{ r.incentive_type.replace('_', ' ') }}
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">
            {{ r.incentive_value ? Number(r.incentive_value).toLocaleString() : '—' }}
          </td>
          <td class="px-4 py-3 text-sm text-slate-500">{{ formatDate(r.award_date) }}</td>
          <td class="px-4 py-3 text-sm text-slate-500">{{ r.recorder?.name ?? '—' }}</td>
        </tr>

        <template #mobileCard>
          <div v-for="r in rewards.data" :key="r.id" class="p-4 space-y-1">
            <p class="font-medium text-slate-800">{{ r.nomination?.nominee?.name }}</p>
            <p class="text-xs text-slate-500">{{ r.nomination?.reward_type?.name }}</p>
            <p class="text-xs text-slate-500 capitalize">{{ r.incentive_type.replace('_', ' ') }} &middot; {{ r.incentive_value ? Number(r.incentive_value).toLocaleString() : '—' }}</p>
            <p class="text-xs text-slate-400">{{ formatDate(r.award_date) }} &middot; Recorded by {{ r.recorder?.name ?? '—' }}</p>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No awards recorded yet" />
        </template>

        <template #footer>
          <PaginationControl :links="rewards.links" :total="rewards.total" />
        </template>
      </AppTable>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'

const props = defineProps({ rewards: Object })

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>
