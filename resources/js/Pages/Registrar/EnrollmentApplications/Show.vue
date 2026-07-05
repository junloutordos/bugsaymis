<script setup>
import { ref } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import { confirmAction } from '@/Composables/useConfirm.js'
import { CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  application: Object,
})

const approveModal = ref(false)
const rejectModal  = ref(false)
const pisaysId     = ref('')
const rejectReason = ref('')
const submitting   = ref(false)
const error        = ref('')

function approve() {
  error.value = ''
  if (!pisaysId.value.trim()) { error.value = 'PISAY ID is required.'; return }
  submitting.value = true
  router.post(
    route('registrar.enrollment-applications.approve', props.application.id),
    { pisays_id: pisaysId.value.trim() },
    {
      onError:  (e) => { error.value = Object.values(e)[0] ?? 'Error.'; submitting.value = false },
      onFinish: () => { submitting.value = false; approveModal.value = false },
    }
  )
}

function reject() {
  error.value = ''
  if (!rejectReason.value.trim()) { error.value = 'Reason is required.'; return }
  submitting.value = true
  router.post(
    route('registrar.enrollment-applications.reject', props.application.id),
    { remarks: rejectReason.value.trim() },
    {
      onError:  (e) => { error.value = Object.values(e)[0] ?? 'Error.'; submitting.value = false },
      onFinish: () => { submitting.value = false; rejectModal.value = false },
    }
  )
}

async function reopen() {
  if (await confirmAction({ title: 'Reopen application?', text: 'Reopen this application for review?', confirmText: 'Reopen' })) {
    router.post(route('registrar.enrollment-applications.reopen', props.application.id))
  }
}

function fmt(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}

function statusColor(status) {
  const map = { pending: 'amber', under_review: 'blue', approved: 'green', rejected: 'red' }
  return map[status] ?? 'slate'
}
const statusLabel = {
  pending:      'Pending',
  under_review: 'Under Review',
  approved:     'Approved',
  rejected:     'Rejected',
}
</script>

