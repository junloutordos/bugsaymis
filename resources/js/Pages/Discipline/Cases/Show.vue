<script setup>
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppSelect from '@/Components/AppSelect.vue'
import { confirmAction, confirmDelete } from '@/Composables/useConfirm.js'
import {
  ArrowLeftIcon, PrinterIcon, PaperClipIcon, TrashIcon, CheckCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  case: Object,
  student: Object,
  offenses: Array,
  canManage: Boolean,
  hasPin: Boolean,
})

const c = ref(props.case)

const statusColor = (s) => ({
  filed: 'amber',
  received: 'blue',
  under_review: 'indigo',
  resolved: 'green',
  dismissed: 'slate',
  cancelled: 'red',
}[s] || 'slate')

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—'
const fmtDateTime = (d) => d ? new Date(d).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) : '—'

const isClosed = () => ['resolved', 'dismissed', 'cancelled'].includes(c.value.status)

// ── Review ────────────────────────────────────────────────────────────────────
const showReview = ref(false)
const reviewForm = useForm({
  offense_id: props.case.offense_id || '',
  offense_level: props.case.offense_level || '',
  is_bullying: !!props.case.is_bullying,
  threat_level: props.case.threat_level || '',
  remarks: '',
})
function submitReview() {
  reviewForm.put(route('discipline.cases.review', c.value.id), {
    preserveScroll: true, onSuccess: () => { showReview.value = false },
  })
}

// ── Resolve ───────────────────────────────────────────────────────────────────
const showResolve = ref(false)
const resolveForm = useForm({ outcome: 'resolved', sanction: '', resolution: '', pin: '' })
function submitResolve() {
  resolveForm.put(route('discipline.cases.resolve', c.value.id), {
    preserveScroll: true, onSuccess: () => { showResolve.value = false; resolveForm.pin = '' },
  })
}

// ── Intervention ──────────────────────────────────────────────────────────────
const showIntervention = ref(false)
const intvForm = useForm({ intervention_type: '', description: '', start_date: '', end_date: '' })
function submitIntervention() {
  intvForm.post(route('discipline.cases.interventions.store', c.value.id), {
    preserveScroll: true, onSuccess: () => { showIntervention.value = false; intvForm.reset() },
  })
}

// ── Receive (with optional PIN signing) ───────────────────────────────────────
const showReceivePin = ref(false)
const receivePin = ref('')
function receive() {
  if (props.hasPin) { showReceivePin.value = true; return }
  router.put(route('discipline.cases.receive', c.value.id), {}, { preserveScroll: true })
}
function confirmReceive() {
  router.put(route('discipline.cases.receive', c.value.id), { pin: receivePin.value }, {
    preserveScroll: true, onSuccess: () => { showReceivePin.value = false; receivePin.value = '' },
  })
}
async function cancelCase() {
  const ok = await confirmAction({ title: 'Cancel this case?', text: 'This cannot be undone.', confirmText: 'Yes, cancel' })
  if (ok) {
    router.put(route('discipline.cases.cancel', c.value.id), {}, { preserveScroll: true })
  }
}

