<script setup>
import { ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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

const statusColors = {
  draft: 'bg-slate-100 text-slate-600',
  pending: 'bg-amber-100 text-amber-700',
  approved: 'bg-blue-100 text-blue-700',
  partially_issued: 'bg-violet-100 text-violet-700',
  fully_issued: 'bg-emerald-100 text-emerald-700',
  cancelled: 'bg-red-100 text-red-700',
}
</script>

<template>
  <Head title="Requisition & Issue Slips" />
  <AdminLayout title="Requisition & Issue Slips (RIS)">
    <div class="flex flex-wrap gap-3 items-center mb-4">
      <div class="relative flex-1 min-w-48">
        <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
        <input v-model="search" @input="currentPage=1" type="text" placeholder="Search RIS number, purpose…"
          class="pl-9 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
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
      <Link :href="route('supply.ris.create')"
        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 ml-auto">
        <PlusIcon class="h-4 w-4" /> New RIS
      </Link>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="min-w-full divide-y divide-slate-100">
        <thead>
          <tr class="bg-slate-50">
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">RIS No.</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Division</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Purpose</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Requested By</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-if="displayed.length === 0">
            <td colspan="6" class="px-4 py-8 text-center text-slate-400 text-sm">No RIS records found.</td>
          </tr>
          <tr v-for="ris in displayed" :key="ris.id" class="hover:bg-slate-50 cursor-pointer"
            @click="$inertia.visit(route('supply.ris.show', ris.id))">
            <td class="px-4 py-3 text-sm font-medium text-indigo-700">{{ ris.ris_number }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ ris.division_name ?? '—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-800 max-w-xs truncate">{{ ris.purpose }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ ris.requested_by }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">
              {{ ris.date_requested ? new Date(ris.date_requested).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' }) : '—' }}
            </td>
            <td class="px-4 py-3 text-center">
              <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium"
                :class="statusColors[ris.status] ?? 'bg-slate-100 text-slate-600'">
                {{ ris.status_label }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="totalPages > 1" class="px-4 py-3 border-t border-slate-100 flex items-center justify-between text-sm text-slate-600">
        <span>Page {{ currentPage }} of {{ totalPages }}</span>
        <div class="flex gap-2">
          <button @click="currentPage--" :disabled="currentPage <= 1"
            class="px-3 py-1 rounded border border-slate-200 disabled:opacity-40">Prev</button>
          <button @click="currentPage++" :disabled="currentPage >= totalPages"
            class="px-3 py-1 rounded border border-slate-200 disabled:opacity-40">Next</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
