<script setup>
import { ref } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { CheckCircleIcon, XCircleIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline'

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

function reopen() {
  if (confirm('Reopen this application for review?')) {
    router.post(route('registrar.enrollment-applications.reopen', props.application.id))
  }
}

function fmt(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}

const statusBadge = {
  pending:      'bg-amber-100 text-amber-700',
  under_review: 'bg-blue-100 text-blue-700',
  approved:     'bg-green-100 text-green-700',
  rejected:     'bg-red-100 text-red-700',
}
</script>

<template>
  <Head :title="`Application ${application.reference_no}`" />
  <AdminLayout :title="`Application ${application.reference_no}`">

    <div class="mb-5">
      <Link :href="route('registrar.enrollment-applications.index')"
            class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
        <ArrowLeftIcon class="h-4 w-4" /> Back to Applications
      </Link>
    </div>

    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
      <div>
        <div class="flex items-center gap-3">
          <h2 class="text-lg font-bold text-slate-800">{{ application.lastname }}, {{ application.firstname }}
            <span v-if="application.middlename" class="font-normal text-slate-400">{{ application.middlename }}</span>
          </h2>
          <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusBadge[application.status]">
            {{ application.status.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase()) }}
          </span>
        </div>
        <p class="text-sm text-slate-500 mt-1">
          Ref: <strong class="font-mono">{{ application.reference_no }}</strong> &bull;
          Grade {{ application.grade_level }} &bull;
          SY {{ application.school_year?.name ?? '—' }} &bull;
          Submitted {{ fmt(application.created_at) }}
        </p>
      </div>

      <!-- Actions -->
      <div v-if="application.status !== 'approved'" class="flex gap-2">
        <button v-if="application.status === 'rejected'"
                @click="reopen"
                class="px-4 py-2 text-sm border border-slate-200 text-slate-600 rounded-lg hover:bg-slate-50">
          Reopen
        </button>
        <button v-if="application.status !== 'rejected'"
                @click="rejectModal = true"
                class="px-4 py-2 text-sm border border-red-200 text-red-600 rounded-lg hover:bg-red-50">
          Reject
        </button>
        <button v-if="application.status !== 'rejected'"
                @click="approveModal = true"
                class="flex items-center gap-1.5 bg-green-600 hover:bg-green-700 text-white px-4 py-2 text-sm rounded-lg font-medium">
          <CheckCircleIcon class="h-4 w-4" /> Approve
        </button>
      </div>

      <!-- Approved badge -->
      <div v-else class="rounded-lg bg-green-50 border border-green-200 px-4 py-2 text-sm text-green-700">
        <CheckCircleIcon class="h-4 w-4 inline mr-1" />
        Approved — PSHS ID: <strong>{{ application.pisays_id_assigned }}</strong>
        <span v-if="application.reviewer"> by {{ application.reviewer.name }}</span>
      </div>
    </div>

    <!-- Rejection notice -->
    <div v-if="application.status === 'rejected' && application.remarks"
         class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      <XCircleIcon class="h-4 w-4 inline mr-1" />
      <strong>Rejected:</strong> {{ application.remarks }}
    </div>

    <!-- Detail grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

      <!-- Personal Info -->
      <div class="rounded-xl border border-slate-200 bg-white p-5">
        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Personal Information</h3>
        <dl class="space-y-2 text-sm">
          <div class="flex justify-between"><dt class="text-slate-400">Birthday</dt><dd>{{ application.birthday || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Sex</dt><dd>{{ application.sex || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Birth Place</dt><dd>{{ application.birth_place || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">LRN</dt><dd class="font-mono">{{ application.lrn || '—' }}</dd></div>
        </dl>
      </div>

      <!-- Previous School -->
      <div class="rounded-xl border border-slate-200 bg-white p-5">
        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Previous School</h3>
        <dl class="space-y-2 text-sm">
          <div class="flex justify-between"><dt class="text-slate-400">School</dt><dd class="text-right max-w-[60%]">{{ application.previous_school || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Address</dt><dd class="text-right max-w-[60%]">{{ application.previous_school_address || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Grade Completed</dt><dd>{{ application.grade_level_completed || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">SY Completed</dt><dd>{{ application.school_year_completed || '—' }}</dd></div>
        </dl>
      </div>

      <!-- Contact -->
      <div class="rounded-xl border border-slate-200 bg-white p-5">
        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Contact & Address</h3>
        <dl class="space-y-2 text-sm">
          <div class="flex justify-between"><dt class="text-slate-400">Email</dt><dd>{{ application.email || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Contact No.</dt><dd>{{ application.contact_no || '—' }}</dd></div>
          <div><dt class="text-slate-400 mb-1">Address</dt><dd class="text-slate-700">{{ application.address || '—' }}</dd></div>
        </dl>
      </div>

      <!-- Family -->
      <div class="rounded-xl border border-slate-200 bg-white p-5">
        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Parent / Guardian</h3>
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
      </div>

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
              Assign PSHS ID (pisaysystemID) <span class="text-red-500">*</span>
            </label>
            <input v-model="pisaysId"
                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 w-full font-mono"
                   placeholder="e.g. CRC-2026-001" />
            <p v-if="error" class="text-xs text-red-500 mt-1">{{ error }}</p>
          </div>
          <div class="flex gap-3 justify-end">
            <button @click="approveModal = false; error = ''"
                    class="px-4 py-2 text-sm border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50">
              Cancel
            </button>
            <button @click="approve" :disabled="submitting"
                    class="flex items-center gap-1.5 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white px-4 py-2 text-sm rounded-lg font-medium">
              <CheckCircleIcon class="h-4 w-4" />
              {{ submitting ? 'Processing…' : 'Confirm Approval' }}
            </button>
          </div>
        </div>
      </div>

      <div v-if="rejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm mx-4">
          <h3 class="text-base font-semibold text-slate-800 mb-4">Reject Application</h3>
          <div class="mb-4">
            <label class="block text-xs font-medium text-slate-600 mb-1">
              Reason for rejection <span class="text-red-500">*</span>
            </label>
            <textarea v-model="rejectReason" rows="4"
                      class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 w-full"
                      placeholder="Explain the reason…" />
            <p v-if="error" class="text-xs text-red-500 mt-1">{{ error }}</p>
          </div>
          <div class="flex gap-3 justify-end">
            <button @click="rejectModal = false; error = ''"
                    class="px-4 py-2 text-sm border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50">
              Cancel
            </button>
            <button @click="reject" :disabled="submitting"
                    class="flex items-center gap-1.5 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white px-4 py-2 text-sm rounded-lg font-medium">
              <XCircleIcon class="h-4 w-4" />
              {{ submitting ? 'Processing…' : 'Confirm Rejection' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>
