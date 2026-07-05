<template>
  <Head title="New ICS" />
  <AdminLayout title="New Inventory Custodian Slip">
    <form @submit.prevent="submit" class="space-y-6">

      <AppCard title="ICS Details">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <AppInput
            v-model="form.issue_date"
            type="date" required
            label="Issue Date"
            :error="errors.issue_date"
          />
          <AppSelect v-model="form.issued_by" label="Issued By" placeholder="— Current user —" :error="errors.issued_by">
            <option v-for="u in officers" :key="u.id" :value="u.id">{{ u.name }}</option>
          </AppSelect>
          <AppSelect v-model="form.received_by" label="Received By" required placeholder="— Select —" :error="errors.received_by">
            <option v-for="u in officers" :key="u.id" :value="u.id">{{ u.name }}</option>
          </AppSelect>
          <AppSelect v-model="form.division_id" label="Division" placeholder="— Select —" :error="errors.division_id">
            <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.division_name }}</option>
          </AppSelect>
          <div class="md:col-span-2">
            <AppTextarea v-model="form.remarks" :rows="1" label="Remarks" :error="errors.remarks" />
          </div>
        </div>
      </AppCard>

      <AppCard title="Items">
        <template #header>
          <AppButton type="button" size="sm" @click="addItem">
            <PlusIcon class="h-4 w-4" />
            Add
          </AppButton>
        </template>

        <p v-if="!form.items.length" class="text-sm text-slate-400 text-center py-4">No items. Click "Add" to start.</p>

        <div v-for="(item, idx) in form.items" :key="idx" class="border border-slate-100 rounded-lg p-4 mb-3 relative">
          <AppIconButton
            label="Remove item"
            variant="danger"
            size="sm"
            class="absolute top-3 right-3"
            @click="removeItem(idx)"
          >
            <TrashIcon class="h-4 w-4" />
          </AppIconButton>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            <div class="col-span-2 md:col-span-3">
              <AppSelect
                :model-value="item.property_item_id"
                @update:model-value="val => { item.property_item_id = val; onPropSelect(idx) }"
                label="Link Property Item (optional)"
                placeholder="— Not linked —"
              >
                <option v-for="p in propertyItems" :key="p.id" :value="p.id">{{ p.property_number }} — {{ p.description }}</option>
              </AppSelect>
            </div>
            <div class="col-span-2">
              <AppInput v-model="item.description" label="Description" required :error="errors[`items.${idx}.description`]" />
            </div>
            <AppInput v-model="item.unit" label="Unit" required :error="errors[`items.${idx}.unit`]" />
            <AppInput v-model="item.quantity" type="number" step="0.001" min="0.001" label="Qty" required :error="errors[`items.${idx}.quantity`]" />
            <AppInput v-model="item.unit_cost" type="number" step="0.01" min="0" label="Unit Cost" required :error="errors[`items.${idx}.unit_cost`]" />
            <AppInput v-model="item.estimated_useful_life" type="date" label="Est. Useful Life" />
          </div>
        </div>
      </AppCard>

      <div class="flex justify-end gap-3">
        <AppButton as="link" variant="secondary" :href="route('property.ics.index')">Cancel</AppButton>
        <AppButton type="submit" :loading="submitting">
          {{ submitting ? 'Saving…' : 'Create ICS' }}
        </AppButton>
      </div>
    </form>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({ propertyItems: Array, officers: Array, divisions: Array })

const form = ref({ issue_date: new Date().toISOString().split('T')[0], issued_by: '', received_by: '', division_id: '', remarks: '', items: [] })
const errors = ref({})
const submitting = ref(false)

function addItem() { form.value.items.push({ property_item_id: '', description: '', unit: '', quantity: '', unit_cost: '', estimated_useful_life: '', remarks: '' }) }
function removeItem(idx) { form.value.items.splice(idx, 1) }

function onPropSelect(idx) {
  const id = form.value.items[idx].property_item_id
  if (!id) return
  const found = props.propertyItems.find(p => p.id == id)
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
  } catch (e) {
    if (e.response?.status === 422) errors.value = e.response.data.errors ?? {}
  } finally { submitting.value = false }
}
</script>
