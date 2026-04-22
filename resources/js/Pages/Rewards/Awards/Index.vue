<template>
  <AdminLayout title="Awards">
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Recorded Awards</h1>
      </div>

      <div class="overflow-x-auto rounded-xl border border-slate-100">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Awardee</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Award</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Incentive</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Value</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Recorded By</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-for="r in rewards.data" :key="r.id" class="hover:bg-slate-50/60">
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
            <tr v-if="!rewards.data.length">
              <td colspan="6" class="py-16 text-center text-slate-400 text-sm">
                No awards recorded yet.
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="rewards.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <Link v-for="link in rewards.links" :key="link.label"
            :href="link.url ?? '#'"
            v-html="link.label"
            :class="[
              'rounded px-3 py-1 text-sm',
              link.active ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100',
              !link.url ? 'pointer-events-none opacity-40' : '',
            ]" />
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ rewards: Object })

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>
