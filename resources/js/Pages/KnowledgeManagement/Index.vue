<template>
  <Head title="Knowledge Management" />
  <AdminLayout title="Knowledge Management">
    <div class="space-y-5">

      <AppPageHeader
        title="Knowledge Management"
        subtitle="Searchable repository of memoranda, orders, and other issuances from the Office of the Executive Director"
      >
        <template v-if="canManage" #actions>
          <AppButton variant="secondary" @click="openCatModal">
            <Cog6ToothIcon class="h-4 w-4" /> Categories
          </AppButton>
          <AppButton as="link" :href="route('km.create')">
            <PlusIcon class="h-4 w-4" /> Upload Document
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="flash.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ flash.success }}
      </div>
      <div v-if="flash.error" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm">
        {{ flash.error }}
      </div>

      <!-- Filters -->
      <AppFilterBar>
        <div class="relative flex-1 min-w-[200px]">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input v-model="search" type="text" placeholder="Search title, reference no., description…"
            class="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <select v-model="filterCategory"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Categories</option>
          <option v-for="c in categories" :key="c.code" :value="c.code">{{ c.label }}</option>
        </select>
        <select v-model="filterYear"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Years</option>
          <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="displayed.length === 0" :skeleton-cols="canManage ? 7 : 5">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Category</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Title</th>
            <th class="hidden lg:table-cell px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Reference No.</th>
            <th class="hidden md:table-cell px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Issued</th>
            <th v-if="canManage" class="hidden md:table-cell px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th v-if="canManage" class="hidden md:table-cell px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Read</th>
            <th class="px-3 py-3"></th>
          </tr>
        </template>

        <tr v-for="i in displayed" :key="i.id"
          class="hover:bg-indigo-50/40 cursor-pointer"
          @click="router.visit(route('km.show', i.id))">
          <td class="px-4 py-3">
            <AppBadge :color="categoryBadgeColor(i.category_code)">{{ i.category?.label ?? i.category_code }}</AppBadge>
          </td>
          <td class="px-4 py-3 max-w-[260px]">
            <div class="flex items-center gap-2">
              <span v-if="!i.is_acknowledged" class="inline-block w-2 h-2 rounded-full bg-danger-600 shrink-0"></span>
              <p class="text-sm font-medium text-slate-800 truncate">{{ i.title }}</p>
              <LockClosedIcon v-if="canManage && i.recipient_type !== 'all'" class="h-3.5 w-3.5 text-slate-400 shrink-0" title="Restricted" />
            </div>
            <p class="text-xs text-slate-400 truncate">{{ fmtSize(i.file_size) }}</p>
          </td>
          <td class="hidden lg:table-cell px-4 py-3 text-xs text-slate-600 font-mono">{{ i.reference_no ?? '—' }}</td>
          <td class="hidden md:table-cell px-4 py-3 text-xs text-slate-500">{{ fmtDate(i.issued_date) }}</td>
          <td v-if="canManage" class="hidden md:table-cell px-4 py-3">
            <AppBadge :color="statusBadgeColor(i.status)" class="capitalize">{{ i.status }}</AppBadge>
          </td>
          <td v-if="canManage" class="hidden md:table-cell px-4 py-3 text-xs text-slate-500">
            {{ i.acknowledgments_count }}/{{ readDenominator(i) }}
          </td>
          <td class="px-3 py-3 text-right">
            <span class="text-indigo-600 text-xs font-medium">View →</span>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="i in displayed" :key="i.id" class="p-4 space-y-2 cursor-pointer"
            @click="router.visit(route('km.show', i.id))">
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-center gap-2 min-w-0">
                <span v-if="!i.is_acknowledged" class="inline-block w-2 h-2 rounded-full bg-danger-600 shrink-0"></span>
                <p class="text-sm font-medium text-slate-800 truncate">{{ i.title }}</p>
                <LockClosedIcon v-if="canManage && i.recipient_type !== 'all'" class="h-3.5 w-3.5 text-slate-400 shrink-0" />
              </div>
              <AppBadge :color="categoryBadgeColor(i.category_code)">{{ i.category?.label ?? i.category_code }}</AppBadge>
            </div>
            <p class="text-xs text-slate-500">{{ i.reference_no ?? '—' }} &middot; {{ fmtDate(i.issued_date) }}</p>
            <div v-if="canManage" class="flex items-center justify-between text-xs text-slate-500">
              <AppBadge :color="statusBadgeColor(i.status)" class="capitalize">{{ i.status }}</AppBadge>
              <span>{{ i.acknowledgments_count }}/{{ readDenominator(i) }} read</span>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No documents found" subtitle="Try adjusting your search or filters." />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage" :total-pages="totalPages" :total="filtered.length"
            @prev="currentPage--" @next="currentPage++" @page="currentPage = $event" />
        </template>
      </AppTable>

    </div>

    <!-- Manage Categories Modal -->
    <Teleport to="body">
      <div v-if="showCatModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[85vh] flex flex-col">

          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200">
            <h3 class="font-semibold text-slate-800">Manage Categories</h3>
            <button @click="closeCatModal" class="text-slate-400 hover:text-slate-600">
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>

          <!-- Body -->
          <div class="overflow-y-auto flex-1 px-5 py-4 space-y-1">
            <div v-for="cat in allCategories" :key="cat.code"
              class="rounded-lg border border-slate-200 px-3 py-2.5">

              <!-- View row -->
              <div v-if="editingCode !== cat.code" class="flex items-center gap-3">
                <AppBadge :color="categoryBadgeColor(cat.code)" class="shrink-0">{{ cat.code }}</AppBadge>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-slate-800 truncate">{{ cat.label }}</p>
                  <p v-if="cat.description" class="text-xs text-slate-400 truncate">{{ cat.description }}</p>
                </div>
                <span v-if="!cat.is_active"
                  class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 border border-slate-200 rounded px-1.5 py-0.5 shrink-0">
                  Inactive
                </span>
                <button @click="startEdit(cat)" class="text-slate-400 hover:text-indigo-600 shrink-0">
                  <PencilIcon class="h-4 w-4" />
                </button>
              </div>

              <!-- Edit row -->
              <div v-else class="space-y-2">
                <div class="flex items-center gap-2 mb-1">
                  <AppBadge :color="categoryBadgeColor(cat.code)">{{ cat.code }}</AppBadge>
                  <span class="text-xs text-slate-400">Code is permanent</span>
                </div>
                <AppInput v-model="editForm.label" placeholder="Label" :error="editForm.errors.label" />
                <AppInput v-model="editForm.description" placeholder="Description (optional)" />
                <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                  <input type="checkbox" v-model="editForm.is_active" class="rounded" />
                  Active (visible in dropdowns)
                </label>
                <div class="flex gap-2 pt-1">
                  <AppButton size="sm" :disabled="editForm.processing" @click="saveEdit(cat.code)">
                    <CheckIcon class="h-3.5 w-3.5" /> Save
                  </AppButton>
                  <AppButton size="sm" variant="secondary" @click="cancelEdit">Cancel</AppButton>
                </div>
              </div>
            </div>
          </div>

          <!-- Add new category -->
          <div class="border-t border-slate-200 px-5 py-4">
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">Add New Category</p>
            <div class="flex gap-2 mb-2">
              <input v-model="addForm.code" type="text" placeholder="Code (e.g. MC2)"
                maxlength="10"
                @input="addForm.code = addForm.code.toUpperCase()"
                class="w-28 rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
              <div class="flex-1">
                <AppInput v-model="addForm.label" placeholder="Label" />
              </div>
            </div>
            <AppInput v-model="addForm.description" placeholder="Description (optional)" />
            <p v-if="addForm.errors.code" class="text-xs text-danger-600 mt-1 mb-1">{{ addForm.errors.code }}</p>
            <p v-if="addForm.errors.label" class="text-xs text-danger-600 mb-1">{{ addForm.errors.label }}</p>
            <AppButton class="mt-2" :disabled="addForm.processing || !addForm.code || !addForm.label" @click="submitAdd">
              <PlusIcon class="h-4 w-4" /> Add Category
            </AppButton>
          </div>

        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, usePage, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppInput from '@/Components/AppInput.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import {
  PlusIcon, MagnifyingGlassIcon, LockClosedIcon, Cog6ToothIcon, XMarkIcon, PencilIcon, CheckIcon, CheckCircleIcon,
} from '@heroicons/vue/24/outline'

