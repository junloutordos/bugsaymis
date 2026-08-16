<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ ipcrs: Array })

const statusBadgeColor = (status) => ({
  'Draft Target': 'bg-slate-100 text-slate-700',
  'Target Submitted': 'bg-amber-100 text-amber-700',
  'Target Approved': 'bg-blue-100 text-blue-700',
  'Submitted for Rating': 'bg-amber-100 text-amber-700',
  'Rated': 'bg-indigo-100 text-indigo-700',
  'DC Reviewed': 'bg-indigo-100 text-indigo-700',
  'PMT/HR Reviewed': 'bg-indigo-100 text-indigo-700',
  'Director Signed': 'bg-emerald-100 text-emerald-700',
  'Returned': 'bg-rose-100 text-rose-700',
}[status] ?? 'bg-slate-100 text-slate-700')
</script>

<template>
  <Head title="My IPCR (SPMS)" />
  <AdminLayout title="My IPCR (SPMS)">
    <div class="rounded-lg border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead>
          <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-4 py-3 text-left">Period</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Final Rating</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="ipcr in ipcrs" :key="ipcr.id">
            <td class="px-4 py-3">{{ ipcr.fiscal_period?.label }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusBadgeColor(ipcr.status)">
                {{ ipcr.status }}
              </span>
            </td>
            <td class="px-4 py-3">{{ ipcr.final_rating ?? '—' }}</td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('spms.ipcr.show', ipcr.id)" class="text-indigo-600 hover:underline">View</Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
