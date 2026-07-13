<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm, Link } from '@inertiajs/vue3'
import DOMPurify from 'dompurify'
import Swal from 'sweetalert2'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import EmptyState from '@/Components/EmptyState.vue'
import {
  ChevronLeftIcon, DocumentArrowDownIcon,
  CheckCircleIcon, UserGroupIcon, ClockIcon, ShieldCheckIcon,
  PencilSquareIcon, EyeIcon, ArrowPathIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  issuance:         Object,
  recipients:       Array,
  divisions:        Array,
  isAdmin:          Boolean,
  hasPin:           Boolean,
  signatureUri:     String,
  myAcknowledgedAt: String,
  verifyUrl:        String,
})

// ── Release flow (for drafts) ──────────────────────────────────────────────
const showReleasePanel    = ref(false)
const showPinModal        = ref(false)
const recipientType       = ref(props.issuance.recipient_type ?? 'all')
const selectedDivisionIds = ref([])
const divisionSearch      = ref('')
const submitting          = ref(false)
const releaseErrors       = ref({})

const filteredDivisions = computed(() => {
  const q = divisionSearch.value.toLowerCase()
  return (props.divisions ?? []).filter(d => !q || d.division_name.toLowerCase().includes(q) || d.acronym?.toLowerCase().includes(q))
})

function toggleDivision(id) {
  const idx = selectedDivisionIds.value.indexOf(id)
  if (idx === -1) selectedDivisionIds.value.push(id)
  else selectedDivisionIds.value.splice(idx, 1)
}

function openRelease() {
  showReleasePanel.value = true
}

function onPinVerified(pin) {
  showPinModal.value = false
  submitting.value   = true
  releaseErrors.value = {}
  router.post(route('issuances.release', props.issuance.id), {
    recipient_type: recipientType.value,
    division_ids:   selectedDivisionIds.value,
    pin,
  }, {
    onSuccess: () => { submitting.value = false; showReleasePanel.value = false },
    onError:   e  => { releaseErrors.value = e; submitting.value = false },
    preserveScroll: true,
  })
}

// ── Acknowledge ────────────────────────────────────────────────────────────
const ackForm     = useForm({})
const ackDone     = ref(!!props.myAcknowledgedAt)

function acknowledge() {
  ackForm.post(route('issuances.acknowledge', props.issuance.id), {
    onSuccess: () => { ackDone.value = true },
    preserveScroll: true,
  })
}

// ── Scan preview ───────────────────────────────────────────────────────────
const showScanModal  = ref(false)

// ── QR copy ───────────────────────────────────────────────────────────────
const qrCopied = ref(false)
function copyVerifyUrl() {
  navigator.clipboard.writeText(props.verifyUrl ?? '')
  qrCopied.value = true
  setTimeout(() => { qrCopied.value = false }, 2000)
}

// ── Helpers — local badge colour mapping (mirrors Issuances/Index.vue) ─────
function typeColor(type) {
  const map = {
    SO:     'indigo',
    TO:     'blue',
    MEMO:   'purple',
    OO:     'blue',
    AO:     'amber',
    CIRC:   'green',
    NOTICE: 'red',
  }
  return map[type] ?? 'slate'
}

function statusColor(status) {
  return status === 'released' ? 'green' : 'slate'
}

const EMAIL_STATUS_COLOR = { sent: 'green', failed: 'red', pending: 'amber', skipped: 'slate' }
const EMAIL_STATUS_LABEL = { sent: 'Emailed', failed: 'Email failed', pending: 'Email pending', skipped: 'No email on file' }

function resendEmail(recipient) {
  Swal.fire({
    icon: 'question',
    title: 'Resend issuance email?',
    text: `${props.issuance.control_number} will be re-sent to ${recipient.user?.name ?? 'this recipient'}.`,
    showCancelButton: true,
    confirmButtonText: 'Resend',
  }).then((res) => {
    if (!res.isConfirmed) return
    router.post(route('issuances.recipients.resend', [props.issuance.id, recipient.id]), {}, { preserveScroll: true })
  })
}

