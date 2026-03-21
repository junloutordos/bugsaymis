<script setup>
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'

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
const approvalColors = { draft: 'bg-gray-100 text-gray-600', submitted: 'bg-yellow-100 text-yellow-700', approved: 'bg-green-100 text-green-700', returned: 'bg-red-100 text-red-600' }
const statusColors   = { planned: 'bg-blue-100 text-blue-700', ongoing: 'bg-yellow-100 text-yellow-700', completed: 'bg-green-100 text-green-700', deferred: 'bg-orange-100 text-orange-700', cancelled: 'bg-red-100 text-red-600' }
const levelColors    = { none: 'bg-gray-100 text-gray-500', basic: 'bg-blue-100 text-blue-600', intermediate: 'bg-indigo-100 text-indigo-700', advanced: 'bg-purple-100 text-purple-700' }

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

    <div class="p-6 space-y-5">

      <!-- Header -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-bold text-gray-800">Team Development Plans</h1>
          <p class="text-sm text-gray-500">IDP records of your team members</p>
        </div>
        <select v-model="selectedYear"
          class="rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-400 focus:outline-none">
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>

      <!-- Pending Approvals -->
      <div v-if="pending.length > 0">
        <h2 class="text-sm font-semibold text-yellow-700 mb-2 flex items-center gap-2">
          <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-yellow-100 text-yellow-700 text-xs font-bold">{{ pending.length }}</span>
          Pending Approval
        </h2>
        <div class="space-y-3">
          <div v-for="idp in pending" :key="idp.id"
            class="rounded-xl border-2 border-yellow-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 bg-yellow-50 border-b border-yellow-200">
              <div>
                <span class="font-semibold text-gray-800">{{ idp.employee?.name }}</span>
                <span class="mx-2 text-gray-400">·</span>
                <span class="text-sm text-gray-600">{{ idp.competency }}</span>
              </div>
              <button @click="openApprove(idp)"
                class="rounded-lg bg-yellow-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-yellow-600">
                Review
              </button>
            </div>
            <div class="px-5 py-3 grid grid-cols-3 gap-4 text-sm">
              <div>
                <div class="text-xs text-gray-500">Timeline</div>
                <div class="text-gray-700 text-xs">{{ fmt(idp.timeline_start) }} – {{ fmt(idp.timeline_end) }}</div>
              </div>
              <div>
                <div class="text-xs text-gray-500">Gap</div>
                <div class="flex items-center gap-1 text-xs mt-0.5">
                  <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[idp.current_level]]">{{ idp.current_level }}</span>
                  <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                  <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[idp.target_level]]">{{ idp.target_level }}</span>
                </div>
              </div>
              <div>
                <div class="text-xs text-gray-500">Program</div>
                <div class="text-gray-700 text-xs">{{ idp.learning_program?.title ?? '—' }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- All IDPs Table -->
      <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b bg-gray-50 px-5 py-3">
          <h2 class="font-semibold text-gray-800">All Team IDPs — {{ year }}</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
              <tr>
                <th class="px-4 py-3 text-left">Employee</th>
                <th class="px-4 py-3 text-left">Competency</th>
                <th class="px-4 py-3 text-center">Gap</th>
                <th class="px-4 py-3 text-center">Timeline</th>
                <th class="px-4 py-3 text-center">Approval</th>
                <th class="px-4 py-3 text-center">Progress</th>
                <th class="px-4 py-3 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-if="idps.length === 0">
                <td colspan="7" class="py-10 text-center text-gray-400">No IDPs for {{ year }}.</td>
              </tr>
              <tr v-for="idp in idps" :key="idp.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-gray-800">{{ idp.employee?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-700 max-w-[160px] truncate">{{ idp.competency }}</td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center justify-center gap-1 text-xs">
                    <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[idp.current_level]]">{{ idp.current_level }}</span>
                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[idp.target_level]]">{{ idp.target_level }}</span>
                  </div>
                </td>
                <td class="px-4 py-3 text-center text-xs text-gray-600 whitespace-nowrap">
                  <template v-if="idp.timeline_start">{{ fmt(idp.timeline_start) }}<br>{{ fmt(idp.timeline_end) }}</template>
                  <span v-else class="text-gray-400">—</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', approvalColors[idp.approval_status] ?? 'bg-gray-100 text-gray-600']">
                    {{ idp.approval_status === 'submitted' ? 'Pending' : idp.approval_status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize', statusColors[idp.status] ?? 'bg-gray-100 text-gray-600']">
                    {{ idp.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <a :href="route('lnd.idp.show', idp.id)"
                      class="rounded px-2 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50">View</a>
                    <button v-if="idp.approval_status === 'submitted'"
                      @click="openApprove(idp)"
                      class="rounded px-2 py-1 text-xs font-medium text-green-600 hover:bg-green-50">Approve</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Approve Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
          <div class="flex items-center justify-between border-b px-6 py-4">
            <h2 class="text-lg font-bold text-gray-800">Review IDP</h2>
            <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="p-6 space-y-4">
            <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
              <span class="font-medium">{{ activeIdp?.employee?.name }}</span> — {{ activeIdp?.competency }}
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Decision</label>
              <div class="flex gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="approveForm.action" value="approved" />
                  <span class="text-sm text-gray-700">Approve</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="approveForm.action" value="returned" />
                  <span class="text-sm text-gray-700">Return for Revision</span>
                </label>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
              <textarea v-model="approveForm.supervisor_remarks" rows="3"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none resize-none" />
            </div>
            <div class="flex justify-end gap-3">
              <button @click="showModal = false"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
              <button @click="submitApprove" :disabled="isSubmitting"
                :class="['rounded-lg px-5 py-2 text-sm font-medium text-white disabled:opacity-50', approveForm.action === 'approved' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-500 hover:bg-red-600']">
                {{ isSubmitting ? 'Saving…' : (approveForm.action === 'approved' ? 'Approve' : 'Return') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
