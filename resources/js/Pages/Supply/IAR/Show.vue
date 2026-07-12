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
import EmptyState from '@/Components/EmptyState.vue'
import { DocumentArrowDownIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
  iar: Object,
  supplyItems: Array,
})

const showInspectModal = ref(false)
const showAcceptModal = ref(false)
const submitting = ref(false)
const errors = ref({})

// Inspect form state
const inspectForm = ref({
  inspection_date: props.iar.inspection_date ?? new Date().toISOString().split('T')[0],
  inspector_id: props.iar.inspector_id ?? '',
  items: props.iar.items.map(i => ({
    id: i.id,
    supply_item_id: i.supply_item_id ?? '',
    quantity_accepted: i.quantity_accepted,
    quantity_rejected: i.quantity_rejected,
    rejection_reason: i.rejection_reason ?? '',
  })),
})

const acceptForm = ref({
  property_officer_id: props.iar.property_officer_id ?? '',
  remarks: props.iar.remarks ?? '',
})

async function saveInspection() {
  submitting.value = true
  errors.value = {}
  try {
    await axios.post(route('supply.iar.inspect', props.iar.id), inspectForm.value)
    showInspectModal.value = false
    router.reload()
  } catch (e) {
    if (e.response?.status === 422) errors.value = e.response.data.errors ?? {}
    else errors.value = { general: e.response?.data?.message ?? 'An error occurred.' }
  } finally {
    submitting.value = false
  }
}

async function saveAcceptance() {
  submitting.value = true
  errors.value = {}
  try {
    await axios.post(route('supply.iar.accept', props.iar.id), acceptForm.value)
    showAcceptModal.value = false
    router.reload()
  } catch (e) {
    if (e.response?.status === 422) errors.value = e.response.data.errors ?? {}
    else errors.value = { general: e.response?.data?.message ?? 'An error occurred.' }
  } finally {
    submitting.value = false
  }
}

function statusColor(status) {
  const map = { draft: 'slate', inspected: 'blue', accepted: 'green', partial: 'amber' }
  return map[status] ?? 'slate'
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}
</script>

