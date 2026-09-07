<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import IpcrV2StrategicSection from "@/Components/IPCRV2/IpcrV2StrategicSection.vue"
import IpcrV2CoreItemsTable from "@/Components/IPCRV2/IpcrV2CoreItemsTable.vue"
import IpcrV2SupportItemsTable from "@/Components/IPCRV2/IpcrV2SupportItemsTable.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({
  ipcr: Object,
  strategicIndicators: Array,
  isOwner: Boolean,
  isMutable: Boolean,
})

const { isSubmitting, submit } = useSubmit()

function submitForReview() {
  submit((opts) => router.post(route("employee-ipcr-v2.submitReview", props.ipcr.id), {}, opts))
}

function submitForRating() {
  submit((opts) => router.post(route("employee-ipcr-v2.submitRating", props.ipcr.id), {}, opts))
}
</script>

<template>
  <Head :title="`IPCR V2 — ${ipcr.period?.label}`" />
  <AdminLayout title="IPCR V2">
    <AppPageHeader :title="ipcr.user?.name" :subtitle="ipcr.period?.label">
      <template #actions>
        <span class="text-xs px-2 py-1 rounded-full mr-2" :class="ipcrStatusClass(ipcr.status)">{{ ipcr.status }}</span>
        <span v-if="ipcr.final_numeric_rating" class="text-xs text-slate-500">
          {{ ipcr.final_numeric_rating }} — {{ ipcrAdjectivalRating(ipcr.final_numeric_rating) }}
        </span>
      </template>
    </AppPageHeader>

    <IpcrV2StrategicSection :indicators="strategicIndicators" />
    <IpcrV2CoreItemsTable :ipcr-id="ipcr.id" :items="ipcr.core_items" :is-owner="isOwner" :is-mutable="isMutable" />
    <IpcrV2SupportItemsTable :ipcr-id="ipcr.id" :items="ipcr.support_items" :is-owner="isOwner" :is-mutable="isMutable" />

    <div v-if="isOwner && isMutable" class="mt-6 flex justify-end gap-2">
      <AppButton v-if="ipcr.status === 'New Target'" :disabled="isSubmitting" @click="submitForReview">Submit for Review</AppButton>
      <AppButton v-if="ipcr.status === 'Targets Approved'" :disabled="isSubmitting" @click="submitForRating">Submit for Rating</AppButton>
    </div>
  </AdminLayout>
</template>
