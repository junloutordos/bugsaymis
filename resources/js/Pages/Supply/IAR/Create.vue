<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
  purchaseOrders: Array,
  supplyItems: Array,
  officers: Array,
})

const form = ref({
  purchase_order_id: '',
  supplier_name: '',
  supplier_address: '',
  supplier_tin: '',
  delivery_date: new Date().toISOString().split('T')[0],
  inspection_date: '',
  inspector_id: '',
  property_officer_id: '',
  remarks: '',
  items: [],
})

const errors = ref({})
const submitting = ref(false)

function addItem() {
  form.value.items.push({
    supply_item_id: '',
    description: '',
    unit: '',
    quantity_ordered: '',
    quantity_delivered: '',
    unit_cost: '',
  })
}

function removeItem(idx) {
  form.value.items.splice(idx, 1)
}

function onSupplyItemSelect(idx) {
  const itemId = form.value.items[idx].supply_item_id
  if (!itemId) return
  const found = props.supplyItems.find(i => i.id == itemId)
  if (found) {
    form.value.items[idx].description = found.description
    form.value.items[idx].unit = found.unit
    form.value.items[idx].unit_cost = found.estimated_unit_cost
  }
}

// When PO is selected, auto-fill supplier info
watch(() => form.value.purchase_order_id, (poId) => {
  if (!poId) return
  const po = props.purchaseOrders.find(p => p.id == poId)
  if (po) {
    form.value.supplier_name = po.label.split(' — ')[1] ?? po.label
  }
})

async function submit() {
  if (form.value.items.length === 0) {
    alert('Add at least one item.')
    return
  }
  submitting.value = true
  errors.value = {}
  try {
    await axios.post(route('supply.iar.store'), form.value)
    router.visit(route('supply.iar.index'))
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors ?? {}
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <Head title="New IAR" />
  <AdminLayout title="New Inspection & Acceptance Report">
    <form @submit.prevent="submit" class="space-y-6">
      <!-- Header Info -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">IAR Details</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Purchase Order (optional)</label>
            <select v-model="form.purchase_order_id"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
              <option value="">— None / Direct Delivery —</option>
              <option v-for="po in purchaseOrders" :key="po.id" :value="po.id">{{ po.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Supplier Name <span class="text-red-500">*</span></label>
            <input v-model="form.supplier_name" type="text" required
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Supplier Address</label>
            <input v-model="form.supplier_address" type="text"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">TIN</label>
            <input v-model="form.supplier_tin" type="text"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Delivery Date <span class="text-red-500">*</span></label>
            <input v-model="form.delivery_date" type="date" required
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Inspection Date</label>
            <input v-model="form.inspection_date" type="date"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Inspector</label>
            <select v-model="form.inspector_id"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
              <option value="">— Select —</option>
              <option v-for="u in officers" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Property Officer</label>
            <select v-model="form.property_officer_id"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
              <option value="">— Select —</option>
              <option v-for="u in officers" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
            <textarea v-model="form.remarks" rows="2"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"></textarea>
          </div>
        </div>
      </div>

      <!-- Items -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-semibold text-slate-700">Items Received</h3>
          <button type="button" @click="addItem"
            class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium flex items-center gap-1">
            <PlusIcon class="h-4 w-4" /> Add Item
          </button>
        </div>

        <p v-if="form.items.length === 0" class="text-sm text-slate-400 text-center py-6">
          No items added. Click "Add Item" to start.
        </p>

        <div v-for="(item, idx) in form.items" :key="idx"
          class="border border-slate-100 rounded-lg p-4 mb-3 relative">
          <button type="button" @click="removeItem(idx)"
            class="absolute top-3 right-3 text-red-400 hover:text-red-600">
            <TrashIcon class="h-4 w-4" />
          </button>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="md:col-span-3">
              <label class="block text-xs font-medium text-slate-600 mb-1">Link to Supply Item (optional)</label>
              <select v-model="item.supply_item_id" @change="onSupplyItemSelect(idx)"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                <option value="">— Not linked —</option>
                <option v-for="si in supplyItems" :key="si.id" :value="si.id">
                  {{ si.item_code }} — {{ si.description }}
                </option>
              </select>
            </div>
            <div class="md:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Description <span class="text-red-500">*</span></label>
              <input v-model="item.description" type="text" required
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Unit <span class="text-red-500">*</span></label>
              <input v-model="item.unit" type="text" required
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Qty Ordered <span class="text-red-500">*</span></label>
              <input v-model="item.quantity_ordered" type="number" step="0.001" min="0.001" required
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Qty Delivered <span class="text-red-500">*</span></label>
              <input v-model="item.quantity_delivered" type="number" step="0.001" min="0" required
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Unit Cost <span class="text-red-500">*</span></label>
              <input v-model="item.unit_cost" type="number" step="0.01" min="0" required
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-3">
        <a :href="route('supply.iar.index')"
          class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
        <button type="submit" :disabled="submitting"
          class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
          {{ submitting ? 'Saving…' : 'Create IAR' }}
        </button>
      </div>
    </form>
  </AdminLayout>
</template>
