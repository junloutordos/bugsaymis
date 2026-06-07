<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({ propertyItems: Array, officers: Array, divisions: Array })

const form = ref({ issue_date: new Date().toISOString().split('T')[0], issued_by:'', received_by:'', division_id:'', remarks:'', items:[] })
const errors = ref({})
const submitting = ref(false)

function addItem() { form.value.items.push({ property_item_id:'', description:'', unit:'', quantity:'', unit_cost:'', estimated_useful_life:'', remarks:'' }) }
function removeItem(idx) { form.value.items.splice(idx,1) }

function onPropSelect(idx) {
  const id = form.value.items[idx].property_item_id
  if (!id) return
  const found = props.propertyItems.find(p => p.id==id)
  if (found) {
    form.value.items[idx].description = found.description
    form.value.items[idx].unit = found.unit
    form.value.items[idx].unit_cost = found.unit_cost
  }
}

async function submit() {
  if (!form.value.items.length) { alert('Add at least one item.'); return }
  submitting.value = true; errors.value = {}
  try {
    await axios.post(route('property.ics.store'), form.value)
    router.visit(route('property.ics.index'))
  } catch(e) {
    if (e.response?.status===422) errors.value = e.response.data.errors??{}
  } finally { submitting.value=false }
}
</script>
<template>
  <Head title="New ICS" />
  <AdminLayout title="New Inventory Custodian Slip">
    <form @submit.prevent="submit" class="space-y-6">
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">ICS Details</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Issue Date *</label><input v-model="form.issue_date" type="date" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"/></div>
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Issued By</label><select v-model="form.issued_by" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"><option value="">— Current user —</option><option v-for="u in officers" :key="u.id" :value="u.id">{{ u.name }}</option></select></div>
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Received By *</label><select v-model="form.received_by" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"><option value="">— Select —</option><option v-for="u in officers" :key="u.id" :value="u.id">{{ u.name }}</option></select></div>
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Division</label><select v-model="form.division_id" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"><option value="">— Select —</option><option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.division_name }}</option></select></div>
          <div class="md:col-span-2"><label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label><textarea v-model="form.remarks" rows="1" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"></textarea></div>
        </div>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <div class="flex items-center justify-between mb-4"><h3 class="text-sm font-semibold text-slate-700">Items</h3><button type="button" @click="addItem" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium flex items-center gap-1"><PlusIcon class="h-4 w-4"/>Add</button></div>
        <p v-if="!form.items.length" class="text-sm text-slate-400 text-center py-4">No items. Click "Add" to start.</p>
        <div v-for="(item,idx) in form.items" :key="idx" class="border border-slate-100 rounded-lg p-4 mb-3 relative">
          <button type="button" @click="removeItem(idx)" class="absolute top-3 right-3 text-red-400 hover:text-red-600"><TrashIcon class="h-4 w-4"/></button>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            <div class="col-span-2 md:col-span-3"><label class="block text-xs font-medium text-slate-600 mb-1">Link Property Item (optional)</label><select v-model="item.property_item_id" @change="onPropSelect(idx)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"><option value="">— Not linked —</option><option v-for="p in propertyItems" :key="p.id" :value="p.id">{{ p.property_number }} — {{ p.description }}</option></select></div>
            <div class="col-span-2"><label class="block text-xs font-medium text-slate-600 mb-1">Description *</label><input v-model="item.description" type="text" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"/></div>
            <div><label class="block text-xs font-medium text-slate-600 mb-1">Unit *</label><input v-model="item.unit" type="text" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"/></div>
            <div><label class="block text-xs font-medium text-slate-600 mb-1">Qty *</label><input v-model="item.quantity" type="number" step="0.001" min="0.001" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"/></div>
            <div><label class="block text-xs font-medium text-slate-600 mb-1">Unit Cost *</label><input v-model="item.unit_cost" type="number" step="0.01" min="0" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"/></div>
            <div><label class="block text-xs font-medium text-slate-600 mb-1">Est. Useful Life</label><input v-model="item.estimated_useful_life" type="date" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"/></div>
          </div>
        </div>
      </div>
      <div class="flex justify-end gap-3"><a :href="route('property.ics.index')" class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">Cancel</a><button type="submit" :disabled="submitting" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg text-sm font-medium disabled:opacity-60">{{ submitting?'Saving…':'Create ICS' }}</button></div>
    </form>
  </AdminLayout>
</template>
