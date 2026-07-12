<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline'
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

const statusBadgeColor = (s) => ({
  pending:  'amber',
  approved: 'green',
  rejected: 'red',
  consumed: 'blue',
  expired:  'slate',
})[s] ?? 'slate'

const fmtDate = (d) => {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<template>
  <Head title="Service Credit Records" />
  <AdminLayout title="Service Credit Records">
    <div class="space-y-5">

      <AppPageHeader title="Service Credit Records" />
      <p class="text-sm text-slate-500 -mt-4">
        Review and approve service credit earning records submitted by Teaching personnel.
        To initialize existing records, use
        <Link :href="route('hr.leave-credits.initialize')" class="text-indigo-600 underline hover:text-indigo-800">Initialize Leave Credits</Link>.
      </p>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">{{ $page.props.flash.success }}</div>
      <div v-if="$page.props.flash?.error"   class="bg-danger-50 border border-danger-100 text-danger-700 rounded-lg px-4 py-3 text-sm">{{ $page.props.flash.error }}</div>

      <!-- Filters -->
      <AppFilterBar>
        <select v-model="status"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>
        <input v-model="search" type="text" placeholder="Search employee…"
               @keydown.enter.prevent="applyFilters"
               class="px-3 py-2 text-sm border border-slate-200 rounded-lg w-48 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        <template #actions>
          <AppButton size="sm" :disabled="isLoading" @click="applyFilters">Search</AppButton>
          <AppButton v-if="search || status !== 'pending'" size="sm" variant="secondary" :disabled="isLoading" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="pageData.length === 0" :skeleton-cols="8">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Employee</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Service Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Type</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Hours</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Days Equiv.</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Expires</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
          </tr>
        </template>

        <tr v-for="rec in pageData" :key="rec.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 font-medium text-slate-800">{{ rec.user?.name ?? '—' }}</td>
          <td class="px-4 py-3 text-slate-600">{{ fmtDate(rec.service_date) }}</td>
          <td class="px-4 py-3 text-slate-600">{{ serviceTypeLabel(rec.service_type) }}</td>
          <td class="px-4 py-3 text-right text-slate-600">{{ rec.hours_rendered }}h</td>
          <td class="px-4 py-3 text-right font-semibold text-slate-800">{{ Number(rec.days_equivalent).toFixed(2) }}</td>
          <td class="px-4 py-3 text-slate-500 text-xs">{{ rec.expires_at ? fmtDate(rec.expires_at) : '—' }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusBadgeColor(rec.status)" class="capitalize">{{ rec.status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <div v-if="rec.status === 'pending'" class="flex items-center justify-center gap-1.5">
              <AppButton size="sm" variant="success" :disabled="isSubmitting" @click="approve(rec)">
                <CheckCircleIcon class="w-3.5 h-3.5" /> Approve
              </AppButton>
              <AppButton size="sm" variant="danger" :disabled="isSubmitting" @click="openReject(rec)">
                <XCircleIcon class="w-3.5 h-3.5" /> Reject
              </AppButton>
            </div>
            <span v-else class="text-slate-400 text-xs">—</span>
          </td>
        </tr>

        <template #empty>
          <EmptyState title="No records found for the selected status." />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="curPage"
            :total-pages="totalPages"
            @prev="goToPage(curPage - 1)"
            @next="goToPage(curPage + 1)"
            @page="goToPage"
          />
        </template>
      </AppTable>
    </div>

    <!-- ── Reject modal ─────────────────────────────────────────────────────── -->
    <AppModal :show="showRejectModal && !!selectedRecord" title="Reject Service Credit" @close="closeReject">
      <template v-if="selectedRecord">
        <div class="space-y-3 text-sm text-slate-700">
          <p><span class="font-medium">Employee:</span> {{ selectedRecord.user?.name }}</p>
          <p><span class="font-medium">Service date:</span> {{ fmtDate(selectedRecord.service_date) }} — {{ serviceTypeLabel(selectedRecord.service_type) }}</p>
          <p><span class="font-medium">Days:</span> {{ Number(selectedRecord.days_equivalent).toFixed(2) }}</p>
          <AppTextarea
            v-model="rejectRemarks"
            rows="3" maxlength="500"
            label="Reason for rejection" required
            placeholder="Explain why this record is being rejected…"
          />
        </div>
      </template>

      <template #footer>
        <AppButton variant="secondary" @click="closeReject">Cancel</AppButton>
        <AppButton variant="danger" :loading="isSubmitting" @click="submitReject">
          {{ isSubmitting ? 'Rejecting…' : 'Reject' }}
        </AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>
