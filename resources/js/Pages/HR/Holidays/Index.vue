<template>
  <Head title="Holidays" />
  <AdminLayout title="Holidays">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Holidays</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage official holidays used in DTR and leave computations.</p>
        </div>
        <button @click="openAdd"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors font-medium">
          + Add Holiday
        </button>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm">
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
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Name</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Type</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Recurring</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="!filtered.length">
              <td colspan="6" class="px-4 py-12 text-center text-slate-400 text-sm">No holidays found.</td>
            </tr>
            <tr v-for="h in filtered" :key="h.id" class="hover:bg-slate-50/60">
              <td class="px-4 py-3 font-medium text-slate-700 whitespace-nowrap">{{ fmtDate(h.holiday_date) }}</td>
              <td class="px-4 py-3 text-slate-800">
                {{ h.name }}
                <p v-if="h.description" class="text-xs text-slate-400 mt-0.5">{{ h.description }}</p>
              </td>
              <td class="px-4 py-3">
                <span :class="typeClass(h.type)"
                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold">
                  {{ typeLabel(h.type) }}
                </span>
              </td>
              <td class="px-4 py-3 text-slate-500 text-xs">{{ h.is_recurring ? 'Yes' : 'No' }}</td>
              <td class="px-4 py-3">
                <span :class="h.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold">
                  {{ h.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-4 py-3 flex gap-2 justify-end">
                <button @click="openEdit(h)"
                        class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                <button @click="confirmDelete(h)"
                        class="text-xs text-red-500 hover:text-red-700 font-medium">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

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
            <button @click="modal = false"
                    class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button @click="submit" :disabled="form.processing"
                    class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg transition-colors">
              {{ form.processing ? 'Saving…' : (editing ? 'Update' : 'Add Holiday') }}
            </button>
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
            <button @click="deleteTarget = null"
                    class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button @click="doDelete"
                    class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors">Delete</button>
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

function typeClass(t) {
  return {
    regular:             'bg-red-100 text-red-700',
    special_non_working: 'bg-amber-100 text-amber-700',
    special_working:     'bg-blue-100 text-blue-700',
    local:               'bg-purple-100 text-purple-700',
  }[t] ?? 'bg-slate-100 text-slate-500'
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', weekday: 'short' })
}
</script>
