<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ dpcrs: Array })
</script>

<template>
  <Head title="DPCR Review Queue (SPMS)" />
  <AdminLayout title="DPCR Review Queue (SPMS)">
    <div class="rounded-lg border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead>
          <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-4 py-3 text-left">Division</th>
            <th class="px-4 py-3 text-left">Division Chief</th>
            <th class="px-4 py-3 text-left">Period</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="dpcr in dpcrs" :key="dpcr.id">
            <td class="px-4 py-3">{{ dpcr.division?.division_name }}</td>
            <td class="px-4 py-3">{{ dpcr.ratee?.name }}</td>
            <td class="px-4 py-3">{{ dpcr.fiscal_period?.label }}</td>
            <td class="px-4 py-3">{{ dpcr.status }}</td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('spms.dpcr.review.show', dpcr.id)" class="text-indigo-600 hover:underline">Review</Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