function fmtDt(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('en-PH', { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit' })
}

const ackCount    = computed(() => (props.recipients ?? []).filter(r => r.acknowledged_at).length)
const totalCount  = computed(() => (props.recipients ?? []).length)
const ackPercent  = computed(() => totalCount.value ? Math.round((ackCount.value / totalCount.value) * 100) : 0)
</script>

<template>
  <Head :title="`${issuance.control_number} — Issuance`" />
  <AdminLayout :title="issuance.control_number">
    <div class="max-w-4xl space-y-5">

      <!-- Back -->
      <Link :href="route('issuances.index')"
        class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800">
        <ChevronLeftIcon class="h-4 w-4" /> Back to Issuances
      </Link>

      <!-- ── Document Header ─────────────────────────────────────────────── -->
      <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 overflow-hidden">
        <!-- Colour band -->
        <div class="h-1.5 bg-indigo-600"></div>

        <div class="p-6">
          <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div class="flex-1">
              <div class="flex flex-wrap items-center gap-2 mb-2">
                <span class="font-mono font-bold text-slate-800 text-base">{{ issuance.control_number }}</span>
                <AppBadge :color="typeColor(issuance.type)">{{ issuance.type_label }}</AppBadge>
                <AppBadge :color="statusColor(issuance.status)" class="capitalize">{{ issuance.status }}</AppBadge>
              </div>
              <h1 class="text-xl font-semibold text-slate-800">{{ issuance.title }}</h1>
              <p class="text-xs text-slate-500 mt-1">
                Issued by <strong>{{ issuance.creator?.name }}</strong>
                <span v-if="issuance.released_at"> · Released {{ fmtDt(issuance.released_at) }}</span>
              </p>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap gap-2 shrink-0">
              <!-- Acknowledge (staff) -->
              <AppButton v-if="!isAdmin && issuance.status === 'released' && !ackDone"
                @click="acknowledge" :disabled="ackForm.processing">
                <CheckCircleIcon class="h-4 w-4" /> Acknowledge Receipt
              </AppButton>
              <div v-else-if="!isAdmin && ackDone"
                class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-success-600 bg-success-50 border border-success-100 rounded-lg">
                <CheckCircleIcon class="h-4 w-4" /> Acknowledged
              </div>

              <!-- Release (admin, draft) -->
              <AppButton v-if="isAdmin && issuance.status === 'draft'" @click="openRelease">
                Sign & Release
              </AppButton>

              <!-- Download PDF -->
              <AppButton v-if="issuance.status === 'released'"
                as="a" :href="route('issuances.pdf', issuance.id)" target="_blank" variant="secondary">
                <DocumentArrowDownIcon class="h-4 w-4" /> PDF
              </AppButton>

              <!-- View scan -->
              <AppButton v-if="issuance.has_attachment" variant="secondary" @click="showScanModal = true">
                <EyeIcon class="h-4 w-4" /> View Scan
              </AppButton>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- ── Document Body ──────────────────────────────────────────────── -->
        <div class="lg:col-span-2 space-y-5">

          <!-- Content -->
          <AppCard v-if="issuance.content" title="Content">
            <!-- Official letterhead preview -->
            <div class="border border-slate-200 rounded-lg p-5">
              <div class="text-center border-b border-slate-200 pb-3 mb-4">
                <p class="text-xs font-bold text-slate-700 tracking-wide">PHILIPPINE SCIENCE HIGH SCHOOL</p>
                <p class="text-xs text-slate-500">Caraga Region Campus</p>
              </div>
              <div class="text-center mb-4">
                <p class="text-sm font-bold uppercase tracking-widest text-slate-800">{{ issuance.type_label }}</p>
                <p class="text-xs text-slate-500 mt-0.5">No. {{ issuance.control_number.split('-').slice(-1)[0] }}, S. {{ issuance.control_number.split('-')[1] }}</p>
              </div>
              <div class="text-sm text-slate-700 leading-relaxed prose prose-sm max-w-none" v-html="DOMPurify.sanitize(issuance.content ?? '')"></div>
            </div>
          </AppCard>

          <AppCard v-else-if="issuance.has_attachment">
            <EmptyState :icon="PencilSquareIcon" title="This issuance has an attached scanned document." :subtitle="issuance.attachment_filename">
              <AppButton class="mt-3" variant="secondary" size="sm" @click="showScanModal = true">
                View Scan
              </AppButton>
            </EmptyState>
          </AppCard>

          <!-- Signature block -->
          <AppCard v-if="issuance.signature">
            <template #header>
              <h3 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
                <ShieldCheckIcon class="h-4 w-4 text-success-500" /> Digital Signature
              </h3>
            </template>
            <div class="flex items-center gap-4">
              <img v-if="issuance.signature.sig_uri" :src="issuance.signature.sig_uri"
                class="h-12 object-contain" alt="Signature" />
              <div>
                <p class="font-semibold text-slate-800 text-sm">{{ issuance.signature.signer?.name }}</p>
                <p class="text-xs text-slate-500">{{ issuance.signature.signer?.position }}</p>
                <p class="text-xs text-slate-400 mt-1">{{ fmtDt(issuance.signature.signed_at) }}</p>
              </div>
            </div>
          </AppCard>
        </div>

        <!-- ── Right panel ────────────────────────────────────────────────── -->
        <div class="space-y-4">

          <!-- QR + Verification -->
          <AppCard v-if="issuance.status === 'released'" title="Verification QR">
            <div class="flex justify-center mb-3" v-html="issuance.qr_svg"></div>
            <AppButton variant="secondary" size="sm" block @click="copyVerifyUrl">
              {{ qrCopied ? '✓ Copied!' : 'Copy verification link' }}
            </AppButton>
            <p class="text-[10px] text-slate-400 text-center mt-2">Scan to verify authenticity</p>
          </AppCard>

          <!-- Hash -->
          <AppCard v-if="issuance.content_hash" title="Content Hash">
            <p class="font-mono text-[10px] text-slate-500 break-all bg-slate-50 rounded p-2">{{ issuance.content_hash }}</p>
            <p class="text-[10px] text-slate-400 mt-1">SHA-256 tamper detection</p>
          </AppCard>

          <!-- Acknowledgment progress (admin) -->
          <AppCard v-if="isAdmin && issuance.status === 'released'">
            <template #header>
              <h3 class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                <UserGroupIcon class="h-3.5 w-3.5" /> Acknowledgments
              </h3>
            </template>
            <div class="flex items-center gap-3 mb-2">
              <div class="flex-1 bg-slate-100 rounded-full h-2 overflow-hidden">
                <div class="bg-success-500 h-full rounded-full transition-all" :style="`width:${ackPercent}%`"></div>
              </div>
              <span class="text-xs font-bold text-slate-700 shrink-0">{{ ackCount }}/{{ totalCount }}</span>
            </div>
            <p class="text-xs text-slate-500">{{ ackPercent }}% acknowledged</p>

            <div class="mt-3 max-h-64 overflow-y-auto space-y-1">
              <div v-for="r in recipients" :key="r.id"
                class="flex items-center justify-between py-1.5 border-b border-slate-50 last:border-0 gap-2">
                <div class="min-w-0 flex-1">
                  <p class="text-xs font-medium text-slate-700 truncate">{{ r.user?.name ?? r.office?.name ?? '—' }}</p>
                  <p v-if="r.user?.position" class="text-[10px] text-slate-400 truncate">{{ r.user.position }}</p>
                  <p v-if="r.email_status === 'failed' && r.email_error" class="text-[10px] text-red-500 truncate" :title="r.email_error">{{ r.email_error }}</p>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                  <AppBadge :color="EMAIL_STATUS_COLOR[r.email_status] ?? 'slate'">
                    {{ EMAIL_STATUS_LABEL[r.email_status] ?? r.email_status }}
                  </AppBadge>
                  <button v-if="r.email_status === 'failed' || r.email_status === 'skipped'" type="button"
                    @click="resendEmail(r)"
                    class="p-1 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors" title="Resend email">
                    <ArrowPathIcon class="h-3.5 w-3.5" />
                  </button>
                  <span v-if="r.acknowledged_at" class="text-success-500 text-xs" title="Acknowledged">✓</span>
                  <ClockIcon v-else class="h-3.5 w-3.5 text-slate-300" />
                </div>
              </div>
            </div>
          </AppCard>

          <!-- Release panel (draft) -->
          <AppCard v-if="isAdmin && issuance.status === 'draft' && showReleasePanel" title="Release Settings">
            <div class="space-y-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1.5">Recipients</label>
                <div class="space-y-1.5">
                  <label v-for="opt in [
                    { key:'all', label:'All Staff' },
                    { key:'office', label:'By Office (configure on create)' },
                    { key:'division', label:'By Division' },
                    { key:'individual', label:'Individual (configure on create)' },
                  ]" :key="opt.key" class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" v-model="recipientType" :value="opt.key" class="text-indigo-600" />
                    <span class="text-sm text-slate-700">{{ opt.label }}</span>
                  </label>
                </div>
              </div>

              <!-- Division picker (only shown when By Division is selected) -->
              <div v-if="recipientType === 'division'" class="space-y-2">
                <AppInput v-model="divisionSearch" type="text" placeholder="Search divisions…" />
                <div class="max-h-40 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
                  <label v-for="d in filteredDivisions" :key="d.id"
                    class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
                    <input type="checkbox" :checked="selectedDivisionIds.includes(d.id)"
                      @change="toggleDivision(d.id)" class="rounded border-slate-300 text-indigo-600" />
                    <div>
                      <p class="text-sm text-slate-700">{{ d.division_name }}</p>
                      <p v-if="d.acronym" class="text-xs text-slate-400">{{ d.acronym }}</p>
                    </div>
                  </label>
                </div>
                <p v-if="selectedDivisionIds.length" class="text-xs text-indigo-600 font-medium">{{ selectedDivisionIds.length }} division(s) selected</p>
              </div>

              <p class="text-xs text-warning-700 bg-warning-50 rounded-lg px-3 py-2">
                ⚠ This will sign and release the issuance permanently.
              </p>

              <AppButton block @click="showPinModal = true">
                Sign & Release Now
              </AppButton>
            </div>
          </AppCard>
        </div>
      </div>
    </div>

    <!-- PIN Modal -->
    <DigitalSignaturePin
      v-if="showPinModal"
      :show="showPinModal"
      :has-pin="hasPin"
      :signature-uri="signatureUri"
      @close="showPinModal = false"
      @cancel="showPinModal = false"
      @confirm="onPinVerified"
    />

    <!-- Scan preview modal -->
    <AppModal :show="showScanModal" :title="issuance.attachment_filename" size="4xl" body-class="p-2" @close="showScanModal = false">
      <iframe :src="route('issuances.scan', issuance.id)"
        class="w-full h-[78vh] rounded-lg border border-slate-100" />
    </AppModal>

  </AdminLayout>
</template>
