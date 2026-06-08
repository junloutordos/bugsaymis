<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  PlusIcon, PencilIcon, MagnifyingGlassIcon,
  ExclamationTriangleIcon, ArchiveBoxIcon, CheckCircleIcon,
  ArrowUpTrayIcon, ArrowDownTrayIcon
} from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
  items: Array,
  categories: Array,
})

const page = usePage()
const perms = computed(() => ({
  canManage: page.props.auth?.user?.permissions?.includes('supply.manage'),
}))

const search = ref('')
const filterCategory = ref('')
const filterType = ref('')
const filterLowStock = ref(false)

const PER_PAGE = 15
const currentPage = ref(1)

const filtered = computed(() => {
  let list = props.items
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(i =>
      i.description.toLowerCase().includes(q) ||
      i.item_code.toLowerCase().includes(q)
    )
  }
  if (filterCategory.value) {
    list = list.filter(i => i.category_id == filterCategory.value)
  }
  if (filterType.value) {
    list = list.filter(i => i.category_type === filterType.value)
  }
  if (filterLowStock.value) {
    list = list.filter(i => i.is_low_stock)
  }
  return list
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})

// Item form
const showItemModal = ref(false)
const editId = ref(null)

const itemForm = useForm({
  description: '',
  unit: '',
  category_id: '',
  estimated_unit_cost: '',
  reorder_point: '',
  reorder_quantity: '',
  specifications: '',
})

function openCreate() {
  editId.value = null
  itemForm.reset()
  showItemModal.value = true
}

function openEdit(item) {
  editId.value = item.id
  itemForm.description = item.description
  itemForm.unit = item.unit
  itemForm.category_id = item.category_id
  itemForm.estimated_unit_cost = item.estimated_unit_cost
  itemForm.reorder_point = item.reorder_point ?? ''
  itemForm.reorder_quantity = item.reorder_quantity ?? ''
  itemForm.specifications = item.specifications ?? ''
  showItemModal.value = true
}

function submitItem() {
  if (editId.value) {
    itemForm.put(route('supply.items.update', editId.value), {
      preserveScroll: true,
      onSuccess: () => { showItemModal.value = false }
    })
  } else {
    itemForm.post(route('supply.items.store'), {
      preserveScroll: true,
      onSuccess: () => { showItemModal.value = false }
    })
  }
}

function toggleActive(item) {
  useForm({}).post(route('supply.items.toggle-active', item.id), { preserveScroll: true })
}

// Category form
const showCatModal = ref(false)
const editCatId = ref(null)

const catForm = useForm({
  name: '',
  account_code: '',
  type: 'consumable',
  description: '',
})

function openCreateCat() {
  editCatId.value = null
  catForm.reset()
  catForm.type = 'consumable'
  showCatModal.value = true
}

function openEditCat(cat) {
  editCatId.value = cat.id
  catForm.name = cat.name
  catForm.account_code = cat.account_code ?? ''
  catForm.type = cat.type
  catForm.description = cat.description ?? ''
  showCatModal.value = true
}

function submitCategory() {
  if (editCatId.value) {
    catForm.put(route('supply.categories.update', editCatId.value), {
      preserveScroll: true,
      onSuccess: () => { showCatModal.value = false }
    })
  } else {
    catForm.post(route('supply.categories.store'), {
      preserveScroll: true,
      onSuccess: () => { showCatModal.value = false }
    })
  }
}

// CSV Import
const showImportModal = ref(false)
const importStep = ref('upload') // upload | preview | done
const importLoading = ref(false)
const importPreview = ref([])
const importResult = ref(null)
const importError = ref('')
let pendingCsvBase64 = ''

function openImport() { showImportModal.value = true; importStep.value = 'upload'; importPreview.value = []; importResult.value = null; importError.value = '' }

async function onFileChange(e) {
  const file = e.target.files[0]
  if (!file) return
  importError.value = ''
  importLoading.value = true
  const reader = new FileReader()
  reader.onload = async ev => {
    const base64 = btoa(ev.target.result)
    pendingCsvBase64 = base64
    try {
      const { data } = await axios.post(route('supply.items.import.preview'), { csv_base64: base64 })
      importPreview.value = data.preview
      importStep.value = 'preview'
    } catch (err) {
      importError.value = err.response?.data?.error ?? 'Failed to parse CSV.'
    } finally { importLoading.value = false }
  }
  reader.readAsText(file)
}

async function confirmImport() {
  importLoading.value = true
  try {
    const { data } = await axios.post(route('supply.items.import'), { csv_base64: pendingCsvBase64 })
    importResult.value = data
    importStep.value = 'done'
  } catch (err) {
    importError.value = err.response?.data?.message ?? 'Import failed.'
  } finally { importLoading.value = false }
}

