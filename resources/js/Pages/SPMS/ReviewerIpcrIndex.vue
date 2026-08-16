<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ ipcrs: Array })
</script>

<template>
  <Head title="IPCR Review Queue (SPMS)" />
  <AdminLayout title="IPCR Review Queue (SPMS)">
    <div class="rounded-lg border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead>
          <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-4 py-3 text-left">Employee</th>
            <th class="px-4 py-3 text-left">Period</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="ipcr in ipcrs" :key="ipcr.id">
            <td class="px-4 py-3">{{ ipcr.user?.name }}</td>
            <td class="px-4 py-3">{{ ipcr.fiscal_period?.label }}</td>
            <td class="px-4 py-3">{{ ipcr.status }}</td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('spms.ipcr.review.show', ipcr.id)" class="text-indigo-600 hover:underline">Review</Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
