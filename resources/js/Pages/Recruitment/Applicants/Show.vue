<script setup>
import { ref } from 'vue'
import { Head, router, usePage, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'
import { statusBadgeClass, badgeBase } from '@/Composables/useStatusBadge.js'
import axios from 'axios'
import { ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  applicant: { type: Object, required: true },
})

const page = usePage()

// ── Tabs ────────────────────────────────────────────────────────────────────────
const activeTab = ref('profile')

// ── Document upload ────────────────────────────────────────────────────────────
const showDocModal  = ref(false)
const docForm       = ref({ document_type: '', file: null })
const docErrors     = ref({})
const docLoading    = ref(false)

const fileInput = ref(null)

const onFileChange = (e) => { docForm.value.file = e.target.files[0] }

const submitDoc = async () => {
  docLoading.value = true
  docErrors.value  = {}

  const fd = new FormData()
  fd.append('document_type', docForm.value.document_type)
  fd.append('file', docForm.value.file)

  try {
    await axios.post(route('recruitment.applicants.documents.upload', props.applicant.id), fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    showDocModal.value = false
    docForm.value = { document_type: '', file: null }
    router.reload({ only: ['applicant'] })
    Swal.fire({ icon: 'success', title: 'Document uploaded!', timer: 1500, showConfirmButton: false })
  } catch (err) {
    docErrors.value = err.response?.data?.errors ?? {}
    if (!Object.keys(docErrors.value).length) {
      Swal.fire('Error', err.response?.data?.message ?? 'Upload failed.', 'error')
    }
  } finally {
    docLoading.value = false
  }
}

// ── Verify / remove document ───────────────────────────────────────────────────
const verifyDoc = (doc, status) => {
  router.patch(route('recruitment.applicants.documents.verify', [props.applicant.id, doc.id]), { status }, {
    onSuccess: () => router.reload({ only: ['applicant'] }),
  })
}

const removeDoc = async (doc) => {
  const res = await Swal.fire({
    title: 'Remove document?', icon: 'warning',
    showCancelButton: true, confirmButtonColor: '#ef4444',
    confirmButtonText: 'Remove', reverseButtons: true,
  })
  if (!res.isConfirmed) return
  router.delete(route('recruitment.applicants.documents.destroy', [props.applicant.id, doc.id]), {
    onSuccess: () => router.reload({ only: ['applicant'] }),
  })
}

// ── Stage badge ────────────────────────────────────────────────────────────────
const stageColors = {
  submitted:  'bg-gray-100 text-gray-600',
  screening:  'bg-yellow-100 text-yellow-700',
  exam:       'bg-orange-100 text-orange-700',
  interview:  'bg-purple-100 text-purple-700',
  ranking:    'bg-blue-100 text-blue-700',
  selection:  'bg-indigo-100 text-indigo-700',
  placement:  'bg-green-100 text-green-700',
  rejected:   'bg-red-100 text-red-600',
  withdrawn:  'bg-gray-100 text-gray-400',
}

const docStatusColors = {
  pending:  'bg-amber-50 text-amber-700',
  verified: 'bg-emerald-50 text-emerald-700',
  rejected: 'bg-red-50 text-red-600',
}

const formatDate = (iso) => iso ? new Date(iso).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'

const formatDateTime = (iso) => iso ? new Date(iso).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—'

const docTypes = [
  'Resume / Application Letter', 'Transcript of Records', 'Diploma',
  'PRC License', 'CSC Eligibility Certificate', 'NBI Clearance',
  'Medical Certificate', 'SALN', 'Birth Certificate', 'Marriage Certificate',
  'Service Record', 'Training Certificates', 'Other',
]
</script>

<template>
  <Head :title="`${applicant.last_name}, ${applicant.first_name} — Applicants`" />
  <AdminLayout :title="`${applicant.last_name}, ${applicant.first_name}`">
    <div class="max-w-5xl mx-auto space-y-4">

      <!-- Flash -->
      <div v-if="page.props.flash?.success" class="px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-700 text-sm">
        {{ page.props.flash.success }}
      </div>

      <!-- Back -->
      <Link :href="route('recruitment.applicants.index')"
            class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800">
        &larr; Back to Applicant Pool
      </Link>

      <!-- Profile Header Card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 class="text-xl font-semibold text-slate-800">
              {{ applicant.last_name }}, {{ applicant.first_name }}
              <span v-if="applicant.middle_name" class="text-slate-500"> {{ applicant.middle_name[0] }}.</span>
              <span v-if="applicant.suffix" class="text-slate-400 text-lg"> {{ applicant.suffix }}</span>
            </h1>
            <div class="mt-1 flex flex-wrap gap-3 text-sm text-slate-500">
              <span>{{ applicant.email }}</span>
              <span v-if="applicant.contact_number">· {{ applicant.contact_number }}</span>
              <span v-if="applicant.civil_status">· {{ applicant.civil_status }}</span>
            </div>
          </div>
          <span :class="[badgeBase, statusBadgeClass(applicant.status), 'capitalize']">{{ applicant.status }}</span>
        </div>
      </div>

      <!-- Tabs -->
      <div class="flex border-b border-slate-200 bg-white rounded-t-xl border border-slate-100 shadow-sm px-4 pt-3 gap-1">
        <button v-for="tab in ['profile','documents','applications']" :key="tab"
                @click="activeTab = tab"
                class="px-4 py-2 text-sm font-medium capitalize rounded-t-lg transition"
                :class="activeTab === tab
                  ? 'bg-white border border-b-white border-slate-200 text-indigo-600 -mb-px'
                  : 'text-slate-500 hover:text-slate-700'">
          {{ tab }}
        </button>
      </div>

      <!-- ── Profile Tab ─────────────────────────────────────────────────────── -->
      <div v-if="activeTab === 'profile'" class="bg-white rounded-b-xl border border-slate-100 shadow-sm p-6 space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
          <div>
            <span class="text-slate-400 text-xs uppercase tracking-wide block mb-0.5">Birthdate</span>
            <span class="text-slate-800">{{ formatDate(applicant.birthdate) }}</span>
          </div>
          <div>
            <span class="text-slate-400 text-xs uppercase tracking-wide block mb-0.5">Address</span>
            <span class="text-slate-800">{{ applicant.address ?? '—' }}</span>
          </div>
          <div>
            <span class="text-slate-400 text-xs uppercase tracking-wide block mb-0.5">CSC Eligibility</span>
            <span class="text-slate-800 font-medium">{{ applicant.eligibility ?? '—' }}</span>
          </div>
          <div>
            <span class="text-slate-400 text-xs uppercase tracking-wide block mb-0.5">PRC License No.</span>
            <span class="text-slate-800 font-medium">{{ applicant.prc_license_no ?? '—' }}</span>
          </div>
          <div>
            <span class="text-slate-400 text-xs uppercase tracking-wide block mb-0.5">School</span>
            <span class="text-slate-800">{{ applicant.school ?? '—' }}</span>
          </div>
          <div>
            <span class="text-slate-400 text-xs uppercase tracking-wide block mb-0.5">Course / Degree</span>
            <span class="text-slate-800">{{ applicant.course ?? '—' }}</span>
          </div>
          <div>
            <span class="text-slate-400 text-xs uppercase tracking-wide block mb-0.5">Year Graduated</span>
            <span class="text-slate-800">{{ applicant.year_graduated ?? '—' }}</span>
          </div>
          <div>
            <span class="text-slate-400 text-xs uppercase tracking-wide block mb-0.5">Source</span>
            <span class="text-slate-800">{{ applicant.source ?? '—' }}</span>
          </div>
        </div>
      </div>

      <!-- ── Documents Tab ───────────────────────────────────────────────────── -->
      <div v-else-if="activeTab === 'documents'" class="bg-white rounded-b-xl border border-slate-100 shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-semibold text-slate-700">Submitted Documents</h3>
          <button @click="showDocModal = true"
                  class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm">
            + Upload
          </button>
        </div>

        <div v-if="applicant.documents?.length" class="space-y-2">
          <div v-for="doc in applicant.documents" :key="doc.id"
               class="flex items-center justify-between p-3 rounded-lg border border-slate-100 hover:bg-slate-50/60">
            <div>
              <div class="text-sm font-medium text-slate-800">{{ doc.document_type }}</div>
              <div class="text-xs text-slate-400 mt-0.5">Uploaded {{ formatDate(doc.created_at) }}</div>
              <div v-if="doc.remarks" class="text-xs text-slate-500 mt-0.5">{{ doc.remarks }}</div>
            </div>
            <div class="flex items-center gap-2">
              <a v-if="doc.drive_url" :href="doc.drive_url" target="_blank" rel="noopener"
                 class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                View <ArrowTopRightOnSquareIcon class="h-3.5 w-3.5" />
              </a>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium" :class="docStatusColors[doc.status]">
                {{ doc.status }}
              </span>
              <button v-if="doc.status !== 'verified'" @click="verifyDoc(doc, 'verified')"
                      class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-2 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">
                Verify
              </button>
              <button v-if="doc.status === 'verified'" @click="verifyDoc(doc, 'rejected')"
                      class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-2 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">
                Unverify
              </button>
              <button @click="removeDoc(doc)"
                      class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded-lg text-xs font-medium transition-colors shadow-sm">
                Remove
              </button>
            </div>
          </div>
        </div>
        <div v-else class="py-16 text-center text-slate-400 text-sm">No documents uploaded yet.</div>
      </div>

      <!-- ── Applications Tab ────────────────────────────────────────────────── -->
      <div v-else-if="activeTab === 'applications'" class="bg-white rounded-b-xl border border-slate-100 shadow-sm p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Application History</h3>

        <div v-if="applicant.applications?.length" class="space-y-3">
          <div v-for="app in applicant.applications" :key="app.id"
               class="flex items-start justify-between p-4 rounded-lg border border-slate-100 hover:bg-slate-50/60">
            <div class="flex-1">
              <div class="font-medium text-slate-800 text-sm">
                {{ app.job_vacancy?.job_item?.position_title ?? '—' }}
              </div>
              <div class="text-xs text-slate-400 mt-0.5">
                {{ app.job_vacancy?.job_item?.recruitment_type?.name ?? '—' }}
                · Applied {{ formatDateTime(app.created_at) }}
              </div>
              <div v-if="app.ranking_summary?.rank" class="text-xs text-slate-500 mt-1">
                Rank #{{ app.ranking_summary.rank }}
                · Score: {{ parseFloat(app.ranking_summary.total_score).toFixed(2) }}
              </div>
            </div>
            <div class="ml-4 flex flex-col items-end gap-1">
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium capitalize" :class="stageColors[app.current_stage]">
                {{ app.current_stage }}
              </span>
              <Link :href="route('recruitment.applications.show', app.id)"
                    class="text-xs text-indigo-600 hover:underline">
                View &rarr;
              </Link>
            </div>
          </div>
        </div>
        <div v-else class="py-16 text-center text-slate-400 text-sm">No applications on record.</div>
      </div>
    </div>

    <!-- ── Upload Document Modal ────────────────────────────────────────────── -->
    <div v-if="showDocModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
      <div class="bg-white rounded-2xl w-full max-w-md shadow-xl relative">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">Upload Document</h2>
          <button @click="showDocModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
        </div>

        <form @submit.prevent="submitDoc" class="px-6 py-5 space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Document Type *</label>
            <select v-model="docForm.document_type" required
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="" disabled>Select type</option>
              <option v-for="dt in docTypes" :key="dt" :value="dt">{{ dt }}</option>
            </select>
            <p v-if="docErrors.document_type" class="text-red-500 text-xs mt-1">{{ docErrors.document_type }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">File * (PDF, JPG, PNG — max 5 MB)</label>
            <input ref="fileInput" type="file" accept=".pdf,.jpg,.jpeg,.png"
                   @change="onFileChange" required
                   class="w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
            <p v-if="docErrors.file" class="text-red-500 text-xs mt-1">{{ docErrors.file }}</p>
          </div>

          <div class="flex justify-end gap-3 pt-2 border-t border-slate-100">
            <button type="button" @click="showDocModal = false"
                    class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
              Cancel
            </button>
            <button type="submit" :disabled="docLoading"
                    class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
              {{ docLoading ? 'Uploading…' : 'Upload' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
