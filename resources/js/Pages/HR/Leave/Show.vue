<template>
  <Head :title="`Leave — ${application.control_no ?? 'Application'}`" />
  <AdminLayout title="Leave Application">
    <div class="max-w-2xl mx-auto space-y-5">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <Link :href="route('hr.leave.index')"
                class="text-xs text-slate-400 hover:text-slate-600 mb-1 inline-block">← Back to list</Link>
          <h1 class="text-xl font-semibold text-slate-800">{{ application.control_no ?? 'Leave Application' }}</h1>
        </div>
        <AppBadge :color="statusColor(application.status)" class="!text-sm !px-3 !py-1">
          {{ statusLabel(application.status) }}
        </AppBadge>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.error }}
      </div>

      <!-- Details Card -->
      <AppCard title="Application Details">
        <div class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm">
          <div>
            <p class="text-xs text-slate-400 mb-0.5">Employee</p>
            <p class="font-medium text-slate-800">{{ application.user?.name }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-400 mb-0.5">Leave Type</p>
            <p class="font-medium text-slate-800">{{ application.leave_type?.name }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-400 mb-0.5">Date From</p>
            <p class="font-medium text-slate-800">{{ fmtDate(application.date_from) }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-400 mb-0.5">Date To</p>
            <p class="font-medium text-slate-800">{{ fmtDate(application.date_to) }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-400 mb-0.5">Days Applied</p>
            <p class="font-medium text-slate-800">{{ application.days_applied }} day(s)</p>
          </div>
          <div>
            <p class="text-xs text-slate-400 mb-0.5">Filed</p>
            <p class="font-medium text-slate-800">{{ fmtDate(application.filed_at) }}</p>
          </div>
          <div v-if="application.leave_details" class="col-span-2">
            <p class="text-xs text-slate-400 mb-0.5">Leave Details</p>
            <p class="font-medium text-slate-800">{{ leaveDetailLabel(application.leave_details) }}
              <span v-if="application.leave_details_specify" class="text-slate-500"> — {{ application.leave_details_specify }}</span>
            </p>
          </div>
          <div v-if="application.reason" class="col-span-2">
            <p class="text-xs text-slate-400 mb-0.5">Reason / Remarks</p>
            <p class="text-slate-700">{{ application.reason }}</p>
          </div>
          <div v-if="application.supporting_document" class="col-span-2">
            <p class="text-xs text-slate-400 mb-0.5">Supporting Document</p>
            <p class="text-slate-600 text-xs flex items-center gap-1"><PaperClipIcon class="h-3.5 w-3.5" /> Document attached</p>
          </div>
        </div>
      </AppCard>

      <!-- Approval Timeline -->
      <AppCard title="Approval History">
        <div class="space-y-4">
          <!-- Filed -->
          <div class="flex items-start gap-3">
            <div class="w-7 h-7 rounded-full bg-slate-200 flex items-center justify-center text-xs font-bold text-slate-600 shrink-0">1</div>
            <div>
              <p class="text-sm font-medium text-slate-700">Filed by {{ application.user?.name }}</p>
              <p class="text-xs text-slate-400">{{ fmtDate(application.filed_at) }}</p>
            </div>
          </div>

          <!-- Stage 1: HR Officer -->
          <div class="flex items-start gap-3">
            <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                 :class="application.hr_officer_id ? 'bg-indigo-100 text-indigo-600' : 'bg-slate-100 text-slate-400'">2</div>
            <div>
              <p class="text-sm font-medium text-slate-700">HR Officer — Certification of Leave Credits</p>
              <template v-if="application.hr_officer_id">
                <p class="text-xs text-slate-600">
                  <span :class="actionClass(application.hr_officer_action)" class="font-semibold capitalize">
                    {{ application.hr_officer_action }}
                  </span>
                  by {{ application.hr_officer?.name }} &bull; {{ fmtDate(application.hr_officer_at) }}
                </p>
                <p v-if="application.hr_officer_remarks" class="text-xs text-slate-400 mt-0.5 italic">"{{ application.hr_officer_remarks }}"</p>
              </template>
              <p v-else class="text-xs text-slate-400">Awaiting HR certification</p>
            </div>
          </div>

          <!-- Stage 2: Division Chief -->
          <div class="flex items-start gap-3">
            <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                 :class="application.division_chief_id ? 'bg-indigo-100 text-indigo-600' : 'bg-slate-100 text-slate-400'">3</div>
            <div>
              <p class="text-sm font-medium text-slate-700">Division Chief — Recommendation</p>
              <template v-if="application.division_chief_id">
                <p class="text-xs text-slate-600">
                  <span :class="actionClass(application.division_chief_action)" class="font-semibold capitalize">
                    {{ application.division_chief_action }}
                  </span>
                  by {{ application.division_chief?.name }} &bull; {{ fmtDate(application.division_chief_at) }}
                </p>
                <p v-if="application.division_chief_remarks" class="text-xs text-slate-400 mt-0.5 italic">"{{ application.division_chief_remarks }}"</p>
              </template>
              <p v-else class="text-xs text-slate-400">Awaiting Division Chief recommendation</p>
            </div>
          </div>

          <!-- Stage 3: Campus Director -->
          <div class="flex items-start gap-3">
            <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                 :class="application.approved_by ? 'bg-indigo-100 text-indigo-600' : 'bg-slate-100 text-slate-400'">4</div>
            <div>
              <p class="text-sm font-medium text-slate-700">Campus Director — Final Approval</p>
              <template v-if="application.approved_by">
                <p class="text-xs text-slate-600">
                  <span :class="actionClass(application.approval_action)" class="font-semibold capitalize">
                    {{ application.approval_action }}
                  </span>
                  by {{ application.approved_by?.name }} &bull; {{ fmtDate(application.approved_at) }}
                </p>
                <p v-if="application.approval_remarks" class="text-xs text-slate-400 mt-0.5 italic">"{{ application.approval_remarks }}"</p>
              </template>
              <p v-else class="text-xs text-slate-400">
                {{ application.status === 'forwarded' ? 'Awaiting Campus Director approval' : 'Pending' }}
              </p>
            </div>
          </div>
        </div>
      </AppCard>

      <!-- Actions -->
      <div class="flex gap-3 flex-wrap">
        <!-- Cancel (owner, pending/forwarded only) -->
        <AppButton v-if="canCancel" variant="danger" @click="cancelApp">
          Cancel Application
        </AppButton>

        <!-- Approve/Reject (Division Chief or HR) -->
        <AppButton v-if="canApprove && ['pending','hr_verified','forwarded'].includes(application.status)"
                   @click="approveModal = true">
          Review Application
        </AppButton>

        <!-- Print CS Form 6 -->
        <AppButton as="a" variant="secondary" :href="route('hr.leave.print', application.id)" target="_blank">
          <PrinterIcon class="h-4 w-4" />
          Print CS Form 6
        </AppButton>
      </div>

      <!-- Approve Modal -->
      <AppModal :show="approveModal" title="Review Leave Application" @close="approveModal = false">
        <div class="space-y-4">
          <!-- Stage selection -->
          <AppSelect v-model="approveForm.stage" label="Review Stage" :show-blank="false">
            <option value="hr_officer">HR Officer — Certification of Leave Credits</option>
            <option value="division_chief">Division Chief — Recommendation</option>
            <option value="campus_director">Campus Director — Final Approval</option>
          </AppSelect>

          <!-- Action -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Action</label>
            <div class="flex gap-3">
              <label v-for="opt in actionOptions" :key="opt.value"
                     class="flex items-center gap-2 cursor-pointer">
                <input type="radio" v-model="approveForm.action" :value="opt.value" class="accent-indigo-600" />
                <span class="text-sm text-slate-700">{{ opt.label }}</span>
              </label>
            </div>
          </div>

          <!-- Remarks -->
          <AppTextarea v-model="approveForm.remarks" label="Remarks" :rows="3" placeholder="Optional…" />
        </div>

        <template #footer>
          <AppButton variant="secondary" @click="approveModal = false">Cancel</AppButton>
          <AppButton :loading="approveForm.processing" @click="submitApprove">
            {{ approveForm.processing ? 'Saving…' : 'Submit Review' }}
          </AppButton>
        </template>
      </AppModal>

      <!-- Digital Signature PIN -->
      <DigitalSignaturePin
        :show="showPinModal"
        :hasPin="hasPin"
        :signatureUri="signatureUri"
        :loading="pinModalLoading"
        confirmLabel="Sign & Submit"
        @confirm="handlePinConfirm"
        @cancel="handlePinCancel"
      />

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppModal from '@/Components/AppModal.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import { PrinterIcon, PaperClipIcon } from '@heroicons/vue/24/outline'
import { confirmAction } from '@/Composables/useConfirm'

const props = defineProps({
  application:  Object,
  hasPin:       Boolean,
  signatureUri: String,
})

const page = usePage()
const me = page.props.auth?.user

const canApprove = me?.permissions?.includes('hr.leave.approve')
const canCancel  = computed(() =>
  props.application.user_id === me?.id &&
  ['pending', 'hr_verified', 'forwarded'].includes(props.application.status)
)

// Approve modal
const approveModal = ref(false)

function defaultStage() {
  const s = props.application.status
  if (s === 'pending')     return 'hr_officer'
  if (s === 'hr_verified') return 'division_chief'
  if (s === 'forwarded')   return 'campus_director'
  return 'hr_officer'
}

function defaultAction(stage) {
  if (stage === 'hr_officer')    return 'certified'
  if (stage === 'division_chief') return 'forwarded'
  return 'approved'
}

const approveForm = useForm({
  stage:   defaultStage(),
  action:  defaultAction(defaultStage()),
  remarks: '',
  pin:     null,
})

// Digital signature PIN
const showPinModal   = ref(false)
const pinModalLoading = ref(false)

const SIGNING_ACTIONS = ['certified', 'forwarded', 'approved']

const actionOptions = computed(() => {
  if (approveForm.stage === 'hr_officer') {
    return [
      { value: 'certified', label: 'Certify Credits' },
      { value: 'rejected',  label: 'Reject' },
    ]
  }
  if (approveForm.stage === 'division_chief') {
    return [
      { value: 'forwarded', label: 'Forward to Campus Director' },
      { value: 'rejected',  label: 'Reject' },
    ]
  }
  return [
    { value: 'approved', label: 'Approve' },
    { value: 'rejected', label: 'Reject' },
  ]
})

watch(() => approveForm.stage, (stage) => {
  approveForm.action = defaultAction(stage)
})

function submitApprove() {
  if (SIGNING_ACTIONS.includes(approveForm.action)) {
    approveModal.value = false
    showPinModal.value = true
    return
  }
  approveForm.pin = null
  postApprove()
}

function postApprove() {
  approveForm.post(route('hr.leave.approve', props.application.id), {
    onSuccess: () => {
      approveModal.value = false
      showPinModal.value = false
    },
    onFinish: () => { pinModalLoading.value = false },
  })
}

function handlePinConfirm(pin) {
  approveForm.pin = pin
  pinModalLoading.value = true
  postApprove()
}

function handlePinCancel() {
  showPinModal.value = false
}

async function cancelApp() {
  const ok = await confirmAction({ title: 'Cancel this leave application?', confirmText: 'Cancel Application' })
  if (ok) {
    router.post(route('hr.leave.cancel', props.application.id))
  }
}

// Helpers
const leaveDetailMap = {
  within_philippines: 'Within the Philippines',
  abroad:             'Abroad',
  in_hospital:        'In Hospital',
  out_patient:        'Out Patient',
  master_degree:      "Master's Degree",
  bar_board_review:   'Bar/Board Review',
  other:              'Other',
}
function leaveDetailLabel(v) { return leaveDetailMap[v] ?? v }

function statusColor(s) {
  const map = {
    pending:     'amber',
    hr_verified: 'purple',
    forwarded:   'blue',
    approved:    'green',
    rejected:    'red',
    cancelled:   'slate',
  }
  return map[s] ?? 'slate'
}

function statusLabel(s) {
  const map = {
    pending:     'Pending',
    hr_verified: 'For Division Chief',
    forwarded:   'For Campus Director',
    approved:    'Approved',
    rejected:    'Rejected',
    cancelled:   'Cancelled',
  }
  return map[s] ?? s
}

function actionClass(a) {
  return a === 'approved' || a === 'forwarded' ? 'text-success-600' : 'text-danger-500'
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>