const page = usePage()
const flash = computed(() => page.props.flash ?? {})

const props = defineProps({
  issuances:        Array,
  categories:       Array,
  allCategories:    Array,
  canManage:        Boolean,
  totalActiveUsers: Number,
  filters:          Object,
})

const search         = ref(props.filters?.search ?? '')
const filterCategory = ref('')
const filterYear     = ref('')
const currentPage    = ref(1)
const PER_PAGE       = 15

watch([filterCategory, filterYear], () => { currentPage.value = 1 })

// Text search is server-side (searches content_text too) — debounced Inertia reload
let searchTimer = null
watch(search, (val) => {
  currentPage.value = 1
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    router.get(
      route('km.index'),
      val ? { search: val } : {},
      { preserveState: true, preserveScroll: true, replace: true }
    )
  }, 400)
})

const categoryBadgeColors = {
  MEMO:  'purple',
  MC:    'indigo',
  OO:    'blue',
  SO:    'blue',
  AO:    'amber',
  EO:    'red',
  BR:    'green',
  ADV:   'blue',
  GUIDE: 'orange',
  OTHER: 'slate',
}
function categoryBadgeColor(code) {
  return categoryBadgeColors[code] ?? categoryBadgeColors.OTHER
}

const statusBadgeColors = {
  active:     'green',
  superseded: 'amber',
  archived:   'slate',
}
function statusBadgeColor(status) {
  return statusBadgeColors[status] ?? 'slate'
}

