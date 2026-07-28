<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'

const props = defineProps({
  reports: { type: Array, required: true },
})

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December']

const showModal = ref(false)
const mode = ref('approve') // approve | return
const active = ref(null)
const remarks = ref('')
const submitting = ref(false)

function openModal(report, m) {
  active.value = report
  mode.value = m
  remarks.value = ''
  showModal.value = true
}

async function confirmAction() {
  submitting.value = true
  try {
    const routeName = mode.value === 'approve'
      ? 'homeroom-attendance.monthly-report.approve'
      : 'homeroom-attendance.monthly-report.return'
    await axios.post(route(routeName, active.value.id), { remarks: remarks.value || null })
    showModal.value = false
    router.reload()
    await Swal.fire({ icon: 'success', title: mode.value === 'approve' ? 'Report approved' : 'Report returned', timer: 1000, showConfirmButton: false })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Action failed.', 'error')
  } finally {
    submitting.value = false
  }
}

const statusColor = { submitted: 'amber', approved: 'green' }
</script>

<template>
  <Head title="Homeroom Attendance — Coordinator Review" />
  <AdminLayout title="Coordinator Review">
    <AppPageHeader title="Homeroom Unit Coordinator Review" subtitle="Monthly Record on Attendance and Punctuality submitted by Homeroom Advisers" />

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Section</th>
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Month</th>
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Submitted By</th>
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
            <th class="px-4 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="!reports.length">
            <td colspan="5" class="px-4 py-8 text-center text-slate-400 text-sm">No reports submitted yet.</td>
          </tr>
          <tr v-for="r in reports" :key="r.id">
            <td class="px-4 py-2 text-slate-700">Grade {{ r.level }} - {{ r.section }}</td>
            <td class="px-4 py-2 text-slate-500">{{ MONTHS[r.month - 1] }} {{ r.year }}</td>
            <td class="px-4 py-2 text-slate-500">{{ r.submitted_by }}</td>
            <td class="px-4 py-2"><AppBadge :color="statusColor[r.status]">{{ r.status }}</AppBadge></td>
            <td class="px-4 py-2 text-right space-x-2">
              <a :href="route('homeroom-attendance.monthly-report.print', r.id)" target="_blank" class="text-indigo-600 hover:underline text-xs">Print</a>
              <template v-if="r.status === 'submitted'">
                <AppButton size="sm" variant="secondary" @click="openModal(r, 'return')">Return</AppButton>
                <AppButton size="sm" @click="openModal(r, 'approve')">Approve</AppButton>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppModal :show="showModal" @close="showModal = false" :title="mode === 'approve' ? 'Approve Report' : 'Return Report to Adviser'">
      <div class="space-y-3">
        <label class="block text-xs font-medium text-slate-600 mb-1">
          Remarks {{ mode === 'return' ? '(required)' : '(optional)' }}
        </label>
        <textarea v-model="remarks" rows="3"
          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showModal = false">Cancel</AppButton>
        <AppButton :loading="submitting" :disabled="mode === 'return' && !remarks" @click="confirmAction">
          {{ mode === 'approve' ? 'Approve' : 'Return' }}
        </AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
