<template>
  <Head title="Holidays" />
  <AdminLayout title="Holidays">
    <div class="space-y-5">

      <AppPageHeader title="Holidays" subtitle="Manage official holidays used in DTR and leave computations.">
        <template #actions>
          <AppButton @click="openAdd">
            <PlusIcon class="h-4 w-4" />
            Add Holiday
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.success }}
      </div>

      <!-- Year filter -->
      <div class="flex flex-wrap gap-2">
        <button v-for="y in years" :key="y"
                @click="activeYear = y"
                :class="[
                  'px-3 py-1.5 rounded-full text-xs font-medium border transition-colors',
                  activeYear === y
                    ? 'bg-indigo-600 text-white border-indigo-600'
                    : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'
                ]">
          {{ y === 0 ? 'All' : y }}
        </button>
      </div>

      <!-- Table -->
      <AppTable :is-empty="!filtered.length" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Name</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Type</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Recurring</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="h in filtered" :key="h.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 font-medium text-slate-700 whitespace-nowrap">{{ fmtDate(h.holiday_date) }}</td>
          <td class="px-4 py-3 text-slate-800">
            {{ h.name }}
            <p v-if="h.description" class="text-xs text-slate-400 mt-0.5">{{ h.description }}</p>
          </td>
          <td class="px-4 py-3">
            <AppBadge :color="typeColor(h.type)">{{ typeLabel(h.type) }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-slate-500 text-xs">{{ h.is_recurring ? 'Yes' : 'No' }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="h.is_active ? 'green' : 'slate'">{{ h.is_active ? 'Active' : 'Inactive' }}</AppBadge>
          </td>
          <td class="px-4 py-3">
            <div class="flex gap-2 justify-end">
              <AppButton size="sm" variant="ghost" @click="openEdit(h)">Edit</AppButton>
              <AppButton size="sm" variant="danger" @click="confirmDelete(h)">Delete</AppButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="h in filtered" :key="h.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs text-slate-500">{{ fmtDate(h.holiday_date) }}</p>
                <p class="font-medium text-slate-800">{{ h.name }}</p>
                <p v-if="h.description" class="text-xs text-slate-400">{{ h.description }}</p>
              </div>
              <AppBadge :color="h.is_active ? 'green' : 'slate'">{{ h.is_active ? 'Active' : 'Inactive' }}</AppBadge>
            </div>
            <div class="flex items-center gap-2 text-xs text-slate-500">
              <AppBadge :color="typeColor(h.type)">{{ typeLabel(h.type) }}</AppBadge>
              <span>Recurring: {{ h.is_recurring ? 'Yes' : 'No' }}</span>
            </div>
            <div class="flex gap-2 justify-end pt-1">
              <AppButton size="sm" variant="ghost" @click="openEdit(h)">Edit</AppButton>
              <AppButton size="sm" variant="danger" @click="confirmDelete(h)">Delete</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No holidays found" />
        </template>
      </AppTable>

    </div>

    <!-- Add/Edit Modal -->
    <Teleport to="body">
      <div v-if="modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md mx-4">
          <h3 class="text-base font-semibold text-slate-800 mb-4">{{ editing ? 'Edit Holiday' : 'Add Holiday' }}</h3>

          <div class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Name <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text"
                     class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                     :class="{ 'border-red-400': form.errors.name }" />
              <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Date <span class="text-red-500">*</span></label>
              <input v-model="form.holiday_date" type="date"
                     class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                     :class="{ 'border-red-400': form.errors.holiday_date }" />
              <p v-if="form.errors.holiday_date" class="text-red-500 text-xs mt-1">{{ form.errors.holiday_date }}</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Type <span class="text-red-500">*</span></label>
              <select v-model="form.type"
                      class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <option value="regular">Regular Holiday</option>
                <option value="special_non_working">Special Non-Working</option>
                <option value="special_working">Special Working</option>
                <option value="local">Local Holiday</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
              <input v-model="form.description" type="text"
                     class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            </div>

            <div class="flex gap-6">
              <label class="flex items-center gap-2 cursor-pointer text-sm text-slate-700">
                <input type="checkbox" v-model="form.is_recurring" class="accent-indigo-600" />
                Recurring annually
              </label>
              <label class="flex items-center gap-2 cursor-pointer text-sm text-slate-700">
                <input type="checkbox" v-model="form.is_active" class="accent-indigo-600" />
                Active
              </label>
            </div>
          </div>

          <div class="flex gap-3 justify-end mt-5">
            <AppButton variant="secondary" @click="modal = false">Cancel</AppButton>
            <AppButton :loading="form.processing" @click="submit">
              {{ editing ? 'Update' : 'Add Holiday' }}
            </AppButton>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Delete Confirm Modal -->
    <Teleport to="body">
      <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm mx-4">
          <h3 class="text-base font-semibold text-slate-800 mb-2">Delete Holiday</h3>
          <p class="text-sm text-slate-600 mb-5">Remove <strong>{{ deleteTarget.name }}</strong>? This cannot be undone.</p>
          <div class="flex gap-3 justify-end">
            <AppButton variant="secondary" @click="deleteTarget = null">Cancel</AppButton>
            <AppButton variant="danger" @click="doDelete">Delete</AppButton>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { PlusIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  holidays: Array,
})

// Year filter
const currentYear = new Date().getFullYear()
const activeYear  = ref(currentYear)
const years = computed(() => {
  const ys = [...new Set(props.holidays.map(h => new Date(h.holiday_date).getFullYear()))].sort()
  if (!ys.includes(currentYear)) ys.push(currentYear)
  return [0, ...ys.sort()]
})
const filtered = computed(() =>
  activeYear.value === 0
    ? props.holidays
    : props.holidays.filter(h => new Date(h.holiday_date).getFullYear() === activeYear.value)
)

// Modal state
const modal   = ref(false)
const editing = ref(null)

const form = useForm({
  name:         '',
  holiday_date: '',
  type:         'regular',
  is_recurring: false,
  is_active:    true,
  description:  '',
})

function openAdd() {
  editing.value = null
  form.reset()
  form.is_active = true
  form.type = 'regular'
  modal.value = true
}

function openEdit(h) {
  editing.value = h
  form.name         = h.name
  form.holiday_date = h.holiday_date
  form.type         = h.type
  form.is_recurring = !!h.is_recurring
  form.is_active    = !!h.is_active
  form.description  = h.description ?? ''
  modal.value = true
}

function submit() {
  if (editing.value) {
    form.put(route('hr.holidays.update', editing.value.id), {
      onSuccess: () => { modal.value = false },
    })
  } else {
    form.post(route('hr.holidays.store'), {
      onSuccess: () => { modal.value = false },
    })
  }
}

// Delete
const deleteTarget = ref(null)
function confirmDelete(h) { deleteTarget.value = h }
function doDelete() {
  router.delete(route('hr.holidays.destroy', deleteTarget.value.id), {
    onSuccess: () => { deleteTarget.value = null },
  })
}

// Helpers
function typeLabel(t) {
  return {
    regular:             'Regular',
    special_non_working: 'Special Non-Working',
    special_working:     'Special Working',
    local:               'Local',
  }[t] ?? t
}

function typeColor(t) {
  return {
    regular:             'red',
    special_non_working: 'amber',
    special_working:     'blue',
    local:               'purple',
  }[t] ?? 'slate'
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', weekday: 'short' })
}
</script>
