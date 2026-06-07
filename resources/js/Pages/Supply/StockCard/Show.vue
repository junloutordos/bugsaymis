<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { AdjustmentsHorizontalIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
  item: Object,
  entries: Array,
  filters: Object,
})

const dateFrom = ref(props.filters.date_from ?? '')
const dateTo = ref(props.filters.date_to ?? '')

function applyFilters() {
  router.get(route('supply.stock-card.show', props.item.id), {
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
  }, { preserveScroll: true })
}

const showAdjustModal = ref(false)
const submitting = ref(false)
const errors = ref({})

const adjustForm = ref({
  adjustment_qty: '',
  unit_cost: props.item.average_unit_cost,
  reason: '',
  transaction_date: new Date().toISOString().split('T')[0],
})

async function saveAdjustment() {
  submitting.value = true
  errors.value = {}
  try {
    await axios.post(route('supply.stock-card.adjust', props.item.id), adjustForm.value)
    showAdjustModal.value = false
    router.reload()
  } catch (e) {
    if (e.response?.status === 422) errors.value = e.response.data.errors ?? {}
    else errors.value = { general: e.response?.data?.message ?? 'An error occurred.' }
  } finally {
    submitting.value = false
  }
}

const refTypeBadge = {
  IAR: 'bg-blue-100 text-blue-700',
  RIS: 'bg-violet-100 text-violet-700',
  RETURN: 'bg-emerald-100 text-emerald-700',
  ADJUSTMENT: 'bg-amber-100 text-amber-700',
  BEGINNING: 'bg-slate-100 text-slate-600',
  DISPOSAL: 'bg-red-100 text-red-700',
}
</script>

