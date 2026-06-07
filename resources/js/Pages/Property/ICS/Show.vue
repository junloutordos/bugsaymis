<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { DocumentArrowDownIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ ics: Object })
const statusColors = { active:'bg-emerald-100 text-emerald-700', returned:'bg-amber-100 text-amber-700', superseded:'bg-slate-100 text-slate-600' }
</script>
<template>
  <Head :title="`ICS ${ics.ics_number}`" />
  <AdminLayout :title="`ICS — ${ics.ics_number}`">
    <div class="flex flex-wrap gap-3 items-center mb-6">
      <span class="px-3 py-1 rounded-full text-sm font-medium" :class="statusColors[ics.status]??'bg-slate-100 text-slate-600'">{{ ics.status_label }}</span>
      <div class="ml-auto"><a :href="route('property.ics.pdf',ics.id)" target="_blank" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2"><DocumentArrowDownIcon class="h-4 w-4"/>Print ICS</a></div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-6 mb-4">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div><div class="text-xs text-slate-500 uppercase font-semibold">ICS No.</div><div class="font-semibold">{{ ics.ics_number }}</div></div>
        <div><div class="text-xs text-slate-500 uppercase font-semibold">Issue Date</div><div>{{ ics.issue_date ? new Date(ics.issue_date).toLocaleDateString('en-PH',{year:'numeric',month:'long',day:'numeric'}) : '—' }}</div></div>
        <div><div class="text-xs text-slate-500 uppercase font-semibold">Issued By</div><div>{{ ics.issued_by??'—' }}</div></div>
        <div><div class="text-xs text-slate-500 uppercase font-semibold">Received By</div><div class="font-semibold">{{ ics.received_by }}</div></div>
        <div><div class="text-xs text-slate-500 uppercase font-semibold">Division</div><div>{{ ics.division_name??'—' }}</div></div>
        <div><div class="text-xs text-slate-500 uppercase font-semibold">Total Amount</div><div class="font-bold text-emerald-700">₱{{ ics.total_amount.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</div></div>
      </div>
      <div v-if="ics.remarks" class="mt-3 text-sm text-slate-600"><strong>Remarks:</strong> {{ ics.remarks }}</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="min-w-full divide-y divide-slate-100">
        <thead><tr class="bg-slate-50">
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">#</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Property No.</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Description</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Unit</th>
          <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Qty</th>
          <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Unit Cost</th>
          <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Total</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Est. Life</th>
        </tr></thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-for="(item,idx) in ics.items" :key="item.id">
            <td class="px-4 py-3 text-sm text-slate-500">{{ idx+1 }}</td>
            <td class="px-4 py-3 text-sm font-mono text-slate-600">{{ item.property_number??'—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-800">{{ item.description }}</td>
            <td class="px-4 py-3 text-center text-sm text-slate-600">{{ item.unit }}</td>
            <td class="px-4 py-3 text-right text-sm">{{ item.quantity.toLocaleString('en-PH',{minimumFractionDigits:3}) }}</td>
            <td class="px-4 py-3 text-right text-sm">₱{{ item.unit_cost.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</td>
            <td class="px-4 py-3 text-right text-sm font-medium">₱{{ item.total_cost.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</td>
            <td class="px-4 py-3 text-center text-sm text-slate-600">{{ item.estimated_useful_life??'—' }}</td>
          </tr>
        </tbody>
        <tfoot><tr class="bg-slate-50"><td colspan="6" class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Total:</td><td class="px-4 py-3 text-right text-sm font-bold text-slate-800">₱{{ ics.total_amount.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</td><td></td></tr></tfoot>
      </table>
    </div>
  </AdminLayout>
</template>
