<script setup>
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import IpcrV2CoreItemsTable from "@/Components/IPCRV2/IpcrV2CoreItemsTable.vue"
import IpcrV2SupportItemsTable from "@/Components/IPCRV2/IpcrV2SupportItemsTable.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"

defineProps({ ipcr: Object })
</script>

<template>
  <Head :title="`IPCR V2 — ${ipcr.user?.name}`" />
  <AdminLayout title="HR IPCR V2 Monitoring">
    <AppPageHeader :title="ipcr.user?.name" :subtitle="ipcr.period?.label">
      <template #actions>
        <span class="text-xs px-2 py-1 rounded-full" :class="ipcrStatusClass(ipcr.status)">{{ ipcr.status }}</span>
        <span v-if="ipcr.final_numeric_rating" class="text-xs text-slate-500 ml-2">
          {{ ipcr.final_numeric_rating }} — {{ ipcrAdjectivalRating(ipcr.final_numeric_rating) }}
        </span>
      </template>
    </AppPageHeader>

    <IpcrV2CoreItemsTable :ipcr-id="ipcr.id" :items="ipcr.core_items" :is-owner="false" :is-mutable="false" />
    <IpcrV2SupportItemsTable :ipcr-id="ipcr.id" :items="ipcr.support_items" :is-owner="false" :is-mutable="false" />
  </AdminLayout>
</template>
