<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'
import { storageUrl } from '@/Composables/useStorage'

defineProps({ equipment: Object })

const statusLabels = { active: 'Active', under_repair: 'Under Repair', for_calibration: 'For Calibration', out_of_service: 'Out of Service', for_disposal: 'For Disposal', disposed: 'Disposed' }
const statusBadgeColor = { active: 'green', under_repair: 'amber', for_calibration: 'blue', out_of_service: 'red', for_disposal: 'orange', disposed: 'slate' }
const resultBadgeColor = { pass: 'green', fail: 'red', adjusted: 'blue' }
</script>

<template>
  <Head :title="equipment.description" />
  <AdminLayout :title="equipment.description">
    <Link :href="route('science-lab.equipment.index')" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700"><ArrowLeftIcon class="h-4 w-4" /> Back to registry</Link>

    <div class="grid gap-6 lg:grid-cols-3">
      <AppCard title="Details" class="lg:col-span-1">
        <dl class="space-y-2 text-sm">
          <div class="flex justify-between"><dt class="text-slate-400">Property No</dt><dd class="font-mono">{{ equipment.property_no || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Model</dt><dd>{{ equipment.model || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Serial</dt><dd>{{ equipment.serial_number || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Location</dt><dd>{{ equipment.room?.name || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Unit</dt><dd>{{ equipment.unit || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Acquired</dt><dd>{{ equipment.date_acquired || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Unit Cost</dt><dd>{{ equipment.unit_cost ? Number(equipment.unit_cost).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : '—' }}</dd></div>
          <div class="flex justify-between items-center"><dt class="text-slate-400">Status</dt><dd><AppBadge :color="statusBadgeColor[equipment.status] ?? 'slate'">{{ statusLabels[equipment.status] }}</AppBadge></dd></div>
        </dl>
      </AppCard>

      <div class="space-y-6 lg:col-span-2">
        <AppCard title="Service / Repair Log" :padded="false">
          <AppTable :is-empty="!equipment.service_logs?.length" :skeleton-cols="5" :card="false">
            <template #head>
              <tr><th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Date</th><th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Type</th><th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Particulars</th><th class="px-4 py-2 text-right text-xs font-semibold uppercase text-slate-500">Cost</th><th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Serviced By</th></tr>
            </template>
            <tr v-for="l in equipment.service_logs" :key="l.id"><td class="px-4 py-2 text-xs">{{ l.log_date }}</td><td class="px-4 py-2 text-xs capitalize">{{ l.type }}</td><td class="px-4 py-2">{{ l.particulars }}</td><td class="px-4 py-2 text-right">{{ Number(l.cost_of_materials).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td><td class="px-4 py-2">{{ l.serviced_by || '—' }}</td></tr>
            <template #empty><p class="text-sm text-slate-400">No service history.</p></template>
          </AppTable>
        </AppCard>

        <AppCard title="Calibration History" :padded="false">
          <AppTable :is-empty="!equipment.calibration_events?.length" :skeleton-cols="5" :card="false">
            <template #head>
              <tr><th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Actual</th><th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">By</th><th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Due</th><th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Result</th><th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Cert</th></tr>
            </template>
            <tr v-for="ev in equipment.calibration_events" :key="ev.id">
              <td class="px-4 py-2 text-xs">{{ ev.actual_date || '—' }}</td>
              <td class="px-4 py-2">{{ ev.calibrated_by || '—' }}</td>
              <td class="px-4 py-2 text-xs">{{ ev.due_date || '—' }}</td>
              <td class="px-4 py-2"><AppBadge :color="resultBadgeColor[ev.result] ?? 'slate'"><span class="capitalize">{{ ev.result }}</span></AppBadge></td>
              <td class="px-4 py-2"><a v-if="ev.certificate_path" :href="storageUrl(ev.certificate_path)" target="_blank" class="text-indigo-600 hover:underline">View</a><span v-else>—</span></td>
            </tr>
            <template #empty><p class="text-sm text-slate-400">No calibration records.</p></template>
          </AppTable>
        </AppCard>
      </div>
    </div>
  </AdminLayout>
</template>
