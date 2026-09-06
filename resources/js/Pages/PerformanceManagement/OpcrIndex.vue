<script setup>
import { Head, Link } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppTable from "@/Components/AppTable.vue"
import EmptyState from "@/Components/EmptyState.vue"
import { DocumentArrowDownIcon, EyeIcon } from "@heroicons/vue/24/outline"

const props = defineProps({
  years: { type: Array, default: () => [] },
  currentFiscalYear: { type: Number, default: null },
  canManage: { type: Boolean, default: false },
})
</script>

<template>
  <Head title="OPCR" />
  <AdminLayout title="Office Performance Commitment and Review (OPCR)">
    <div class="space-y-5">
      <AppPageHeader title="OPCR" subtitle="Every fiscal year's Office Performance Commitment and Review" />

      <AppTable :is-empty="years.length === 0" :skeleton-cols="4">
        <template #head>
          <tr>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Fiscal Year</th>
            <th class="px-3 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase">Indicators</th>
            <th class="px-3 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Actions</th>
          </tr>
        </template>

        <tr v-for="row in years" :key="row.year" class="hover:bg-indigo-50/40">
          <td class="px-3 py-2 text-sm font-medium text-slate-700">
            FY {{ row.year }}
            <span v-if="row.is_current" class="ml-2 rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">Current</span>
          </td>
          <td class="px-3 py-2 text-sm text-slate-700">{{ row.indicator_count }}</td>
          <td class="px-3 py-2">
            <div class="flex items-center justify-center gap-3">
              <Link :href="route('opcr.index', { fiscal_year: row.year })" class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:underline">
                <EyeIcon class="w-4 h-4" /> View
              </Link>
              <a :href="route('opcr.pdf', row.year)" target="_blank" class="inline-flex items-center gap-1 text-xs font-medium text-slate-600 hover:underline">
                <DocumentArrowDownIcon class="w-4 h-4" /> PDF
              </a>
            </div>
          </td>
        </tr>

        <template #empty>
          <EmptyState title="No OPCR fiscal years yet" subtitle="OPCR indicators are sourced from Performance Indicators tagged to a PSHS Program." />
        </template>
      </AppTable>
    </div>
  </AdminLayout>
</template>
