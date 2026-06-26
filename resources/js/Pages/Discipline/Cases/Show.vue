<script setup>
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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

const statusClass = (s) => ({
  filed: 'bg-amber-100 text-amber-700',
  received: 'bg-sky-100 text-sky-700',
  under_review: 'bg-indigo-100 text-indigo-700',
  resolved: 'bg-emerald-100 text-emerald-700',
  dismissed: 'bg-slate-100 text-slate-600',
  cancelled: 'bg-rose-100 text-rose-700',
}[s] || 'bg-slate-100 text-slate-600')

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
function cancelCase() {
  if (confirm('Cancel this case? This cannot be undone.')) {
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
function deleteAttachment(a) {
  if (confirm('Remove this attachment?')) {
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
            <span class="inline-block px-2 py-1 rounded-md text-xs font-medium" :class="statusClass(c.status)">{{ c.status.replace('_', ' ') }}</span>
            <span v-if="c.is_bullying" class="inline-block px-2 py-1 rounded-md text-xs font-medium bg-rose-100 text-rose-700">Bullying</span>
          </div>
          <p class="text-sm text-slate-500 mt-1">Filed by {{ c.filer?.name }} · {{ fmtDate(c.date_filed) }}</p>
        </div>
        <div class="flex gap-2">
          <button @click="printPdf" class="inline-flex items-center gap-1.5 border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-2 rounded-lg text-sm">
            <PrinterIcon class="w-4 h-4" /> Print
          </button>
        </div>
      </div>

      <!-- SDO action bar -->
      <div v-if="canManage && !isClosed()" class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap gap-2">
        <button v-if="c.status === 'filed'" @click="receive"
                class="bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Mark Received</button>
        <button v-if="['received','under_review'].includes(c.status)" @click="showReview = !showReview"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Review / Classify</button>
        <button v-if="['received','under_review'].includes(c.status)" @click="showResolve = !showResolve"
                class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Resolve / Dismiss</button>
        <button @click="showIntervention = !showIntervention"
                class="border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium">Add Intervention</button>
        <button @click="cancelCase"
                class="ml-auto text-rose-600 hover:text-rose-800 px-3 py-2 rounded-lg text-sm">Cancel case</button>
      </div>

      <!-- Receive PIN panel -->
      <div v-if="showReceivePin" class="bg-white rounded-xl border border-sky-100 shadow-sm p-5 space-y-3">
        <h3 class="font-semibold text-slate-700">Sign &amp; Receive</h3>
        <p class="text-sm text-slate-500">Enter your signature PIN to digitally sign as the receiving officer.</p>
        <div class="flex items-end gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Signature PIN</label>
            <input v-model="receivePin" type="password" inputmode="numeric" autocomplete="off"
                   class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-sky-500" />
          </div>
          <button @click="confirmReceive" :disabled="!receivePin" class="bg-sky-600 hover:bg-sky-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">Sign &amp; Receive</button>
          <button @click="showReceivePin = false" class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
        </div>
      </div>

      <!-- Review form -->
      <div v-if="showReview" class="bg-white rounded-xl border border-indigo-100 shadow-sm p-5 space-y-4">
        <h3 class="font-semibold text-slate-700">Review &amp; Classify</h3>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Offense</label>
            <select v-model="reviewForm.offense_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
              <option value="">—</option>
              <option v-for="o in offenses" :key="o.id" :value="o.id">L{{ o.level }} · {{ o.title }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Level</label>
            <select v-model="reviewForm.offense_level" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
              <option value="">—</option><option value="1">Level 1</option><option value="2">Level 2</option><option value="3">Level 3</option>
            </select>
          </div>
        </div>
        <div class="flex flex-wrap gap-4 items-end">
          <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" v-model="reviewForm.is_bullying" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" /> Bullying
          </label>
          <div v-if="reviewForm.is_bullying">
            <label class="block text-xs font-medium text-slate-600 mb-1">Threat level</label>
            <select v-model="reviewForm.threat_level" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
              <option value="">—</option><option value="low">Low</option><option value="medium">Medium</option><option value="high">High (24h response)</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
          <textarea v-model="reviewForm.remarks" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-y"></textarea>
        </div>
        <div class="flex justify-end">
          <button @click="submitReview" :disabled="reviewForm.processing" class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">Save review</button>
        </div>
      </div>

      <!-- Resolve form -->
      <div v-if="showResolve" class="bg-white rounded-xl border border-emerald-100 shadow-sm p-5 space-y-4">
        <h3 class="font-semibold text-slate-700">Resolve / Dismiss</h3>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Outcome</label>
          <select v-model="resolveForm.outcome" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
            <option value="resolved">Resolved</option>
            <option value="dismissed">Dismissed</option>
          </select>
        </div>
        <div v-if="resolveForm.outcome === 'resolved'">
          <label class="block text-xs font-medium text-slate-600 mb-1">Sanction / Action</label>
          <textarea v-model="resolveForm.sanction" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 resize-y"></textarea>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Resolution notes <span class="text-red-500">*</span></label>
          <textarea v-model="resolveForm.resolution" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 resize-y"
                    :class="{ 'border-red-400': resolveForm.errors.resolution }"></textarea>
          <p v-if="resolveForm.errors.resolution" class="text-red-500 text-xs mt-1">{{ resolveForm.errors.resolution }}</p>
        </div>
        <div v-if="hasPin">
          <label class="block text-xs font-medium text-slate-600 mb-1">Signature PIN <span class="text-red-500">*</span></label>
          <input v-model="resolveForm.pin" type="password" inputmode="numeric" autocomplete="off"
                 class="w-40 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500" />
          <p class="text-xs text-slate-400 mt-1">Digitally signs the resolution.</p>
        </div>
        <div class="flex justify-end">
          <button @click="submitResolve" :disabled="resolveForm.processing" class="bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">Submit</button>
        </div>
      </div>

      <!-- Intervention form -->
      <div v-if="showIntervention" class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-4">
        <h3 class="font-semibold text-slate-700">Add Intervention</h3>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Type <span class="text-red-500">*</span></label>
            <input v-model="intvForm.intervention_type" type="text" placeholder="Warning, Counseling, Suspension…" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Start</label>
              <input v-model="intvForm.start_date" type="date" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">End</label>
              <input v-model="intvForm.end_date" type="date" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
            </div>
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
          <textarea v-model="intvForm.description" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-y"></textarea>
        </div>
        <div class="flex justify-end">
          <button @click="submitIntervention" :disabled="intvForm.processing" class="bg-slate-800 hover:bg-slate-900 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">Add</button>
        </div>
      </div>

      <!-- Details -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6 space-y-4">
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

      <!-- Resolution -->
      <div v-if="c.resolution || c.sanction" class="bg-emerald-50/50 rounded-xl border border-emerald-100 p-6 space-y-3">
        <div class="flex items-center gap-2 text-emerald-700"><CheckCircleIcon class="w-5 h-5" /><h3 class="font-semibold">Resolution</h3></div>
        <div v-if="c.sanction"><div class="text-xs text-slate-400 uppercase tracking-wide mb-1">Sanction</div><p class="text-sm text-slate-700 whitespace-pre-wrap">{{ c.sanction }}</p></div>
        <div><div class="text-xs text-slate-400 uppercase tracking-wide mb-1">Notes</div><p class="text-sm text-slate-700 whitespace-pre-wrap">{{ c.resolution }}</p></div>
        <p class="text-xs text-slate-500">Resolved by {{ c.resolver?.name }} · {{ fmtDateTime(c.resolved_at) }}</p>
      </div>

      <!-- Interventions list -->
      <div v-if="c.interventions?.length" class="bg-white rounded-xl border border-slate-100 shadow-sm p-6">
        <h3 class="font-semibold text-slate-700 mb-3">Interventions</h3>
        <ul class="space-y-2">
          <li v-for="iv in c.interventions" :key="iv.id" class="text-sm border-b border-slate-50 pb-2 last:border-0">
            <span class="font-medium text-slate-700">{{ iv.intervention_type }}</span>
            <span class="text-slate-400"> · {{ iv.imposer?.name }}</span>
            <p v-if="iv.description" class="text-slate-600">{{ iv.description }}</p>
            <p v-if="iv.start_date" class="text-xs text-slate-400">{{ fmtDate(iv.start_date) }} → {{ fmtDate(iv.end_date) }}</p>
          </li>
        </ul>
      </div>

      <!-- Attachments -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6">
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-semibold text-slate-700">Attachments</h3>
          <label class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 cursor-pointer">
            <PaperClipIcon class="w-4 h-4" /> {{ uploading ? 'Uploading…' : 'Upload' }}
            <input ref="fileInput" type="file" class="hidden" @change="onFile" :disabled="uploading" />
          </label>
        </div>
        <ul v-if="c.attachments?.length" class="space-y-2">
          <li v-for="a in c.attachments" :key="a.id" class="flex items-center justify-between text-sm border-b border-slate-50 pb-2 last:border-0">
            <a :href="route('discipline.cases.attachments.show', [c.id, a.id])" target="_blank" class="text-indigo-600 hover:underline">{{ a.file_name }}</a>
            <button @click="deleteAttachment(a)" class="text-slate-400 hover:text-rose-600"><TrashIcon class="w-4 h-4" /></button>
          </li>
        </ul>
        <p v-else class="text-sm text-slate-400">No attachments.</p>
      </div>

      <!-- Timeline -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6">
        <h3 class="font-semibold text-slate-700 mb-3">Case Timeline</h3>
        <ol class="relative border-l border-slate-200 ml-2 space-y-4">
          <li v-for="snap in c.approval_snapshots" :key="snap.id" class="ml-4">
            <div class="absolute -left-1.5 w-3 h-3 rounded-full bg-indigo-500"></div>
            <p class="text-sm font-medium text-slate-700 capitalize">{{ (snap.step || '').replace('discipline_', '').replace('_', ' ') }}</p>
            <p class="text-xs text-slate-500">{{ snap.name_snapshot }} · {{ fmtDateTime(snap.signed_at) }}</p>
            <p v-if="snap.remarks" class="text-xs text-slate-600 mt-0.5">{{ snap.remarks }}</p>
          </li>
          <li v-if="!c.approval_snapshots?.length" class="ml-4 text-sm text-slate-400">No timeline entries.</li>
        </ol>
      </div>
    </div>
  </AdminLayout>
</template>
