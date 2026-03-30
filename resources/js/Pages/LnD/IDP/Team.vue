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
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Team Development Plans</h1>
          <p class="text-sm text-slate-500">IDP records of your team members</p>
        </div>
        <select v-model="selectedYear"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>

      <!-- Pending Approvals -->
      <div v-if="pending.length > 0">
        <h2 class="text-sm font-semibold text-amber-700 mb-2 flex items-center gap-2">
          <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-100 text-amber-700 text-xs font-bold">{{ pending.length }}</span>
          Pending Approval
        </h2>
        <div class="space-y-3">
          <div v-for="idp in pending" :key="idp.id"
            class="bg-white rounded-xl border border-amber-200 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 bg-amber-50 border-b border-amber-100">
              <div>
                <span class="font-semibold text-slate-800">{{ idp.employee?.name }}</span>
                <span class="mx-2 text-slate-300">·</span>
                <span class="text-sm text-slate-600">{{ idp.competency }}</span>
              </div>
              <button @click="openApprove(idp)"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                Review
              </button>
            </div>
            <div class="px-5 py-3 grid grid-cols-3 gap-4 text-sm">
              <div>
                <div class="text-xs text-slate-500 mb-1">Timeline</div>
                <div class="text-slate-700 text-xs">{{ fmt(idp.timeline_start) }} – {{ fmt(idp.timeline_end) }}</div>
              </div>
              <div>
                <div class="text-xs text-slate-500 mb-1">Gap</div>
                <div class="flex items-center gap-1 text-xs mt-0.5">
                  <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[idp.current_level]]">{{ idp.current_level }}</span>
                  <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                  <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[idp.target_level]]">{{ idp.target_level }}</span>
                </div>
              </div>
              <div>
                <div class="text-xs text-slate-500 mb-1">Program</div>
                <div class="text-slate-700 text-xs">{{ idp.learning_program?.title ?? '—' }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- All IDPs Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
          <h2 class="font-semibold text-slate-800">All Team IDPs — {{ year }}</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Employee</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Competency</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Gap</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Timeline</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Approval</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Progress</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="idps.length === 0">
                <td colspan="7" class="py-16 text-center text-slate-400 text-sm">No IDPs for {{ year }}.</td>
              </tr>
              <tr v-for="idp in idps" :key="idp.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 font-medium text-sm text-slate-800">{{ idp.employee?.name ?? '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-700 max-w-[160px] truncate">{{ idp.competency }}</td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center justify-center gap-1 text-xs">
                    <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[idp.current_level]]">{{ idp.current_level }}</span>
                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <span :class="['rounded px-1.5 py-0.5 font-medium', levelColors[idp.target_level]]">{{ idp.target_level }}</span>
                  </div>
                </td>
                <td class="px-4 py-3 text-center text-xs text-slate-600 whitespace-nowrap">
                  <template v-if="idp.timeline_start">{{ fmt(idp.timeline_start) }}<br>{{ fmt(idp.timeline_end) }}</template>
                  <span v-else class="text-slate-400">—</span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium', approvalColors[idp.approval_status] ?? 'bg-slate-100 text-slate-600']">
                    {{ idp.approval_status === 'submitted' ? 'Pending' : idp.approval_status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium capitalize', statusColors[idp.status] ?? 'bg-slate-100 text-slate-600']">
                    {{ idp.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <a :href="route('lnd.idp.show', idp.id)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors text-xs font-medium">View</a>
                    <button v-if="idp.approval_status === 'submitted'"
                      @click="openApprove(idp)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-indigo-600 hover:text-indigo-700 transition-colors text-xs font-medium">Approve</button>
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
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Review IDP</h2>
            <button @click="showModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="px-6 py-5 space-y-4">
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
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click="showModal = false"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button @click="submitApprove" :disabled="isSubmitting"
              :class="['inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white transition-colors shadow-sm disabled:opacity-50', approveForm.action === 'approved' ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-red-600 hover:bg-red-700']">
              {{ isSubmitting ? 'Saving…' : (approveForm.action === 'approved' ? 'Approve' : 'Return') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