const years = computed(() => {
  const y = new Set((props.issuances ?? []).map(i => i.issued_date?.slice(0, 4)).filter(Boolean))
  return [...y].sort().reverse()
})

// Text search is handled server-side; only category/year filter client-side here
const filtered = computed(() => {
  return (props.issuances ?? []).filter(i => {
    if (filterCategory.value && i.category_code !== filterCategory.value) return false
    if (filterYear.value && i.issued_date?.slice(0, 4) !== filterYear.value) return false
    return true
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed  = computed(() => {
  const s = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(s, s + PER_PAGE)
})

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}

function fmtSize(bytes) {
  if (!bytes) return '—'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function readDenominator(i) {
  return i.recipient_type === 'all' ? props.totalActiveUsers : i.recipients_count
}

// ── Category management modal ──────────────────────────────────────────────
const showCatModal = ref(false)
const editingCode  = ref(null)

const addForm = useForm({ code: '', label: '', description: '' })
const editForm = useForm({ label: '', description: '', is_active: true })

function openCatModal() { showCatModal.value = true; editingCode.value = null; addForm.reset() }
function closeCatModal() { showCatModal.value = false; editingCode.value = null }

function startEdit(cat) {
  editingCode.value = cat.code
  editForm.label       = cat.label
  editForm.description = cat.description ?? ''
  editForm.is_active   = cat.is_active
}
function cancelEdit() { editingCode.value = null }

function saveEdit(code) {
  editForm.put(route('km.categories.update', code), {
    preserveScroll: true,
    onSuccess: () => { editingCode.value = null },
  })
}

function submitAdd() {
  addForm.post(route('km.categories.store'), {
    preserveScroll: true,
    onSuccess: () => { addForm.reset() },
  })
}
</script>
