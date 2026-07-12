<template>
  <Head title="Property Acknowledgment Receipts" />
  <AdminLayout title="Property Acknowledgment Receipts (PAR)">
    <div class="space-y-5">

      <AppPageHeader title="Property Acknowledgment Receipts (PAR)" subtitle="Track issued acknowledgment receipts for accountable properties.">
        <template #actions>
          <AppButton as="link" :href="route('property.par.create')">
            <PlusIcon class="h-4 w-4" />
            New PAR
          </AppButton>
        </template>
      </AppPageHeader>

      <AppFilterBar>
        <div class="relative w-full sm:w-96">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input
            v-model="search"
            @input="currentPage = 1"
            type="text"
            placeholder="Search PAR number, recipient…"
            class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
        </div>
      </AppFilterBar>

      <AppTable :is-empty="!displayed.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">PAR No.</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Issued By</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Received By</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Division</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Amount</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
          </tr>
        </template>

        <tr v-for="par in displayed" :key="par.id" class="hover:bg-indigo-50/40 cursor-pointer" @click="router.visit(route('property.par.show', par.id))">
          <td class="px-4 py-3 text-sm font-medium text-indigo-700">{{ par.par_number }}</td>
          <td class="px-4 py-3 text-sm text-slate-600">{{ fmtDate(par.issue_date) }}</td>
          <td class="px-4 py-3 text-sm text-slate-600">{{ par.issued_by ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-800">{{ par.received_by }}</td>
          <td class="px-4 py-3 text-sm text-slate-600">{{ par.division_name ?? '—' }}</td>
          <td class="px-4 py-3 text-right text-sm font-medium">₱{{ Number(par.total_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="statusColor(par.status)">{{ par.status_label }}</AppBadge>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="par in displayed" :key="par.id" class="p-4 space-y-2 cursor-pointer" @click="router.visit(route('property.par.show', par.id))">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-sm font-medium text-indigo-700">{{ par.par_number }}</p>
                <p class="text-sm text-slate-800">{{ par.received_by }}</p>
                <p class="text-xs text-slate-500">{{ par.division_name ?? '—' }}</p>
              </div>
              <AppBadge :color="statusColor(par.status)">{{ par.status_label }}</AppBadge>
            </div>
            <div class="flex justify-between text-xs text-slate-500">
              <span>{{ fmtDate(par.issue_date) }}</span>
              <span class="font-semibold text-slate-800">₱{{ Number(par.total_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No PAR records found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            @prev="currentPage--"
            @next="currentPage++"
            @page="currentPage = $event"
          />
        </template>
      </AppTable>

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
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { PlusIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ par_list: Array })
const search = ref('')
const PER_PAGE = 15
const currentPage = ref(1)

const filtered = computed(() => {
  if (!search.value) return props.par_list
  const q = search.value.toLowerCase()
  return props.par_list.filter(i => i.par_number.toLowerCase().includes(q) || (i.received_by || '').toLowerCase().includes(q))
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => filtered.value.slice((currentPage.value - 1) * PER_PAGE, currentPage.value * PER_PAGE))

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

function statusColor(status) {
  const map = { active: 'green', returned: 'amber', transferred: 'blue', superseded: 'slate' }
  return map[status] ?? 'slate'
}
</script>
