<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { DocumentArrowDownIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({ ris: Object })

const submitting = ref(false)
const errors = ref({})
const showIssueModal = ref(false)
const showApproveModal = ref(false)

const issueItems = ref(
  props.ris.items.map(i => ({
    id: i.id,
    description: i.description,
    unit: i.unit,
    quantity_requested: i.quantity_requested,
    quantity_issued: i.quantity_issued,
    qty: Math.max(0, i.quantity_requested - i.quantity_issued),
    is_fully_issued: i.is_fully_issued,
  }))
)

const approvalRemarks = ref('')

async function submitAction(actionRoute, payload, onSuccess) {
  submitting.value = true
  errors.value = {}
  try {
    await axios.post(actionRoute, payload)
    onSuccess()
    router.reload()
  } catch (e) {
    errors.value = { general: e.response?.data?.message ?? 'An error occurred.' }
  } finally {
    submitting.value = false
  }
}

function submit_RIS() {
  submitAction(route('supply.ris.submit', props.ris.id), {}, () => {})
}

function approveRIS() {
  submitAction(route('supply.ris.approve', props.ris.id), { approval_remarks: approvalRemarks.value }, () => {
    showApproveModal.value = false
  })
}

function issueItems_submit() {
  submitAction(route('supply.ris.issue', props.ris.id), {
    items: issueItems.value.map(i => ({ id: i.id, qty: parseFloat(i.qty) || 0 }))
  }, () => {
    showIssueModal.value = false
  })
}

const statusColors = {
  draft: 'bg-slate-100 text-slate-600',
  pending: 'bg-amber-100 text-amber-700',
  approved: 'bg-blue-100 text-blue-700',
  partially_issued: 'bg-violet-100 text-violet-700',
  fully_issued: 'bg-emerald-100 text-emerald-700',
  cancelled: 'bg-red-100 text-red-700',
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}
</script>

<template>
  <Head :title="`RIS ${ris.ris_number}`" />
  <AdminLayout :title="`RIS — ${ris.ris_number}`">

    <!-- Action bar -->
    <div class="flex flex-wrap gap-3 items-center mb-6">
      <span class="inline-block px-3 py-1 rounded-full text-sm font-medium"
        :class="statusColors[ris.status] ?? 'bg-slate-100 text-slate-600'">
        {{ ris.status_label }}
      </span>
      <div class="ml-auto flex flex-wrap gap-2">
        <button v-if="ris.is_editable" @click="submit_RIS" :disabled="submitting"
          class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
          Submit for Approval
        </button>
        <button v-if="ris.status === 'pending'" @click="showApproveModal=true"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          Approve
        </button>
        <button v-if="ris.can_be_issued" @click="showIssueModal=true"
          class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          Issue Items
        </button>
        <a :href="route('supply.ris.pdf', ris.id)" target="_blank"
          class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2">
          <DocumentArrowDownIcon class="h-4 w-4" /> Print RIS
        </a>
      </div>
    </div>

    <p v-if="errors.general" class="text-sm text-red-600 bg-red-50 rounded-lg p-3 mb-4">{{ errors.general }}</p>

    <!-- Details -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 mb-4">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div><div class="text-xs text-slate-500 uppercase font-semibold">RIS No.</div><div class="font-semibold text-slate-800">{{ ris.ris_number }}</div></div>
        <div><div class="text-xs text-slate-500 uppercase font-semibold">Division</div><div>{{ ris.division_name ?? '—' }}</div></div>
        <div><div class="text-xs text-slate-500 uppercase font-semibold">Date Requested</div><div>{{ fmtDate(ris.date_requested) }}</div></div>
        <div><div class="text-xs text-slate-500 uppercase font-semibold">Date Needed</div><div>{{ fmtDate(ris.date_needed) }}</div></div>
        <div class="md:col-span-2"><div class="text-xs text-slate-500 uppercase font-semibold">Purpose</div><div>{{ ris.purpose }}</div></div>
        <div><div class="text-xs text-slate-500 uppercase font-semibold">Requested By</div><div>{{ ris.requested_by }}</div></div>
        <div><div class="text-xs text-slate-500 uppercase font-semibold">Approved By</div><div>{{ ris.approved_by ?? '—' }}</div></div>
        <div v-if="ris.issued_by"><div class="text-xs text-slate-500 uppercase font-semibold">Issued By</div><div>{{ ris.issued_by }}</div></div>
        <div v-if="ris.issued_at"><div class="text-xs text-slate-500 uppercase font-semibold">Issued At</div><div>{{ ris.issued_at }}</div></div>
      </div>
      <div v-if="ris.approval_remarks" class="mt-3 text-sm text-slate-600">
        <strong>Remarks:</strong> {{ ris.approval_remarks }}
      </div>
    </div>

    <!-- Items -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="min-w-full divide-y divide-slate-100">
        <thead>
          <tr class="bg-slate-50">
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Item</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Requested</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Issued</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit Cost</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Total</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-for="(item, idx) in ris.items" :key="item.id">
            <td class="px-4 py-3 text-sm text-slate-500">{{ idx + 1 }}</td>
            <td class="px-4 py-3">
              <div class="text-sm text-slate-800">{{ item.description }}</div>
              <div v-if="item.item_code" class="text-xs text-slate-400 font-mono">{{ item.item_code }}</div>
            </td>
            <td class="px-4 py-3 text-center text-sm text-slate-600">{{ item.unit }}</td>
            <td class="px-4 py-3 text-right text-sm text-slate-600">{{ item.quantity_requested.toLocaleString('en-PH', {minimumFractionDigits:3}) }}</td>
            <td class="px-4 py-3 text-right text-sm font-medium"
              :class="item.is_fully_issued ? 'text-emerald-700' : 'text-slate-800'">
              {{ item.quantity_issued.toLocaleString('en-PH', {minimumFractionDigits:3}) }}
            </td>
            <td class="px-4 py-3 text-right text-sm text-slate-600">₱{{ item.unit_cost.toLocaleString('en-PH', {minimumFractionDigits:2}) }}</td>
            <td class="px-4 py-3 text-right text-sm font-medium text-slate-800">₱{{ item.total_cost.toLocaleString('en-PH', {minimumFractionDigits:2}) }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="bg-slate-50">
            <td colspan="6" class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Total:</td>
            <td class="px-4 py-3 text-right text-sm font-bold text-slate-800">
              ₱{{ ris.items.reduce((s,i) => s + i.total_cost, 0).toLocaleString('en-PH', {minimumFractionDigits:2}) }}
            </td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Approve Modal -->
    <div v-if="showApproveModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
        <div class="flex items-center justify-between p-6 border-b border-slate-100">
          <h3 class="text-base font-semibold text-slate-800">Approve RIS</h3>
          <button @click="showApproveModal=false" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
        </div>
        <div class="p-6 space-y-4">
          <p v-if="errors.general" class="text-sm text-red-600">{{ errors.general }}</p>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Remarks (optional)</label>
            <textarea v-model="approvalRemarks" rows="2"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"></textarea>
          </div>
        </div>
        <div class="flex justify-end gap-3 p-6 border-t border-slate-100">
          <button @click="showApproveModal=false"
            class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
          <button @click="approveRIS" :disabled="submitting"
            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
            {{ submitting ? 'Approving…' : 'Approve' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Issue Modal -->
    <div v-if="showIssueModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4 overflow-auto">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl my-4">
        <div class="flex items-center justify-between p-6 border-b border-slate-100">
          <h3 class="text-base font-semibold text-slate-800">Issue Supplies</h3>
          <button @click="showIssueModal=false" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
        </div>
        <div class="p-6 space-y-3">
          <p v-if="errors.general" class="text-sm text-red-600 bg-red-50 rounded-lg p-3">{{ errors.general }}</p>
          <div v-for="item in issueItems" :key="item.id"
            class="flex items-center gap-4 border border-slate-100 rounded-lg p-3"
            :class="item.is_fully_issued ? 'opacity-50' : ''">
            <div class="flex-1">
              <div class="text-sm font-medium text-slate-800">{{ item.description }}</div>
              <div class="text-xs text-slate-500">
                Requested: {{ item.quantity_requested.toLocaleString('en-PH', {minimumFractionDigits:3}) }} {{ item.unit }}
                &nbsp;·&nbsp; Already issued: {{ item.quantity_issued.toLocaleString('en-PH', {minimumFractionDigits:3}) }}
              </div>
            </div>
            <div class="w-32">
              <label class="block text-xs font-medium text-slate-600 mb-1">Issue Qty</label>
              <input v-model="item.qty" type="number" step="0.001" min="0"
                :max="item.quantity_requested - item.quantity_issued"
                :disabled="item.is_fully_issued"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full disabled:bg-slate-50" />
            </div>
          </div>
        </div>
        <div class="flex justify-end gap-3 p-6 border-t border-slate-100">
          <button @click="showIssueModal=false"
            class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
          <button @click="issueItems_submit" :disabled="submitting"
            class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
            {{ submitting ? 'Issuing…' : 'Confirm Issue' }}
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>
