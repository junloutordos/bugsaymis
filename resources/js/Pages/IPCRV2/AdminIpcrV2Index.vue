<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppCard from "@/Components/AppCard.vue"
import AppButton from "@/Components/AppButton.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"

defineProps({ records: Array })
</script>

<template>
  <Head title="IPCR V2 Monitoring" />
  <AdminLayout title="IPCR V2 Monitoring">
    <AppPageHeader title="IPCR V2 Monitoring" subtitle="All employees, every status" />
    <AppCard>
      <table class="w-full">
        <thead>
          <tr>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Employee</th>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Period</th>
            <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-2">Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="record in records" :key="record.id">
            <td class="px-4 py-3 text-sm">{{ record.user?.name }}</td>
            <td class="px-4 py-3 text-sm">{{ record.period?.label }}</td>
            <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded-full" :class="ipcrStatusClass(record.status)">{{ record.status }}</span></td>
            <td class="px-4 py-3 text-right">
              <AppButton variant="secondary" @click="router.get(route('admin-ipcr-v2.show', record.id))">View</AppButton>
            </td>
          </tr>
          <tr v-if="!records.length">
            <td class="px-4 py-3 text-sm text-slate-500" colspan="4">No IPCR V2 records yet.</td>
          </tr>
        </tbody>
      </table>
    </AppCard>
  </AdminLayout>
</template>
