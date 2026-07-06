<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
  supplyItems: Array,
  divisions: Array,
  userDivision: [Number, String, null],
})

const form = ref({
  division_id: props.userDivision ?? '',
  purpose: '',
  date_requested: new Date().toISOString().split('T')[0],
  date_needed: '',
  remarks: '',
  items: [],
})

const errors = ref({})
const submitting = ref(false)

function addItem() {
  form.value.items.push({ supply_item_id: '', quantity_requested: '', remarks: '' })
}

function removeItem(idx) {
  form.value.items.splice(idx, 1)
}

function getItemInfo(id) {
  return props.supplyItems.find(i => i.id == id)
}

async function submit() {
  if (form.value.items.length === 0) {
    alert('Add at least one item.')
    return
  }
  submitting.value = true
  errors.value = {}
  try {
    await axios.post(route('supply.ris.store'), form.value)
    router.visit(route('supply.ris.index'))
  } catch (e) {
    if (e.response?.status === 422) errors.value = e.response.data.errors ?? {}
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <Head title="New RIS" />
  <AdminLayout title="New Requisition & Issue Slip">
    <form @submit.prevent="submit" class="space-y-6">
      <AppCard title="RIS Details">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Division / Office</label>
            <select v-model="form.division_id"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
              <option value="">— Select —</option>
              <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.division_name }}</option>
            </select>
          </div>
          <AppInput v-model="form.date_requested" type="date" label="Date Requested" required />
          <AppInput v-model="form.date_needed" type="date" label="Date Needed" />
          <div class="md:col-span-2">
            <AppTextarea v-model="form.purpose" :rows="2" required label="Purpose" placeholder="State the purpose for requisition…" />
          </div>
          <div class="md:col-span-2">
            <AppTextarea v-model="form.remarks" :rows="1" label="Remarks" />
          </div>
        </div>
      </AppCard>

      <AppCard>
        <template #header>
          <h3 class="text-sm font-semibold text-slate-700">Items Requested</h3>
        </template>

        <div class="flex justify-end mb-4">
          <AppButton type="button" size="sm" @click="addItem">
            <PlusIcon class="h-4 w-4" /> Add Item
          </AppButton>
        </div>

        <p v-if="form.items.length === 0" class="text-sm text-slate-400 text-center py-6">
          No items added. Click "Add Item" to start.
        </p>

        <div v-for="(item, idx) in form.items" :key="idx"
          class="border border-slate-100 rounded-lg p-4 mb-3 relative">
          <AppIconButton label="Remove item" variant="danger" size="sm" type="button"
            class="absolute top-3 right-3" @click="removeItem(idx)">
            <TrashIcon class="h-4 w-4" />
          </AppIconButton>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="md:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Supply Item <span class="text-red-500">*</span></label>
              <select v-model="item.supply_item_id" required
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                <option value="">— Select item —</option>
                <option v-for="si in supplyItems" :key="si.id" :value="si.id"
                  :disabled="si.balance_quantity <= 0">
                  {{ si.item_code }} — {{ si.description }}
                  (Balance: {{ si.balance_quantity.toLocaleString('en-PH', {minimumFractionDigits:3}) }} {{ si.unit }})
                </option>
              </select>
            </div>
            <div>
              <AppInput v-model="item.quantity_requested" type="number" step="0.001" min="0.001" required label="Qty Requested" />
              <p v-if="item.supply_item_id" class="text-xs text-slate-400 mt-1">
                Available: {{ (getItemInfo(item.supply_item_id)?.balance_quantity ?? 0).toLocaleString('en-PH', {minimumFractionDigits:3}) }}
                {{ getItemInfo(item.supply_item_id)?.unit }}
              </p>
            </div>
            <div class="md:col-span-3">
              <AppInput v-model="item.remarks" type="text" label="Remarks" />
            </div>
          </div>
        </div>
      </AppCard>

      <div class="flex justify-end gap-3">
        <AppButton as="a" variant="secondary" :href="route('supply.ris.index')">Cancel</AppButton>
        <AppButton type="submit" :loading="submitting" :disabled="submitting">
          {{ submitting ? 'Saving…' : 'Create RIS' }}
        </AppButton>
      </div>
    </form>
  </AdminLayout>
</template>
