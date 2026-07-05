<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppInput from '@/Components/AppInput.vue'
import AppButton from '@/Components/AppButton.vue'
import { DocumentArrowDownIcon, TableCellsIcon } from '@heroicons/vue/24/outline'

const asOf = ref(new Date().toISOString().split('T')[0])
</script>
<template>
  <Head title="Property Reports" />
  <AdminLayout title="Property Reports">
    <div class="space-y-5">

      <AppPageHeader title="Property Reports" subtitle="Generate COA-compliant property and inventory reports." />

      <AppFilterBar>
        <AppInput v-model="asOf" type="date" label="As of Date (RPCI / RPCPPE)" />
      </AppFilterBar>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

        <!-- RPCI -->
        <AppCard>
          <div class="flex items-start gap-4 mb-4">
            <div class="p-3 bg-indigo-50 rounded-xl"><TableCellsIcon class="h-6 w-6 text-indigo-600"/></div>
            <div>
              <h3 class="font-semibold text-slate-800">RPCI</h3>
              <p class="text-sm text-slate-500 mt-0.5">Report on Physical Count of Inventories</p>
              <p class="text-xs text-slate-400 mt-1">GAM Form No. 21 — COA Circular 2015-010</p>
            </div>
          </div>
          <AppButton as="a" :href="route('property.reports.rpci-pdf')+'?as_of='+asOf" target="_blank" block>
            <DocumentArrowDownIcon class="h-4 w-4"/>Generate PDF
          </AppButton>
        </AppCard>

        <!-- RPCPPE -->
        <AppCard>
          <div class="flex items-start gap-4 mb-4">
            <div class="p-3 bg-emerald-50 rounded-xl"><TableCellsIcon class="h-6 w-6 text-emerald-600"/></div>
            <div>
              <h3 class="font-semibold text-slate-800">RPCPPE</h3>
              <p class="text-sm text-slate-500 mt-0.5">Report on Physical Count of PPE</p>
              <p class="text-xs text-slate-400 mt-1">GAM Form No. 22 — COA Circular 2015-010</p>
            </div>
          </div>
          <AppButton as="a" :href="route('property.reports.rpcppe-pdf')+'?as_of='+asOf" target="_blank" block>
            <DocumentArrowDownIcon class="h-4 w-4"/>Generate PDF
          </AppButton>
        </AppCard>

        <!-- Depreciation Schedule (individual) -->
        <AppCard>
          <div class="flex items-start gap-4 mb-4">
            <div class="p-3 bg-amber-50 rounded-xl"><DocumentArrowDownIcon class="h-6 w-6 text-amber-600"/></div>
            <div>
              <h3 class="font-semibold text-slate-800">Depreciation Schedule</h3>
              <p class="text-sm text-slate-500 mt-0.5">Straight-line lapsing schedule per property item</p>
              <p class="text-xs text-slate-400 mt-1">5% residual value, monthly computation</p>
            </div>
          </div>
          <p class="text-sm text-slate-500 text-center py-4">Open a property item and click <strong>Print Schedule</strong> to generate the depreciation lapsing schedule for that item.</p>
          <AppButton as="link" :href="route('property.items.index')" block>
            View Property Items
          </AppButton>
        </AppCard>

      </div>

      <!-- Info box -->
      <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm text-slate-600">
        <strong class="text-slate-700">Notes:</strong>
        <ul class="list-disc ml-5 mt-1 space-y-1">
          <li>RPCI covers semi-expendable supplies and equipment with unit cost below the PPE threshold (₱15,000).</li>
          <li>RPCPPE covers Property, Plant &amp; Equipment with unit cost ≥ ₱15,000 — includes accumulated depreciation and book values.</li>
          <li>Depreciation is computed using straight-line method with 5% residual value. Depreciation starts the first day of the month following acquisition.</li>
          <li>All reports conform to COA Circular 2015-010 (Government Accounting Manual for NGAs).</li>
        </ul>
      </div>

    </div>
  </AdminLayout>
</template>
