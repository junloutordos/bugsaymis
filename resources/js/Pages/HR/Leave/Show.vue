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
        <span :class="statusClass(application.status)"
              class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold">
          {{ statusLabel(application.status) }}
        </span>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.error }}
      </div>

      <!-- Details Card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6 space-y-4">
        <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide">Application Details</h2>
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
            <p class="text-slate-600 text-xs">📎 Document attached</p>
          </div>
        </div>
      </div>

      <!-- Approval Timeline -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6 space-y-4">
        <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wide">Approval History</h2>

        <!-- Filed -->
        <div class="flex items-start gap-3">
          <div class="w-7 h-7 rounded-full bg-slate-200 flex items-center justify-center text-xs font-bold text-slate-600 shrink-0">1</div>
          <div>
            <p class="text-sm font-medium text-slate-700">Filed by {{ application.user?.name }}</p>
            <p class="text-xs text-slate-400">{{ fmtDate(application.filed_at) }}</p>
          </div>
        </div>

        <!-- Division Chief -->
        <div class="flex items-start gap-3">
          <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
               :class="application.division_chief_id ? 'bg-indigo-100 text-indigo-600' : 'bg-slate-100 text-slate-400'">2</div>
          <div>
            <p class="text-sm font-medium text-slate-700">Division Chief Review</p>
            <template v-if="application.division_chief_id">
              <p class="text-xs text-slate-600">
                <span :class="actionClass(application.division_chief_action)" class="font-semibold capitalize">
                  {{ application.division_chief_action }}
                </span>
                by {{ application.division_chief?.name }} &bull; {{ fmtDate(application.division_chief_at) }}
              </p>
              <p v-if="application.division_chief_remarks" class="text-xs text-slate-400 mt-0.5 italic">"{{ application.division_chief_remarks }}"</p>
            </template>
            <p v-else class="text-xs text-slate-400">Awaiting review</p>
          </div>
        </div>

        <!-- HR Final -->
        <div class="flex items-start gap-3">
          <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
               :class="application.approved_by ? 'bg-indigo-100 text-indigo-600' : 'bg-slate-100 text-slate-400'">3</div>
          <div>
            <p class="text-sm font-medium text-slate-700">HR Final Approval</p>
            <template v-if="application.approved_by">
              <p class="text-xs text-slate-600">
                <span :class="actionClass(application.approval_action)" class="font-semibold capitalize">
                  {{ application.approval_action }}
                </span>
                by {{ application.approved_by_user?.name ?? application.approved_by }} &bull; {{ fmtDate(application.approved_at) }}
              </p>
              <p v-if="application.approval_remarks" class="text-xs text-slate-400 mt-0.5 italic">"{{ application.approval_remarks }}"</p>
            </template>
            <p v-else class="text-xs text-slate-400">
              {{ application.status === 'forwarded' ? 'Awaiting HR approval' : 'Pending' }}
            </p>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex gap-3 flex-wrap">
        <!-- Cancel (owner, pending/forwarded only) -->
        <button v-if="canCancel"
                @click="cancelApp"
                class="px-4 py-2 text-sm border border-red-200 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
          Cancel Application
        </button>

        <!-- Approve/Reject (Division Chief or HR) -->
        <button v-if="canApprove && ['pending','forwarded'].includes(application.status)"
                @click="approveModal = true"
                class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors font-medium">
          Review Application
        </button>

        <!-- Print CS Form 6 -->
        <a :href="route('hr.leave.print', application.id)"
           target="_blank"
           class="inline-flex items-center gap-2 px-4 py-2 text-sm border border-slate-200 text-slate-600 hover:bg-slate-50 rounded-lg transition-colors font-medium">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.75 19.817m0 0a2.25 2.25 0 01-2.244-2.077L3 7.5M6.75 19.817l-1.66-1.24M17.25 19.817l1.66-1.24M17.25 19.817A2.25 2.25 0 0019.494 17.74l1.506-9.746M3 7.5h18M3 7.5l.89-5.5h16.22L21 7.5" />
          </svg>
          Print CS Form 6
        </a>
      </div>

      <!-- Approve Modal -->
      <Teleport to="body">
        <div v-if="approveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
          <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md mx-4">
            <h3 class="text-base font-semibold text-slate-800 mb-4">Review Leave Application</h3>

            <!-- Stage selection -->
            <div class="mb-4">
              <label class="block text-xs font-medium text-slate-600 mb-1">Review Stage</label>
              <select v-model="approveForm.stage"
                      class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <option value="division_chief">Division Chief</option>
                <option value="hr">HR Final Approval</option>
              </select>
            </div>

            <!-- Action -->
            <div class="mb-4">
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
            <div class="mb-4">
              <label class="block text-xs font-medium text-slate-600 mb-1">Remarks <span class="font-normal text-slate-400">(optional)</span></label>
              <textarea v-model="approveForm.remarks" rows="3"
                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"></textarea>
            </div>

            <div class="flex gap-3 justify-end">
              <button @click="approveModal = false"
                      class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
              <button @click="submitApprove" :disabled="approveForm.processing"
                      class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg transition-colors">
                {{ approveForm.processing ? 'Saving…' : 'Submit Review' }}
              </button>
            </div>
          </div>
        </div>
      </Teleport>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  application: Object,
})

const page = usePage()
const me = page.props.auth?.user

const canApprove = me?.permissions?.includes('hr.leave.approve')
const canCancel  = computed(() =>
  props.application.user_id === me?.id &&
  ['pending', 'forwarded'].includes(props.application.status)
)

// Approve modal
const approveModal = ref(false)
const approveForm  = useForm({
  stage:   props.application.status === 'forwarded' ? 'hr' : 'division_chief',
  action:  'forwarded',
  remarks: '',
})

const actionOptions = computed(() => {
  if (approveForm.stage === 'division_chief') {
    return [
      { value: 'forwarded', label: 'Forward to HR' },
      { value: 'rejected',  label: 'Reject' },
    ]
  }
  return [
    { value: 'approved', label: 'Approve' },
    { value: 'rejected', label: 'Reject' },
  ]
})

function submitApprove() {
  approveForm.post(route('hr.leave.approve', props.application.id), {
    onSuccess: () => { approveModal.value = false },
  })
}

function cancelApp() {
  if (confirm('Cancel this leave application?')) {
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

function statusClass(s) {
  const map = {
    pending:   'bg-amber-100 text-amber-700',
    forwarded: 'bg-blue-100 text-blue-700',
    approved:  'bg-emerald-100 text-emerald-700',
    rejected:  'bg-red-100 text-red-600',
    cancelled: 'bg-slate-100 text-slate-500',
  }
  return map[s] ?? 'bg-slate-100 text-slate-500'
}

function statusLabel(s) {
  const map = { pending: 'Pending', forwarded: 'For HR Approval', approved: 'Approved', rejected: 'Rejected', cancelled: 'Cancelled' }
  return map[s] ?? s
}

function actionClass(a) {
  return a === 'approved' || a === 'forwarded' ? 'text-emerald-600' : 'text-red-500'
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>
