<script setup>
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { ChevronRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  idps: { type: Array,  default: () => [] },
  year: { type: Number, required: true },
})

const selectedYear = ref(props.year)
const currentYear  = new Date().getFullYear()
const yearOptions  = Array.from({ length: 6 }, (_, i) => currentYear - 2 + i)

watch(selectedYear, (y) => {
  router.get(route('lnd.team-idp'), { year: y }, { preserveState: true, replace: true })
})

const fmt = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'

function approvalBadgeColor(status) {
  const map = { draft: 'slate', submitted: 'amber', approved: 'green', returned: 'red' }
  return map[status] ?? 'slate'
}
function statusBadgeColor(status) {
  const map = { planned: 'blue', ongoing: 'amber', completed: 'green', deferred: 'orange', cancelled: 'red' }
  return map[status] ?? 'slate'
}
function levelBadgeColor(level) {
  const map = { none: 'slate', basic: 'blue', intermediate: 'indigo', advanced: 'purple' }
  return map[level] ?? 'slate'
}

const isSubmitting = ref(false)
const showModal    = ref(false)
const activeIdp    = ref(null)
const approveForm  = ref({ action: 'approved', supervisor_remarks: '' })

const openApprove = (idp) => {
  activeIdp.value  = idp
  approveForm.value = { action: 'approved', supervisor_remarks: '' }
  showModal.value  = true
}

const submitApprove = () => {
  isSubmitting.value = true
  router.patch(route('lnd.idp.approve', activeIdp.value.id), approveForm.value, {
    preserveState: false,
    onSuccess: () => {
      showModal.value = false
      Swal.fire({ icon: 'success', title: approveForm.value.action === 'approved' ? 'Approved' : 'Returned', timer: 1400, showConfirmButton: false })
    },
    onError: (errs) => Swal.fire({ icon: 'error', title: 'Error', text: Object.values(errs)[0] }),
    onFinish: () => { isSubmitting.value = false },
  })
}

const pending = props.idps.filter(i => i.approval_status === 'submitted')
const others  = props.idps.filter(i => i.approval_status !== 'submitted')
</script>

