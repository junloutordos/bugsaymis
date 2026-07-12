<script setup>
import { ref, computed, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import { PlusIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

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

const clearFilters = () => {
  search.value = ''
  type.value = ''
  status.value = ''
  year.value = ''
}

// ── Status / Type helpers ──────────────────────────────────────────────────────
const typeLabel = {
  mandatory:  'Mandatory',
  technical:  'Technical',
  leadership: 'Leadership',
  functional: 'Functional',
}

function typeBadgeColor (t) {
  const map = { mandatory: 'purple', technical: 'indigo', leadership: 'orange', functional: 'blue' }
  return map[t] ?? 'slate'
}

function statusBadgeColor (s) {
  const map = { planned: 'blue', ongoing: 'amber', completed: 'green', cancelled: 'red' }
  return map[s] ?? 'slate'
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

const deleteProgram = async (p) => {
  if (!await confirmDelete(`"${p.title}" will be removed.`)) return
  router.delete(route('lnd.programs.destroy', p.id), {
    preserveState: true,
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Deleted', timer: 1200, showConfirmButton: false }),
    onError: (errs) => Swal.fire({ icon: 'error', title: 'Error', text: Object.values(errs)[0] }),
  })
}

const yearOptions = computed(() => {
  const cur = new Date().getFullYear()
  return Array.from({ length: 6 }, (_, i) => cur - 2 + i)
})
</script>

<template>
  <Head title="Learning Programs" />
  <AdminLayout title="Learning Programs">
    <div class="space-y-5">

      <AppPageHeader title="Learning Programs" subtitle="Manage training and development programs">
        <template #actions>
          <AppButton @click="openCreate">
            <PlusIcon class="h-4 w-4" />
            New Program
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <div class="relative w-56">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input v-model="search" type="text" placeholder="Search title / provider..."
            class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <select v-model="type"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Types</option>
          <option value="mandatory">Mandatory</option>
          <option value="technical">Technical</option>
          <option value="leadership">Leadership</option>
          <option value="functional">Functional</option>
        </select>
        <select v-model="status"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Status</option>
          <option value="planned">Planned</option>
          <option value="ongoing">Ongoing</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <select v-model="year"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Years</option>
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
        <template #actions>
          <AppButton v-if="search || type || status || year" size="sm" variant="secondary" @click="clearFilters">Clear</AppButton>
        </template>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :loading="isLoading" :is-empty="!programs.data.length" :skeleton-cols="8">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Title</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Type</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Provider</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Hours</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Sessions</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Duration</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="p in programs.data" :key="p.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3">
            <div class="font-medium text-slate-800">{{ p.title }}</div>
            <div v-if="p.competency_area" class="text-xs text-slate-500">{{ p.competency_area }}</div>
          </td>
          <td class="px-4 py-3">
            <AppBadge :color="typeBadgeColor(p.type)">{{ typeLabel[p.type] ?? p.type }}</AppBadge>
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
            <AppBadge :color="statusBadgeColor(p.status)">{{ p.status.charAt(0).toUpperCase() + p.status.slice(1) }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <div class="flex items-center justify-center gap-3">
              <Link :href="route('lnd.programs.show', p.id)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View</Link>
              <AppButton size="sm" variant="secondary" @click="openEdit(p)">Edit</AppButton>
              <AppButton size="sm" variant="danger" @click="deleteProgram(p)">Delete</AppButton>
            </div>
          </td>
        </tr>

        <template #empty>
          <EmptyState title="No programs found." />
        </template>

        <template #footer>
          <PaginationControl :links="programs.links" :total="programs.total" />
        </template>
      </AppTable>

    </div>

    <!-- Create / Edit Modal -->
    <AppModal :show="showModal" :title="editingItem ? 'Edit Program' : 'New Learning Program'" size="2xl" @close="closeModal">
      <div class="space-y-4">

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
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
        <AppButton :loading="isSubmitting" @click="submit">{{ editingItem ? 'Update' : 'Create' }}</AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
