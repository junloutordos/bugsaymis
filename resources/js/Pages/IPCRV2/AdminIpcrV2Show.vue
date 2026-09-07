<script setup>
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import IpcrV2DocumentHeader from "@/Components/IPCRV2/IpcrV2DocumentHeader.vue"
import IpcrV2StrategicSection from "@/Components/IPCRV2/IpcrV2StrategicSection.vue"
import IpcrV2CoreItemsTable from "@/Components/IPCRV2/IpcrV2CoreItemsTable.vue"
import IpcrV2SupportItemsTable from "@/Components/IPCRV2/IpcrV2SupportItemsTable.vue"
import IpcrV2SummarySection from "@/Components/IPCRV2/IpcrV2SummarySection.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"

defineProps({
  ipcr: Object,
  strategicIndicators: Array,
  ocdUser: Object,
  summary: Object,
})
</script>

<template>
  <Head :title="`IPCR V2 — ${ipcr.user?.name}`" />
  <AdminLayout title="IPCR V2 Monitoring (All Stages)">
    <AppPageHeader :title="ipcr.user?.name" :subtitle="ipcr.period?.label">
      <template #actions>
        <span class="text-xs px-2 py-1 rounded-full" :class="ipcrStatusClass(ipcr.status)">{{ ipcr.status }}</span>
        <span v-if="ipcr.final_numeric_rating" class="text-xs text-slate-500 ml-2">
          {{ ipcr.final_numeric_rating }} — {{ ipcrAdjectivalRating(ipcr.final_numeric_rating) }}
        </span>
      </template>
    </AppPageHeader>

    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
      <IpcrV2DocumentHeader :employee="ipcr.user" :period="ipcr.period" :supervisor="null" :ocd-user="ocdUser" />
      <div class="overflow-x-auto">
        <table class="min-w-full border-collapse border border-slate-200 text-sm">
          <thead class="bg-slate-50/80">
            <tr>
              <th class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase">Function</th>
              <th class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase">Success Indicator</th>
              <th class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase">Target</th>
              <th class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase">Actual Accomplishment</th>
              <th class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase">Rating</th>
            </tr>
          </thead>
          <IpcrV2StrategicSection :indicators="strategicIndicators" />
          <IpcrV2CoreItemsTable :ipcr-id="ipcr.id" :items="ipcr.core_items" :is-owner="false" :is-mutable="false" />
          <IpcrV2SupportItemsTable :ipcr-id="ipcr.id" :items="ipcr.support_items" :is-owner="false" :is-mutable="false" />
        </table>
      </div>
    </div>

    <IpcrV2SummarySection :summary="summary" />
  </AdminLayout>
</template>
