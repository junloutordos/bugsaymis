<script setup>
import { Head } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import IpcrV2DocumentHeader from "@/Components/IPCRV2/IpcrV2DocumentHeader.vue"
import IpcrV2StrategicSection from "@/Components/IPCRV2/IpcrV2StrategicSection.vue"
import IpcrV2CoreItemsTable from "@/Components/IPCRV2/IpcrV2CoreItemsTable.vue"
import IpcrV2SupportItemsTable from "@/Components/IPCRV2/IpcrV2SupportItemsTable.vue"
import IpcrV2SummarySection from "@/Components/IPCRV2/IpcrV2SummarySection.vue"
import IpcrV2StatusTimeline from "@/Components/IPCRV2/IpcrV2StatusTimeline.vue"
import AppButton from "@/Components/AppButton.vue"
import DigitalSignaturePin from "@/Components/DigitalSignaturePin.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"
import { useSubmit } from "@/Composables/useSubmit"
import { usePinConfirm } from "@/Composables/usePinConfirm"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"

const props = defineProps({
  ipcr: Object,
  strategicIndicators: Array,
  ocdUser: Object,
  summary: Object,
  hasPin: Boolean,
  signatureUri: String,
})

const { isSubmitting, submit } = useSubmit()
const { showPinModal, requestPin, confirmPin, cancelPin } = usePinConfirm()

async function reopen() {
  const { value: reason, isConfirmed } = await Swal.fire({
    title: "Reopen this IPCR V2?",
    input: "textarea",
    inputLabel: "Reason for reopening",
    inputPlaceholder: "Explain why this record needs to be reopened...",
    showCancelButton: true,
    inputValidator: (value) => (!value ? "A reason is required." : undefined),
  })
  if (!isConfirmed || !reason) return

  requestPin((pin) => submit((opts) => router.post(route("admin-ipcr-v2.reopen", props.ipcr.id), { reason, pin }, opts)))
}
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
          <IpcrV2CoreItemsTable :ipcr-id="ipcr.id" :items="ipcr.core_items" :is-owner="false" :is-mutable="false" :ipcr-status="ipcr.status" />
          <IpcrV2SupportItemsTable :ipcr-id="ipcr.id" :items="ipcr.support_items" :is-owner="false" :is-mutable="false" :ipcr-status="ipcr.status" />
        </table>
      </div>
    </div>

    <IpcrV2SummarySection :summary="summary" :rating-date="ipcr.director_signed_at" :comments="ipcr.comments_recommendations" />
    <IpcrV2StatusTimeline :logs="ipcr.status_logs ?? []" />

    <div v-if="ipcr.status === 'Director Signed'" class="mt-6 flex justify-end">
      <AppButton variant="secondary" :disabled="isSubmitting" @click="reopen">Reopen</AppButton>
    </div>

    <DigitalSignaturePin
      :show="showPinModal"
      :has-pin="hasPin"
      :signature-uri="signatureUri"
      :loading="isSubmitting"
      confirm-label="Reopen"
      @confirm="confirmPin"
      @cancel="cancelPin"
    />
  </AdminLayout>
</template>