<template>
  <Head :title="`Stock Card — ${item.description}`" />
  <AdminLayout :title="`Stock Card: ${item.description}`">

    <!-- Item Info -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 mb-4">
      <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
        <div><div class="text-xs text-slate-500 uppercase font-semibold">Item Code</div><div class="font-mono">{{ item.item_code }}</div></div>
        <div><div class="text-xs text-slate-500 uppercase font-semibold">Category</div><div>{{ item.category_name }}</div></div>
        <div><div class="text-xs text-slate-500 uppercase font-semibold">Unit</div><div>{{ item.unit }}</div></div>
        <div><div class="text-xs text-slate-500 uppercase font-semibold">Account Code</div><div>{{ item.account_code ?? '—' }}</div></div>
        <div><div class="text-xs text-slate-500 uppercase font-semibold">Reorder Point</div><div>{{ item.reorder_point ?? '—' }}</div></div>
      </div>
      <!-- Balance highlight -->
      <div class="mt-4 flex gap-6">
        <div>
          <div class="text-xs text-slate-500 uppercase font-semibold">Current Balance</div>
          <div class="text-2xl font-bold mt-0.5" :class="item.is_low_stock ? 'text-red-600' : 'text-slate-800'">
            {{ item.balance_quantity.toLocaleString('en-PH', {minimumFractionDigits:3}) }}
            <span class="text-base font-normal text-slate-500">{{ item.unit }}</span>
          </div>
        </div>
        <div>
          <div class="text-xs text-slate-500 uppercase font-semibold">Average Unit Cost</div>
          <div class="text-2xl font-bold text-slate-800 mt-0.5">
            ₱{{ item.average_unit_cost.toLocaleString('en-PH', {minimumFractionDigits:2}) }}
          </div>
        </div>
        <div>
          <div class="text-xs text-slate-500 uppercase font-semibold">Total Value</div>
          <div class="text-2xl font-bold text-emerald-700 mt-0.5">
            ₱{{ item.total_value.toLocaleString('en-PH', {minimumFractionDigits:2}) }}
          </div>
        </div>
      </div>
    </div>

    <!-- Filters + Adjust -->
    <div class="flex flex-wrap gap-3 items-end mb-4">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">From</label>
        <input v-model="dateFrom" type="date"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">To</label>
        <input v-model="dateTo" type="date"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>
      <button @click="applyFilters"
        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        Filter
      </button>
      <button @click="showAdjustModal=true"
        class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 ml-auto">
        <AdjustmentsHorizontalIcon class="h-4 w-4" /> Adjust Stock
      </button>
    </div>

    <!-- Ledger -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="min-w-full divide-y divide-slate-100 text-sm">
        <thead>
          <tr class="bg-slate-50">
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Reference</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Received</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Issued</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Returned</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Balance</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit Cost</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Amount</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Remarks</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-if="entries.length === 0">
            <td colspan="10" class="px-4 py-8 text-center text-slate-400">No ledger entries for this period.</td>
          </tr>
          <tr v-for="entry in entries" :key="entry.id">
            <td class="px-4 py-3 text-slate-600">
              {{ new Date(entry.transaction_date).toLocaleDateString('en-PH', {year:'numeric',month:'short',day:'numeric'}) }}
            </td>
            <td class="px-4 py-3">
              <span class="inline-block px-2 py-0.5 rounded text-xs font-medium"
                :class="refTypeBadge[entry.reference_type] ?? 'bg-slate-100 text-slate-600'">
                {{ entry.reference_type }}
              </span>
            </td>
            <td class="px-4 py-3 text-slate-700 font-mono text-xs">{{ entry.reference_number }}</td>
            <td class="px-4 py-3 text-right" :class="entry.qty_received > 0 ? 'text-blue-700 font-medium' : 'text-slate-300'">
              {{ entry.qty_received > 0 ? entry.qty_received.toLocaleString('en-PH', {minimumFractionDigits:3}) : '—' }}
            </td>
            <td class="px-4 py-3 text-right" :class="entry.qty_issued > 0 ? 'text-red-600 font-medium' : 'text-slate-300'">
              {{ entry.qty_issued > 0 ? entry.qty_issued.toLocaleString('en-PH', {minimumFractionDigits:3}) : '—' }}
            </td>
            <td class="px-4 py-3 text-right" :class="entry.qty_returned > 0 ? 'text-emerald-700 font-medium' : 'text-slate-300'">
              {{ entry.qty_returned > 0 ? entry.qty_returned.toLocaleString('en-PH', {minimumFractionDigits:3}) : '—' }}
            </td>
            <td class="px-4 py-3 text-right font-semibold text-slate-800">
              {{ entry.balance_after.toLocaleString('en-PH', {minimumFractionDigits:3}) }}
            </td>
            <td class="px-4 py-3 text-right text-slate-600">₱{{ entry.unit_cost.toLocaleString('en-PH', {minimumFractionDigits:2}) }}</td>
            <td class="px-4 py-3 text-right text-slate-700">₱{{ entry.total_cost.toLocaleString('en-PH', {minimumFractionDigits:2}) }}</td>
            <td class="px-4 py-3 text-slate-500 text-xs max-w-xs truncate">{{ entry.remarks }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Adjustment Modal -->
    <div v-if="showAdjustModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
        <div class="flex items-center justify-between p-6 border-b border-slate-100">
          <h3 class="text-base font-semibold text-slate-800">Stock Adjustment</h3>
          <button @click="showAdjustModal=false" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
        </div>
        <div class="p-6 space-y-4">
          <p v-if="errors.general" class="text-sm text-red-600 bg-red-50 rounded-lg p-3">{{ errors.general }}</p>
          <p class="text-sm text-slate-600 bg-amber-50 border border-amber-200 rounded-lg p-3">
            Use positive values to add stock, negative to deduct (e.g. count discrepancy, spoilage).
          </p>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Adjustment Qty <span class="text-red-500">*</span></label>
              <input v-model="adjustForm.adjustment_qty" type="number" step="0.001" required
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
                placeholder="+10 or -5" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Unit Cost</label>
              <input v-model="adjustForm.unit_cost" type="number" step="0.01" min="0"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Transaction Date</label>
            <input v-model="adjustForm.transaction_date" type="date"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Reason <span class="text-red-500">*</span></label>
            <textarea v-model="adjustForm.reason" rows="2" required
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
              placeholder="Physical count variance, spoilage, correction…"></textarea>
          </div>
        </div>
        <div class="flex justify-end gap-3 p-6 border-t border-slate-100">
          <button @click="showAdjustModal=false"
            class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
          <button @click="saveAdjustment" :disabled="submitting"
            class="bg-amber-600 hover:bg-amber-700 text-white px-6 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
            {{ submitting ? 'Saving…' : 'Save Adjustment' }}
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>
