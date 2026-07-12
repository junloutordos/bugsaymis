<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
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

function statusBadgeColor(status) {
  const map = {
    draft: 'slate',
    pending: 'amber',
    approved: 'blue',
    partially_issued: 'purple',
    fully_issued: 'green',
    cancelled: 'red',
  }
  return map[status] ?? 'slate'
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}
</script>

<template>
  <Head :title="`RIS ${ris.ris_number}`" />
  <AdminLayout :title="`RIS — ${ris.ris_number}`">
    <div class="space-y-5">

      <!-- Action bar -->
      <div class="flex flex-wrap gap-3 items-center">
        <AppBadge :color="statusBadgeColor(ris.status)">{{ ris.status_label }}</AppBadge>
        <div class="ml-auto flex flex-wrap gap-2">
          <AppButton v-if="ris.is_editable" variant="warning" :disabled="submitting" @click="submit_RIS">
            Submit for Approval
          </AppButton>
          <AppButton v-if="ris.status === 'pending'" @click="showApproveModal = true">
            Approve
          </AppButton>
          <AppButton v-if="ris.can_be_issued" variant="success" @click="showIssueModal = true">
            Issue Items
          </AppButton>
          <AppButton as="a" variant="secondary" :href="route('supply.ris.pdf', ris.id)" target="_blank">
            <DocumentArrowDownIcon class="h-4 w-4" /> Print RIS
          </AppButton>
        </div>
      </div>

      <p v-if="errors.general" class="text-sm text-danger-600 bg-danger-50 rounded-lg p-3">{{ errors.general }}</p>

      <!-- Details -->
      <AppCard>
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
      </AppCard>

      <!-- Items -->
      <AppTable :is-empty="!ris.items.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">#</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Item</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Unit</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Requested</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Issued</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Unit Cost</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total</th>
          </tr>
        </template>

        <tr v-for="(item, idx) in ris.items" :key="item.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-500">{{ idx + 1 }}</td>
          <td class="px-4 py-3">
            <div class="text-sm text-slate-800">{{ item.description }}</div>
            <div v-if="item.item_code" class="text-xs text-slate-400 font-mono">{{ item.item_code }}</div>
          </td>
          <td class="px-4 py-3 text-center text-sm text-slate-600">{{ item.unit }}</td>
          <td class="px-4 py-3 text-right text-sm text-slate-600">{{ item.quantity_requested.toLocaleString('en-PH', {minimumFractionDigits:3}) }}</td>
          <td class="px-4 py-3 text-right text-sm font-medium"
            :class="item.is_fully_issued ? 'text-success-700' : 'text-slate-800'">
            {{ item.quantity_issued.toLocaleString('en-PH', {minimumFractionDigits:3}) }}
          </td>
          <td class="px-4 py-3 text-right text-sm text-slate-600">₱{{ item.unit_cost.toLocaleString('en-PH', {minimumFractionDigits:2}) }}</td>
          <td class="px-4 py-3 text-right text-sm font-medium text-slate-800">₱{{ item.total_cost.toLocaleString('en-PH', {minimumFractionDigits:2}) }}</td>
        </tr>
        <tr class="bg-slate-50">
          <td colspan="6" class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Total:</td>
          <td class="px-4 py-3 text-right text-sm font-bold text-slate-800">
            ₱{{ ris.items.reduce((s,i) => s + i.total_cost, 0).toLocaleString('en-PH', {minimumFractionDigits:2}) }}
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="(item, idx) in ris.items" :key="item.id" class="p-4 space-y-1">
            <p class="text-sm font-medium text-slate-800">{{ idx + 1 }}. {{ item.description }}</p>
            <p v-if="item.item_code" class="text-xs text-slate-400 font-mono">{{ item.item_code }}</p>
            <div class="flex justify-between text-xs text-slate-500">
              <span>Requested: {{ item.quantity_requested.toLocaleString('en-PH', {minimumFractionDigits:3}) }} {{ item.unit }}</span>
              <span :class="item.is_fully_issued ? 'text-success-700 font-medium' : ''">Issued: {{ item.quantity_issued.toLocaleString('en-PH', {minimumFractionDigits:3}) }}</span>
            </div>
            <div class="flex justify-between text-xs text-slate-500">
              <span>Unit cost ₱{{ item.unit_cost.toLocaleString('en-PH', {minimumFractionDigits:2}) }}</span>
              <span class="font-semibold text-slate-800">Total ₱{{ item.total_cost.toLocaleString('en-PH', {minimumFractionDigits:2}) }}</span>
            </div>
          </div>
          <div class="p-4 flex justify-between text-sm font-semibold text-slate-800 bg-slate-50">
            <span>Total</span>
            <span>₱{{ ris.items.reduce((s,i) => s + i.total_cost, 0).toLocaleString('en-PH', {minimumFractionDigits:2}) }}</span>
          </div>
        </template>
      </AppTable>

    </div>

    <!-- Approve Modal -->
    <AppModal :show="showApproveModal" title="Approve RIS" size="sm" @close="showApproveModal = false">
      <div class="space-y-4">
        <p v-if="errors.general" class="text-sm text-danger-600">{{ errors.general }}</p>
        <AppTextarea v-model="approvalRemarks" :rows="2" label="Remarks (optional)" />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showApproveModal = false">Cancel</AppButton>
        <AppButton :loading="submitting" :disabled="submitting" @click="approveRIS">
          {{ submitting ? 'Approving…' : 'Approve' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- Issue Modal -->
    <AppModal :show="showIssueModal" title="Issue Supplies" size="2xl" @close="showIssueModal = false">
      <div class="space-y-3">
        <p v-if="errors.general" class="text-sm text-danger-600 bg-danger-50 rounded-lg p-3">{{ errors.general }}</p>
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
            <AppInput v-model="item.qty" type="number" step="0.001" min="0"
              :max="item.quantity_requested - item.quantity_issued"
              :disabled="item.is_fully_issued"
              label="Issue Qty" />
          </div>
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showIssueModal = false">Cancel</AppButton>
        <AppButton variant="success" :loading="submitting" :disabled="submitting" @click="issueItems_submit">
          {{ submitting ? 'Issuing…' : 'Confirm Issue' }}
        </AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>