<template>
  <Head :title="`Application ${application.reference_no}`" />
  <AdminLayout :title="`Application ${application.reference_no}`">

    <AppPageHeader
      :title="`${application.lastname}, ${application.firstname}`"
      :breadcrumb="[
        { label: 'Enrollment Applications', href: route('registrar.enrollment-applications.index') },
        { label: `Application ${application.reference_no}` },
      ]"
    >
      <template #actions>
        <template v-if="application.status !== 'approved'">
          <AppButton v-if="application.status === 'rejected'" variant="secondary" @click="reopen">Reopen</AppButton>
          <AppButton v-if="application.status !== 'rejected'" variant="danger" @click="rejectModal = true">Reject</AppButton>
          <AppButton v-if="application.status !== 'rejected'" variant="success" @click="approveModal = true">
            <CheckCircleIcon class="h-4 w-4" /> Approve
          </AppButton>
        </template>
      </template>
    </AppPageHeader>

    <!-- Status + meta line -->
    <div class="flex flex-wrap items-center gap-3 -mt-2 mb-5">
      <AppBadge :color="statusColor(application.status)">{{ statusLabel[application.status] }}</AppBadge>
      <p class="text-sm text-slate-500">
        Grade {{ application.grade_level }} &bull;
        SY {{ application.school_year?.name ?? '—' }} &bull;
        Submitted {{ fmt(application.created_at) }}
      </p>
    </div>

    <!-- Approved notice -->
    <div v-if="application.status === 'approved'" class="mb-5 rounded-lg bg-success-50 border border-success-100 text-success-700 px-4 py-3 text-sm">
      <CheckCircleIcon class="h-4 w-4 inline mr-1" />
      Approved — PSHS ID: <strong>{{ application.pisays_id_assigned }}</strong>
      <span v-if="application.reviewer"> by {{ application.reviewer.name }}</span>
    </div>

    <!-- Rejection notice -->
    <div v-if="application.status === 'rejected' && application.remarks"
         class="mb-5 rounded-lg border border-danger-100 bg-danger-50 px-4 py-3 text-sm text-danger-600">
      <XCircleIcon class="h-4 w-4 inline mr-1" />
      <strong>Rejected:</strong> {{ application.remarks }}
    </div>

    <!-- Detail grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

      <!-- Personal Info -->
      <AppCard title="Personal Information">
        <dl class="space-y-2 text-sm">
          <div class="flex justify-between"><dt class="text-slate-400">Birthday</dt><dd>{{ application.birthday || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Sex</dt><dd>{{ application.sex || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Birth Place</dt><dd>{{ application.birth_place || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">LRN</dt><dd class="font-mono">{{ application.lrn || '—' }}</dd></div>
        </dl>
      </AppCard>

      <!-- Previous School -->
      <AppCard title="Previous School">
        <dl class="space-y-2 text-sm">
          <div class="flex justify-between"><dt class="text-slate-400">School</dt><dd class="text-right max-w-[60%]">{{ application.previous_school || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Address</dt><dd class="text-right max-w-[60%]">{{ application.previous_school_address || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Grade Completed</dt><dd>{{ application.grade_level_completed || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">SY Completed</dt><dd>{{ application.school_year_completed || '—' }}</dd></div>
        </dl>
      </AppCard>

      <!-- Contact -->
      <AppCard title="Contact & Address">
        <dl class="space-y-2 text-sm">
          <div class="flex justify-between"><dt class="text-slate-400">Email</dt><dd>{{ application.email || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Contact No.</dt><dd>{{ application.contact_no || '—' }}</dd></div>
          <div><dt class="text-slate-400 mb-1">Address</dt><dd class="text-slate-700">{{ application.address || '—' }}</dd></div>
        </dl>
      </AppCard>

      <!-- Family -->
      <AppCard title="Parent / Guardian">
        <div class="space-y-3 text-sm">
          <div v-if="application.father_name">
            <p class="text-xs text-slate-400 mb-0.5">Father</p>
            <p class="font-medium">{{ application.father_name }}</p>
            <p class="text-slate-500">{{ application.father_occupation || '—' }} · {{ application.father_contact || '—' }}</p>
          </div>
          <div v-if="application.mother_name">
            <p class="text-xs text-slate-400 mb-0.5">Mother</p>
            <p class="font-medium">{{ application.mother_name }}</p>
            <p class="text-slate-500">{{ application.mother_occupation || '—' }} · {{ application.mother_contact || '—' }}</p>
          </div>
          <div v-if="application.guardian_name">
            <p class="text-xs text-slate-400 mb-0.5">Guardian ({{ application.guardian_relationship }})</p>
            <p class="font-medium">{{ application.guardian_name }}</p>
            <p class="text-slate-500">{{ application.guardian_contact || '—' }}</p>
          </div>
          <p v-if="!application.father_name && !application.mother_name && !application.guardian_name"
             class="text-slate-400">No parent/guardian information provided.</p>
        </div>
      </AppCard>

    </div>

    <!-- Approve Modal -->
    <Teleport to="body">
      <div v-if="approveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm mx-4">
          <h3 class="text-base font-semibold text-slate-800 mb-4">Approve Enrollment</h3>
          <p class="text-sm text-slate-500 mb-4">
            Approving will create a student record and enrollment stub for
            <strong>{{ application.firstname }} {{ application.lastname }}</strong>.
          </p>
          <div class="mb-4">
            <label class="block text-xs font-medium text-slate-600 mb-1">
              Assign PSHS ID (pisaysystemID) <span class="text-danger-500">*</span>
            </label>
            <input v-model="pisaysId"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-success-500 w-full font-mono"
                   placeholder="e.g. CRC-2026-001" />
            <p v-if="error" class="text-xs text-danger-500 mt-1">{{ error }}</p>
          </div>
          <div class="flex gap-3 justify-end">
            <AppButton variant="secondary" @click="approveModal = false; error = ''">Cancel</AppButton>
            <AppButton variant="success" :loading="submitting" :disabled="submitting" @click="approve">
              <CheckCircleIcon class="h-4 w-4" />
              {{ submitting ? 'Processing…' : 'Confirm Approval' }}
            </AppButton>
          </div>
        </div>
      </div>

      <div v-if="rejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm mx-4">
          <h3 class="text-base font-semibold text-slate-800 mb-4">Reject Application</h3>
          <div class="mb-4">
            <label class="block text-xs font-medium text-slate-600 mb-1">
              Reason for rejection <span class="text-danger-500">*</span>
            </label>
            <textarea v-model="rejectReason" rows="4"
                      class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-danger-400 w-full"
                      placeholder="Explain the reason…" />
            <p v-if="error" class="text-xs text-danger-500 mt-1">{{ error }}</p>
          </div>
          <div class="flex gap-3 justify-end">
            <AppButton variant="secondary" @click="rejectModal = false; error = ''">Cancel</AppButton>
            <AppButton variant="danger" :loading="submitting" :disabled="submitting" @click="reject">
              <XCircleIcon class="h-4 w-4" />
              {{ submitting ? 'Processing…' : 'Confirm Rejection' }}
            </AppButton>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>
