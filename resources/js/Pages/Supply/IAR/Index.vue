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

const props = defineProps({ iars: Array })

const search = ref('')
const filterStatus = ref('')

const PER_PAGE = 15
const currentPage = ref(1)

const filtered = computed(() => {
  let list = props.iars
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(i =>
      i.iar_number.toLowerCase().includes(q) ||
      i.supplier_name.toLowerCase().includes(q) ||
      (i.po_number || '').toLowerCase().includes(q)
    )
  }
  if (filterStatus.value) {
    list = list.filter(i => i.status === filterStatus.value)
  }
  return list
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

function statusColor(status) {
  const map = { draft: 'slate', inspected: 'blue', accepted: 'green', partial: 'amber' }
  return map[status] ?? 'slate'
}

function fmtDate(d) {
  return d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
}
</script>

<template>
  <Head title="Inspection & Acceptance Reports" />
  <AdminLayout title="Inspection & Acceptance Reports (IAR)">
    <div class="space-y-5">

      <AppPageHeader title="Inspection & Acceptance Reports (IAR)" subtitle="Track deliveries, inspections, and acceptance into stock.">
        <template #actions>
          <AppButton as="link" :href="route('supply.iar.create')">
            <PlusIcon class="h-4 w-4" />
            New IAR
          </AppButton>
        </template>
      </AppPageHeader>

      <AppFilterBar>
        <div class="relative w-full sm:w-96">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input v-model="search" @input="currentPage=1" type="text" placeholder="Search IAR number, supplier…"
            class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <select v-model="filterStatus" @change="currentPage=1"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Status</option>
          <option value="draft">Draft</option>
          <option value="inspected">Inspected</option>
          <option value="accepted">Accepted</option>
          <option value="partial">Partially Accepted</option>
        </select>
      </AppFilterBar>

      <AppTable :is-empty="!displayed.length" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">IAR No.</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">PO Reference</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Supplier</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Delivery Date</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Inspector</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
          </tr>
        </template>

        <tr v-for="iar in displayed" :key="iar.id" class="hover:bg-slate-50 cursor-pointer"
          @click="$inertia.visit(route('supply.iar.show', iar.id))">
          <td class="px-4 py-3 text-sm font-medium text-indigo-700">{{ iar.iar_number }}</td>
          <td class="px-4 py-3 text-sm text-slate-600">{{ iar.po_number ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-800">{{ iar.supplier_name }}</td>
          <td class="px-4 py-3 text-sm text-slate-600">{{ fmtDate(iar.delivery_date) }}</td>
          <td class="px-4 py-3 text-sm text-slate-600">{{ iar.inspector_name ?? '—' }}</td>
          <td class="px-4 py-3 text-center">
            <AppBadge :color="statusColor(iar.status)">{{ iar.status_label }}</AppBadge>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="iar in displayed" :key="iar.id" class="p-4 space-y-2 cursor-pointer"
            @click="$inertia.visit(route('supply.iar.show', iar.id))">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-sm font-medium text-indigo-700">{{ iar.iar_number }}</p>
                <p class="text-sm text-slate-800">{{ iar.supplier_name }}</p>
                <p class="text-xs text-slate-400">PO: {{ iar.po_number ?? '—' }}</p>
              </div>
              <AppBadge :color="statusColor(iar.status)">{{ iar.status_label }}</AppBadge>
            </div>
            <div class="flex justify-between text-xs text-slate-500">
              <span>{{ fmtDate(iar.delivery_date) }}</span>
              <span>{{ iar.inspector_name ?? '—' }}</span>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No IARs found." />
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
