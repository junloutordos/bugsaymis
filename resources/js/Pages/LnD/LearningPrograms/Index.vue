<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'

const props = defineProps({
  programs: { type: Object, required: true },
  filters:  { type: Object, default: () => ({}) },
})

const page = usePage()

// ── Filters ────────────────────────────────────────────────────────────────────
const search = ref(props.filters?.search  ?? '')
const type   = ref(props.filters?.type    ?? '')
const status = ref(props.filters?.status  ?? '')
const year   = ref(props.filters?.year    ?? '')
const isLoading = ref(false)
let debounceTimer = null

const applyFilters = (immediate = true) => {
  clearTimeout(debounceTimer)
  const go = () => {
    isLoading.value = true
    router.get(route('lnd.programs.index'), {
      search: search.value  || undefined,
      type:   type.value    || undefined,
      status: status.value  || undefined,
      year:   year.value    || undefined,
    }, {
      preserveState: true, replace: true,
      only: ['programs', 'filters'],
      onFinish: () => { isLoading.value = false },
    })
  }
  if (immediate) go()
  else debounceTimer = setTimeout(go, 400)
}

watch(search, () => applyFilters(false))
watch([type, status, year], () => applyFilters(true))

const goToPage = (p) => {
  isLoading.value = true
  router.get(route('lnd.programs.index'), {
    search: search.value || undefined,
    type:   type.value   || undefined,
    status: status.value || undefined,
    year:   year.value   || undefined,
    page: p,
  }, { preserveState: true, replace: true, only: ['programs', 'filters'], onFinish: () => { isLoading.value = false } })
}

// ── Status / Type helpers ──────────────────────────────────────────────────────
const statusColors = {
  planned:   'bg-blue-100 text-blue-700',
  ongoing:   'bg-yellow-100 text-yellow-700',
  completed: 'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-600',
}
const typeColors = {
  mandatory:   'bg-purple-100 text-purple-700',
  technical:   'bg-indigo-100 text-indigo-700',
  leadership:  'bg-orange-100 text-orange-700',
  functional:  'bg-teal-100 text-teal-700',
}
const typeLabel = {
  mandatory:  'Mandatory',
  technical:  'Technical',
  leadership: 'Leadership',
  functional: 'Functional',
}

// ── Modal state ────────────────────────────────────────────────────────────────
const showModal    = ref(false)
const editingItem  = ref(null)
const isSubmitting = ref(false)

const emptyForm = () => ({
  title:           '',
  description:     '',
  type:            'mandatory',
  competency_area: '',
  target_position: '',
  provider:        '',
  start_date:      '',
  end_date:        '',
  hours:           '',
  budget:          '',
  status:          'planned',
})
const form = ref(emptyForm())

const openCreate = () => {
  editingItem.value = null
  form.value = emptyForm()
  showModal.value = true
}

const openEdit = (p) => {
  editingItem.value = p
  form.value = {
    title:           p.title           ?? '',
    description:     p.description     ?? '',
    type:            p.type            ?? 'mandatory',
    competency_area: p.competency_area ?? '',
    target_position: p.target_position ?? '',
    provider:        p.provider        ?? '',
    start_date:      p.start_date      ? p.start_date.substring(0, 10) : '',
    end_date:        p.end_date        ? p.end_date.substring(0, 10)   : '',
    hours:           p.hours           ?? '',
    budget:          p.budget          ?? '',
    status:          p.status          ?? 'planned',
  }
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
  editingItem.value = null
}

const submit = () => {
  isSubmitting.value = true
  const url = editingItem.value
    ? route('lnd.programs.update', editingItem.value.id)
    : route('lnd.programs.store')
  const method = editingItem.value ? 'put' : 'post'
  router[method](url, form.value, {
    preserveState: true,
    onSuccess: () => {
      closeModal()
      Swal.fire({ icon: 'success', title: 'Saved', timer: 1500, showConfirmButton: false })
    },
    onError: () => {},
    onFinish: () => { isSubmitting.value = false },
  })
}

