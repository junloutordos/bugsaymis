<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppTable from '@/Components/AppTable.vue'
import AppButton from '@/Components/AppButton.vue'
import { CheckIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ requests: Array })

const rejecting = ref(null)
const rejectNotes = ref('')

function approve(req) {
  router.post(route('students.change-requests.approve', req.id), {}, { preserveScroll: true })
}

function openReject(req) {
  rejecting.value = req
  rejectNotes.value = ''
}

function submitReject() {
  router.post(route('students.change-requests.reject', rejecting.value.id), { review_notes: rejectNotes.value }, {
    preserveScroll: true,
    onSuccess: () => { rejecting.value = null },
  })
}

function statusColor(status) {
  return { pending: 'text-amber-700 bg-amber-50', approved: 'text-emerald-700 bg-emerald-50', rejected: 'text-slate-500 bg-slate-100' }[status]
}
</script>

<template>
  <Head title="Student Update Requests" />
  <AdminLayout title="Student Update Requests">
    <div class="space-y-5">
      <AppPageHeader title="Student Update Requests" subtitle="Self-submitted personal-info changes awaiting registrar review" />

      <AppTable :is-empty="requests.length === 0" :skeleton-cols="5">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Student</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Requested Changes</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Submitted</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
          </tr>
        </template>

        <tr v-for="req in requests" :key="req.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm">
            <div class="font-medium text-slate-800">{{ req.student_name }}</div>
            <div class="text-xs text-slate-400">{{ req.pisaysystemID }}</div>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <ul class="space-y-0.5">
              <li v-for="(value, field) in req.requested_changes" :key="field">
                <span class="text-slate-400">{{ field }}:</span> {{ value || '—' }}
              </li>
            </ul>
          </td>
          <td class="px-4 py-3 text-sm text-slate-500">{{ req.submitted_at }}</td>
          <td class="px-4 py-3 text-sm">
            <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusColor(req.status)">{{ req.status }}</span>
            <div v-if="req.status === 'rejected' && req.review_notes" class="mt-1 text-xs text-slate-400">{{ req.review_notes }}</div>
          </td>
          <td class="px-4 py-3 text-sm">
            <div v-if="req.status === 'pending'" class="flex gap-2">
              <AppButton size="sm" @click="approve(req)"><CheckIcon class="h-4 w-4" /> Approve</AppButton>
              <AppButton size="sm" variant="secondary" @click="openReject(req)"><XMarkIcon class="h-4 w-4" /> Reject</AppButton>
            </div>
            <span v-else class="text-xs text-slate-400">{{ req.reviewer }} · {{ req.reviewed_at }}</span>
          </td>
        </tr>
      </AppTable>
    </div>

    <div v-if="rejecting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
      <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
        <h2 class="mb-3 text-lg font-semibold text-slate-900">Reject update for {{ rejecting.student_name }}</h2>
        <textarea v-model="rejectNotes" rows="3" placeholder="Reason for rejection (required)"
          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        <div class="mt-4 flex justify-end gap-2">
          <AppButton size="sm" variant="secondary" @click="rejecting = null">Cancel</AppButton>
          <AppButton size="sm" :disabled="!rejectNotes.trim()" @click="submitReject">Reject</AppButton>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
