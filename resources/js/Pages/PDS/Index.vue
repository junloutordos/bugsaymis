<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Link } from '@inertiajs/vue3'

defineProps({
  pdsList: Object,
})
</script>

<template>
  <AdminLayout title="All PDS">
    <div>
      <AppPageHeader title="Personal Data Sheets" subtitle="View all submitted personal data sheets" />

      <AppTable :is-empty="!pdsList.data || pdsList.data.length === 0" :skeleton-cols="4">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Email</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Created</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
          </tr>
        </template>

        <tr v-for="pds in pdsList.data" :key="pds.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-sm text-slate-700">{{ pds.user.name }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ pds.user.email }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ pds.created_at }}</td>
          <td class="px-4 py-3">
            <AppButton as="link" size="sm" variant="secondary" :href="route('pds.show', pds.id)">View</AppButton>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="pds in pdsList.data" :key="pds.id" class="p-4 space-y-2">
            <div>
              <p class="font-medium text-slate-800">{{ pds.user.name }}</p>
              <p class="text-xs text-slate-500">{{ pds.user.email }}</p>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-xs text-slate-400">{{ pds.created_at }}</span>
              <AppButton as="link" size="sm" variant="secondary" :href="route('pds.show', pds.id)">View</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No personal data sheets found" />
        </template>
      </AppTable>
    </div>
  </AdminLayout>
</template>
