<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import EmptyState from '@/Components/EmptyState.vue'
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

function refTypeBadgeColor(type) {
  const map = {
    IAR: 'blue',
    RIS: 'purple',
    RETURN: 'green',
    ADJUSTMENT: 'amber',
    BEGINNING: 'slate',
    DISPOSAL: 'red',
  }
  return map[type] ?? 'slate'
}
</script>

<template>
  <Head :title="`Stock Card — ${item.description}`" />
  <AdminLayout :title="`Stock Card: ${item.description}`">
    <div class="space-y-5">

      <AppPageHeader :title="`Stock Card: ${item.description}`" subtitle="Track receipts, issuances, and balances for this item.">
        <template #actions>
          <AppButton variant="warning" @click="showAdjustModal = true">
            <AdjustmentsHorizontalIcon class="h-4 w-4" /> Adjust Stock
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Item Info -->
      <AppCard>
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
            <div class="text-2xl font-bold mt-0.5" :class="item.is_low_stock ? 'text-danger-600' : 'text-slate-800'">
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
            <div class="text-2xl font-bold text-success-700 mt-0.5">
              ₱{{ item.total_value.toLocaleString('en-PH', {minimumFractionDigits:2}) }}
            </div>
          </div>
        </div>
      </AppCard>

      <!-- Filters -->
      <AppFilterBar>
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
        <template #actions>
          <AppButton @click="applyFilters">Filter</AppButton>
        </template>
      </AppFilterBar>

      <!-- Ledger -->
      <AppTable :is-empty="entries.length === 0" :skeleton-cols="10">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Type</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Reference</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Received</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Issued</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Returned</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Balance</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Unit Cost</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Amount</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Remarks</th>
          </tr>
        </template>

        <tr v-for="entry in entries" :key="entry.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-slate-600">
            {{ new Date(entry.transaction_date).toLocaleDateString('en-PH', {year:'numeric',month:'short',day:'numeric'}) }}
          </td>
          <td class="px-4 py-3">
            <AppBadge :color="refTypeBadgeColor(entry.reference_type)">{{ entry.reference_type }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-slate-700 font-mono text-xs">{{ entry.reference_number }}</td>
          <td class="px-4 py-3 text-right" :class="entry.qty_received > 0 ? 'text-blue-700 font-medium' : 'text-slate-300'">
            {{ entry.qty_received > 0 ? entry.qty_received.toLocaleString('en-PH', {minimumFractionDigits:3}) : '—' }}
          </td>
          <td class="px-4 py-3 text-right" :class="entry.qty_issued > 0 ? 'text-danger-600 font-medium' : 'text-slate-300'">
            {{ entry.qty_issued > 0 ? entry.qty_issued.toLocaleString('en-PH', {minimumFractionDigits:3}) : '—' }}
          </td>
          <td class="px-4 py-3 text-right" :class="entry.qty_returned > 0 ? 'text-success-700 font-medium' : 'text-slate-300'">
            {{ entry.qty_returned > 0 ? entry.qty_returned.toLocaleString('en-PH', {minimumFractionDigits:3}) : '—' }}
          </td>
          <td class="px-4 py-3 text-right font-semibold text-slate-800">
            {{ entry.balance_after.toLocaleString('en-PH', {minimumFractionDigits:3}) }}
          </td>
          <td class="px-4 py-3 text-right text-slate-600">₱{{ entry.unit_cost.toLocaleString('en-PH', {minimumFractionDigits:2}) }}</td>
          <td class="px-4 py-3 text-right text-slate-700">₱{{ entry.total_cost.toLocaleString('en-PH', {minimumFractionDigits:2}) }}</td>
          <td class="px-4 py-3 text-slate-500 text-xs max-w-xs truncate">{{ entry.remarks }}</td>
        </tr>

        <template #mobileCard>
          <div v-for="entry in entries" :key="entry.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <span class="text-sm text-slate-600">
                {{ new Date(entry.transaction_date).toLocaleDateString('en-PH', {year:'numeric',month:'short',day:'numeric'}) }}
              </span>
              <AppBadge :color="refTypeBadgeColor(entry.reference_type)">{{ entry.reference_type }}</AppBadge>
            </div>
            <div class="text-xs font-mono text-slate-700">{{ entry.reference_number }}</div>
            <div class="grid grid-cols-3 gap-2 text-xs">
              <span :class="entry.qty_received > 0 ? 'text-blue-700 font-medium' : 'text-slate-300'">
                Recv: {{ entry.qty_received > 0 ? entry.qty_received.toLocaleString('en-PH', {minimumFractionDigits:3}) : '—' }}
              </span>
              <span :class="entry.qty_issued > 0 ? 'text-danger-600 font-medium' : 'text-slate-300'">
                Issued: {{ entry.qty_issued > 0 ? entry.qty_issued.toLocaleString('en-PH', {minimumFractionDigits:3}) : '—' }}
              </span>
              <span :class="entry.qty_returned > 0 ? 'text-success-700 font-medium' : 'text-slate-300'">
                Ret: {{ entry.qty_returned > 0 ? entry.qty_returned.toLocaleString('en-PH', {minimumFractionDigits:3}) : '—' }}
              </span>
            </div>
            <div class="flex justify-between text-xs text-slate-600">
              <span>Balance: <strong class="text-slate-800">{{ entry.balance_after.toLocaleString('en-PH', {minimumFractionDigits:3}) }}</strong></span>
              <span>₱{{ entry.total_cost.toLocaleString('en-PH', {minimumFractionDigits:2}) }}</span>
            </div>
            <div v-if="entry.remarks" class="text-xs text-slate-500">{{ entry.remarks }}</div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No ledger entries for this period." />
        </template>
      </AppTable>

    </div>

    <!-- Adjustment Modal -->
    <AppModal :show="showAdjustModal" title="Stock Adjustment" @close="showAdjustModal = false">
      <div class="space-y-4">
        <p v-if="errors.general" class="text-sm text-danger-600 bg-danger-50 rounded-lg p-3">{{ errors.general }}</p>
        <p class="text-sm text-slate-600 bg-warning-50 border border-warning-100 rounded-lg p-3">
          Use positive values to add stock, negative to deduct (e.g. count discrepancy, spoilage).
        </p>
        <div class="grid grid-cols-2 gap-4">
          <AppInput v-model="adjustForm.adjustment_qty" type="number" step="0.001" required
            label="Adjustment Qty" placeholder="+10 or -5" :error="errors.adjustment_qty" />
          <AppInput v-model="adjustForm.unit_cost" type="number" step="0.01" min="0" label="Unit Cost" :error="errors.unit_cost" />
        </div>
        <AppInput v-model="adjustForm.transaction_date" type="date" label="Transaction Date" :error="errors.transaction_date" />
        <AppTextarea v-model="adjustForm.reason" :rows="2" required label="Reason"
          placeholder="Physical count variance, spoilage, correction…" :error="errors.reason" />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showAdjustModal = false">Cancel</AppButton>
        <AppButton variant="warning" :loading="submitting" :disabled="submitting" @click="saveAdjustment">
          {{ submitting ? 'Saving…' : 'Save Adjustment' }}
        </AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>
