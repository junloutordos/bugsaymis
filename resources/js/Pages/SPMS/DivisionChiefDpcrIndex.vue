<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ dpcrs: Array })

const statusBadgeColor = (status) => ({
  'Draft': 'bg-slate-100 text-slate-700',
  'Submitted to Reviewer': 'bg-amber-100 text-amber-700',
  'Reviewed': 'bg-blue-100 text-blue-700',
  'Submitted to Approver': 'bg-amber-100 text-amber-700',
  'Approved': 'bg-emerald-100 text-emerald-700',
  'Returned': 'bg-rose-100 text-rose-700',
}[status] ?? 'bg-slate-100 text-slate-700')
</script>

<template>
  <Head title="My DPCR (SPMS)" />
  <AdminLayout title="My DPCR (SPMS)">
    <div class="rounded-lg border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead>
          <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-4 py-3 text-left">Division</th>
            <th class="px-4 py-3 text-left">Period</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Final Rating</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="dpcr in dpcrs" :key="dpcr.id">
            <td class="px-4 py-3">{{ dpcr.division?.division_name }}</td>
            <td class="px-4 py-3">{{ dpcr.fiscal_period?.label }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusBadgeColor(dpcr.status)">
                {{ dpcr.status }}
              </span>
            </td>
            <td class="px-4 py-3">{{ dpcr.final_rating ?? '—' }}</td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('spms.dpcr.show', dpcr.id)" class="text-indigo-600 hover:underline">View</Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
