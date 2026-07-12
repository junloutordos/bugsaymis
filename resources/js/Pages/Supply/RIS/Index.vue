<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { PlusIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ ris_list: Array })

const search = ref('')
const filterStatus = ref('')

const PER_PAGE = 15
const currentPage = ref(1)

const filtered = computed(() => {
  let list = props.ris_list
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(r =>
      r.ris_number.toLowerCase().includes(q) ||
      r.purpose.toLowerCase().includes(q) ||
      (r.division_name || '').toLowerCase().includes(q)
    )
  }
  if (filterStatus.value) {
    list = list.filter(r => r.status === filterStatus.value)
  }
  return list
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

function statusColor(status) {
  const map = {
    draft: 'slate',
    pending: 'amber',
    approved: 'blue',
    partially_issued: 'purple',
    fully_issued: 'green',
    cancelled: 'red',
  }
  return map[status] ?? 'slate'
}

function fmtDate(d) {
  return d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
}
</script>

<template>
  <Head title="Requisition & Issue Slips" />
  <AdminLayout title="Requisition & Issue Slips (RIS)">
    <div class="space-y-5">

      <AppPageHeader title="Requisition & Issue Slips (RIS)" subtitle="Track supply requisitions from request through issuance.">
        <template #actions>
          <AppButton as="link" :href="route('supply.ris.create')">
            <PlusIcon class="h-4 w-4" />
            New RIS
          </AppButton>
        </template>
      </AppPageHeader>

      <AppFilterBar>
        <div class="relative w-full sm:w-96">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input v-model="search" @input="currentPage=1" type="text" placeholder="Search RIS number, purpose…"
            class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <select v-model="filterStatus" @change="currentPage=1"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Status</option>
          <option value="draft">Draft</option>
          <option value="pending">Pending Approval</option>
          <option value="approved">Approved</option>
          <option value="partially_issued">Partially Issued</option>
          <option value="fully_issued">Fully Issued</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </AppFilterBar>

      <AppTable :is-empty="!displayed.length" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">RIS No.</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Division</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Purpose</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Requested By</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Date</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
          </tr>
        </template>

        <tr v-for="ris in displayed" :key="ris.id" class="hover:bg-slate-50 cursor-pointer"
          @click="$inertia.visit(route('supply.ris.show', ris.id))">
          <td class="px-4 py-3 text-sm font-medium text-indigo-700">{{ ris.ris_number }}</td>
          <td class="px-4 py-3 text-sm text-slate-600">{{ ris.division_name ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-800 max-w-xs truncate">{{ ris.purpose }}</td>
          <td class="px-4 py-3 text-sm text-slate-600">{{ ris.requested_by }}</td>
          <td class="px-4 py-3 text-sm text-slate-600">{{ fmtDate(ris.date_requested) }}</td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="statusColor(ris.status)">{{ ris.status_label }}</AppBadge>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="ris in displayed" :key="ris.id" class="p-4 space-y-2 cursor-pointer"
            @click="$inertia.visit(route('supply.ris.show', ris.id))">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-sm font-medium text-indigo-700">{{ ris.ris_number }}</p>
                <p class="text-sm text-slate-800 truncate">{{ ris.purpose }}</p>
                <p class="text-xs text-slate-400">{{ ris.division_name ?? '—' }}</p>
              </div>
              <AppBadge :color="statusColor(ris.status)">{{ ris.status_label }}</AppBadge>
            </div>
            <div class="flex justify-between text-xs text-slate-500">
              <span>{{ ris.requested_by }}</span>
              <span>{{ fmtDate(ris.date_requested) }}</span>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No RIS records found." />
        </template>

        <template #footer>
          <PaginationControl
            v-if="totalPages > 1"
            :current-page="currentPage"
            :total-pages="totalPages"
            :total="filtered.length"
            @prev="currentPage--"
            @next="currentPage++"
            @page="currentPage = $event"
          />
        </template>
      </AppTable>

    </div>
  </AdminLayout>
</template>
