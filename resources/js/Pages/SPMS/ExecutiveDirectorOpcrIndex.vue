<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ opcrs: Array })
</script>

<template>
  <Head title="OPCR Approval Queue (SPMS)" />
  <AdminLayout title="OPCR Approval Queue (SPMS)">
    <div class="rounded-lg border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead>
          <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
            <th class="px-4 py-3 text-left">Campus Director</th>
            <th class="px-4 py-3 text-left">Fiscal Year</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="opcr in opcrs" :key="opcr.id">
            <td class="px-4 py-3">{{ opcr.ratee?.name }}</td>
            <td class="px-4 py-3">{{ opcr.fiscal_period?.label }}</td>
            <td class="px-4 py-3">{{ opcr.status }}</td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('spms.opcr.ed.show', opcr.id)" class="text-indigo-600 hover:underline">Review</Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