function finishImport() { showImportModal.value = false; window.location.reload() }

const importHasErrors = computed(() => importPreview.value.some(r => r.errors.length > 0))
const importValidRows = computed(() => importPreview.value.filter(r => r.errors.length === 0).length)

const typeLabel = { consumable: 'Consumable', semi_expendable: 'Semi-Expendable', equipment: 'Equipment' }
const typeBadge = { consumable: 'bg-blue-100 text-blue-700', semi_expendable: 'bg-amber-100 text-amber-700', equipment: 'bg-violet-100 text-violet-700' }

const totalStockValue = computed(() =>
  props.items.reduce((sum, i) => sum + i.total_value, 0)
)
const lowStockCount = computed(() => props.items.filter(i => i.is_low_stock).length)
</script>

<template>
  <Head title="Supply Item Catalog" />
  <AdminLayout title="Supply Item Catalog">
    <!-- Stats -->
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Items</div>
        <div class="text-2xl font-bold text-slate-800 mt-1">{{ items.length }}</div>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Low Stock</div>
        <div class="text-2xl font-bold mt-1" :class="lowStockCount > 0 ? 'text-red-600' : 'text-slate-800'">{{ lowStockCount }}</div>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Stock Value</div>
        <div class="text-2xl font-bold text-emerald-700 mt-1">₱{{ totalStockValue.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="flex flex-wrap gap-3 items-center mb-4">
      <div class="relative flex-1 min-w-48">
        <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
        <input v-model="search" @input="currentPage=1" type="text" placeholder="Search items..."
          class="pl-9 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
      </div>
      <select v-model="filterCategory" @change="currentPage=1"
        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="">All Categories</option>
        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
      </select>
      <select v-model="filterType" @change="currentPage=1"
        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="">All Types</option>
        <option value="consumable">Consumable</option>
        <option value="semi_expendable">Semi-Expendable</option>
        <option value="equipment">Equipment</option>
      </select>
      <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
        <input v-model="filterLowStock" @change="currentPage=1" type="checkbox" class="rounded" />
        Low Stock Only
      </label>
      <div class="flex gap-2 ml-auto">
        <button v-if="perms.canManage" @click="openCreateCat"
          class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2">
          <PlusIcon class="h-4 w-4" /> Category
        </button>
        <a v-if="perms.canManage" :href="route('supply.items.import.template')"
          class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2">
          <ArrowDownTrayIcon class="h-4 w-4" /> Template
        </a>
        <button v-if="perms.canManage" @click="openImport"
          class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2">
          <ArrowUpTrayIcon class="h-4 w-4" /> Import CSV
        </button>
        <button v-if="perms.canManage" @click="openCreate"
          class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2">
          <PlusIcon class="h-4 w-4" /> Add Item
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="min-w-full divide-y divide-slate-100">
        <thead>
          <tr class="bg-slate-50">
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Item Code</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Description</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Category</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Balance</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Avg Cost</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Value</th>
            <th v-if="perms.canManage" class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-if="displayed.length === 0">
            <td :colspan="perms.canManage ? 8 : 7" class="px-4 py-8 text-center text-slate-400 text-sm">No items found.</td>
          </tr>
          <tr v-for="item in displayed" :key="item.id" :class="!item.is_active ? 'opacity-50' : ''">
            <td class="px-4 py-3 text-sm font-mono text-slate-600">{{ item.item_code }}</td>
            <td class="px-4 py-3">
              <div class="text-sm font-medium text-slate-800">{{ item.description }}</div>
              <div v-if="item.is_low_stock" class="flex items-center gap-1 text-xs text-red-600 mt-0.5">
                <ExclamationTriangleIcon class="h-3 w-3" /> Low stock
              </div>
            </td>
            <td class="px-4 py-3">
              <div class="text-sm text-slate-700">{{ item.category_name }}</div>
              <span v-if="item.category_type" class="inline-block px-1.5 py-0.5 rounded text-xs font-medium mt-0.5"
                :class="typeBadge[item.category_type]">
                {{ typeLabel[item.category_type] }}
              </span>
            </td>
            <td class="px-4 py-3 text-center text-sm text-slate-600">{{ item.unit }}</td>
            <td class="px-4 py-3 text-right text-sm font-medium"
              :class="item.is_low_stock ? 'text-red-600' : 'text-slate-800'">
              {{ item.balance_quantity.toLocaleString('en-PH', { minimumFractionDigits: 3 }) }}
            </td>
            <td class="px-4 py-3 text-right text-sm text-slate-600">
              ₱{{ item.average_unit_cost.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
            </td>
            <td class="px-4 py-3 text-right text-sm font-medium text-emerald-700">
              ₱{{ item.total_value.toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
            </td>
            <td v-if="perms.canManage" class="px-4 py-3">
              <div class="flex gap-2 justify-end">
                <button @click="openEdit(item)" class="text-indigo-600 hover:text-indigo-800 p-1">
                  <PencilIcon class="h-4 w-4" />
                </button>
                <button @click="toggleActive(item)" class="p-1"
                  :class="item.is_active ? 'text-slate-400 hover:text-red-600' : 'text-green-600 hover:text-green-800'">
                  <ArchiveBoxIcon v-if="item.is_active" class="h-4 w-4" />
                  <CheckCircleIcon v-else class="h-4 w-4" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="px-4 py-3 border-t border-slate-100 flex items-center justify-between text-sm text-slate-600">
        <span>Page {{ currentPage }} of {{ totalPages }}</span>
        <div class="flex gap-2">
          <button @click="currentPage--" :disabled="currentPage <= 1"
            class="px-3 py-1 rounded border border-slate-200 disabled:opacity-40">Prev</button>
          <button @click="currentPage++" :disabled="currentPage >= totalPages"
            class="px-3 py-1 rounded border border-slate-200 disabled:opacity-40">Next</button>
        </div>
      </div>
    </div>

    <!-- Item Modal -->
    <div v-if="showItemModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
        <div class="flex items-center justify-between p-6 border-b border-slate-100">
          <h3 class="text-base font-semibold text-slate-800">{{ editId ? 'Edit Item' : 'Add Supply Item' }}</h3>
          <button @click="showItemModal=false" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
        </div>
        <form @submit.prevent="submitItem" class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Description <span class="text-red-500">*</span></label>
            <input v-model="itemForm.description" type="text" required
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            <p v-if="itemForm.errors.description" class="text-xs text-red-600 mt-1">{{ itemForm.errors.description }}</p>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Unit <span class="text-red-500">*</span></label>
              <input v-model="itemForm.unit" type="text" required placeholder="pcs, ream, box…"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Category <span class="text-red-500">*</span></label>
              <select v-model="itemForm.category_id" required
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                <option value="">Select…</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Est. Unit Cost</label>
              <input v-model="itemForm.estimated_unit_cost" type="number" step="0.01" min="0"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Reorder Point</label>
              <input v-model="itemForm.reorder_point" type="number" min="0"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Reorder Qty</label>
              <input v-model="itemForm.reorder_quantity" type="number" min="0"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Specifications</label>
            <textarea v-model="itemForm.specifications" rows="2"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"></textarea>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="showItemModal=false"
              class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
            <button type="submit" :disabled="itemForm.processing"
              class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
              {{ editId ? 'Update' : 'Create' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Category Modal -->
    <div v-if="showCatModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
        <div class="flex items-center justify-between p-6 border-b border-slate-100">
          <h3 class="text-base font-semibold text-slate-800">{{ editCatId ? 'Edit Category' : 'Add Category' }}</h3>
          <button @click="showCatModal=false" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
        </div>
        <form @submit.prevent="submitCategory" class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Name <span class="text-red-500">*</span></label>
            <input v-model="catForm.name" type="text" required
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Account Code</label>
              <input v-model="catForm.account_code" type="text" placeholder="e.g. 5-02-03-010"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Type <span class="text-red-500">*</span></label>
              <select v-model="catForm.type" required
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                <option value="consumable">Consumable</option>
                <option value="semi_expendable">Semi-Expendable</option>
                <option value="equipment">Equipment</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
            <textarea v-model="catForm.description" rows="2"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"></textarea>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="showCatModal=false"
              class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
            <button type="submit" :disabled="catForm.processing"
              class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
              {{ editCatId ? 'Update' : 'Create' }}
            </button>
          </div>
        </form>
      </div>
    </div>
    <!-- Import CSV Modal -->
    <div v-if="showImportModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4 overflow-auto">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl my-4">
        <div class="flex items-center justify-between p-6 border-b border-slate-100">
          <h3 class="text-base font-semibold text-slate-800">Import Supply Items from CSV</h3>
          <button @click="showImportModal=false" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
        </div>
        <div class="p-6">

          <!-- Step: Upload -->
          <div v-if="importStep==='upload'">
            <p class="text-sm text-slate-600 mb-4">Upload a CSV file to bulk-import supply items. <a :href="route('supply.items.import.template')" class="text-indigo-600 hover:underline font-medium">Download the template</a> to see the required format.</p>
            <div class="bg-slate-50 border-2 border-dashed border-slate-300 rounded-xl p-8 text-center">
              <ArrowUpTrayIcon class="h-8 w-8 text-slate-400 mx-auto mb-2"/>
              <p class="text-sm text-slate-500 mb-3">Select your filled-in CSV file</p>
              <input type="file" accept=".csv,text/csv" @change="onFileChange" class="block mx-auto text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"/>
            </div>
            <p v-if="importError" class="text-sm text-red-600 mt-3">{{ importError }}</p>
            <p v-if="importLoading" class="text-sm text-slate-500 mt-3 text-center">Parsing CSV…</p>
          </div>

          <!-- Step: Preview -->
          <div v-if="importStep==='preview'">
            <div class="flex items-center justify-between mb-3">
              <p class="text-sm text-slate-700"><strong>{{ importValidRows }}</strong> valid rows ready to import<span v-if="importHasErrors">, <strong class="text-amber-600">{{ importPreview.length - importValidRows }}</strong> with errors (will be skipped)</span>.</p>
              <button @click="importStep='upload'" class="text-xs text-slate-500 hover:text-slate-700 underline">Re-upload</button>
            </div>
            <div class="overflow-auto max-h-72 border border-slate-200 rounded-lg">
              <table class="min-w-full text-xs divide-y divide-slate-100">
                <thead class="bg-slate-50 sticky top-0">
                  <tr>
                    <th class="px-3 py-2 text-left font-semibold text-slate-500">Row</th>
                    <th class="px-3 py-2 text-left font-semibold text-slate-500">Item Code</th>
                    <th class="px-3 py-2 text-left font-semibold text-slate-500">Description</th>
                    <th class="px-3 py-2 text-left font-semibold text-slate-500">Category</th>
                    <th class="px-3 py-2 text-right font-semibold text-slate-500">Qty</th>
                    <th class="px-3 py-2 text-right font-semibold text-slate-500">Unit Cost</th>
                    <th class="px-3 py-2 text-center font-semibold text-slate-500">Action</th>
                    <th class="px-3 py-2 text-left font-semibold text-slate-500">Issues</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="r in importPreview" :key="r.row" :class="r.errors.length ? 'bg-red-50' : 'hover:bg-slate-50'">
                    <td class="px-3 py-2 text-slate-400">{{ r.row }}</td>
                    <td class="px-3 py-2 font-mono text-slate-600">{{ r.item_code }}</td>
                    <td class="px-3 py-2 text-slate-800">{{ r.description }}</td>
                    <td class="px-3 py-2" :class="r.category_matched ? 'text-slate-600' : 'text-red-600 font-medium'">{{ r.category_name }}</td>
                    <td class="px-3 py-2 text-right">{{ r.opening_quantity }}</td>
                    <td class="px-3 py-2 text-right">{{ r.average_unit_cost }}</td>
                    <td class="px-3 py-2 text-center"><span :class="r.action==='update' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'" class="px-2 py-0.5 rounded-full font-medium capitalize">{{ r.action }}</span></td>
                    <td class="px-3 py-2 text-red-600">{{ r.errors.join('; ') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-if="importError" class="text-sm text-red-600 mt-3">{{ importError }}</p>
            <div class="flex justify-end gap-3 mt-4">
              <button @click="showImportModal=false" class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
              <button @click="confirmImport" :disabled="importLoading || importValidRows===0" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
                {{ importLoading ? 'Importing…' : `Import ${importValidRows} Row${importValidRows===1?'':'s'}` }}
              </button>
            </div>
          </div>

          <!-- Step: Done -->
          <div v-if="importStep==='done'" class="text-center py-6">
            <CheckCircleIcon class="h-12 w-12 text-emerald-500 mx-auto mb-3"/>
            <p class="text-lg font-semibold text-slate-800">Import Complete</p>
            <p class="text-sm text-slate-500 mt-1"><strong>{{ importResult.imported }}</strong> items imported<span v-if="importResult.skipped">, <strong>{{ importResult.skipped }}</strong> skipped</span>.</p>
            <ul v-if="importResult.errors?.length" class="mt-3 text-xs text-amber-700 text-left bg-amber-50 rounded-lg p-3 space-y-1 max-h-32 overflow-auto">
              <li v-for="e in importResult.errors" :key="e">{{ e }}</li>
            </ul>
            <button @click="finishImport" class="mt-5 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg text-sm font-medium">Done</button>
          </div>

        </div>
      </div>
    </div>
  </AdminLayout>
</template>
