<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppModal from '@/Components/AppModal.vue'
import { DocumentArrowDownIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({ item: Object })

const showDepModal = ref(false)
const schedule = ref([])
const loadingSched = ref(false)

async function loadSchedule() {
  loadingSched.value = true
  try {
    const { data } = await axios.get(route('property.reports.depreciation-schedule'), { params: { item_id: props.item.id } })
    schedule.value = data.schedule
    showDepModal.value = true
  } finally {
    loadingSched.value = false
  }
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' })
}

function statusColor(status) {
  const map = { serviceable: 'green', unserviceable: 'amber', disposed: 'red', transferred: 'blue', lost: 'slate' }
  return map[status] ?? 'slate'
}
</script>
<template>
  <Head :title="`Property — ${item.property_number}`" />
  <AdminLayout :title="`Property: ${item.property_number}`">
    <div class="space-y-5">

      <AppPageHeader :title="`Property: ${item.property_number}`">
        <template #actions>
          <AppBadge :color="statusColor(item.status)">{{ item.status }}</AppBadge>
          <AppButton variant="secondary" :loading="loadingSched" @click="loadSchedule">
            {{ loadingSched ? 'Loading…' : 'Depreciation Schedule' }}
          </AppButton>
          <AppButton as="a" variant="secondary" :href="route('property.reports.depreciation-schedule')+'?item_id='+item.id+'&format=pdf'" target="_blank">
            <DocumentArrowDownIcon class="h-4 w-4"/>Print Schedule
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Details -->
      <AppCard>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Property No.</div><div class="font-semibold">{{ item.property_number }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Category</div><div>{{ item.category_name }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Account Code</div><div>{{ item.account_code??'—' }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Unit</div><div>{{ item.unit }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Acquisition Date</div><div>{{ fmtDate(item.acquisition_date) }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Acquisition Mode</div><div class="capitalize">{{ item.acquisition_mode }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Brand / Model</div><div>{{ [item.brand,item.model].filter(Boolean).join(' / ')||'—' }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Serial No.</div><div class="font-mono">{{ item.serial_number??'—' }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Location</div><div>{{ item.location??'—' }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Accountable Officer</div><div>{{ item.current_officer??'—' }}</div></div>
        </div>
      </AppCard>

      <!-- Financials -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <AppCard>
          <div class="text-xs font-semibold text-slate-500 uppercase">Total Cost</div>
          <div class="text-xl font-bold mt-1">₱{{ item.total_cost.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</div>
        </AppCard>
        <AppCard>
          <div class="text-xs font-semibold text-slate-500 uppercase">Residual Value</div>
          <div class="text-xl font-bold text-slate-600 mt-1">₱{{ item.residual_value.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</div>
        </AppCard>
        <AppCard>
          <div class="text-xs font-semibold text-slate-500 uppercase">Accum. Depreciation</div>
          <div class="text-xl font-bold text-warning-700 mt-1">₱{{ item.accumulated_dep.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</div>
        </AppCard>
        <AppCard>
          <div class="text-xs font-semibold text-slate-500 uppercase">Book Value (Today)</div>
          <div class="text-xl font-bold text-indigo-700 mt-1">₱{{ item.book_value.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</div>
        </AppCard>
      </div>

      <!-- Depreciation Schedule Modal -->
      <AppModal :show="showDepModal" title="Depreciation Lapsing Schedule" size="lg" @close="showDepModal=false">
        <p class="text-xs text-slate-500 mb-3">Straight-line method | 5% residual | Monthly depreciation: ₱{{ item.monthly_dep.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</p>
        <table class="min-w-full text-sm divide-y divide-slate-100">
          <thead><tr class="bg-slate-50"><th class="px-3 py-2 text-center text-xs font-semibold text-slate-500">Year</th><th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">Annual Dep.</th><th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">Accumulated</th><th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">Book Value</th></tr></thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="row in schedule" :key="row.year" :class="{'font-bold bg-slate-50':row===schedule[schedule.length-1]}">
              <td class="px-3 py-2 text-center">{{ row.year }}</td>
              <td class="px-3 py-2 text-right">₱{{ row.annual_depreciation.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</td>
              <td class="px-3 py-2 text-right">₱{{ row.accumulated.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</td>
              <td class="px-3 py-2 text-right">₱{{ row.book_value.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</td>
            </tr>
          </tbody>
        </table>
      </AppModal>

    </div>
  </AdminLayout>
</template>
