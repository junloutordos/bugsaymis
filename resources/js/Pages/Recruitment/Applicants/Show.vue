<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppBreadcrumb from '@/Components/AppBreadcrumb.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppTabs from '@/Components/AppTabs.vue'
import AppModal from '@/Components/AppModal.vue'
import AppSelect from '@/Components/AppSelect.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import Swal from 'sweetalert2'
import axios from 'axios'
import { ArrowTopRightOnSquareIcon, UserIcon, DocumentTextIcon, BriefcaseIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  applicant: { type: Object, required: true },
})

const page = usePage()

// ── Tabs ────────────────────────────────────────────────────────────────────────
const activeTab = ref('profile')
const tabs = [
  { key: 'profile',      label: 'Profile',      icon: UserIcon },
  { key: 'documents',    label: 'Documents',    icon: DocumentTextIcon },
  { key: 'applications', label: 'Applications', icon: BriefcaseIcon },
]

const breadcrumbItems = computed(() => [
  { label: 'Applicant Pool', href: route('recruitment.applicants.index') },
  { label: `${props.applicant.last_name}, ${props.applicant.first_name}` },
])

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
  const confirmed = await confirmDelete('This document will be permanently removed.')
  if (!confirmed) return
  router.delete(route('recruitment.applicants.documents.destroy', [props.applicant.id, doc.id]), {
    onSuccess: () => router.reload({ only: ['applicant'] }),
  })
}

// ── Badge colors ───────────────────────────────────────────────────────────────
const statusColors = {
  active:      'green',
  blacklisted: 'red',
  hired:       'blue',
  withdrawn:   'slate',
}

function stageColor(stage) {
  const map = {
    submitted:  'slate',
    screening:  'amber',
    exam:       'amber',
    interview:  'blue',
    ranking:    'blue',
    selection:  'blue',
    placement:  'green',
    rejected:   'red',
    withdrawn:  'slate',
  }
  return map[stage] ?? 'slate'
}

const docStatusColors = {
  pending:  'amber',
  verified: 'green',
  rejected: 'red',
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

      <AppBreadcrumb :items="breadcrumbItems" />

      <!-- Flash -->
      <div v-if="page.props.flash?.success" class="px-4 py-3 rounded-lg bg-success-50 border border-success-100 text-success-700 text-sm">
        {{ page.props.flash.success }}
      </div>

      <!-- Profile Header Card -->
      <AppCard>
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
          <AppBadge :color="statusColors[applicant.status] ?? 'slate'"><span class="capitalize">{{ applicant.status }}</span></AppBadge>
        </div>
      </AppCard>

      <!-- Tabs -->
      <AppTabs :tabs="tabs" v-model="activeTab">

        <!-- ── Profile Tab ─────────────────────────────────────────────────────── -->
        <AppCard v-if="activeTab === 'profile'">
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
        </AppCard>

        <!-- ── Documents Tab ───────────────────────────────────────────────────── -->
        <AppCard v-if="activeTab === 'documents'" :padded="false">
          <template #header>
            <div class="flex items-center justify-between gap-3 w-full">
              <h3 class="text-sm font-semibold text-slate-700">Submitted Documents</h3>
              <AppButton size="sm" @click="showDocModal = true">+ Upload</AppButton>
            </div>
          </template>

          <div v-if="applicant.documents?.length" class="divide-y divide-slate-100">
            <div v-for="doc in applicant.documents" :key="doc.id"
                 class="flex items-center justify-between p-4 hover:bg-slate-50/60">
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
                <AppBadge :color="docStatusColors[doc.status] ?? 'slate'">{{ doc.status }}</AppBadge>
                <AppButton v-if="doc.status !== 'verified'" size="sm" variant="secondary" @click="verifyDoc(doc, 'verified')">Verify</AppButton>
                <AppButton v-if="doc.status === 'verified'" size="sm" variant="secondary" @click="verifyDoc(doc, 'rejected')">Unverify</AppButton>
                <AppButton size="sm" variant="danger" @click="removeDoc(doc)">Remove</AppButton>
              </div>
            </div>
          </div>
          <EmptyState v-else title="No documents uploaded yet." />
        </AppCard>

        <!-- ── Applications Tab ────────────────────────────────────────────────── -->
        <AppCard v-if="activeTab === 'applications'" :padded="false" title="Application History">
          <div v-if="applicant.applications?.length" class="divide-y divide-slate-100">
            <div v-for="app in applicant.applications" :key="app.id"
                 class="flex items-start justify-between p-4 hover:bg-slate-50/60">
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
                <AppBadge :color="stageColor(app.current_stage)"><span class="capitalize">{{ app.current_stage }}</span></AppBadge>
                <Link :href="route('recruitment.applications.show', app.id)"
                      class="text-xs text-indigo-600 hover:underline">
                  View &rarr;
                </Link>
              </div>
            </div>
          </div>
          <EmptyState v-else title="No applications on record." />
        </AppCard>
      </AppTabs>
    </div>

    <!-- ── Upload Document Modal ────────────────────────────────────────────── -->
    <AppModal :show="showDocModal" title="Upload Document" @close="showDocModal = false">
      <form @submit.prevent="submitDoc" class="space-y-4">
        <AppSelect v-model="docForm.document_type" label="Document Type" required
                   :error="docErrors.document_type" placeholder="Select type">
          <option v-for="dt in docTypes" :key="dt" :value="dt">{{ dt }}</option>
        </AppSelect>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">File * (PDF, JPG, PNG — max 5 MB)</label>
          <input ref="fileInput" type="file" accept=".pdf,.jpg,.jpeg,.png"
                 @change="onFileChange" required
                 class="w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
          <p v-if="docErrors.file" class="text-danger-600 text-xs mt-1">{{ docErrors.file }}</p>
        </div>
      </form>
      <template #footer>
        <AppButton variant="secondary" @click="showDocModal = false">Cancel</AppButton>
        <AppButton :loading="docLoading" :disabled="docLoading" @click="submitDoc">
          {{ docLoading ? 'Uploading…' : 'Upload' }}
        </AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
