<template>
  <Head title="Hazard Report" />
  <AdminLayout title="Hazard Report">
    <div class="space-y-5">

      <AppPageHeader title="Hazard Report" subtitle="Basis for Hazard Pay — Plantilla employees, Hazard Actual Exposure days per the selected period.">
        <template #actions>
          <AppButton as="a" :href="pdfHref" target="_blank" variant="secondary">
            <PrinterIcon class="h-4 w-4" />
            Download PDF
          </AppButton>
        </template>
      </AppPageHeader>

      <AppFilterBar :result-label="`${rows.length} plantilla employee(s)`">
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">From</label>
          <input
            v-model="filterDateFrom"
            type="date"
            @change="applyRange"
            class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white"
          />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">To</label>
          <input
            v-model="filterDateTo"
            type="date"
            @change="applyRange"
            class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-white"
          />
        </div>
      </AppFilterBar>

      <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide bg-slate-50">
              <th class="px-4 py-2.5 text-left">#</th>
              <th class="px-4 py-2.5 text-left">Employee Name</th>
              <th class="px-4 py-2.5 text-left">Category</th>
              <th class="px-4 py-2.5 text-center">Full Days (&ge;6h)</th>
              <th class="px-4 py-2.5 text-center">Half Days (4&ndash;6h)</th>
              <th class="px-4 py-2.5 text-center">Total Hazard Actual Exposure Days</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="(row, i) in rows" :key="row.user_id" class="hover:bg-slate-50">
              <td class="px-4 py-2.5 text-slate-500">{{ i + 1 }}</td>
              <td class="px-4 py-2.5 font-medium text-slate-800">{{ row.name }}</td>
              <td class="px-4 py-2.5 text-slate-600">{{ row.emp_category.includes('Non-Teaching') ? 'Non-Teaching' : 'Teaching' }}</td>
              <td class="px-4 py-2.5 text-center">{{ row.full_days }}</td>
              <td class="px-4 py-2.5 text-center">{{ row.half_days }}</td>
              <td class="px-4 py-2.5 text-center font-semibold text-indigo-700">{{ row.total_hazard_days }}</td>
            </tr>
            <tr v-if="!rows.length">
              <td colspan="6" class="px-4 py-8 text-center text-slate-400">No active Plantilla employees found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <p class="text-xs text-slate-500">
        A day counts as 1.0 Hazard Actual Exposure day at 6+ effective hours present, 0.5 day at 4&ndash;6 hours,
        and is not counted below 4 hours. Work-from-home days, official travel days, and self-declared (penned)
        time not yet reviewed by HR are excluded.
      </p>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import { PrinterIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  rows:      Array,
  date_from: String,
  date_to:   String,
})

const filterDateFrom = ref(props.date_from)
const filterDateTo   = ref(props.date_to)

function applyRange () {
  if (!filterDateFrom.value || !filterDateTo.value) return
  router.get(route('hr.dtr.hazard-report'), {
    date_from: filterDateFrom.value,
    date_to:   filterDateTo.value,
  }, { preserveState: true, replace: true })
}

const pdfHref = computed(() => route('hr.dtr.hazard-report.pdf', {
  date_from: filterDateFrom.value,
  date_to:   filterDateTo.value,
}))
</script>
