<script setup>
import { ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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

const statusColors = {
  draft: 'bg-slate-100 text-slate-600',
  inspected: 'bg-blue-100 text-blue-700',
  accepted: 'bg-emerald-100 text-emerald-700',
  partial: 'bg-amber-100 text-amber-700',
}
</script>

<template>
  <Head title="Inspection & Acceptance Reports" />
  <AdminLayout title="Inspection & Acceptance Reports (IAR)">
    <div class="flex flex-wrap gap-3 items-center mb-4">
      <div class="relative flex-1 min-w-48">
        <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
        <input v-model="search" @input="currentPage=1" type="text" placeholder="Search IAR number, supplier…"
          class="pl-9 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
      </div>
      <select v-model="filterStatus" @change="currentPage=1"
        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="">All Status</option>
        <option value="draft">Draft</option>
        <option value="inspected">Inspected</option>
        <option value="accepted">Accepted</option>
        <option value="partial">Partially Accepted</option>
      </select>
      <Link :href="route('supply.iar.create')"
        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 ml-auto">
        <PlusIcon class="h-4 w-4" /> New IAR
      </Link>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="min-w-full divide-y divide-slate-100">
        <thead>
          <tr class="bg-slate-50">
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">IAR No.</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">PO Reference</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Supplier</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Delivery Date</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Inspector</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-if="displayed.length === 0">
            <td colspan="6" class="px-4 py-8 text-center text-slate-400 text-sm">No IARs found.</td>
          </tr>
          <tr v-for="iar in displayed" :key="iar.id" class="hover:bg-slate-50 cursor-pointer"
            @click="$inertia.visit(route('supply.iar.show', iar.id))">
            <td class="px-4 py-3 text-sm font-medium text-indigo-700">{{ iar.iar_number }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ iar.po_number ?? '—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-800">{{ iar.supplier_name }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">
              {{ iar.delivery_date ? new Date(iar.delivery_date).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' }) : '—' }}
            </td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ iar.inspector_name ?? '—' }}</td>
            <td class="px-4 py-3 text-center">
              <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium"
                :class="statusColors[iar.status] ?? 'bg-slate-100 text-slate-600'">
                {{ iar.status_label }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>

      <PaginationControl
        v-if="totalPages > 1"
        :current-page="currentPage"
        :total-pages="totalPages"
        @prev="currentPage--"
        @next="currentPage++"
        @page="currentPage = $event"
      />
    </div>
  </AdminLayout>
</template>
