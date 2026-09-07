<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import { ArrowPathIcon } from "@heroicons/vue/24/outline"
import IpcrV2DocumentHeader from "@/Components/IPCRV2/IpcrV2DocumentHeader.vue"
import IpcrV2StrategicSection from "@/Components/IPCRV2/IpcrV2StrategicSection.vue"
import IpcrV2CoreItemsTable from "@/Components/IPCRV2/IpcrV2CoreItemsTable.vue"
import IpcrV2SupportItemsTable from "@/Components/IPCRV2/IpcrV2SupportItemsTable.vue"
import IpcrV2SummarySection from "@/Components/IPCRV2/IpcrV2SummarySection.vue"
import DigitalSignaturePin from "@/Components/DigitalSignaturePin.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"
import { useSubmit } from "@/Composables/useSubmit"
import { usePinConfirm } from "@/Composables/usePinConfirm"

const props = defineProps({
  ipcr: Object,
  strategicIndicators: Array,
  supervisor: Object,
  ocdUser: Object,
  summary: Object,
  isOwner: Boolean,
  isMutable: Boolean,
  hasPin: Boolean,
  signatureUri: String,
})

const { isSubmitting, submit } = useSubmit()
const { showPinModal, requestPin, confirmPin, cancelPin } = usePinConfirm()

function submitForReview() {
  requestPin((pin) => submit((opts) => router.post(route("employee-ipcr-v2.submitReview", props.ipcr.id), { pin }, opts)))
}
function submitForRating() {
  requestPin((pin) => submit((opts) => router.post(route("employee-ipcr-v2.submitRating", props.ipcr.id), { pin }, opts)))
}
function syncFunctions() {
  submit((opts) => router.post(route("employee-ipcr-v2.syncFunctions", props.ipcr.id), {}, opts))
}
</script>

<template>
  <Head :title="`IPCR V2 — ${ipcr.period?.label}`" />
  <AdminLayout title="IPCR V2">
    <AppPageHeader :title="ipcr.user?.name" :subtitle="ipcr.period?.label">
      <template #actions>
        <a :href="route('ipcr-v2-pdf.show', ipcr.id)" target="_blank" rel="noopener">
          <AppButton variant="secondary">Print PDF</AppButton>
        </a>
        <span class="text-xs px-2 py-1 rounded-full ml-2" :class="ipcrStatusClass(ipcr.status)">{{ ipcr.status }}</span>
        <span v-if="ipcr.final_numeric_rating" class="text-xs text-slate-500 ml-2">
          {{ ipcr.final_numeric_rating }} — {{ ipcrAdjectivalRating(ipcr.final_numeric_rating) }}
        </span>
      </template>
    </AppPageHeader>

    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
      <IpcrV2DocumentHeader :employee="ipcr.user" :period="ipcr.period" :supervisor="supervisor" :ocd-user="ocdUser" />
      <div class="overflow-x-auto">
        <table class="min-w-full border-collapse border border-slate-200 text-sm">
          <thead class="bg-slate-50/80">
            <tr>
              <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Function</th>
              <th colspan="2" class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase">Output/Outcomes</th>
              <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Success Indicator</th>
              <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Target</th>
              <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Actual Accomplishment</th>
              <th colspan="4" class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase">Rating</th>
              <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Remarks</th>
            </tr>
            <tr>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Sub Strategy</th>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Program</th>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Q</th>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">E</th>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">T</th>
              <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">A</th>
            </tr>
          </thead>
          <IpcrV2StrategicSection :indicators="strategicIndicators" />
          <IpcrV2CoreItemsTable :ipcr-id="ipcr.id" :items="ipcr.core_items" :is-owner="isOwner" :is-mutable="isMutable" />
          <IpcrV2SupportItemsTable :ipcr-id="ipcr.id" :items="ipcr.support_items" :is-owner="isOwner" :is-mutable="isMutable" />
        </table>
      </div>
    </div>

    <IpcrV2SummarySection :summary="summary" :rating-date="ipcr.director_signed_at" :comments="ipcr.comments_recommendations" />

    <div v-if="isOwner && isMutable" class="mt-6 flex justify-end gap-2">
      <AppButton variant="secondary" :disabled="isSubmitting" @click="syncFunctions">
        <ArrowPathIcon class="w-4 h-4 mr-1" /> Sync from Employee Functions
      </AppButton>
      <AppButton v-if="ipcr.status === 'New Target'" :disabled="isSubmitting" @click="submitForReview">Submit for Review</AppButton>
      <AppButton v-if="ipcr.status === 'Targets Approved'" :disabled="isSubmitting" @click="submitForRating">Submit for Rating</AppButton>
    </div>

    <DigitalSignaturePin
      :show="showPinModal"
      :has-pin="hasPin"
      :signature-uri="signatureUri"
      :loading="isSubmitting"
      @confirm="confirmPin"
      @cancel="cancelPin"
    />
  </AdminLayout>
</template>