const deleteProgram = (p) => {
  Swal.fire({
    title: 'Delete program?',
    text: `"${p.title}" will be removed.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    confirmButtonText: 'Delete',
  }).then(res => {
    if (!res.isConfirmed) return
    router.delete(route('lnd.programs.destroy', p.id), {
      preserveState: true,
      onSuccess: () => Swal.fire({ icon: 'success', title: 'Deleted', timer: 1200, showConfirmButton: false }),
      onError: (errs) => Swal.fire({ icon: 'error', title: 'Error', text: Object.values(errs)[0] }),
    })
  })
}

const yearOptions = computed(() => {
  const cur = new Date().getFullYear()
  return Array.from({ length: 6 }, (_, i) => cur - 2 + i)
})
</script>

<template>
  <AdminLayout title="Learning Programs">
    <Head title="Learning Programs" />

    <div class="p-6 space-y-5">

      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Learning Programs</h1>
          <p class="text-sm text-slate-500">Manage training and development programs</p>
        </div>
        <button @click="openCreate"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          New Program
        </button>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
        <input v-model="search" type="text" placeholder="Search title / provider..."
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-56" />
        <select v-model="type"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option value="">All Types</option>
          <option value="mandatory">Mandatory</option>
          <option value="technical">Technical</option>
          <option value="leadership">Leadership</option>
          <option value="functional">Functional</option>
        </select>
        <select v-model="status"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option value="">All Status</option>
          <option value="planned">Planned</option>
          <option value="ongoing">Ongoing</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <select v-model="year"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option value="">All Years</option>
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
        <button v-if="search || type || status || year" @click="search=''; type=''; status=''; year=''"
          class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          Clear
        </button>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div v-if="isLoading" class="py-16 text-center text-slate-400 text-sm">Loading…</div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Title</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Type</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Provider</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Hours</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Sessions</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Duration</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="programs.data.length === 0">
                <td colspan="8" class="py-16 text-center text-slate-400 text-sm">No programs found.</td>
              </tr>
              <tr v-for="p in programs.data" :key="p.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-800">{{ p.title }}</div>
                  <div v-if="p.competency_area" class="text-xs text-slate-500">{{ p.competency_area }}</div>
                </td>
                <td class="px-4 py-3">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium', typeColors[p.type] ?? 'bg-slate-100 text-slate-600']">
                    {{ typeLabel[p.type] ?? p.type }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ p.provider ?? '—' }}</td>
                <td class="px-4 py-3 text-center text-sm text-slate-700">{{ p.hours ?? '—' }}</td>
                <td class="px-4 py-3 text-center font-medium text-indigo-600 text-sm">{{ p.sessions_count }}</td>
                <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">
                  <template v-if="p.start_date">
                    {{ p.start_date.substring(0,10) }}
                    <template v-if="p.end_date"> – {{ p.end_date.substring(0,10) }}</template>
                  </template>
                  <template v-else>—</template>
                </td>
                <td class="px-4 py-3">
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium', statusColors[p.status] ?? 'bg-slate-100 text-slate-600']">
                    {{ p.status.charAt(0).toUpperCase() + p.status.slice(1) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <a :href="route('lnd.programs.show', p.id)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-indigo-600 transition-colors text-xs font-medium px-2">View</a>
                    <button @click="openEdit(p)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors text-xs font-medium px-2">Edit</button>
                    <button @click="deleteProgram(p)"
                      class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors text-xs font-medium px-2">Delete</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="programs.last_page > 1"
        class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
        <span>Showing {{ programs.from }}–{{ programs.to }} of {{ programs.total }}</span>
        <div class="flex gap-1">
          <button v-for="p in programs.links" :key="p.label"
            @click="p.url && goToPage(new URL(p.url).searchParams.get('page'))"
            :disabled="!p.url"
            :class="['px-3 py-1 rounded border text-xs', p.active ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-200 hover:bg-slate-50 disabled:opacity-40 text-slate-600']"
            v-html="p.label" />
        </div>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto bg-white rounded-2xl shadow-xl">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">
              {{ editingItem ? 'Edit Program' : 'New Learning Program' }}
            </h2>
            <button @click="closeModal"
              class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <form @submit.prevent="submit" class="px-6 py-5 space-y-4">

            <!-- Title -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Title <span class="text-red-500">*</span></label>
              <input v-model="form.title" type="text" required
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>

            <!-- Type + Status -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Type <span class="text-red-500">*</span></label>
                <select v-model="form.type" required
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                  <option value="mandatory">Mandatory</option>
                  <option value="technical">Technical</option>
                  <option value="leadership">Leadership</option>
                  <option value="functional">Functional</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Status <span class="text-red-500">*</span></label>
                <select v-model="form.status" required
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                  <option value="planned">Planned</option>
                  <option value="ongoing">Ongoing</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>
            </div>

            <!-- Competency Area + Target Position -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Competency Area</label>
                <input v-model="form.competency_area" type="text"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Target Position</label>
                <input v-model="form.target_position" type="text"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
            </div>

            <!-- Provider -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Provider / Organizer</label>
              <input v-model="form.provider" type="text"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>

            <!-- Start / End date -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Start Date</label>
                <input v-model="form.start_date" type="date"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">End Date</label>
                <input v-model="form.end_date" type="date" :min="form.start_date"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
            </div>

            <!-- Hours + Budget -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Training Hours</label>
                <input v-model="form.hours" type="number" min="1"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Budget (₱)</label>
                <input v-model="form.budget" type="number" min="0" step="0.01"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
            </div>

            <!-- Description -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
              <textarea v-model="form.description" rows="3"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 resize-none" />
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
              <button type="button" @click="closeModal"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                Cancel
              </button>
              <button type="submit" :disabled="isSubmitting"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
                {{ isSubmitting ? 'Saving…' : (editingItem ? 'Update' : 'Create') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
