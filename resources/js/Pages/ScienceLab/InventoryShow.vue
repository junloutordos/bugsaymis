<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'
import { storageUrl } from '@/Composables/useStorage'

defineProps({ consumable: Object, lots: Array, movements: Array })

const lotBadgeColor = { available: 'green', depleted: 'slate', expired: 'red', disposed: 'slate' }
</script>

<template>
  <Head :title="consumable.name" />
  <AdminLayout :title="consumable.name">
    <Link :href="route('science-lab.inventory.index')" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700"><ArrowLeftIcon class="h-4 w-4" /> Back to inventory</Link>

    <AppCard class="mb-6">
      <div class="flex flex-wrap items-center gap-6">
        <div><div class="text-xs text-slate-400">On Hand</div><div class="text-2xl font-semibold text-slate-800">{{ consumable.balance }} {{ consumable.unit_of_measure }}</div></div>
        <div><div class="text-xs text-slate-400">Type</div><div class="capitalize text-slate-700">{{ consumable.type }}</div></div>
        <div><div class="text-xs text-slate-400">Reorder Level</div><div class="text-slate-700">{{ consumable.reorder_level }}</div></div>
        <div v-if="consumable.sds_path"><div class="text-xs text-slate-400">SDS</div><a :href="storageUrl(consumable.sds_path)" target="_blank" class="text-indigo-600 hover:underline">View sheet</a></div>
      </div>
    </AppCard>

    <div class="grid gap-6 lg:grid-cols-2">
      <AppCard title="Lots (FIFO / FEFO)" :padded="false">
        <AppTable :is-empty="!lots.length" :skeleton-cols="5" :card="false">
          <template #head>
            <tr><th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Lot</th><th class="px-4 py-2 text-right text-xs font-semibold uppercase text-slate-500">Qty</th><th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Received</th><th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Expiry</th><th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Status</th></tr>
          </template>
          <tr v-for="l in lots" :key="l.id">
            <td class="px-4 py-2">{{ l.lot_no || '—' }}</td>
            <td class="px-4 py-2 text-right">{{ l.quantity }}</td>
            <td class="px-4 py-2 text-xs">{{ l.received_date || '—' }}</td>
            <td class="px-4 py-2 text-xs">{{ l.expiry_date || '—' }}</td>
            <td class="px-4 py-2"><AppBadge :color="lotBadgeColor[l.status] ?? 'slate'"><span class="capitalize">{{ l.status }}</span></AppBadge></td>
          </tr>
          <template #empty><p class="text-sm text-slate-400">No lots.</p></template>
        </AppTable>
      </AppCard>

      <AppCard title="Stock Ledger" :padded="false">
        <AppTable :is-empty="!movements.length" :skeleton-cols="5" :card="false">
          <template #head>
            <tr><th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Date</th><th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Ref</th><th class="px-4 py-2 text-right text-xs font-semibold uppercase text-slate-500">In</th><th class="px-4 py-2 text-right text-xs font-semibold uppercase text-slate-500">Out</th><th class="px-4 py-2 text-right text-xs font-semibold uppercase text-slate-500">Balance</th></tr>
          </template>
          <tr v-for="m in movements" :key="m.id">
            <td class="px-4 py-2 text-xs">{{ m.transaction_date }}</td>
            <td class="px-4 py-2 text-xs">{{ m.reference_type }}<span v-if="m.reference_number"> · {{ m.reference_number }}</span></td>
            <td class="px-4 py-2 text-right text-success-700">{{ Number(m.qty_received) > 0 ? m.qty_received : '' }}</td>
            <td class="px-4 py-2 text-right text-danger-600">{{ Number(m.qty_issued) > 0 ? m.qty_issued : '' }}</td>
            <td class="px-4 py-2 text-right font-medium">{{ m.balance_after }}</td>
          </tr>
          <template #empty><p class="text-sm text-slate-400">No movements.</p></template>
        </AppTable>
      </AppCard>
    </div>
  </AdminLayout>
</template>
