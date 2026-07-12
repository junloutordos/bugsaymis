<template>
  <Head title="Faculty List" />
  <AdminLayout title="Faculty List">
    <div class="space-y-5">

      <AppPageHeader title="Faculty List" subtitle="All active faculty members with their positions and specializations">
        <template #actions>
          <AppBadge color="indigo">
            <UsersIcon class="h-4 w-4" />
            {{ faculty.length }} faculty
          </AppBadge>
        </template>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar>
        <div class="w-72">
          <AppInput v-model="search" type="search" placeholder="Search name, badge ID, position, specialization..." />
        </div>
        <select v-model="divisionFilter" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option value="">All Divisions</option>
          <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <div class="w-52">
          <AppSelect :model-value="categoryFilter" :show-blank="false"
            @update:model-value="v => { categoryFilter = v; applyFilters() }">
            <option value="">All Categories</option>
            <option value="Plantilla Teaching">Plantilla Teaching</option>
            <option value="Plantilla Non-Teaching">Plantilla Non-Teaching</option>
            <option value="COS Teaching">COS Teaching</option>
            <option value="COS Non Teaching">COS Non Teaching</option>
          </AppSelect>
        </div>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="faculty.length === 0" :skeleton-cols="9">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Name</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Badge ID</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Position</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Specialization</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Division / Office</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Category</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Sex</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="(f, i) in paged" :key="f.id" class="hover:bg-slate-50 transition-colors">
          <td class="px-4 py-3 text-slate-400 tabular-nums">{{ (currentPage - 1) * perPage + i + 1 }}</td>
          <td class="px-4 py-3">
            <div class="font-medium text-slate-800">{{ f.name }}</div>
            <AppBadge v-if="f.on_study_leave" color="amber">Study Leave</AppBadge>
          </td>
          <td class="px-4 py-3 text-slate-600">{{ f.badge_id || '—' }}</td>
          <td class="px-4 py-3 text-slate-600">{{ f.position || '—' }}</td>
          <td class="px-4 py-3 text-slate-600">
            <span v-if="f.specialization" class="inline-flex items-center gap-1">
              <AcademicCapIcon class="h-3.5 w-3.5 text-indigo-400 shrink-0" />
              {{ f.specialization }}
            </span>
            <span v-else class="text-slate-400">—</span>
          </td>
          <td class="px-4 py-3 text-slate-600">
            <div>{{ f.division?.name || '—' }}</div>
            <div v-if="f.office" class="text-xs text-slate-400">{{ f.office.name }}</div>
          </td>
          <td class="px-4 py-3">
            <AppBadge v-if="f.emp_category" :color="categoryBadge(f.emp_category)">{{ f.emp_category }}</AppBadge>
            <span v-else class="text-slate-400">—</span>
          </td>
          <td class="px-4 py-3 text-center text-slate-600">{{ f.sex || '—' }}</td>
          <td class="px-4 py-3 text-center">
            <AppIconButton label="Edit faculty" @click="openEdit(f)"><PencilSquareIcon class="h-4 w-4" /></AppIconButton>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="(f, i) in paged" :key="f.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ f.name }}</p>
                <p class="text-xs text-slate-400">{{ f.badge_id || '—' }} &middot; {{ f.position || '—' }}</p>
              </div>
              <AppBadge v-if="f.on_study_leave" color="amber">Study Leave</AppBadge>
            </div>
            <p v-if="f.specialization" class="inline-flex items-center gap-1 text-xs text-slate-600">
              <AcademicCapIcon class="h-3.5 w-3.5 text-indigo-400 shrink-0" />
              {{ f.specialization }}
            </p>
            <p class="text-xs text-slate-500">
              {{ f.division?.name || '—' }}<span v-if="f.office"> &middot; {{ f.office.name }}</span>
            </p>
            <div class="flex items-center gap-2">
              <AppBadge v-if="f.emp_category" :color="categoryBadge(f.emp_category)">{{ f.emp_category }}</AppBadge>
              <span class="text-xs text-slate-500">Sex: {{ f.sex || '—' }}</span>
            </div>
            <div class="pt-1">
              <AppIconButton label="Edit faculty" @click="openEdit(f)"><PencilSquareIcon class="h-4 w-4" /></AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No faculty members found" subtitle="Try adjusting your filters or ensure users are assigned the Faculty role." :icon="UsersIcon" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            :total="faculty.length"
            @prev="currentPage--"
            @next="currentPage++"
            @page="currentPage = $event"
          />
        </template>
      </AppTable>

    </div>

    <!-- Edit Modal -->
    <AppModal :show="editModal.open" title="Edit Faculty Details" :subtitle="editModal.name" @close="closeEdit">
      <form @submit.prevent="submitEdit" class="space-y-4">

        <AppInput v-model="form.name" label="Full Name" required placeholder="e.g. Juan dela Cruz" />

        <AppSelect v-model="form.sex" label="Sex" placeholder="— Select —">
          <option value="Male">Male</option>
          <option value="Female">Female</option>
        </AppSelect>

        <AppInput v-model="form.position" label="Position" placeholder="e.g. Teacher I" />

        <div>
          <AppInput v-model="form.specialization" label="Specialization" placeholder="e.g. Mathematics, Science" />
          <p class="text-xs text-slate-400 mt-1">Used for auto-assignment of teaching loads.</p>
        </div>

        <!-- Division -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Division</label>
          <select v-model="form.division_id" @change="form.office_id = ''"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option value="">— None —</option>
            <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>

        <!-- Office (filtered by division) -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Office</label>
          <select v-model="form.office_id"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option value="">— None —</option>
            <option v-for="o in filteredOffices" :key="o.id" :value="o.id">{{ o.name }}</option>
          </select>
        </div>

        <AppSelect v-model="form.emp_category" label="Employment Category" placeholder="— None —">
          <option value="Plantilla Teaching">Plantilla Teaching</option>
          <option value="Plantilla Non-Teaching">Plantilla Non-Teaching</option>
          <option value="COS Teaching">COS Teaching</option>
          <option value="COS Non Teaching">COS Non Teaching</option>
        </AppSelect>

        <!-- Study Leave -->
        <div class="rounded-xl border p-4 flex items-start gap-3"
          :class="form.on_study_leave ? 'border-warning-100 bg-warning-50' : 'border-slate-200 bg-slate-50'">
          <div class="flex-1">
            <p class="text-sm font-medium" :class="form.on_study_leave ? 'text-warning-700' : 'text-slate-700'">
              On Study Leave
            </p>
            <p class="text-xs mt-0.5" :class="form.on_study_leave ? 'text-warning-600' : 'text-slate-400'">
              {{ form.on_study_leave
                ? 'This faculty is on study leave and will be excluded from load assignments and scheduling.'
                : 'Mark this faculty as on study leave to exclude them from load assignments and scheduling.' }}
            </p>
          </div>
          <button type="button" @click="form.on_study_leave = !form.on_study_leave"
            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-warning-500 focus:ring-offset-2"
            :class="form.on_study_leave ? 'bg-warning-500' : 'bg-slate-300'">
            <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform transition-transform"
              :class="form.on_study_leave ? 'translate-x-5' : 'translate-x-0'" />
          </button>
        </div>
      </form>

      <template #footer>
        <AppButton variant="secondary" @click="closeEdit">Cancel</AppButton>
        <AppButton :loading="saving" @click="submitEdit">Save Changes</AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import {
  UsersIcon,
  AcademicCapIcon,
  PencilSquareIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  faculty:   { type: Array,  default: () => [] },
  divisions: { type: Array,  default: () => [] },
  offices:   { type: Array,  default: () => [] },
  filters:   { type: Object, default: () => ({}) },
})

