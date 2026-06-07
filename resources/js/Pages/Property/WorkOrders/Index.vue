<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon, MagnifyingGlassIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({ workOrders: Array, propertyItems: Array, officers: Array })

const search = ref('')
const filterStatus = ref('')
const PER_PAGE = 15
const currentPage = ref(1)
const showModal = ref(false)
const submitting = ref(false)
const errors = ref({})

const form = ref({ property_item_id:'', description:'', priority:'normal', assigned_to_id:'', requested_date: new Date().toISOString().split('T')[0], target_completion_date:'', estimated_cost:'', remarks:'' })

const STATUSES = ['pending','in_progress','completed','cancelled']
const PRIORITIES = ['low','normal','high','urgent']

const filtered = computed(() => {
  let list = props.workOrders
  if (filterStatus.value) list = list.filter(w => w.status===filterStatus.value)
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(w => w.work_order_number.toLowerCase().includes(q) || (w.description||'').toLowerCase().includes(q))
  }
  return list
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => filtered.value.slice((currentPage.value-1)*PER_PAGE, currentPage.value*PER_PAGE))

const statusColors = { pending:'bg-amber-100 text-amber-700', in_progress:'bg-blue-100 text-blue-700', completed:'bg-emerald-100 text-emerald-700', cancelled:'bg-slate-100 text-slate-600' }
const priorityColors = { low:'bg-slate-100 text-slate-600', normal:'bg-blue-50 text-blue-600', high:'bg-amber-100 text-amber-700', urgent:'bg-red-100 text-red-700' }

function openModal() { form.value = { property_item_id:'', description:'', priority:'normal', assigned_to_id:'', requested_date: new Date().toISOString().split('T')[0], target_completion_date:'', estimated_cost:'', remarks:'' }; errors.value={}; showModal.value=true }

async function submit() {
  submitting.value=true; errors.value={}
  try {
    await axios.post(route('property.work-orders.store'), form.value)
    showModal.value=false
    window.location.reload()
  } catch(e) {
    if (e.response?.status===422) errors.value = e.response.data.errors??{}
  } finally { submitting.value=false }
}

async function updateStatus(id, status) {
  await axios.patch(route('property.work-orders.update', id), { status })
  window.location.reload()
}
</script>
<template>
  <Head title="Work Orders" />
  <AdminLayout title="Work Orders">
    <div class="flex flex-wrap gap-3 items-center mb-4">
      <div class="relative flex-1 min-w-48"><MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400"/><input v-model="search" @input="currentPage=1" type="text" placeholder="Search work order or description…" class="pl-9 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"/></div>
      <select v-model="filterStatus" @change="currentPage=1" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"><option value="">All Statuses</option><option v-for="s in STATUSES" :key="s" :value="s">{{ s.replace('_',' ') }}</option></select>
      <button @click="openModal" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 ml-auto"><PlusIcon class="h-4 w-4"/>New Work Order</button>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="min-w-full divide-y divide-slate-100">
        <thead><tr class="bg-slate-50">
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">WO No.</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Description</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Property</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Priority</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Assigned To</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Target Date</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Status</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Actions</th>
        </tr></thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-if="displayed.length===0"><td colspan="8" class="px-4 py-8 text-center text-slate-400 text-sm">No work orders found.</td></tr>
          <tr v-for="wo in displayed" :key="wo.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 text-sm font-medium text-indigo-700">{{ wo.work_order_number }}</td>
            <td class="px-4 py-3 text-sm text-slate-800 max-w-xs truncate">{{ wo.description }}</td>
            <td class="px-4 py-3 text-sm text-slate-600 font-mono">{{ wo.property_number??'—' }}</td>
            <td class="px-4 py-3 text-center"><span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium capitalize" :class="priorityColors[wo.priority]??'bg-slate-100 text-slate-600'">{{ wo.priority }}</span></td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ wo.assigned_to??'—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ wo.target_completion_date ? new Date(wo.target_completion_date).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'}) : '—' }}</td>
            <td class="px-4 py-3 text-center"><span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium capitalize" :class="statusColors[wo.status]??'bg-slate-100 text-slate-600'">{{ wo.status.replace('_',' ') }}</span></td>
            <td class="px-4 py-3 text-center">
              <select v-if="wo.status!=='completed'&&wo.status!=='cancelled'" @change="e=>updateStatus(wo.id,e.target.value)" :value="wo.status" class="text-xs rounded border border-slate-200 bg-white px-2 py-1 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                <option v-for="s in STATUSES" :key="s" :value="s">{{ s.replace('_',' ') }}</option>
              </select>
              <span v-else class="text-xs text-slate-400">—</span>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="totalPages>1" class="px-4 py-3 border-t border-slate-100 flex items-center justify-between text-sm text-slate-600">
        <span>Page {{ currentPage }} of {{ totalPages }}</span>
        <div class="flex gap-2"><button @click="currentPage--" :disabled="currentPage<=1" class="px-3 py-1 rounded border border-slate-200 disabled:opacity-40">Prev</button><button @click="currentPage++" :disabled="currentPage>=totalPages" class="px-3 py-1 rounded border border-slate-200 disabled:opacity-40">Next</button></div>
      </div>
    </div>

    <!-- New Work Order Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4 overflow-auto">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg my-4">
        <div class="flex items-center justify-between p-6 border-b border-slate-100">
          <h3 class="text-base font-semibold text-slate-800">New Work Order</h3>
          <button @click="showModal=false" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
        </div>
        <form @submit.prevent="submit" class="p-6 space-y-4">
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Description *</label><textarea v-model="form.description" required rows="2" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"></textarea></div>
          <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2"><label class="block text-sm font-medium text-slate-700 mb-1">Property Item</label><select v-model="form.property_item_id" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"><option value="">— Not linked —</option><option v-for="p in propertyItems" :key="p.id" :value="p.id">{{ p.property_number }} — {{ p.description }}</option></select></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-1">Priority</label><select v-model="form.priority" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"><option v-for="p in PRIORITIES" :key="p" :value="p">{{ p }}</option></select></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-1">Assigned To</label><select v-model="form.assigned_to_id" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"><option value="">— Unassigned —</option><option v-for="u in officers" :key="u.id" :value="u.id">{{ u.name }}</option></select></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-1">Requested Date *</label><input v-model="form.requested_date" type="date" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"/></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-1">Target Completion</label><input v-model="form.target_completion_date" type="date" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"/></div>
            <div class="col-span-2"><label class="block text-sm font-medium text-slate-700 mb-1">Estimated Cost (₱)</label><input v-model="form.estimated_cost" type="number" step="0.01" min="0" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"/></div>
            <div class="col-span-2"><label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label><textarea v-model="form.remarks" rows="1" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"></textarea></div>
          </div>
          <div v-if="Object.keys(errors).length" class="text-xs text-red-600">{{ Object.values(errors).flat().join('; ') }}</div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="showModal=false" class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
            <button type="submit" :disabled="submitting" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg text-sm font-medium disabled:opacity-60">{{ submitting?'Saving…':'Create' }}</button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
