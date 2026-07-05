<template>
  <Head :title="`ICS ${ics.ics_number}`" />
  <AdminLayout :title="`ICS — ${ics.ics_number}`">
    <div class="space-y-5">

      <AppPageHeader :title="`ICS — ${ics.ics_number}`" :subtitle="ics.division_name ?? undefined">
        <template #actions>
          <AppBadge :color="statusColor(ics.status)">{{ ics.status_label }}</AppBadge>
          <AppButton as="a" variant="secondary" :href="route('property.ics.pdf', ics.id)" target="_blank">
            <DocumentArrowDownIcon class="h-4 w-4" />
            Print ICS
          </AppButton>
        </template>
      </AppPageHeader>

      <AppCard>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div>
            <div class="text-xs text-slate-500 uppercase font-semibold">ICS No.</div>
            <div class="font-semibold">{{ ics.ics_number }}</div>
          </div>
          <div>
            <div class="text-xs text-slate-500 uppercase font-semibold">Issue Date</div>
            <div>{{ ics.issue_date ? new Date(ics.issue_date).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—' }}</div>
          </div>
          <div>
            <div class="text-xs text-slate-500 uppercase font-semibold">Issued By</div>
            <div>{{ ics.issued_by ?? '—' }}</div>
          </div>
          <div>
            <div class="text-xs text-slate-500 uppercase font-semibold">Received By</div>
            <div class="font-semibold">{{ ics.received_by }}</div>
          </div>
          <div>
            <div class="text-xs text-slate-500 uppercase font-semibold">Division</div>
            <div>{{ ics.division_name ?? '—' }}</div>
          </div>
          <div>
            <div class="text-xs text-slate-500 uppercase font-semibold">Total Amount</div>
            <div class="font-bold text-success-700">₱{{ ics.total_amount.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</div>
          </div>
        </div>
        <div v-if="ics.remarks" class="mt-3 text-sm text-slate-600"><strong>Remarks:</strong> {{ ics.remarks }}</div>
      </AppCard>

      <AppTable :is-empty="!ics.items?.length" :skeleton-cols="8">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Property No.</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Description</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Qty</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit Cost</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Total</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Est. Life</th>
          </tr>
        </template>

        <tr v-for="(item, idx) in ics.items" :key="item.id">
          <td class="px-4 py-3 text-sm text-slate-500">{{ idx + 1 }}</td>
          <td class="px-4 py-3 text-sm font-mono text-slate-600">{{ item.property_number ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-800">{{ item.description }}</td>
          <td class="px-4 py-3 text-center text-sm text-slate-600">{{ item.unit }}</td>
          <td class="px-4 py-3 text-right text-sm">{{ item.quantity.toLocaleString('en-PH', { minimumFractionDigits: 3 }) }}</td>
          <td class="px-4 py-3 text-right text-sm">₱{{ item.unit_cost.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td>
          <td class="px-4 py-3 text-right text-sm font-medium">₱{{ item.total_cost.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td>
          <td class="px-4 py-3 text-center text-sm text-slate-600">{{ item.estimated_useful_life ?? '—' }}</td>
        </tr>

        <template #mobileCard>
          <div v-for="(item, idx) in ics.items" :key="item.id" class="p-4 space-y-1">
            <div class="flex items-start justify-between gap-2">
              <p class="text-sm font-medium text-slate-800">{{ idx + 1 }}. {{ item.description }}</p>
              <p class="text-sm font-semibold text-slate-800">₱{{ item.total_cost.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</p>
            </div>
            <p class="text-xs text-slate-500">{{ item.property_number ?? '—' }} &middot; {{ item.quantity.toLocaleString('en-PH', { minimumFractionDigits: 3 }) }} {{ item.unit }} &times; ₱{{ item.unit_cost.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</p>
            <p class="text-xs text-slate-400">Est. Life: {{ item.estimated_useful_life ?? '—' }}</p>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No items on this ICS" />
        </template>

        <template #footer>
          <div class="flex items-center justify-between px-4 py-3 text-sm">
            <span class="font-semibold text-slate-700">Total</span>
            <span class="font-bold text-slate-800">₱{{ ics.total_amount.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
          </div>
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { DocumentArrowDownIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ ics: Object })

function statusColor(status) {
  const map = { active: 'green', returned: 'amber', superseded: 'slate' }
  return map[status] ?? 'slate'
}
</script>