<template>
  <Head :title="`IAR ${iar.iar_number}`" />
  <AdminLayout :title="`IAR — ${iar.iar_number}`">
    <div class="space-y-5">

      <!-- Actions bar -->
      <div class="flex flex-wrap gap-3 items-center">
        <AppBadge :color="statusColor(iar.status)">{{ iar.status_label }}</AppBadge>
        <div class="ml-auto flex gap-2">
          <AppButton v-if="iar.is_editable || iar.status === 'inspected'" @click="showInspectModal=true">
            {{ iar.status === 'draft' ? 'Record Inspection' : 'Edit Inspection' }}
          </AppButton>
          <AppButton v-if="iar.status === 'inspected'" variant="success" @click="showAcceptModal=true">
            <CheckCircleIcon class="h-4 w-4" /> Accept & Post to Stock
          </AppButton>
          <AppButton as="a" variant="secondary" :href="route('supply.iar.pdf', iar.id)" target="_blank">
            <DocumentArrowDownIcon class="h-4 w-4" /> Print IAR
          </AppButton>
        </div>
      </div>

      <!-- Header details -->
      <AppCard>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div><div class="text-xs text-slate-500 uppercase font-semibold">IAR No.</div><div class="font-semibold text-slate-800">{{ iar.iar_number }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">PO Reference</div><div>{{ iar.po_number ?? '—' }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Supplier</div><div>{{ iar.supplier_name }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Delivery Date</div><div>{{ fmtDate(iar.delivery_date) }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Inspection Date</div><div>{{ fmtDate(iar.inspection_date) }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Inspector</div><div>{{ iar.inspector_name ?? '—' }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Property Officer</div><div>{{ iar.property_officer ?? '—' }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Accepted At</div><div>{{ iar.accepted_at ?? '—' }}</div></div>
        </div>
        <div v-if="iar.remarks" class="mt-3 text-sm text-slate-600"><strong>Remarks:</strong> {{ iar.remarks }}</div>
      </AppCard>

      <!-- Items table -->
      <AppTable :is-empty="!iar.items.length" :skeleton-cols="10">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">#</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Description</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Unit</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Ordered</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Delivered</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Accepted</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Rejected</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Unit Cost</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Posted</th>
          </tr>
        </template>

        <tr v-for="(item, idx) in iar.items" :key="item.id">
          <td class="px-4 py-3 text-sm text-slate-500">{{ idx + 1 }}</td>
          <td class="px-4 py-3">
            <div class="text-sm text-slate-800">{{ item.description }}</div>
            <div v-if="item.item_code" class="text-xs text-slate-400 font-mono">{{ item.item_code }}</div>
            <div v-if="item.rejection_reason" class="text-xs text-danger-600 mt-0.5">{{ item.rejection_reason }}</div>
          </td>
          <td class="px-4 py-3 text-center text-sm text-slate-600">{{ item.unit }}</td>
          <td class="px-4 py-3 text-right text-sm text-slate-600">{{ item.quantity_ordered.toLocaleString('en-PH', {minimumFractionDigits:3}) }}</td>
          <td class="px-4 py-3 text-right text-sm text-slate-600">{{ item.quantity_delivered.toLocaleString('en-PH', {minimumFractionDigits:3}) }}</td>
          <td class="px-4 py-3 text-right text-sm font-medium text-success-700">{{ item.quantity_accepted.toLocaleString('en-PH', {minimumFractionDigits:3}) }}</td>
          <td class="px-4 py-3 text-right text-sm" :class="item.quantity_rejected > 0 ? 'text-danger-600' : 'text-slate-400'">{{ item.quantity_rejected.toLocaleString('en-PH', {minimumFractionDigits:3}) }}</td>
          <td class="px-4 py-3 text-right text-sm text-slate-600">₱{{ item.unit_cost.toLocaleString('en-PH', {minimumFractionDigits:2}) }}</td>
          <td class="px-4 py-3 text-right text-sm font-medium text-slate-800">₱{{ item.total_cost.toLocaleString('en-PH', {minimumFractionDigits:2}) }}</td>
          <td class="px-4 py-3 text-center">
            <span :class="item.is_posted ? 'text-success-600' : 'text-slate-300'" class="text-lg">✓</span>
          </td>
        </tr>

        <template #empty>
          <EmptyState title="No items on this IAR." />
        </template>

        <template #footer>
          <div class="flex justify-between px-4 py-3">
            <span class="text-sm font-semibold text-slate-700">Total:</span>
            <span class="text-sm font-bold text-slate-800">
              ₱{{ iar.items.reduce((s,i) => s + i.total_cost, 0).toLocaleString('en-PH', {minimumFractionDigits:2}) }}
            </span>
          </div>
        </template>
      </AppTable>

    </div>

    <!-- Inspect Modal -->
    <AppModal :show="showInspectModal" title="Record Inspection" size="3xl" @close="showInspectModal=false">
      <div class="space-y-4">
        <p v-if="errors.general" class="text-sm text-danger-600 bg-danger-50 rounded-lg p-3">{{ errors.general }}</p>
        <div class="grid grid-cols-2 gap-4">
          <AppInput v-model="inspectForm.inspection_date" type="date" label="Inspection Date" required />
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Inspector <span class="text-red-500">*</span></label>
            <select v-model="inspectForm.inspector_id" required
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
              <option value="">— Select —</option>
              <option v-for="u in supplyItems.officers" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
        </div>
        <div v-for="(item, idx) in inspectForm.items" :key="item.id"
          class="border border-slate-100 rounded-lg p-4">
          <div class="font-medium text-sm text-slate-700 mb-3">
            Item {{ idx + 1 }}: {{ iar.items[idx]?.description }}
            <span class="text-slate-400 ml-2 text-xs">{{ iar.items[idx]?.unit }}</span>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Link Supply Item</label>
              <select v-model="item.supply_item_id"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                <option value="">— Not linked —</option>
                <option v-for="si in supplyItems" :key="si.id" :value="si.id">
                  {{ si.item_code }} — {{ si.description }}
                </option>
              </select>
            </div>
            <AppInput v-model="item.quantity_accepted" type="number" step="0.001" min="0" label="Qty Accepted" />
            <AppInput v-model="item.quantity_rejected" type="number" step="0.001" min="0" label="Qty Rejected" />
            <AppInput v-model="item.rejection_reason" type="text" label="Rejection Reason" />
          </div>
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showInspectModal=false">Cancel</AppButton>
        <AppButton :loading="submitting" :disabled="submitting" @click="saveInspection">
          {{ submitting ? 'Saving…' : 'Save Inspection' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- Accept Modal -->
    <AppModal :show="showAcceptModal" title="Accept & Post to Stock" size="md" @close="showAcceptModal=false">
      <div class="space-y-4">
        <p class="text-sm text-warning-700 bg-warning-50 border border-warning-100 rounded-lg p-3">
          Accepting this IAR will post all accepted quantities to the stock cards using the moving average cost method.
          This action cannot be reversed.
        </p>
        <p v-if="errors.general" class="text-sm text-danger-600">{{ errors.general }}</p>
        <AppInput v-model="acceptForm.property_officer_id" type="text" placeholder="Officer ID or name…" label="Property Officer" required />
        <AppTextarea v-model="acceptForm.remarks" :rows="2" label="Remarks" />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showAcceptModal=false">Cancel</AppButton>
        <AppButton variant="success" :loading="submitting" :disabled="submitting" @click="saveAcceptance">
          {{ submitting ? 'Posting…' : 'Accept & Post' }}
        </AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>
