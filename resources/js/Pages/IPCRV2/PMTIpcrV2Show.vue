<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import IpcrV2CoreItemsTable from "@/Components/IPCRV2/IpcrV2CoreItemsTable.vue"
import IpcrV2SupportItemsTable from "@/Components/IPCRV2/IpcrV2SupportItemsTable.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({ ipcr: Object, isMutable: Boolean })
const { isSubmitting, submit } = useSubmit()

function approve() {
  submit((opts) => router.post(route("pmt-ipcr-v2.approve", props.ipcr.id), {}, opts))
}
function returnForRevision() {
  submit((opts) => router.post(route("pmt-ipcr-v2.return", props.ipcr.id), {}, opts))
}
function directorSign() {
  submit((opts) => router.post(route("pmt-ipcr-v2.directorSign", props.ipcr.id), {}, opts))
}
</script>

<template>
  <Head :title="`PMT Review — ${ipcr.user?.name}`" />
  <AdminLayout title="PMT Review">
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

    <div v-if="isMutable" class="mt-6 flex justify-end gap-2">
      <AppButton v-if="ipcr.status === 'Submitted to PMT'" variant="secondary" :disabled="isSubmitting" @click="returnForRevision">Return</AppButton>
      <AppButton v-if="ipcr.status === 'Submitted to PMT'" :disabled="isSubmitting" @click="approve">Approve</AppButton>
      <AppButton v-if="ipcr.status === 'Approved by PMT'" :disabled="isSubmitting" @click="directorSign">Director Sign</AppButton>
    </div>
  </AdminLayout>
</template>
