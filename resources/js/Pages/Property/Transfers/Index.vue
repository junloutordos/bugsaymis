<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon, MagnifyingGlassIcon, XMarkIcon, CheckIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({ transfers: Array, propertyItems: Array, officers: Array })

const search = ref('')
const PER_PAGE = 15
const currentPage = ref(1)
const showModal = ref(false)
const submitting = ref(false)
const errors = ref({})

const form = ref({ transfer_date: new Date().toISOString().split('T')[0], from_officer_id:'', to_officer_id:'', remarks:'', items:[] })

const filtered = computed(() => {
  if (!search.value) return props.transfers
  const q = search.value.toLowerCase()
  return props.transfers.filter(t =>
    t.transfer_number.toLowerCase().includes(q) ||
    (t.from_officer||'').toLowerCase().includes(q) ||
    (t.to_officer||'').toLowerCase().includes(q)
  )
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => filtered.value.slice((currentPage.value-1)*PER_PAGE, currentPage.value*PER_PAGE))

const statusColors = { pending:'bg-amber-100 text-amber-700', completed:'bg-emerald-100 text-emerald-700', cancelled:'bg-slate-100 text-slate-600' }

function openModal() { form.value = { transfer_date: new Date().toISOString().split('T')[0], from_officer_id:'', to_officer_id:'', remarks:'', items:[] }; errors.value={}; showModal.value=true }
function addItem() { form.value.items.push({ property_item_id:'' }) }
function removeItem(idx) { form.value.items.splice(idx,1) }

async function submit() {
  if (!form.value.items.length) { alert('Add at least one property item.'); return }
  submitting.value=true; errors.value={}
  try {
    await axios.post(route('property.transfers.store'), form.value)
    showModal.value=false
    window.location.reload()
  } catch(e) {
    if (e.response?.status===422) errors.value = e.response.data.errors??{}
  } finally { submitting.value=false }
}

async function complete(id) {
  if (!confirm('Mark this transfer as completed? This will update accountability records.')) return
  await axios.post(route('property.transfers.complete', id))
  window.location.reload()
}

async function cancel(id) {
  if (!confirm('Cancel this transfer?')) return
  await axios.post(route('property.transfers.cancel', id))
  window.location.reload()
}
</script>
<template>
  <Head title="Property Transfers" />
  <AdminLayout title="Property Transfers">
    <div class="flex flex-wrap gap-3 items-center mb-4">
      <div class="relative flex-1 min-w-48"><MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400"/><input v-model="search" @input="currentPage=1" type="text" placeholder="Search transfer number or officer…" class="pl-9 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"/></div>
      <button @click="openModal" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 ml-auto"><PlusIcon class="h-4 w-4"/>New Transfer</button>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="min-w-full divide-y divide-slate-100">
        <thead><tr class="bg-slate-50">
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Transfer No.</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Date</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">From</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">To</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Items</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Status</th>
          <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Actions</th>
        </tr></thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-if="displayed.length===0"><td colspan="7" class="px-4 py-8 text-center text-slate-400 text-sm">No transfer records found.</td></tr>
          <tr v-for="t in displayed" :key="t.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 text-sm font-medium text-indigo-700">{{ t.transfer_number }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ t.transfer_date ? new Date(t.transfer_date).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'}) : '—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-600">{{ t.from_officer??'—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-800">{{ t.to_officer??'—' }}</td>
            <td class="px-4 py-3 text-center text-sm text-slate-600">{{ t.items_count }}</td>
            <td class="px-4 py-3 text-center"><span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium" :class="statusColors[t.status]??'bg-slate-100 text-slate-600'">{{ t.status }}</span></td>
            <td class="px-4 py-3 text-center">
              <div v-if="t.status==='pending'" class="flex justify-center gap-2">
                <button @click="complete(t.id)" class="inline-flex items-center gap-1 text-xs bg-emerald-50 hover:bg-emerald-100 text-emerald-700 px-2 py-1 rounded"><CheckIcon class="h-3 w-3"/>Complete</button>
                <button @click="cancel(t.id)" class="inline-flex items-center gap-1 text-xs bg-red-50 hover:bg-red-100 text-red-600 px-2 py-1 rounded"><XMarkIcon class="h-3 w-3"/>Cancel</button>
              </div>
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

    <!-- New Transfer Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4 overflow-auto">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl my-4">
        <div class="flex items-center justify-between p-6 border-b border-slate-100">
          <h3 class="text-base font-semibold text-slate-800">New Property Transfer</h3>
          <button @click="showModal=false" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
        </div>
        <form @submit.prevent="submit" class="p-6 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-slate-700 mb-1">Transfer Date *</label><input v-model="form.transfer_date" type="date" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"/></div>
            <div></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-1">From Officer *</label><select v-model="form.from_officer_id" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"><option value="">— Select —</option><option v-for="u in officers" :key="u.id" :value="u.id">{{ u.name }}</option></select></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-1">To Officer *</label><select v-model="form.to_officer_id" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"><option value="">— Select —</option><option v-for="u in officers" :key="u.id" :value="u.id">{{ u.name }}</option></select></div>
            <div class="col-span-2"><label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label><textarea v-model="form.remarks" rows="2" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"></textarea></div>
          </div>
          <div>
            <div class="flex items-center justify-between mb-2"><span class="text-sm font-semibold text-slate-700">Property Items</span><button type="button" @click="addItem" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">+ Add Item</button></div>
            <p v-if="!form.items.length" class="text-xs text-slate-400 text-center py-3">No items added.</p>
            <div v-for="(item,idx) in form.items" :key="idx" class="flex gap-2 mb-2">
              <select v-model="item.property_item_id" required class="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"><option value="">— Select property item —</option><option v-for="p in propertyItems" :key="p.id" :value="p.id">{{ p.property_number }} — {{ p.description }}</option></select>
              <button type="button" @click="removeItem(idx)" class="text-red-400 hover:text-red-600 px-2"><XMarkIcon class="h-4 w-4"/></button>
            </div>
          </div>
          <div v-if="errors.items" class="text-xs text-red-600">{{ errors.items }}</div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="showModal=false" class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
            <button type="submit" :disabled="submitting" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg text-sm font-medium disabled:opacity-60">{{ submitting?'Saving…':'Create Transfer' }}</button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
