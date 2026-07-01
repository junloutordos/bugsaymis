<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { CheckCircleIcon, XCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'

const props = defineProps({
  records: Object,
  filters: Object,
})

const search       = ref(props.filters?.search ?? '')
const status       = ref(props.filters?.status ?? 'pending')
const isLoading    = ref(false)
const isSubmitting = ref(false)

// ── Reject modal ──────────────────────────────────────────────────────────────
const showRejectModal = ref(false)
const selectedRecord  = ref(null)
const rejectRemarks   = ref('')

// ── Status filter / search ────────────────────────────────────────────────────
const statusOptions = [
  { label: 'Pending',  value: 'pending'  },
  { label: 'Approved', value: 'approved' },
  { label: 'Rejected', value: 'rejected' },
  { label: 'Consumed', value: 'consumed' },
]

const applyFilters = () => {
  isLoading.value = true
  router.get(route('hr.leave-credits.service-credits'), {
    search: search.value || undefined,
    status: status.value || undefined,
  }, {
    preserveState: true, replace: true,
    only: ['records', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

const clearFilters = () => {
  search.value = ''
  status.value = 'pending'
  isLoading.value = true
  router.get(route('hr.leave-credits.service-credits'), { status: status.value }, {
    preserveState: true, replace: true,
    only: ['records', 'filters'],
    onFinish: () => { isLoading.value = false },
  })
}

// ── Approve ───────────────────────────────────────────────────────────────────
const approve = async (record) => {
  const res = await Swal.fire({
    title: 'Approve this service credit?',
    html: `<b>${record.user?.name}</b> — ${Number(record.days_equivalent).toFixed(2)} day(s) on ${fmtDate(record.service_date)}`,
    icon: 'question', showCancelButton: true,
    confirmButtonText: 'Yes, approve', reverseButtons: true,
  })
  if (!res.isConfirmed) return

  isSubmitting.value = true
  Swal.fire({ title: 'Approving…', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() })
  router.post(route('hr.leave-credits.service-credits.approve', record.id), {}, {
    onSuccess: () => Swal.fire('Approved!', 'Service credit approved.', 'success'),
    onError:   (e) => Swal.fire('Error', Object.values(e)[0], 'error'),
    onFinish:  ()  => { isSubmitting.value = false },
  })
}

// ── Reject ────────────────────────────────────────────────────────────────────
const openReject  = (record) => { selectedRecord.value = record; rejectRemarks.value = ''; showRejectModal.value = true }
const closeReject = () => { showRejectModal.value = false; selectedRecord.value = null }

const submitReject = () => {
  if (!rejectRemarks.value.trim()) return Swal.fire('Remarks Required', 'Enter a reason for rejection.', 'warning')
  isSubmitting.value = true
  router.post(route('hr.leave-credits.service-credits.reject', selectedRecord.value.id), { remarks: rejectRemarks.value }, {
    onSuccess: () => { Swal.fire('Rejected', 'Service credit rejected.', 'info'); closeReject() },
    onError:   (e) => Swal.fire('Error', Object.values(e)[0], 'error'),
    onFinish:  ()  => { isSubmitting.value = false },
  })
}

// ── Pagination ────────────────────────────────────────────────────────────────
const pageData   = computed(() => props.records?.data ?? [])
const totalPages = computed(() => props.records?.last_page ?? 1)
const curPage    = computed(() => props.records?.current_page ?? 1)

const goToPage = (p) => {
  isLoading.value = true
  router.get(route('hr.leave-credits.service-credits'), {
    search: search.value || undefined, status: status.value || undefined, page: p,
  }, {
    preserveState: true, replace: true,
    only: ['records'],
    onFinish: () => { isLoading.value = false },
  })
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const serviceTypeLabel = (t) => ({
  extra_teaching_load: 'Extra Teaching Load',
  committee_work:      'Committee Work',
  school_activity:     'School Activity',
  special_assignment:  'Special Assignment',
  other:               'Other',
})[t] ?? t

const statusClass = (s) => ({
  pending:  'bg-amber-100 text-amber-700',
  approved: 'bg-emerald-100 text-emerald-700',
  rejected: 'bg-red-100 text-red-700',
  consumed: 'bg-blue-100 text-blue-700',
  expired:  'bg-slate-100 text-slate-500',
})[s] ?? 'bg-slate-100 text-slate-600'

const fmtDate = (d) => {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<template>
  <Head title="Service Credit Records" />
  <AdminLayout title="Service Credit Records">
    <div class="space-y-5">

      <div>
        <h1 class="text-xl font-semibold text-slate-800">Service Credit Records</h1>
        <p class="text-sm text-slate-500 mt-0.5">
          Review and approve service credit earning records submitted by Teaching personnel.
          To initialize existing records, use
          <a :href="route('hr.leave-credits.initialize')" class="text-indigo-600 underline hover:text-indigo-800">Initialize Leave Credits</a>.
        </p>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm">{{ $page.props.flash.success }}</div>
      <div v-if="$page.props.flash?.error"   class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">{{ $page.props.flash.error }}</div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-wrap items-center gap-3">
        <select v-model="status"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>
        <div class="flex flex-wrap gap-2">
          <input v-model="search" type="text" placeholder="Search employee…"
                 @keydown.enter.prevent="applyFilters"
                 class="px-3 py-2 text-sm border border-slate-200 rounded-lg w-48 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          <button @click="applyFilters" :disabled="isLoading"
                  class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">Search</button>
          <button v-if="search || status !== 'pending'" @click="clearFilters" :disabled="isLoading"
                  class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">Clear</button>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Employee</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Service Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Hours</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Days Equiv.</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Expires</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="rec in pageData" :key="rec.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 font-medium text-slate-800">{{ rec.user?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-600">{{ fmtDate(rec.service_date) }}</td>
                <td class="px-4 py-3 text-slate-600">{{ serviceTypeLabel(rec.service_type) }}</td>
                <td class="px-4 py-3 text-right text-slate-600">{{ rec.hours_rendered }}h</td>
                <td class="px-4 py-3 text-right font-semibold text-slate-800">{{ Number(rec.days_equivalent).toFixed(2) }}</td>
                <td class="px-4 py-3 text-slate-500 text-xs">{{ rec.expires_at ? fmtDate(rec.expires_at) : '—' }}</td>
                <td class="px-4 py-3">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded text-xs font-medium capitalize', statusClass(rec.status)]">
                    {{ rec.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div v-if="rec.status === 'pending'" class="flex items-center justify-center gap-1.5">
                    <button @click="approve(rec)" :disabled="isSubmitting"
                            class="inline-flex items-center gap-1 bg-emerald-600 hover:bg-emerald-700 text-white px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors disabled:opacity-50">
                      <CheckCircleIcon class="w-3.5 h-3.5" /> Approve
                    </button>
                    <button @click="openReject(rec)" :disabled="isSubmitting"
                            class="inline-flex items-center gap-1 bg-red-600 hover:bg-red-700 text-white px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors disabled:opacity-50">
                      <XCircleIcon class="w-3.5 h-3.5" /> Reject
                    </button>
                  </div>
                  <span v-else class="text-slate-400 text-xs">—</span>
                </td>
              </tr>
              <tr v-if="pageData.length === 0">
                <td colspan="8" class="py-16 text-center text-slate-400 text-sm">No records found for the selected status.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ curPage }} of {{ totalPages }}</span>
          <div class="flex gap-2">
            <button @click="goToPage(curPage-1)" :disabled="curPage===1||isLoading" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-sm disabled:opacity-50">Prev</button>
            <button @click="goToPage(curPage+1)" :disabled="curPage===totalPages||isLoading" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-sm disabled:opacity-50">Next</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Reject modal ─────────────────────────────────────────────────────── -->
    <div v-if="showRejectModal && selectedRecord" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">Reject Service Credit</h2>
          <button @click="closeReject" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500">
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>
        <div class="px-6 py-5 space-y-3 text-sm text-slate-700">
          <p><span class="font-medium">Employee:</span> {{ selectedRecord.user?.name }}</p>
          <p><span class="font-medium">Service date:</span> {{ fmtDate(selectedRecord.service_date) }} — {{ serviceTypeLabel(selectedRecord.service_type) }}</p>
          <p><span class="font-medium">Days:</span> {{ Number(selectedRecord.days_equivalent).toFixed(2) }}</p>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Reason for rejection <span class="text-red-500">*</span></label>
            <textarea v-model="rejectRemarks" rows="3" maxlength="500"
                      placeholder="Explain why this record is being rejected…"
                      class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-400 resize-none" />
          </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
          <button @click="closeReject" class="px-4 py-2 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-sm font-medium">Cancel</button>
          <button @click="submitReject" :disabled="isSubmitting"
                  class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition-colors disabled:opacity-50">
            {{ isSubmitting ? 'Rejecting…' : 'Reject' }}
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>
