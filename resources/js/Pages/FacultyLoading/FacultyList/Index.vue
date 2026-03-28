<template>
  <Head title="Faculty List" />
  <AdminLayout title="Faculty List">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Faculty List</h1>
          <p class="text-sm text-slate-500 mt-0.5">All active faculty members with their positions and specializations</p>
        </div>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-indigo-50 text-indigo-700 text-sm font-medium">
          <UsersIcon class="h-4 w-4" />
          {{ faculty.length }} faculty
        </span>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-2">
        <input
          v-model="search"
          @input="onSearch"
          type="search"
          placeholder="Search name, badge ID, position, specialization..."
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 w-72 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
        />
        <select
          v-model="divisionFilter"
          @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
        >
          <option value="">All Divisions</option>
          <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <select
          v-model="categoryFilter"
          @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
        >
          <option value="">All Categories</option>
          <option value="Plantilla Teaching">Plantilla Teaching</option>
          <option value="Plantilla Non-Teaching">Plantilla Non-Teaching</option>
          <option value="COS Teaching">COS Teaching</option>
          <option value="COS Non Teaching">COS Non Teaching</option>
        </select>
      </div>

      <!-- Empty -->
      <div v-if="faculty.length === 0" class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center">
        <UsersIcon class="mx-auto h-12 w-12 text-slate-200 mb-3" />
        <p class="text-sm font-medium text-slate-500">No faculty members found</p>
        <p class="text-xs text-slate-400 mt-1">Try adjusting your filters or ensure users are assigned the Faculty role.</p>
      </div>

      <!-- Table -->
      <div v-else class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">#</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Name</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Badge ID</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Position</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Specialization</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Division / Office</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Category</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Sex</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr
              v-for="(f, i) in paged"
              :key="f.id"
              class="hover:bg-slate-50 transition-colors"
            >
              <td class="px-4 py-3 text-slate-400 tabular-nums">{{ (currentPage - 1) * perPage + i + 1 }}</td>
              <td class="px-4 py-3">
                <div class="font-medium text-slate-800">{{ f.name }}</div>
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
                <span
                  v-if="f.emp_category"
                  :class="categoryBadge(f.emp_category)"
                  class="inline-block px-2 py-0.5 rounded-full text-xs font-medium"
                >
                  {{ f.emp_category }}
                </span>
                <span v-else class="text-slate-400">—</span>
              </td>
              <td class="px-4 py-3 text-center text-slate-600">{{ f.sex || '—' }}</td>
              <td class="px-4 py-3 text-center">
                <button
                  @click="openEdit(f)"
                  class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors"
                >
                  <PencilSquareIcon class="h-3.5 w-3.5" /> Edit
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 bg-slate-50">
          <p class="text-xs text-slate-500">
            Showing {{ (currentPage - 1) * perPage + 1 }}–{{ Math.min(currentPage * perPage, faculty.length) }} of {{ faculty.length }}
          </p>
          <div class="flex gap-1">
            <button :disabled="currentPage === 1" @click="currentPage--"
              class="px-2.5 py-1 text-xs rounded border border-slate-200 text-slate-600 disabled:opacity-40 hover:bg-slate-100 transition-colors">Prev</button>
            <button
              v-for="p in pageRange" :key="p" @click="currentPage = p"
              :class="p === currentPage ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-200 text-slate-600 hover:bg-slate-100'"
              class="px-2.5 py-1 text-xs rounded border transition-colors">{{ p }}</button>
            <button :disabled="currentPage === totalPages" @click="currentPage++"
              class="px-2.5 py-1 text-xs rounded border border-slate-200 text-slate-600 disabled:opacity-40 hover:bg-slate-100 transition-colors">Next</button>
          </div>
        </div>
      </div>

    </div>

    <!-- ── Edit Modal ─────────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="editModal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeEdit" />

        <!-- Panel -->
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">

          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
            <div>
              <h2 class="text-base font-semibold text-slate-800">Edit Faculty Details</h2>
              <p class="text-xs text-slate-400 mt-0.5">{{ editModal.name }}</p>
            </div>
            <button @click="closeEdit" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 transition-colors">
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>

          <!-- Body -->
          <form @submit.prevent="submitEdit" class="px-6 py-5 space-y-4">

            <!-- Name -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Full Name <span class="text-red-400">*</span></label>
              <input v-model="form.name" type="text" required
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                placeholder="e.g. Juan dela Cruz" />
            </div>

            <!-- Sex -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Sex</label>
              <select v-model="form.sex"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                <option value="">— Select —</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>

            <!-- Position -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Position</label>
              <input v-model="form.position" type="text"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                placeholder="e.g. Teacher I" />
            </div>

            <!-- Specialization -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Specialization</label>
              <input v-model="form.specialization" type="text"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                placeholder="e.g. Mathematics, Science" />
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
                <option
                  v-for="o in filteredOffices"
                  :key="o.id"
                  :value="o.id"
                >{{ o.name }}</option>
              </select>
            </div>

            <!-- Employment Category -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Employment Category</label>
              <select v-model="form.emp_category"
                class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                <option value="">— None —</option>
                <option value="Plantilla Teaching">Plantilla Teaching</option>
                <option value="Plantilla Non-Teaching">Plantilla Non-Teaching</option>
                <option value="COS Teaching">COS Teaching</option>
                <option value="COS Non Teaching">COS Non Teaching</option>
              </select>
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="closeEdit"
                class="px-4 py-2 text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg font-medium transition-colors">
                Cancel
              </button>
              <button type="submit" :disabled="saving"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white rounded-lg font-medium transition-colors">
                <ArrowPathIcon v-if="saving" class="h-4 w-4 animate-spin" />
                <CheckIcon v-else class="h-4 w-4" />
                {{ saving ? 'Saving…' : 'Save Changes' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  UsersIcon,
  AcademicCapIcon,
  PencilSquareIcon,
  XMarkIcon,
  ArrowPathIcon,
  CheckIcon,
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
  email:          '',
  sex:            '',
  badge_id:       '',
  position:       '',
  specialization: '',
  division_id:    '',
  office_id:      '',
  emp_category:   '',
})

const filteredOffices = computed(() =>
  form.division_id
    ? props.offices.filter(o => o.division_id === form.division_id)
    : props.offices
)

function openEdit(f) {
  Object.assign(form, {
    name:           f.name           ?? '',
    email:          f.email          ?? '',
    sex:            f.sex            ?? '',
    badge_id:       f.badge_id       ?? '',
    position:       f.position       ?? '',
    specialization: f.specialization ?? '',
    division_id:    f.division?.id   ?? '',
    office_id:      f.office?.id     ?? '',
    emp_category:   f.emp_category   ?? '',
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
  router.put(`/users/${editModal.id}`, { ...form }, {
    onSuccess: async () => {
      saving.value = false
      closeEdit()
      await Swal.fire('Updated', 'Faculty details have been saved.', 'success')
      router.reload({ only: ['faculty'] })
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
    'Plantilla Teaching':     'bg-blue-100 text-blue-700',
    'Plantilla Non-Teaching': 'bg-purple-100 text-purple-700',
    'COS Teaching':           'bg-amber-100 text-amber-700',
    'COS Non Teaching':       'bg-orange-100 text-orange-700',
  }
  return map[cat] ?? 'bg-slate-100 text-slate-600'
}
</script>
