<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'
import { storageUrl } from '@/Composables/useStorage'

defineProps({ equipment: Object })

const statusLabels = { active: 'Active', under_repair: 'Under Repair', for_calibration: 'For Calibration', out_of_service: 'Out of Service', for_disposal: 'For Disposal', disposed: 'Disposed' }
</script>

<template>
  <Head :title="equipment.description" />
  <AdminLayout :title="equipment.description">
    <Link :href="route('science-lab.equipment.index')" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700"><ArrowLeftIcon class="h-4 w-4" /> Back to registry</Link>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-1">
        <h2 class="mb-3 text-sm font-semibold text-slate-700">Details</h2>
        <dl class="space-y-2 text-sm">
          <div class="flex justify-between"><dt class="text-slate-400">Property No</dt><dd class="font-mono">{{ equipment.property_no || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Model</dt><dd>{{ equipment.model || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Serial</dt><dd>{{ equipment.serial_number || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Location</dt><dd>{{ equipment.room?.name || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Unit</dt><dd>{{ equipment.unit || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Acquired</dt><dd>{{ equipment.date_acquired || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Unit Cost</dt><dd>{{ equipment.unit_cost ? Number(equipment.unit_cost).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Status</dt><dd class="capitalize">{{ statusLabels[equipment.status] }}</dd></div>
        </dl>
      </div>

      <div class="space-y-6 lg:col-span-2">
        <div class="rounded-xl border border-slate-200 bg-white">
          <header class="border-b border-slate-100 px-5 py-3"><h2 class="text-sm font-semibold text-slate-700">Service / Repair Log</h2></header>
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500"><tr><th class="px-4 py-2 text-left">Date</th><th class="px-4 py-2 text-left">Type</th><th class="px-4 py-2 text-left">Particulars</th><th class="px-4 py-2 text-right">Cost</th><th class="px-4 py-2 text-left">Serviced By</th></tr></thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="l in equipment.service_logs" :key="l.id"><td class="px-4 py-2 text-xs">{{ l.log_date }}</td><td class="px-4 py-2 text-xs capitalize">{{ l.type }}</td><td class="px-4 py-2">{{ l.particulars }}</td><td class="px-4 py-2 text-right">{{ Number(l.cost_of_materials).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td><td class="px-4 py-2">{{ l.serviced_by || '—' }}</td></tr>
              <tr v-if="!equipment.service_logs?.length"><td colspan="5" class="px-4 py-6 text-center text-slate-400">No service history.</td></tr>
            </tbody>
          </table>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white">
          <header class="border-b border-slate-100 px-5 py-3"><h2 class="text-sm font-semibold text-slate-700">Calibration History</h2></header>
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500"><tr><th class="px-4 py-2 text-left">Actual</th><th class="px-4 py-2 text-left">By</th><th class="px-4 py-2 text-left">Due</th><th class="px-4 py-2 text-left">Result</th><th class="px-4 py-2 text-left">Cert</th></tr></thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="ev in equipment.calibration_events" :key="ev.id"><td class="px-4 py-2 text-xs">{{ ev.actual_date || '—' }}</td><td class="px-4 py-2">{{ ev.calibrated_by || '—' }}</td><td class="px-4 py-2 text-xs">{{ ev.due_date || '—' }}</td><td class="px-4 py-2 capitalize">{{ ev.result }}</td><td class="px-4 py-2"><a v-if="ev.certificate_path" :href="storageUrl(ev.certificate_path)" target="_blank" class="text-indigo-600 hover:underline">View</a><span v-else>—</span></td></tr>
              <tr v-if="!equipment.calibration_events?.length"><td colspan="5" class="px-4 py-6 text-center text-slate-400">No calibration records.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
