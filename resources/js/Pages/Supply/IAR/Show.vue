<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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

const statusColors = {
  draft: 'bg-slate-100 text-slate-600',
  inspected: 'bg-blue-100 text-blue-700',
  accepted: 'bg-emerald-100 text-emerald-700',
  partial: 'bg-amber-100 text-amber-700',
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}
</script>

<template>
  <Head :title="`IAR ${iar.iar_number}`" />
  <AdminLayout :title="`IAR — ${iar.iar_number}`">

    <!-- Actions bar -->
    <div class="flex flex-wrap gap-3 items-center mb-6">
      <span class="inline-block px-3 py-1 rounded-full text-sm font-medium"
        :class="statusColors[iar.status] ?? 'bg-slate-100 text-slate-600'">
        {{ iar.status_label }}
      </span>
      <div class="ml-auto flex gap-2">
        <button v-if="iar.is_editable || iar.status === 'inspected'" @click="showInspectModal=true"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          {{ iar.status === 'draft' ? 'Record Inspection' : 'Edit Inspection' }}
        </button>
        <button v-if="iar.status === 'inspected'" @click="showAcceptModal=true"
          class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2">
          <CheckCircleIcon class="h-4 w-4" /> Accept & Post to Stock
        </button>
        <a :href="route('supply.iar.pdf', iar.id)" target="_blank"
          class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2">
          <DocumentArrowDownIcon class="h-4 w-4" /> Print IAR
        </a>
      </div>
    </div>

    <!-- Header details -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 mb-4">
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
    </div>

    <!-- Items table -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="min-w-full divide-y divide-slate-100">
        <thead>
          <tr class="bg-slate-50">
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Description</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Ordered</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Delivered</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Accepted</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Rejected</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit Cost</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Total</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Posted</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-for="(item, idx) in iar.items" :key="item.id">
            <td class="px-4 py-3 text-sm text-slate-500">{{ idx + 1 }}</td>
            <td class="px-4 py-3">
              <div class="text-sm text-slate-800">{{ item.description }}</div>
              <div v-if="item.item_code" class="text-xs text-slate-400 font-mono">{{ item.item_code }}</div>
              <div v-if="item.rejection_reason" class="text-xs text-red-600 mt-0.5">{{ item.rejection_reason }}</div>
            </td>
            <td class="px-4 py-3 text-center text-sm text-slate-600">{{ item.unit }}</td>
            <td class="px-4 py-3 text-right text-sm text-slate-600">{{ item.quantity_ordered.toLocaleString('en-PH', {minimumFractionDigits:3}) }}</td>
            <td class="px-4 py-3 text-right text-sm text-slate-600">{{ item.quantity_delivered.toLocaleString('en-PH', {minimumFractionDigits:3}) }}</td>
            <td class="px-4 py-3 text-right text-sm font-medium text-emerald-700">{{ item.quantity_accepted.toLocaleString('en-PH', {minimumFractionDigits:3}) }}</td>
            <td class="px-4 py-3 text-right text-sm" :class="item.quantity_rejected > 0 ? 'text-red-600' : 'text-slate-400'">{{ item.quantity_rejected.toLocaleString('en-PH', {minimumFractionDigits:3}) }}</td>
            <td class="px-4 py-3 text-right text-sm text-slate-600">₱{{ item.unit_cost.toLocaleString('en-PH', {minimumFractionDigits:2}) }}</td>
            <td class="px-4 py-3 text-right text-sm font-medium text-slate-800">₱{{ item.total_cost.toLocaleString('en-PH', {minimumFractionDigits:2}) }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="item.is_posted ? 'text-emerald-600' : 'text-slate-300'" class="text-lg">✓</span>
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="bg-slate-50">
            <td colspan="8" class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Total:</td>
            <td class="px-4 py-3 text-right text-sm font-bold text-slate-800">
              ₱{{ iar.items.reduce((s,i) => s + i.total_cost, 0).toLocaleString('en-PH', {minimumFractionDigits:2}) }}
            </td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Inspect Modal -->
    <div v-if="showInspectModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4 overflow-auto">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl my-4">
        <div class="flex items-center justify-between p-6 border-b border-slate-100">
          <h3 class="text-base font-semibold text-slate-800">Record Inspection</h3>
          <button @click="showInspectModal=false" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
        </div>
        <div class="p-6 space-y-4">
          <p v-if="errors.general" class="text-sm text-red-600 bg-red-50 rounded-lg p-3">{{ errors.general }}</p>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Inspection Date <span class="text-red-500">*</span></label>
              <input v-model="inspectForm.inspection_date" type="date" required
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
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
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Qty Accepted</label>
                <input v-model="item.quantity_accepted" type="number" step="0.001" min="0"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Qty Rejected</label>
                <input v-model="item.quantity_rejected" type="number" step="0.001" min="0"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Rejection Reason</label>
                <input v-model="item.rejection_reason" type="text"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
              </div>
            </div>
          </div>
        </div>
        <div class="flex justify-end gap-3 p-6 border-t border-slate-100">
          <button @click="showInspectModal=false"
            class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
          <button @click="saveInspection" :disabled="submitting"
            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
            {{ submitting ? 'Saving…' : 'Save Inspection' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Accept Modal -->
    <div v-if="showAcceptModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
        <div class="flex items-center justify-between p-6 border-b border-slate-100">
          <h3 class="text-base font-semibold text-slate-800">Accept & Post to Stock</h3>
          <button @click="showAcceptModal=false" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
        </div>
        <div class="p-6 space-y-4">
          <p class="text-sm text-slate-600 bg-amber-50 border border-amber-200 rounded-lg p-3">
            Accepting this IAR will post all accepted quantities to the stock cards using the moving average cost method.
            This action cannot be reversed.
          </p>
          <p v-if="errors.general" class="text-sm text-red-600">{{ errors.general }}</p>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Property Officer <span class="text-red-500">*</span></label>
            <input v-model="acceptForm.property_officer_id" type="text" placeholder="Officer ID or name…"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
            <textarea v-model="acceptForm.remarks" rows="2"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"></textarea>
          </div>
        </div>
        <div class="flex justify-end gap-3 p-6 border-t border-slate-100">
          <button @click="showAcceptModal=false"
            class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
          <button @click="saveAcceptance" :disabled="submitting"
            class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
            {{ submitting ? 'Posting…' : 'Accept & Post' }}
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>