<template>
  <AdminLayout title="Team IDP">
    <Head title="Team IDP" />

    <div class="space-y-5">

      <AppPageHeader title="Team Development Plans" subtitle="IDP records of your team members">
        <template #actions>
          <select v-model="selectedYear"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
          </select>
        </template>
      </AppPageHeader>

      <!-- Pending Approvals -->
      <div v-if="pending.length > 0">
        <h2 class="text-sm font-semibold text-warning-700 mb-2 flex items-center gap-2">
          <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-warning-100 text-warning-700 text-xs font-bold">{{ pending.length }}</span>
          Pending Approval
        </h2>
        <div class="space-y-3">
          <AppCard v-for="idp in pending" :key="idp.id" :padded="false" class="overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 bg-warning-50 border-b border-warning-100">
              <div>
                <span class="font-semibold text-slate-800">{{ idp.employee?.name }}</span>
                <span class="mx-2 text-slate-300">·</span>
                <span class="text-sm text-slate-600">{{ idp.competency }}</span>
              </div>
              <AppButton @click="openApprove(idp)">Review</AppButton>
            </div>
            <div class="px-5 py-3 grid grid-cols-3 gap-4 text-sm">
              <div>
                <div class="text-xs text-slate-500 mb-1">Timeline</div>
                <div class="text-slate-700 text-xs">{{ fmt(idp.timeline_start) }} – {{ fmt(idp.timeline_end) }}</div>
              </div>
              <div>
                <div class="text-xs text-slate-500 mb-1">Gap</div>
                <div class="flex items-center gap-1 text-xs mt-0.5">
                  <AppBadge :color="levelBadgeColor(idp.current_level)">{{ idp.current_level }}</AppBadge>
                  <ChevronRightIcon class="w-3 h-3 text-slate-400" />
                  <AppBadge :color="levelBadgeColor(idp.target_level)">{{ idp.target_level }}</AppBadge>
                </div>
              </div>
              <div>
                <div class="text-xs text-slate-500 mb-1">Program</div>
                <div class="text-slate-700 text-xs">{{ idp.learning_program?.title ?? '—' }}</div>
              </div>
            </div>
          </AppCard>
        </div>
      </div>

      <!-- All IDPs Table -->
      <AppTable :is-empty="!idps.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Employee</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Competency</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Gap</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Timeline</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Approval</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Progress</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
          </tr>
        </template>

        <tr v-for="idp in idps" :key="idp.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 font-medium text-sm text-slate-800">{{ idp.employee?.name ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700 max-w-[160px] truncate">{{ idp.competency }}</td>
          <td class="px-4 py-3 text-center">
            <div class="flex items-center justify-center gap-1 text-xs">
              <AppBadge :color="levelBadgeColor(idp.current_level)">{{ idp.current_level }}</AppBadge>
              <ChevronRightIcon class="w-3 h-3 text-slate-400" />
              <AppBadge :color="levelBadgeColor(idp.target_level)">{{ idp.target_level }}</AppBadge>
            </div>
          </td>
          <td class="px-4 py-3 text-center text-xs text-slate-600 whitespace-nowrap">
            <template v-if="idp.timeline_start">{{ fmt(idp.timeline_start) }}<br>{{ fmt(idp.timeline_end) }}</template>
            <span v-else class="text-slate-400">—</span>
          </td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="approvalBadgeColor(idp.approval_status)">{{ idp.approval_status === 'submitted' ? 'Pending' : idp.approval_status }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="statusBadgeColor(idp.status)"><span class="capitalize">{{ idp.status }}</span></AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <div class="flex items-center justify-center gap-1">
              <AppButton as="a" size="sm" variant="ghost" :href="route('lnd.idp.show', idp.id)">View</AppButton>
              <AppButton v-if="idp.approval_status === 'submitted'" size="sm" variant="ghost" @click="openApprove(idp)">Approve</AppButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="idp in idps" :key="idp.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-sm text-slate-800">{{ idp.employee?.name ?? '—' }}</p>
                <p class="text-xs text-slate-500">{{ idp.competency }}</p>
              </div>
              <div class="flex flex-col items-end gap-1 shrink-0">
                <AppBadge :color="approvalBadgeColor(idp.approval_status)">{{ idp.approval_status === 'submitted' ? 'Pending' : idp.approval_status }}</AppBadge>
                <AppBadge :color="statusBadgeColor(idp.status)"><span class="capitalize">{{ idp.status }}</span></AppBadge>
              </div>
            </div>
            <div class="flex items-center gap-1 text-xs">
              <AppBadge :color="levelBadgeColor(idp.current_level)">{{ idp.current_level }}</AppBadge>
              <ChevronRightIcon class="w-3 h-3 text-slate-400" />
              <AppBadge :color="levelBadgeColor(idp.target_level)">{{ idp.target_level }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">
              <template v-if="idp.timeline_start">{{ fmt(idp.timeline_start) }} – {{ fmt(idp.timeline_end) }}</template>
              <template v-else>—</template>
            </p>
            <div class="flex items-center gap-1 pt-1">
              <AppButton as="a" size="sm" variant="ghost" :href="route('lnd.idp.show', idp.id)">View</AppButton>
              <AppButton v-if="idp.approval_status === 'submitted'" size="sm" variant="ghost" @click="openApprove(idp)">Approve</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState :title="`No IDPs for ${year}.`" />
        </template>
      </AppTable>
    </div>

    <!-- Approve Modal -->
    <AppModal :show="showModal" title="Review IDP" @close="showModal = false">
      <div class="space-y-4">
        <div class="rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
          <span class="font-medium">{{ activeIdp?.employee?.name }}</span> — {{ activeIdp?.competency }}
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-2">Decision</label>
          <div class="flex gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" v-model="approveForm.action" value="approved" />
              <span class="text-sm text-slate-700">Approve</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" v-model="approveForm.action" value="returned" />
              <span class="text-sm text-slate-700">Return for Revision</span>
            </label>
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
          <textarea v-model="approveForm.supervisor_remarks" rows="3"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 resize-none" />
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showModal = false">Cancel</AppButton>
        <AppButton :variant="approveForm.action === 'approved' ? 'primary' : 'danger'" :loading="isSubmitting" @click="submitApprove">
          {{ approveForm.action === 'approved' ? 'Approve' : 'Return' }}
        </AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
