<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'
import { storageUrl } from '@/Composables/useStorage'

defineProps({ consumable: Object, lots: Array, movements: Array })

const lotTone = { available: 'bg-emerald-50 text-emerald-600', depleted: 'bg-slate-100 text-slate-500', expired: 'bg-rose-50 text-rose-600', disposed: 'bg-slate-100 text-slate-400' }
</script>

<template>
  <Head :title="consumable.name" />
  <AdminLayout :title="consumable.name">
    <Link :href="route('science-lab.inventory.index')" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700"><ArrowLeftIcon class="h-4 w-4" /> Back to inventory</Link>

    <div class="mb-6 flex flex-wrap items-center gap-6 rounded-xl border border-slate-200 bg-white p-5">
      <div><div class="text-xs text-slate-400">On Hand</div><div class="text-2xl font-semibold text-slate-800">{{ consumable.balance }} {{ consumable.unit_of_measure }}</div></div>
      <div><div class="text-xs text-slate-400">Type</div><div class="capitalize text-slate-700">{{ consumable.type }}</div></div>
      <div><div class="text-xs text-slate-400">Reorder Level</div><div class="text-slate-700">{{ consumable.reorder_level }}</div></div>
      <div v-if="consumable.sds_path"><div class="text-xs text-slate-400">SDS</div><a :href="storageUrl(consumable.sds_path)" target="_blank" class="text-indigo-600 hover:underline">View sheet</a></div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <div class="rounded-xl border border-slate-200 bg-white">
        <header class="border-b border-slate-100 px-5 py-3"><h2 class="text-sm font-semibold text-slate-700">Lots (FIFO / FEFO)</h2></header>
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500"><tr><th class="px-4 py-2 text-left">Lot</th><th class="px-4 py-2 text-right">Qty</th><th class="px-4 py-2 text-left">Received</th><th class="px-4 py-2 text-left">Expiry</th><th class="px-4 py-2 text-left">Status</th></tr></thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="l in lots" :key="l.id"><td class="px-4 py-2">{{ l.lot_no || '—' }}</td><td class="px-4 py-2 text-right">{{ l.quantity }}</td><td class="px-4 py-2 text-xs">{{ l.received_date || '—' }}</td><td class="px-4 py-2 text-xs">{{ l.expiry_date || '—' }}</td><td class="px-4 py-2"><span :class="['rounded-full px-2 py-0.5 text-xs font-medium capitalize', lotTone[l.status]]">{{ l.status }}</span></td></tr>
            <tr v-if="!lots.length"><td colspan="5" class="px-4 py-6 text-center text-slate-400">No lots.</td></tr>
          </tbody>
        </table>
      </div>

      <div class="rounded-xl border border-slate-200 bg-white">
        <header class="border-b border-slate-100 px-5 py-3"><h2 class="text-sm font-semibold text-slate-700">Stock Ledger</h2></header>
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500"><tr><th class="px-4 py-2 text-left">Date</th><th class="px-4 py-2 text-left">Ref</th><th class="px-4 py-2 text-right">In</th><th class="px-4 py-2 text-right">Out</th><th class="px-4 py-2 text-right">Balance</th></tr></thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="m in movements" :key="m.id"><td class="px-4 py-2 text-xs">{{ m.transaction_date }}</td><td class="px-4 py-2 text-xs">{{ m.reference_type }}<span v-if="m.reference_number"> · {{ m.reference_number }}</span></td><td class="px-4 py-2 text-right text-emerald-600">{{ Number(m.qty_received) > 0 ? m.qty_received : '' }}</td><td class="px-4 py-2 text-right text-rose-600">{{ Number(m.qty_issued) > 0 ? m.qty_issued : '' }}</td><td class="px-4 py-2 text-right font-medium">{{ m.balance_after }}</td></tr>
            <tr v-if="!movements.length"><td colspan="5" class="px-4 py-6 text-center text-slate-400">No movements.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>
