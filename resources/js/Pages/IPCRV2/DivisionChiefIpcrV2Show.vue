<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import IpcrV2StrategicSection from "@/Components/IPCRV2/IpcrV2StrategicSection.vue"
import IpcrV2CoreItemsTable from "@/Components/IPCRV2/IpcrV2CoreItemsTable.vue"
import IpcrV2SupportItemsTable from "@/Components/IPCRV2/IpcrV2SupportItemsTable.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({
  ipcr: Object,
  strategicIndicators: Array,
  isMutable: Boolean,
})

const { isSubmitting, submit } = useSubmit()

function approveTargets() {
  submit((opts) => router.post(route("division-chief-ipcr-v2.approveTargets", props.ipcr.id), {}, opts))
}
function disapproveTargets() {
  submit((opts) => router.post(route("division-chief-ipcr-v2.disapproveTargets", props.ipcr.id), {}, opts))
}
function submitToPMT() {
  submit((opts) => router.post(route("division-chief-ipcr-v2.submitToPMT", props.ipcr.id), {}, opts))
}
</script>

<template>
  <Head :title="`IPCR V2 — ${ipcr.user?.name}`" />
  <AdminLayout title="IPCR V2 Review">
    <AppPageHeader :title="ipcr.user?.name" :subtitle="ipcr.period?.label">
      <template #actions>
        <span class="text-xs px-2 py-1 rounded-full" :class="ipcrStatusClass(ipcr.status)">{{ ipcr.status }}</span>
      </template>
    </AppPageHeader>

    <IpcrV2StrategicSection :indicators="strategicIndicators" />
    <IpcrV2CoreItemsTable :ipcr-id="ipcr.id" :items="ipcr.core_items" :is-owner="false" :is-mutable="isMutable" can-rate />
    <IpcrV2SupportItemsTable :ipcr-id="ipcr.id" :items="ipcr.support_items" :is-owner="false" :is-mutable="isMutable" can-rate />

    <div v-if="isMutable" class="mt-6 flex justify-end gap-2">
      <AppButton v-if="ipcr.status === 'For Review'" variant="secondary" :disabled="isSubmitting" @click="disapproveTargets">Return for Revision</AppButton>
      <AppButton v-if="ipcr.status === 'For Review'" :disabled="isSubmitting" @click="approveTargets">Approve Targets</AppButton>
      <AppButton v-if="ipcr.status === 'Rated & For PMT Review'" :disabled="isSubmitting" @click="submitToPMT">Submit to PMT</AppButton>
    </div>
  </AdminLayout>
</template>
