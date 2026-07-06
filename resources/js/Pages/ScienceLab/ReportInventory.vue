<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppTable from '@/Components/AppTable.vue'
import AppButton from '@/Components/AppButton.vue'
import { PrinterIcon } from '@heroicons/vue/24/outline'

defineProps({ equipment: Array, consumables: Array, expiringSoon: Array, generatedAt: String })

function printReport() { window.print() }
</script>

<template>
  <Head title="Inventory Report" />
  <AdminLayout title="Inventory Report">
    <AppPageHeader title="Inventory Report" :subtitle="`Generated ${generatedAt}`">
      <template #actions>
        <AppButton variant="secondary" @click="printReport"><PrinterIcon class="h-4 w-4" /> Print</AppButton>
      </template>
    </AppPageHeader>

    <section class="mb-8">
      <h2 class="mb-2 text-sm font-semibold text-slate-700">Equipment Inventory ({{ equipment.length }})</h2>
      <AppTable :is-empty="!equipment.length" :skeleton-cols="8">
        <template #head>
          <tr>
            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Property No</th>
            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Description</th>
            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Model/Serial</th>
            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Location</th>
            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Unit</th>
            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Acquired</th>
            <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Cost</th>
            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
          </tr>
        </template>
        <tr v-for="(e, i) in equipment" :key="i">
          <td class="px-3 py-2 font-mono text-xs">{{ e.property_no || '—' }}</td>
          <td class="px-3 py-2">{{ e.description }}</td>
          <td class="px-3 py-2 text-xs text-slate-500">{{ e.model }} {{ e.serial_number }}</td>
          <td class="px-3 py-2">{{ e.location || '—' }}</td>
          <td class="px-3 py-2">{{ e.unit || '—' }}</td>
          <td class="px-3 py-2 text-xs">{{ e.date_acquired || '—' }}</td>
          <td class="px-3 py-2 text-right">{{ e.unit_cost ? Number(e.unit_cost).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : '—' }}</td>
          <td class="px-3 py-2 text-xs capitalize">{{ e.status.replace('_',' ') }}</td>
        </tr>
      </AppTable>
    </section>

    <section class="mb-8">
      <h2 class="mb-2 text-sm font-semibold text-slate-700">Consumables & Reagents ({{ consumables.length }})</h2>
      <AppTable :is-empty="!consumables.length" :skeleton-cols="4">
        <template #head>
          <tr>
            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
            <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">On Hand</th>
            <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Reorder</th>
          </tr>
        </template>
        <tr v-for="(c, i) in consumables" :key="i" :class="c.is_low ? 'bg-amber-50/40' : ''">
          <td class="px-3 py-2">{{ c.name }}</td>
          <td class="px-3 py-2 text-xs capitalize">{{ c.type }}</td>
          <td class="px-3 py-2 text-right" :class="c.is_low ? 'font-semibold text-amber-600' : ''">{{ c.balance }} {{ c.unit }}</td>
          <td class="px-3 py-2 text-right text-slate-500">{{ c.reorder_level }}</td>
        </tr>
      </AppTable>
    </section>

    <section v-if="expiringSoon.length">
      <h2 class="mb-2 text-sm font-semibold text-slate-700">Expiring within 60 days</h2>
      <AppTable :is-empty="!expiringSoon.length" :skeleton-cols="4">
        <template #head>
          <tr>
            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-rose-500">Reagent</th>
            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-rose-500">Lot</th>
            <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-rose-500">Qty</th>
            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-rose-500">Expiry</th>
          </tr>
        </template>
        <tr v-for="(e, i) in expiringSoon" :key="i">
          <td class="px-3 py-2">{{ e.name }}</td>
          <td class="px-3 py-2">{{ e.lot_no || '—' }}</td>
          <td class="px-3 py-2 text-right">{{ e.quantity }}</td>
          <td class="px-3 py-2 text-rose-600">{{ e.expiry_date }}</td>
        </tr>
      </AppTable>
    </section>
  </AdminLayout>
</template>
