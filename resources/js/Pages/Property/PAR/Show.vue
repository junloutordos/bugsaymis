<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import { DocumentArrowDownIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ par: Object })

function statusColor(status) {
  const map = { active: 'green', returned: 'amber', transferred: 'blue', superseded: 'slate' }
  return map[status] ?? 'slate'
}
</script>
<template>
  <Head :title="`PAR ${par.par_number}`" />
  <AdminLayout :title="`PAR — ${par.par_number}`">
    <div class="space-y-5">

      <AppPageHeader :title="`PAR — ${par.par_number}`">
        <template #actions>
          <AppBadge :color="statusColor(par.status)">{{ par.status_label }}</AppBadge>
          <AppButton as="a" variant="secondary" :href="route('property.par.pdf', par.id)" target="_blank">
            <DocumentArrowDownIcon class="h-4 w-4" />Print PAR
          </AppButton>
        </template>
      </AppPageHeader>

      <AppCard>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div><div class="text-xs text-slate-500 uppercase font-semibold">PAR No.</div><div class="font-semibold">{{ par.par_number }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Issue Date</div><div>{{ par.issue_date ? new Date(par.issue_date).toLocaleDateString('en-PH',{year:'numeric',month:'long',day:'numeric'}) : '—' }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Issued By</div><div>{{ par.issued_by??'—' }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Received By</div><div class="font-semibold">{{ par.received_by }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Division</div><div>{{ par.division_name??'—' }}</div></div>
          <div><div class="text-xs text-slate-500 uppercase font-semibold">Total Amount</div><div class="font-bold text-emerald-700">₱{{ Number(par.total_amount).toLocaleString('en-PH',{minimumFractionDigits:2}) }}</div></div>
        </div>
        <div v-if="par.remarks" class="mt-3 text-sm text-slate-600"><strong>Remarks:</strong> {{ par.remarks }}</div>
      </AppCard>

      <AppCard :padded="false">
        <table class="min-w-full divide-y divide-slate-100">
          <thead><tr class="bg-slate-50">
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Property No.</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Description</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Unit</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Qty</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Unit Cost</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Total</th>
          </tr></thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="(item,idx) in par.items" :key="item.id">
              <td class="px-4 py-3 text-sm text-slate-500">{{ idx+1 }}</td>
              <td class="px-4 py-3 text-sm font-mono text-slate-600">{{ item.property_number??'—' }}</td>
              <td class="px-4 py-3 text-sm text-slate-800">{{ item.description }}</td>
              <td class="px-4 py-3 text-center text-sm text-slate-600">{{ item.unit }}</td>
              <td class="px-4 py-3 text-right text-sm">{{ Number(item.quantity).toLocaleString('en-PH',{minimumFractionDigits:3}) }}</td>
              <td class="px-4 py-3 text-right text-sm">₱{{ Number(item.unit_cost).toLocaleString('en-PH',{minimumFractionDigits:2}) }}</td>
              <td class="px-4 py-3 text-right text-sm font-medium">₱{{ Number(item.total_cost).toLocaleString('en-PH',{minimumFractionDigits:2}) }}</td>
            </tr>
          </tbody>
          <tfoot><tr class="bg-slate-50"><td colspan="6" class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Total:</td><td class="px-4 py-3 text-right text-sm font-bold text-slate-800">₱{{ Number(par.total_amount).toLocaleString('en-PH',{minimumFractionDigits:2}) }}</td></tr></tfoot>
        </table>
      </AppCard>

    </div>
  </AdminLayout>
</template>
