<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ ipcrs: Array })
</script>

<template>
  <Head title="PM V2 — My Unit" />
  <AdminLayout title="PM V2 — My Unit">
    <div class="p-6">
      <table class="w-full text-sm bg-white rounded-lg border border-slate-200 overflow-hidden">
        <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
          <tr><th class="px-3 py-2 text-left">Employee</th><th class="px-3 py-2 text-left">Period</th><th class="px-3 py-2 text-left">Status</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="ipcr in props.ipcrs" :key="ipcr.id" class="border-t border-slate-100">
            <td class="px-3 py-2">{{ ipcr.user?.name }}</td>
            <td class="px-3 py-2">{{ ipcr.rating_period?.label }}</td>
            <td class="px-3 py-2">{{ ipcr.status }}</td>
            <td class="px-3 py-2 text-right"><Link :href="route('pm2.supervisor-ipcr.show', ipcr.id)" class="text-indigo-600 hover:underline">Review</Link></td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
