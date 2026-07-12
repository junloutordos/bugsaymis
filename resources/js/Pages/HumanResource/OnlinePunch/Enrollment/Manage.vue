<template>
  <Head title="Face Enrollment — HR Review" />
  <AdminLayout title="Face Enrollment — HR Review">
    <div class="space-y-5">

      <AppPageHeader title="Face Enrollment" subtitle="Review employee face-enrollment requests for Online Time Punches." />

      <AppFilterBar>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
          <select v-model="filterStatus" @change="load()"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="">All</option>
          </select>
        </div>
      </AppFilterBar>

      <AppTable :loading="loading" :is-empty="!rows.length" :skeleton-cols="4">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Employee</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Submitted</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="row in rows" :key="row.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3">
            <div class="flex items-center gap-3">
              <img v-if="row.photo_url" :src="row.photo_url" class="w-10 h-10 rounded-full object-cover border border-slate-100" alt="Enrollment photo" />
              <div>
                <p class="font-medium text-slate-800">{{ row.user?.name }}</p>
                <p class="text-xs text-slate-400">{{ row.user?.position }}</p>
              </div>
            </div>
          </td>
          <td class="px-4 py-3 text-slate-600">{{ fmtDate(row.consent_given_at) }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusBadgeColor(row.status)">{{ row.status }}</AppBadge>
          </td>
          <td class="px-4 py-3">
            <div v-if="row.status === 'pending'" class="flex gap-2">
              <AppButton size="sm" variant="success" @click="approve(row)">Approve</AppButton>
              <AppButton size="sm" variant="danger" @click="rejectPrompt(row)">Reject</AppButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="row in rows" :key="row.id" class="p-4 space-y-2">
            <div class="flex items-center gap-3">
              <img v-if="row.photo_url" :src="row.photo_url" class="w-12 h-12 rounded-full object-cover border border-slate-100" alt="Enrollment photo" />
              <div class="flex-1">
                <p class="font-medium text-slate-800">{{ row.user?.name }}</p>
                <p class="text-xs text-slate-400">{{ row.user?.position }}</p>
                <p class="text-xs text-slate-400">Submitted {{ fmtDate(row.consent_given_at) }}</p>
              </div>
              <AppBadge :color="statusBadgeColor(row.status)">{{ row.status }}</AppBadge>
            </div>
            <div v-if="row.status === 'pending'" class="flex gap-2 pt-1">
              <AppButton size="sm" variant="success" @click="approve(row)">Approve</AppButton>
              <AppButton size="sm" variant="danger" @click="rejectPrompt(row)">Reject</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No enrollments found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="meta?.current_page"
            :total-pages="meta?.last_page"
            :total="meta?.total"
            @prev="load(meta.current_page - 1)"
            @next="load(meta.current_page + 1)"
            @page="load($event)"
          />
        </template>
      </AppTable>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import Swal from 'sweetalert2'
import { confirmAction } from '@/Composables/useConfirm.js'
import axios from 'axios'

const filterStatus = ref('pending')
const rows    = ref([])
const meta    = ref(null)
const loading = ref(false)

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await axios.get(route('hr.face-enrollment.data'), {
      params: { status: filterStatus.value, page },
    })
    rows.value = data.data
    meta.value = { current_page: data.current_page, last_page: data.last_page, total: data.total }
  } finally {
    loading.value = false
  }
}

async function approve(row) {
  const confirmed = await confirmAction({
    title: `Approve ${row.user?.name}'s face enrollment?`,
    confirmText: 'Approve',
  })
  if (!confirmed) return

  await axios.post(route('hr.face-enrollment.approve', { enrollment: row.id }))
  Swal.fire({ icon: 'success', title: 'Approved', timer: 1500, showConfirmButton: false })
  load(meta.value?.current_page ?? 1)
}

async function rejectPrompt(row) {
  const { value: reason } = await Swal.fire({
    title: `Reject ${row.user?.name}'s face enrollment?`,
    input: 'text',
    inputPlaceholder: 'Reason for rejection',
    showCancelButton: true,
    confirmButtonText: 'Reject',
    inputValidator: (val) => !val ? 'A reason is required' : null,
  })
  if (!reason) return

  await axios.post(route('hr.face-enrollment.reject', { enrollment: row.id }), { reason })
  Swal.fire({ icon: 'success', title: 'Rejected', timer: 1500, showConfirmButton: false })
  load(meta.value?.current_page ?? 1)
}

function statusPillClass(status) {
  if (status === 'approved') return 'bg-emerald-100 text-emerald-700'
  if (status === 'pending') return 'bg-amber-100 text-amber-700'
  return 'bg-rose-100 text-rose-700'
}

function statusBadgeColor(status) {
  if (status === 'approved') return 'green'
  if (status === 'pending') return 'amber'
  return 'red'
}

function fmtDate(val) {
  if (!val) return '—'
  const d = new Date(String(val).replace(' ', 'T'))
  if (isNaN(d.getTime())) return '—'
  return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}

onMounted(() => load())
</script>
