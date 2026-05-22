<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'
import {
  ChevronLeftIcon, DocumentArrowDownIcon, QrCodeIcon,
  CheckCircleIcon, UserGroupIcon, ClockIcon, ShieldCheckIcon,
  PencilSquareIcon, EyeIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  issuance:         Object,
  recipients:       Array,
  isAdmin:          Boolean,
  hasPin:           Boolean,
  signatureUri:     String,
  myAcknowledgedAt: String,
  verifyUrl:        String,
})

// ── Release flow (for drafts) ──────────────────────────────────────────────
const showReleasePanel = ref(false)
const showPinModal     = ref(false)
const recipientType    = ref(props.issuance.recipient_type ?? 'all')
const submitting       = ref(false)
const releaseErrors    = ref({})

function openRelease() {
  showReleasePanel.value = true
}

function onPinVerified(pin) {
  showPinModal.value = false
  submitting.value   = true
  releaseErrors.value = {}
  router.post(route('issuances.release', props.issuance.id), {
    recipient_type: recipientType.value,
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

// ── Helpers ────────────────────────────────────────────────────────────────
const typeCls = {
  SO: 'bg-indigo-100 text-indigo-700', TO: 'bg-blue-100 text-blue-700',
  MEMO: 'bg-violet-100 text-violet-700', OO: 'bg-cyan-100 text-cyan-700',
  AO: 'bg-amber-100 text-amber-700', CIRC: 'bg-emerald-100 text-emerald-700',
  NOTICE: 'bg-rose-100 text-rose-700',
}
const statusCls = { draft: 'bg-slate-100 text-slate-600', released: 'bg-emerald-100 text-emerald-700' }

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
      <button @click="router.visit(route('issuances.index'))"
        class="flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800">
        <ChevronLeftIcon class="h-4 w-4" /> Back to Issuances
      </button>

      <!-- ── Document Header ─────────────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <!-- Colour band -->
        <div class="h-1.5 bg-indigo-600"></div>

        <div class="p-6">
          <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div class="flex-1">
              <div class="flex flex-wrap items-center gap-2 mb-2">
                <span class="font-mono font-bold text-slate-800 text-base">{{ issuance.control_number }}</span>
                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold"
                  :class="typeCls[issuance.type] ?? 'bg-slate-100 text-slate-600'">
                  {{ issuance.type_label }}
                </span>
                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold capitalize"
                  :class="statusCls[issuance.status] ?? 'bg-slate-100 text-slate-600'">
                  {{ issuance.status }}
                </span>
              </div>
              <h1 class="text-lg font-bold text-slate-800">{{ issuance.title }}</h1>
              <p class="text-xs text-slate-500 mt-1">
                Issued by <strong>{{ issuance.creator?.name }}</strong>
                <span v-if="issuance.released_at"> · Released {{ fmtDt(issuance.released_at) }}</span>
              </p>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap gap-2 shrink-0">
              <!-- Acknowledge (staff) -->
              <button v-if="!isAdmin && issuance.status === 'released' && !ackDone"
                @click="acknowledge" :disabled="ackForm.processing"
                class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white rounded-lg"
                class="bg-indigo-600 hover:bg-indigo-700">
                <CheckCircleIcon class="h-4 w-4" /> Acknowledge Receipt
              </button>
              <div v-else-if="!isAdmin && ackDone"
                class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-emerald-600 bg-emerald-50 border border-emerald-200 rounded-lg">
                <CheckCircleIcon class="h-4 w-4" /> Acknowledged
              </div>

              <!-- Release (admin, draft) -->
              <button v-if="isAdmin && issuance.status === 'draft'"
                @click="openRelease"
                class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white rounded-lg"
                class="bg-indigo-600 hover:bg-indigo-700">
                Sign & Release
              </button>

              <!-- Download PDF -->
              <a v-if="issuance.status === 'released'"
                :href="route('issuances.pdf', issuance.id)" target="_blank"
                class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-700 border border-slate-200 rounded-lg hover:bg-slate-50">
                <DocumentArrowDownIcon class="h-4 w-4" /> PDF
              </a>

              <!-- View scan -->
              <button v-if="issuance.has_attachment" @click="showScanModal = true"
                class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-700 border border-slate-200 rounded-lg hover:bg-slate-50">
                <EyeIcon class="h-4 w-4" /> View Scan
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- ── Document Body ──────────────────────────────────────────────── -->
        <div class="lg:col-span-2 space-y-5">

          <!-- Content -->
          <div v-if="issuance.content" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-4">Content</h2>

            <!-- Official letterhead preview -->
            <div class="border border-slate-200 rounded-lg p-5">
              <div class="text-center border-b border-slate-200 pb-3 mb-4">
                <p class="text-xs font-bold text-slate-700 tracking-wide">PHILIPPINE SCIENCE HIGH SCHOOL</p>
                <p class="text-xs text-slate-500">Caraga Region Campus</p>
              </div>
              <div class="text-center mb-4">
                <p class="text-sm font-bold uppercase tracking-widest text-slate-800">{{ issuance.type_label }}</p>
                <p class="text-xs text-slate-500 mt-0.5">No. {{ issuance.control_number.split('-')[2] }}, S. {{ issuance.control_number.split('-')[1] }}</p>
              </div>
              <div class="text-sm text-slate-700 whitespace-pre-wrap leading-relaxed">{{ issuance.content }}</div>
            </div>
          </div>

          <div v-else-if="issuance.has_attachment"
            class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 text-center">
            <PencilSquareIcon class="h-8 w-8 text-slate-300 mx-auto mb-2" />
            <p class="text-sm text-slate-500">This issuance has an attached scanned document.</p>
            <p class="text-xs text-slate-400 mt-1">{{ issuance.attachment_filename }}</p>
            <button @click="showScanModal = true"
              class="mt-3 px-4 py-2 text-sm font-medium text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50">
              View Scan
            </button>
          </div>

          <!-- Signature block -->
          <div v-if="issuance.signature" class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3 flex items-center gap-2">
              <ShieldCheckIcon class="h-4 w-4 text-emerald-500" /> Digital Signature
            </h2>
            <div class="flex items-center gap-4">
              <img v-if="issuance.signature.sig_uri" :src="issuance.signature.sig_uri"
                class="h-12 object-contain" alt="Signature" />
              <div>
                <p class="font-semibold text-slate-800 text-sm">{{ issuance.signature.signer?.name }}</p>
                <p class="text-xs text-slate-500">{{ issuance.signature.signer?.position }}</p>
                <p class="text-xs text-slate-400 mt-1">{{ fmtDt(issuance.signature.signed_at) }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Right panel ────────────────────────────────────────────────── -->
        <div class="space-y-4">

          <!-- QR + Verification -->
          <div v-if="issuance.status === 'released'" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Verification QR</h2>
            <div class="flex justify-center mb-3" v-html="issuance.qr_svg"></div>
            <button @click="copyVerifyUrl"
              class="w-full px-3 py-2 text-xs font-medium text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50 transition-colors">
              {{ qrCopied ? '✓ Copied!' : 'Copy verification link' }}
            </button>
            <p class="text-[10px] text-slate-400 text-center mt-2">Scan to verify authenticity</p>
          </div>

          <!-- Hash -->
          <div v-if="issuance.content_hash" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Content Hash</h2>
            <p class="font-mono text-[10px] text-slate-500 break-all bg-slate-50 rounded p-2">{{ issuance.content_hash }}</p>
            <p class="text-[10px] text-slate-400 mt-1">SHA-256 tamper detection</p>
          </div>

          <!-- Acknowledgment progress (admin) -->
          <div v-if="isAdmin && issuance.status === 'released'" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3 flex items-center gap-1.5">
              <UserGroupIcon class="h-3.5 w-3.5" /> Acknowledgments
            </h2>
            <div class="flex items-center gap-3 mb-2">
              <div class="flex-1 bg-slate-100 rounded-full h-2 overflow-hidden">
                <div class="bg-emerald-500 h-full rounded-full transition-all" :style="`width:${ackPercent}%`"></div>
              </div>
              <span class="text-xs font-bold text-slate-700 shrink-0">{{ ackCount }}/{{ totalCount }}</span>
            </div>
            <p class="text-xs text-slate-500">{{ ackPercent }}% acknowledged</p>

            <div class="mt-3 max-h-48 overflow-y-auto space-y-1">
              <div v-for="r in recipients" :key="r.id"
                class="flex items-center justify-between py-1.5 border-b border-slate-50 last:border-0">
                <div class="min-w-0 flex-1">
                  <p class="text-xs font-medium text-slate-700 truncate">{{ r.user?.name ?? r.office?.name ?? '—' }}</p>
                  <p v-if="r.user?.position" class="text-[10px] text-slate-400 truncate">{{ r.user.position }}</p>
                </div>
                <span v-if="r.acknowledged_at" class="text-emerald-500 text-xs shrink-0 ml-2" title="Acknowledged">✓</span>
                <ClockIcon v-else class="h-3.5 w-3.5 text-slate-300 shrink-0 ml-2" />
              </div>
            </div>
          </div>

          <!-- Release panel (draft) -->
          <div v-if="isAdmin && issuance.status === 'draft' && showReleasePanel"
            class="bg-white rounded-xl border border-indigo-200 shadow-sm p-4 space-y-3">
            <h2 class="text-sm font-semibold text-slate-700">Release Settings</h2>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1.5">Recipients</label>
              <div class="space-y-1.5">
                <label v-for="opt in [
                  { key:'all', label:'All Staff' },
                  { key:'office', label:'By Office (configure on create)' },
                  { key:'individual', label:'Individual (configure on create)' },
                ]" :key="opt.key" class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="recipientType" :value="opt.key" class="text-indigo-600" />
                  <span class="text-sm text-slate-700">{{ opt.label }}</span>
                </label>
              </div>
            </div>

            <p class="text-xs text-amber-600 bg-amber-50 rounded-lg px-3 py-2">
              ⚠ This will sign and release the issuance permanently.
            </p>

            <button @click="showPinModal = true"
              class="w-full py-2 text-sm font-medium text-white rounded-lg"
              class="bg-indigo-600 hover:bg-indigo-700">
              Sign & Release Now
            </button>
          </div>
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
      @verified="onPinVerified"
    />

    <!-- Scan preview modal -->
    <Teleport to="body">
      <div v-if="showScanModal" class="fixed inset-0 z-50 flex items-center justify-center px-4 bg-black/60">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col">
          <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <p class="text-sm font-semibold text-slate-800">{{ issuance.attachment_filename }}</p>
            <button @click="showScanModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold leading-none">✕</button>
          </div>
          <div class="flex-1 overflow-auto p-2">
            <iframe :src="route('issuances.scan', issuance.id)"
              class="w-full h-[78vh] rounded-lg border border-slate-100" />
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>
