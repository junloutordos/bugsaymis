<script setup>
import { ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ ics_list: Array })
const search = ref('')
const PER_PAGE = 15
const currentPage = ref(1)

const filtered = computed(() => {
  if (!search.value) return props.ics_list
  const q = search.value.toLowerCase()
  return props.ics_list.filter(i => i.ics_number.toLowerCase().includes(q) || (i.received_by||'').toLowerCase().includes(q))
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => filtered.value.slice((currentPage.value-1)*PER_PAGE, currentPage.value*PER_PAGE))

const statusColors = { active:'bg-emerald-100 text-emerald-700', returned:'bg-amber-100 text-amber-700', superseded:'bg-slate-100 text-slate-600' }
</script>
<template>
  <Head title="Inventory Custodian Slips" />
  <AdminLayout title="Inventory Custodian Slips (ICS)">
    <div class="flex flex-wrap gap-3 items-center mb-4">
      <div class="relative flex-1 min-w-48"><MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400"/><input v-model="search" @input="currentPage=1" type="text" placeholder="Search ICS number, recipient…" class="pl-9 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"/></div>
      <Link :href="route('property.ics.create')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 ml-auto"><PlusIcon class="h-4 w-4"/>New ICS</Link>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="min-w-full divide-y divide-slate-100">
        <thead><tr class="bg-slate-50">
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">ICS No.</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Date</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Issued By</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Received By</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Division</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Status</th>
        </tr></thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-if="displayed.length===0"><td colspan="6" class="px-4 py-8 text-center text-slate-400 text-sm">No ICS records found.</td></tr>
          <tr v-for="ics in displayed" :key="ics.id" class="hover:bg-slate-50 cursor-pointer" @click="$inertia.visit(route('property.ics.show',ics.id))">
            <td class="px-4 py-3 text-sm font-medium text-indigo-700">{{ ics.ics_number }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ ics.issue_date ? new Date(ics.issue_date).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'}) : '—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ ics.issued_by??'—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-800">{{ ics.received_by }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ ics.division_name??'—' }}</td>
            <td class="px-4 py-3 text-center"><span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium" :class="statusColors[ics.status]??'bg-slate-100 text-slate-600'">{{ ics.status_label }}</span></td>
          </tr>
        </tbody>
      </table>
      <div v-if="totalPages>1" class="px-4 py-3 border-t border-slate-100 flex items-center justify-between text-sm text-slate-600">
        <span>Page {{ currentPage }} of {{ totalPages }}</span>
        <div class="flex gap-2"><button @click="currentPage--" :disabled="currentPage<=1" class="px-3 py-1 rounded border border-slate-200 disabled:opacity-40">Prev</button><button @click="currentPage++" :disabled="currentPage>=totalPages" class="px-3 py-1 rounded border border-slate-200 disabled:opacity-40">Next</button></div>
      </div>
    </div>
  </AdminLayout>
</template>
