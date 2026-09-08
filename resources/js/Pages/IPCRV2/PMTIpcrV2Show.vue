<script setup>
import { Head, router } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import IpcrV2DocumentHeader from "@/Components/IPCRV2/IpcrV2DocumentHeader.vue"
import IpcrV2StrategicSection from "@/Components/IPCRV2/IpcrV2StrategicSection.vue"
import IpcrV2CoreItemsTable from "@/Components/IPCRV2/IpcrV2CoreItemsTable.vue"
import IpcrV2SupportItemsTable from "@/Components/IPCRV2/IpcrV2SupportItemsTable.vue"
import IpcrV2SummarySection from "@/Components/IPCRV2/IpcrV2SummarySection.vue"
import IpcrV2StatusTimeline from "@/Components/IPCRV2/IpcrV2StatusTimeline.vue"
import DigitalSignaturePin from "@/Components/DigitalSignaturePin.vue"
import { ipcrStatusClass } from "@/Composables/ipcrStatusClass"
import { ipcrAdjectivalRating } from "@/Composables/ipcrAdjectivalRating"
import { useSubmit } from "@/Composables/useSubmit"
import { usePinConfirm } from "@/Composables/usePinConfirm"
import Swal from "sweetalert2"

const props = defineProps({
  ipcr: Object,
  strategicIndicators: Array,
  ocdUser: Object,
  summary: Object,
  isMutable: Boolean,
  hasPin: Boolean,
  signatureUri: String,
})
const { isSubmitting, submit } = useSubmit()
const { showPinModal, requestPin, confirmPin, cancelPin } = usePinConfirm()

function approve() {
  requestPin((pin) => submit((opts) => router.post(route("pmt-ipcr-v2.approve", props.ipcr.id), { pin }, opts)))
}

async function returnForRevision() {
  const { value: remarks, isConfirmed } = await Swal.fire({
    title: "Return to Division Chief for revision?",
    input: "textarea",
    inputLabel: "Remarks",
    inputPlaceholder: "Explain what needs to change...",
    showCancelButton: true,
    inputValidator: (value) => (!value ? "Remarks are required when returning for revision." : undefined),
  })
  if (!isConfirmed || !remarks) return
  submit((opts) => router.post(route("pmt-ipcr-v2.return", props.ipcr.id), { remarks }, opts))
}

function directorSign() {
  requestPin((pin) => submit((opts) => router.post(route("pmt-ipcr-v2.directorSign", props.ipcr.id), { pin }, opts)))
}
</script>

<template>
  <Head :title="`PMT Review — ${ipcr.user?.formatted_name}`" />
  <AdminLayout title="PMT Review">
    <AppPageHeader :title="ipcr.user?.formatted_name" :subtitle="ipcr.period?.label">
      <template #actions>
        <span class="text-xs px-2 py-1 rounded-full" :class="ipcrStatusClass(ipcr.status)">{{ ipcr.status }}</span>
        <span v-if="ipcr.final_numeric_rating" class="text-xs text-slate-500 ml-2">
          {{ ipcr.final_numeric_rating }} — {{ ipcrAdjectivalRating(ipcr.final_numeric_rating) }}
        </span>
      </template>
    </AppPageHeader>

    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
      <IpcrV2DocumentHeader :employee="ipcr.user" :period="ipcr.period" :supervisor="null" :ocd-user="ocdUser" :submitted-for-review-at="ipcr.submitted_for_review_at" :target-approved-at="ipcr.target_approved_at" />
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

    <IpcrV2SummarySection
      :summary="summary"
      :rating-date="ipcr.director_signed_at"
      :comments="ipcr.comments_recommendations"
      :employee="ipcr.user"
      :supervisor="null"
      :ocd-user="ocdUser"
      :final-numeric-rating="ipcr.final_numeric_rating"
      :final-adjectival-rating="ipcr.final_adjectival_rating"
      :submitted-for-review-at="ipcr.submitted_for_review_at"
      :submitted-rating-at="ipcr.submitted_rating_at"
      :director-signed-at="ipcr.director_signed_at"
    />
    <IpcrV2StatusTimeline :logs="ipcr.status_logs ?? []" />

    <div v-if="isMutable" class="mt-6 flex justify-end gap-2">
      <AppButton v-if="ipcr.status === 'Submitted to PMT'" variant="secondary" :disabled="isSubmitting" @click="returnForRevision">Return</AppButton>
      <AppButton v-if="ipcr.status === 'Submitted to PMT'" :disabled="isSubmitting" @click="approve">Approve</AppButton>
      <AppButton v-if="ipcr.status === 'Approved by PMT'" :disabled="isSubmitting" @click="directorSign">Director Sign</AppButton>
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
