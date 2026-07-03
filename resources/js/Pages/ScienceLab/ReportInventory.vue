<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PrinterIcon } from '@heroicons/vue/24/outline'

defineProps({ equipment: Array, consumables: Array, expiringSoon: Array, generatedAt: String })
</script>

<template>
  <Head title="Inventory Report" />
  <AdminLayout title="Inventory Report">
    <div class="mb-4 flex items-center justify-between">
      <p class="text-sm text-slate-500">Generated {{ generatedAt }}</p>
      <button onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"><PrinterIcon class="h-4 w-4" /> Print</button>
    </div>

    <section class="mb-8">
      <h2 class="mb-2 text-sm font-semibold text-slate-700">Equipment Inventory ({{ equipment.length }})</h2>
      <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-2 text-left">Property No</th><th class="px-3 py-2 text-left">Description</th><th class="px-3 py-2 text-left">Model/Serial</th><th class="px-3 py-2 text-left">Location</th><th class="px-3 py-2 text-left">Unit</th><th class="px-3 py-2 text-left">Acquired</th><th class="px-3 py-2 text-right">Cost</th><th class="px-3 py-2 text-left">Status</th></tr></thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="(e, i) in equipment" :key="i"><td class="px-3 py-2 font-mono text-xs">{{ e.property_no || '—' }}</td><td class="px-3 py-2">{{ e.description }}</td><td class="px-3 py-2 text-xs text-slate-500">{{ e.model }} {{ e.serial_number }}</td><td class="px-3 py-2">{{ e.location || '—' }}</td><td class="px-3 py-2">{{ e.unit || '—' }}</td><td class="px-3 py-2 text-xs">{{ e.date_acquired || '—' }}</td><td class="px-3 py-2 text-right">{{ e.unit_cost ? Number(e.unit_cost).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : '—' }}</td><td class="px-3 py-2 text-xs capitalize">{{ e.status.replace('_',' ') }}</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="mb-8">
      <h2 class="mb-2 text-sm font-semibold text-slate-700">Consumables & Reagents ({{ consumables.length }})</h2>
      <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-2 text-left">Name</th><th class="px-3 py-2 text-left">Type</th><th class="px-3 py-2 text-right">On Hand</th><th class="px-3 py-2 text-right">Reorder</th></tr></thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="(c, i) in consumables" :key="i" :class="c.is_low ? 'bg-amber-50/40' : ''"><td class="px-3 py-2">{{ c.name }}</td><td class="px-3 py-2 text-xs capitalize">{{ c.type }}</td><td class="px-3 py-2 text-right" :class="c.is_low ? 'font-semibold text-amber-600' : ''">{{ c.balance }} {{ c.unit }}</td><td class="px-3 py-2 text-right text-slate-500">{{ c.reorder_level }}</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-if="expiringSoon.length">
      <h2 class="mb-2 text-sm font-semibold text-slate-700">Expiring within 60 days</h2>
      <div class="overflow-x-auto rounded-xl border border-rose-100 bg-white">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-rose-50 text-xs font-semibold uppercase tracking-wide text-rose-500"><tr><th class="px-3 py-2 text-left">Reagent</th><th class="px-3 py-2 text-left">Lot</th><th class="px-3 py-2 text-right">Qty</th><th class="px-3 py-2 text-left">Expiry</th></tr></thead>
          <tbody class="divide-y divide-slate-50"><tr v-for="(e, i) in expiringSoon" :key="i"><td class="px-3 py-2">{{ e.name }}</td><td class="px-3 py-2">{{ e.lot_no || '—' }}</td><td class="px-3 py-2 text-right">{{ e.quantity }}</td><td class="px-3 py-2 text-rose-600">{{ e.expiry_date }}</td></tr></tbody>
        </table>
      </div>
    </section>
  </AdminLayout>
</template>
