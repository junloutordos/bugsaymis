<script setup>
import { ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ par_list: Array })
const search = ref('')
const PER_PAGE = 15
const currentPage = ref(1)

const filtered = computed(() => {
  if (!search.value) return props.par_list
  const q = search.value.toLowerCase()
  return props.par_list.filter(i => i.par_number.toLowerCase().includes(q) || (i.received_by||'').toLowerCase().includes(q))
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => filtered.value.slice((currentPage.value-1)*PER_PAGE, currentPage.value*PER_PAGE))

const statusColors = { active:'bg-emerald-100 text-emerald-700', returned:'bg-amber-100 text-amber-700', transferred:'bg-blue-100 text-blue-700', superseded:'bg-slate-100 text-slate-600' }
</script>
<template>
  <Head title="Property Acknowledgment Receipts" />
  <AdminLayout title="Property Acknowledgment Receipts (PAR)">
    <div class="flex flex-wrap gap-3 items-center mb-4">
      <div class="relative flex-1 min-w-48"><MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400"/><input v-model="search" @input="currentPage=1" type="text" placeholder="Search PAR number, recipient…" class="pl-9 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"/></div>
      <Link :href="route('property.par.create')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 ml-auto"><PlusIcon class="h-4 w-4"/>New PAR</Link>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="min-w-full divide-y divide-slate-100">
        <thead><tr class="bg-slate-50">
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">PAR No.</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Date</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Issued By</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Received By</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Division</th>
          <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Total Amount</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Status</th>
        </tr></thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-if="displayed.length===0"><td colspan="7" class="px-4 py-8 text-center text-slate-400 text-sm">No PAR records found.</td></tr>
          <tr v-for="par in displayed" :key="par.id" class="hover:bg-slate-50 cursor-pointer" @click="$inertia.visit(route('property.par.show',par.id))">
            <td class="px-4 py-3 text-sm font-medium text-indigo-700">{{ par.par_number }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ par.issue_date ? new Date(par.issue_date).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'}) : '—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ par.issued_by??'—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-800">{{ par.received_by }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ par.division_name??'—' }}</td>
            <td class="px-4 py-3 text-right text-sm font-medium">₱{{ Number(par.total_amount).toLocaleString('en-PH',{minimumFractionDigits:2}) }}</td>
            <td class="px-4 py-3 text-center"><span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium" :class="statusColors[par.status]??'bg-slate-100 text-slate-600'">{{ par.status_label }}</span></td>
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
