<template>
  <AdminLayout title="Reward Types">
    <div class="space-y-6">
      <AppPageHeader title="Reward Types">
        <template #actions>
          <AppButton @click="openCreate">+ New Type</AppButton>
        </template>
      </AppPageHeader>

      <AppTable :is-empty="!types.length" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Name</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Category</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Type</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Frequency</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="t in types" :key="t.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-700">
            <p class="font-medium text-slate-800">{{ t.name }}</p>
            <p v-if="t.description" class="text-xs text-slate-500">{{ t.description }}</p>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700 capitalize">{{ t.category }}</td>
          <td class="px-4 py-3 text-sm text-slate-700 capitalize">{{ t.type.replace('_', ' ') }}</td>
          <td class="px-4 py-3 text-sm text-slate-700 capitalize">{{ t.frequency.replace('_', ' ') }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <AppBadge :color="t.is_active ? 'green' : 'slate'">{{ t.is_active ? 'Active' : 'Inactive' }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <div class="flex gap-2">
              <AppButton size="sm" variant="secondary" @click="openEdit(t)">Edit</AppButton>
              <AppButton size="sm" variant="danger" @click="handleDelete(t)">Delete</AppButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="t in types" :key="t.id" class="p-4 space-y-1">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ t.name }}</p>
                <p v-if="t.description" class="text-xs text-slate-500">{{ t.description }}</p>
              </div>
              <AppBadge :color="t.is_active ? 'green' : 'slate'">{{ t.is_active ? 'Active' : 'Inactive' }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500 capitalize">{{ t.category }} &middot; {{ t.type.replace('_', ' ') }} &middot; {{ t.frequency.replace('_', ' ') }}</p>
            <div class="flex gap-2 pt-1">
              <AppButton size="sm" variant="secondary" @click="openEdit(t)">Edit</AppButton>
              <AppButton size="sm" variant="danger" @click="handleDelete(t)">Delete</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No reward types yet" />
        </template>
      </AppTable>
    </div>

    <!-- Create/Edit Modal -->
    <AppModal :show="showModal" :title="`${editing ? 'Edit' : 'New'} Reward Type`" size="lg" @close="showModal = false">
      <form @submit.prevent="submit" id="reward-type-form">
        <div class="space-y-4">
          <AppInput v-model="form.name" label="Name" required />
          <AppTextarea v-model="form.description" label="Description" :rows="2" />
          <div class="grid grid-cols-2 gap-4">
            <AppSelect v-model="form.category" label="Category" :show-blank="false">
              <option value="individual">Individual</option>
              <option value="team">Team</option>
            </AppSelect>
            <AppSelect v-model="form.type" label="Type" :show-blank="false">
              <option value="monetary">Monetary</option>
              <option value="non_monetary">Non-Monetary</option>
              <option value="mixed">Mixed</option>
            </AppSelect>
          </div>
          <AppSelect v-model="form.frequency" label="Frequency" :show-blank="false">
            <option value="monthly">Monthly</option>
            <option value="quarterly">Quarterly</option>
            <option value="annual">Annual</option>
            <option value="ad_hoc">Ad Hoc</option>
          </AppSelect>
          <AppTextarea v-model="form.criteria" label="Criteria" :rows="3" />
          <div class="flex items-center gap-2">
            <input type="checkbox" v-model="form.is_active" id="is_active" class="h-4 w-4" />
            <label for="is_active" class="text-sm text-slate-700">Active</label>
          </div>
        </div>
      </form>

      <template #footer>
        <AppButton variant="secondary" @click="showModal = false">Cancel</AppButton>
        <AppButton type="submit" form="reward-type-form" :loading="processing">{{ editing ? 'Update' : 'Create' }}</AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmDelete as confirmDeleteDialog } from '@/Composables/useConfirm.js'

const props = defineProps({ types: Array })

const showModal = ref(false)
const editing = ref(null)
const processing = ref(false)

const form = useForm({
  name: '',
  description: '',
  category: 'individual',
  type: 'non_monetary',
  frequency: 'annual',
  criteria: '',
  is_active: true,
})

function openCreate() {
  editing.value = null
  form.reset()
  form.is_active = true
  form.category = 'individual'
  form.type = 'non_monetary'
  form.frequency = 'annual'
  showModal.value = true
}

function openEdit(t) {
  editing.value = t
  form.name = t.name
  form.description = t.description ?? ''
  form.category = t.category
  form.type = t.type
  form.frequency = t.frequency
  form.criteria = t.criteria ?? ''
  form.is_active = t.is_active
  showModal.value = true
}

function submit() {
  if (editing.value) {
    form.patch(route('rewards.types.update', editing.value.id), {
      onSuccess: () => { showModal.value = false },
    })
  } else {
    form.post(route('rewards.types.store'), {
      onSuccess: () => { showModal.value = false },
    })
  }
}

async function handleDelete(t) {
  const confirmed = await confirmDeleteDialog(`Delete "${t.name}"?`)
  if (confirmed) {
    useForm({}).delete(route('rewards.types.destroy', t.id))
  }
}
</script>