// ── Filters ──────────────────────────────────────────────────────────────────
const search         = ref(props.filters.search        ?? '')
const divisionFilter = ref(props.filters.division_id   ?? '')
const categoryFilter = ref(props.filters.emp_category  ?? '')
const currentPage    = ref(1)
const perPage        = 15

let searchTimer = null
function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(applyFilters, 350)
}

function applyFilters() {
  currentPage.value = 1
  router.get(
    route('faculty-loading.faculty-list'),
    {
      search:       search.value        || undefined,
      division_id:  divisionFilter.value || undefined,
      emp_category: categoryFilter.value || undefined,
    },
    { preserveState: true, replace: true }
  )
}

watch(search, onSearch)

// ── Pagination ────────────────────────────────────────────────────────────────
const totalPages = computed(() => Math.max(1, Math.ceil(props.faculty.length / perPage)))
const paged      = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return props.faculty.slice(start, start + perPage)
})
const pageRange  = computed(() => {
  const pages = []
  const start = Math.max(1, currentPage.value - 2)
  const end   = Math.min(totalPages.value, currentPage.value + 2)
  for (let i = start; i <= end; i++) pages.push(i)
  return pages
})

// ── Edit Modal ────────────────────────────────────────────────────────────────
const editModal = reactive({ open: false, id: null, name: '' })
const saving    = ref(false)

const form = reactive({
  name:           '',
  sex:            '',
  position:       '',
  specialization: '',
  division_id:    '',
  office_id:      '',
  emp_category:   '',
  on_study_leave: false,
})

const filteredOffices = computed(() =>
  form.division_id
    ? props.offices.filter(o => o.division_id === form.division_id)
    : props.offices
)

function openEdit(f) {
  Object.assign(form, {
    name:           f.name           ?? '',
    sex:            f.sex            ?? '',
    position:       f.position       ?? '',
    specialization: f.specialization ?? '',
    division_id:    f.division?.id   ?? '',
    office_id:      f.office?.id     ?? '',
    emp_category:   f.emp_category   ?? '',
    on_study_leave: f.on_study_leave ?? false,
  })
  editModal.id   = f.id
  editModal.name = f.name
  editModal.open = true
}

function closeEdit() {
  editModal.open = false
}

async function submitEdit() {
  saving.value = true
  router.put(route('faculty-loading.faculty-list.update', editModal.id), { ...form }, {
    onSuccess: async () => {
      saving.value = false
      closeEdit()
      await Swal.fire('Updated', 'Faculty details have been saved.', 'success')
    },
    onError: async (errors) => {
      saving.value = false
      await Swal.fire('Error', Object.values(errors).flat().join('\n'), 'error')
    },
  })
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function categoryBadge(cat) {
  const map = {
    'Plantilla Teaching':     'blue',
    'Plantilla Non-Teaching': 'purple',
    'COS Teaching':           'amber',
    'COS Non Teaching':       'orange',
  }
  return map[cat] ?? 'slate'
}
</script>
