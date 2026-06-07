<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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

const statusColors = { serviceable:'bg-emerald-100 text-emerald-700', unserviceable:'bg-amber-100 text-amber-700', disposed:'bg-red-100 text-red-700', transferred:'bg-blue-100 text-blue-700', lost:'bg-slate-100 text-slate-600' }
</script>
<template>
  <Head :title="`Property — ${item.property_number}`" />
  <AdminLayout :title="`Property: ${item.property_number}`">
    <div class="flex flex-wrap gap-3 items-center mb-6">
      <span class="px-3 py-1 rounded-full text-sm font-medium" :class="statusColors[item.status]">{{ item.status }}</span>
      <div class="ml-auto flex gap-2">
        <button @click="loadSchedule" :disabled="loadingSched" class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ loadingSched ? 'Loading…' : 'Depreciation Schedule' }}</button>
        <a :href="route('property.reports.depreciation-schedule')+'?item_id='+item.id+'&format=pdf'" target="_blank" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2"><DocumentArrowDownIcon class="h-4 w-4"/>Print Schedule</a>
      </div>
    </div>

    <!-- Details -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 mb-4">
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
    </div>

    <!-- Financials -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
      <div class="bg-white rounded-xl border border-slate-200 p-4"><div class="text-xs font-semibold text-slate-500 uppercase">Total Cost</div><div class="text-xl font-bold mt-1">₱{{ item.total_cost.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</div></div>
      <div class="bg-white rounded-xl border border-slate-200 p-4"><div class="text-xs font-semibold text-slate-500 uppercase">Residual Value</div><div class="text-xl font-bold text-slate-600 mt-1">₱{{ item.residual_value.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</div></div>
      <div class="bg-white rounded-xl border border-slate-200 p-4"><div class="text-xs font-semibold text-slate-500 uppercase">Accum. Depreciation</div><div class="text-xl font-bold text-amber-700 mt-1">₱{{ item.accumulated_dep.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</div></div>
      <div class="bg-white rounded-xl border border-slate-200 p-4"><div class="text-xs font-semibold text-slate-500 uppercase">Book Value (Today)</div><div class="text-xl font-bold text-indigo-700 mt-1">₱{{ item.book_value.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</div></div>
    </div>

    <!-- Depreciation Schedule Modal -->
    <div v-if="showDepModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4 overflow-auto">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg my-4">
        <div class="flex items-center justify-between p-6 border-b border-slate-100">
          <h3 class="text-base font-semibold text-slate-800">Depreciation Lapsing Schedule</h3>
          <button @click="showDepModal=false" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
        </div>
        <div class="p-6">
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
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