// ── Attachments (base64 → JSON, Cloudflare-safe) ──────────────────────────────
const uploading = ref(false)
const fileInput = ref(null)
async function onFile(e) {
  const file = e.target.files?.[0]
  if (!file) return
  uploading.value = true
  try {
    const dataUri = await new Promise((resolve, reject) => {
      const r = new FileReader()
      r.onload = () => resolve(r.result)
      r.onerror = reject
      r.readAsDataURL(file)
    })
    await axios.post(route('discipline.cases.attachments.store', c.value.id), {
      file_base64: dataUri, file_name: file.name,
    })
    router.reload({ only: ['case'] })
  } catch (err) {
    alert(err?.response?.data?.message || 'Upload failed.')
  } finally {
    uploading.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}
async function deleteAttachment(a) {
  const ok = await confirmDelete('Remove this attachment?')
  if (ok) {
    router.delete(route('discipline.cases.attachments.destroy', [c.value.id, a.id]), { preserveScroll: true })
  }
}

function printPdf() {
  window.open(route('discipline.cases.pdf', c.value.id), '_blank')
}
</script>

<template>
  <Head :title="`Case ${c.case_no}`" />
  <AdminLayout :title="`Case ${c.case_no}`">
    <div class="max-w-4xl mx-auto space-y-5">
      <Link :href="route('discipline.cases.index')" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700">
        <ArrowLeftIcon class="w-4 h-4" /> Back to cases
      </Link>

      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-xl font-semibold text-slate-800">{{ c.case_no }}</h1>
            <AppBadge :color="statusColor(c.status)">{{ c.status.replace('_', ' ') }}</AppBadge>
            <AppBadge v-if="c.is_bullying" color="red">Bullying</AppBadge>
          </div>
          <p class="text-sm text-slate-500 mt-1">Filed by {{ c.filer?.name }} · {{ fmtDate(c.date_filed) }}</p>
        </div>
        <div class="flex gap-2">
          <AppButton variant="secondary" @click="printPdf">
            <PrinterIcon class="w-4 h-4" /> Print
          </AppButton>
        </div>
      </div>

      <!-- SDO action bar -->
      <AppCard v-if="canManage && !isClosed()">
        <div class="flex flex-wrap gap-2">
          <AppButton v-if="c.status === 'filed'" @click="receive">Mark Received</AppButton>
          <AppButton v-if="['received','under_review'].includes(c.status)" @click="showReview = !showReview">Review / Classify</AppButton>
          <AppButton v-if="['received','under_review'].includes(c.status)" variant="success" @click="showResolve = !showResolve">Resolve / Dismiss</AppButton>
          <AppButton variant="secondary" @click="showIntervention = !showIntervention">Add Intervention</AppButton>
          <button @click="cancelCase" class="ml-auto text-sm text-danger-600 hover:text-danger-700 px-3 py-2 rounded-lg">Cancel case</button>
        </div>
      </AppCard>

      <!-- Receive PIN panel -->
      <AppCard v-if="showReceivePin" title="Sign &amp; Receive" subtitle="Enter your signature PIN to digitally sign as the receiving officer.">
        <div class="flex items-end gap-3">
          <AppInput v-model="receivePin" type="password" inputmode="numeric" autocomplete="off" label="Signature PIN" class="w-48" />
          <AppButton :disabled="!receivePin" @click="confirmReceive">Sign &amp; Receive</AppButton>
          <AppButton variant="secondary" @click="showReceivePin = false">Cancel</AppButton>
        </div>
      </AppCard>

      <!-- Review form -->
      <AppCard v-if="showReview" title="Review &amp; Classify">
        <div class="space-y-4">
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Offense</label>
              <select v-model="reviewForm.offense_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">—</option>
                <option v-for="o in offenses" :key="o.id" :value="o.id">L{{ o.level }} · {{ o.title }}</option>
              </select>
            </div>
            <AppSelect v-model="reviewForm.offense_level" label="Level" placeholder="—">
              <option value="1">Level 1</option>
              <option value="2">Level 2</option>
              <option value="3">Level 3</option>
            </AppSelect>
          </div>
          <div class="flex flex-wrap gap-4 items-end">
            <label class="flex items-center gap-2 text-sm text-slate-700">
              <input type="checkbox" v-model="reviewForm.is_bullying" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" /> Bullying
            </label>
            <AppSelect v-if="reviewForm.is_bullying" v-model="reviewForm.threat_level" label="Threat level" placeholder="—" class="w-48">
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High (24h response)</option>
            </AppSelect>
          </div>
          <AppTextarea v-model="reviewForm.remarks" :rows="3" label="Remarks" />
          <div class="flex justify-end">
            <AppButton :loading="reviewForm.processing" @click="submitReview">Save review</AppButton>
          </div>
        </div>
      </AppCard>

      <!-- Resolve form -->
      <AppCard v-if="showResolve" title="Resolve / Dismiss">
        <div class="space-y-4">
          <AppSelect v-model="resolveForm.outcome" label="Outcome" :show-blank="false" class="w-48">
            <option value="resolved">Resolved</option>
            <option value="dismissed">Dismissed</option>
          </AppSelect>
          <AppTextarea v-if="resolveForm.outcome === 'resolved'" v-model="resolveForm.sanction" :rows="3" label="Sanction / Action" />
          <AppTextarea v-model="resolveForm.resolution" :rows="3" label="Resolution notes" required :error="resolveForm.errors.resolution" />
          <div v-if="hasPin">
            <AppInput v-model="resolveForm.pin" type="password" inputmode="numeric" autocomplete="off" label="Signature PIN" required class="w-40" />
            <p class="text-xs text-slate-400 mt-1">Digitally signs the resolution.</p>
          </div>
          <div class="flex justify-end">
            <AppButton variant="success" :loading="resolveForm.processing" @click="submitResolve">Submit</AppButton>
          </div>
        </div>
      </AppCard>

      <!-- Intervention form -->
      <AppCard v-if="showIntervention" title="Add Intervention">
        <div class="space-y-4">
          <div class="grid sm:grid-cols-2 gap-4">
            <AppInput v-model="intvForm.intervention_type" label="Type" required placeholder="Warning, Counseling, Suspension…" />
            <div class="grid grid-cols-2 gap-2">
              <AppInput v-model="intvForm.start_date" type="date" label="Start" />
              <AppInput v-model="intvForm.end_date" type="date" label="End" />
            </div>
          </div>
          <AppTextarea v-model="intvForm.description" :rows="2" label="Description" />
          <div class="flex justify-end">
            <AppButton :loading="intvForm.processing" @click="submitIntervention">Add</AppButton>
          </div>
        </div>
      </AppCard>

      <!-- Details -->
      <AppCard>
        <div class="space-y-4">
          <div class="grid sm:grid-cols-2 gap-4 text-sm">
            <div><div class="text-xs text-slate-400 uppercase tracking-wide">Student</div><div class="text-slate-700 font-medium">{{ student.name }}</div></div>
            <div><div class="text-xs text-slate-400 uppercase tracking-wide">Grade / Section</div><div class="text-slate-700">{{ student.grade_section || c.filer_section_snapshot || '—' }}</div></div>
            <div><div class="text-xs text-slate-400 uppercase tracking-wide">Offense</div><div class="text-slate-700">{{ c.offense?.title || c.nature_of_offense || '—' }} <span v-if="c.offense_level" class="text-slate-400">(L{{ c.offense_level }})</span></div></div>
            <div><div class="text-xs text-slate-400 uppercase tracking-wide">Incident</div><div class="text-slate-700">{{ fmtDate(c.incident_date) }} {{ c.incident_time }} · {{ c.place || '—' }}</div></div>
          </div>
          <div>
            <div class="text-xs text-slate-400 uppercase tracking-wide mb-1">Narrative</div>
            <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ c.narrative }}</p>
          </div>
          <div v-if="c.other_witnesses">
            <div class="text-xs text-slate-400 uppercase tracking-wide mb-1">Other witnesses</div>
            <p class="text-sm text-slate-700">{{ c.other_witnesses }}</p>
          </div>
          <div v-if="c.interventions_done">
            <div class="text-xs text-slate-400 uppercase tracking-wide mb-1">Interventions done (stated)</div>
            <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ c.interventions_done }}</p>
          </div>
        </div>
      </AppCard>

      <!-- Resolution -->
      <div v-if="c.resolution || c.sanction" class="bg-success-50/50 rounded-xl border border-success-100 p-6 space-y-3">
        <div class="flex items-center gap-2 text-success-700"><CheckCircleIcon class="w-5 h-5" /><h3 class="font-semibold">Resolution</h3></div>
        <div v-if="c.sanction"><div class="text-xs text-slate-400 uppercase tracking-wide mb-1">Sanction</div><p class="text-sm text-slate-700 whitespace-pre-wrap">{{ c.sanction }}</p></div>
        <div><div class="text-xs text-slate-400 uppercase tracking-wide mb-1">Notes</div><p class="text-sm text-slate-700 whitespace-pre-wrap">{{ c.resolution }}</p></div>
        <p class="text-xs text-slate-500">Resolved by {{ c.resolver?.name }} · {{ fmtDateTime(c.resolved_at) }}</p>
      </div>

      <!-- Interventions list -->
      <AppCard v-if="c.interventions?.length" title="Interventions">
        <ul class="space-y-2">
          <li v-for="iv in c.interventions" :key="iv.id" class="text-sm border-b border-slate-50 pb-2 last:border-0">
            <span class="font-medium text-slate-700">{{ iv.intervention_type }}</span>
            <span class="text-slate-400"> · {{ iv.imposer?.name }}</span>
            <p v-if="iv.description" class="text-slate-600">{{ iv.description }}</p>
            <p v-if="iv.start_date" class="text-xs text-slate-400">{{ fmtDate(iv.start_date) }} → {{ fmtDate(iv.end_date) }}</p>
          </li>
        </ul>
      </AppCard>

      <!-- Attachments -->
      <AppCard title="Attachments">
        <template #header>
          <label class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 cursor-pointer">
            <PaperClipIcon class="w-4 h-4" /> {{ uploading ? 'Uploading…' : 'Upload' }}
            <input ref="fileInput" type="file" class="hidden" @change="onFile" :disabled="uploading" />
          </label>
        </template>
        <ul v-if="c.attachments?.length" class="space-y-2">
          <li v-for="a in c.attachments" :key="a.id" class="flex items-center justify-between text-sm border-b border-slate-50 pb-2 last:border-0">
            <a :href="route('discipline.cases.attachments.show', [c.id, a.id])" target="_blank" class="text-indigo-600 hover:underline">{{ a.file_name }}</a>
            <AppIconButton label="Remove attachment" variant="danger" size="sm" @click="deleteAttachment(a)">
              <TrashIcon class="w-4 h-4" />
            </AppIconButton>
          </li>
        </ul>
        <p v-else class="text-sm text-slate-400">No attachments.</p>
      </AppCard>

      <!-- Timeline -->
      <AppCard title="Case Timeline">
        <ol class="relative border-l border-slate-200 ml-2 space-y-4">
          <li v-for="snap in c.approval_snapshots" :key="snap.id" class="ml-4">
            <div class="absolute -left-1.5 w-3 h-3 rounded-full bg-indigo-500"></div>
            <p class="text-sm font-medium text-slate-700 capitalize">{{ (snap.step || '').replace('discipline_', '').replace('_', ' ') }}</p>
            <p class="text-xs text-slate-500">{{ snap.name_snapshot }} · {{ fmtDateTime(snap.signed_at) }}</p>
            <p v-if="snap.remarks" class="text-xs text-slate-600 mt-0.5">{{ snap.remarks }}</p>
          </li>
          <li v-if="!c.approval_snapshots?.length" class="ml-4 text-sm text-slate-400">No timeline entries.</li>
        </ol>
      </AppCard>
    </div>
  </AdminLayout>
</template>
