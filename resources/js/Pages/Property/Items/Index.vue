<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { PlusIcon, MagnifyingGlassIcon, PencilIcon, ArrowUpTrayIcon, ArrowDownTrayIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({ items: Array, categories: Array, officers: Array, summary: Object })
const page = usePage()
const canManage = computed(() => page.props.auth?.user?.permissions?.includes('property.manage'))

const search = ref('')
const filterStatus = ref('')
const filterType = ref('')
const PER_PAGE = 15
const currentPage = ref(1)

const filtered = computed(() => {
  let list = props.items
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(i => i.description.toLowerCase().includes(q) || i.property_number.toLowerCase().includes(q))
  }
  if (filterStatus.value) list = list.filter(i => i.status === filterStatus.value)
  if (filterType.value) list = list.filter(i => i.category_type === filterType.value)
  return list
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => filtered.value.slice((currentPage.value-1)*PER_PAGE, currentPage.value*PER_PAGE))

const showItemModal = ref(false)
const editId = ref(null)
const form = useForm({ description:'', category_id:'', unit:'unit', quantity:1, unit_cost:'', acquisition_date:'', acquisition_mode:'purchase', supplier_name:'', brand:'', model:'', serial_number:'', location:'', current_officer_id:'', status:'serviceable', remarks:'' })

function openCreate() { editId.value=null; form.reset(); form.acquisition_mode='purchase'; form.status='serviceable'; form.quantity=1; form.unit='unit'; showItemModal.value=true }
function openEdit(item) {
  editId.value=item.id
  Object.assign(form, { description:item.description, category_id:item.category_id, unit:item.unit, unit_cost:item.unit_cost, acquisition_date:item.acquisition_date, acquisition_mode:item.acquisition_mode, supplier_name:item.supplier_name??'', brand:item.brand??'', model:item.model??'', serial_number:item.serial_number??'', location:item.location??'', current_officer_id:item.current_officer_id??'', status:item.status, remarks:item.remarks??'' })
  showItemModal.value=true
}
function submitItem() {
  if (editId.value) {
    form.put(route('property.items.update', editId.value), { preserveScroll:true, onSuccess:()=>{ showItemModal.value=false } })
  } else {
    form.post(route('property.items.store'), { preserveScroll:true, onSuccess:()=>{ showItemModal.value=false } })
  }
}

const showCatModal = ref(false)
const catForm = useForm({ name:'', account_code:'', type:'equipment', useful_life_years:5, residual_rate:0.05, description:'' })
function submitCategory() {
  catForm.post(route('property.categories.store'), { preserveScroll:true, onSuccess:()=>{ showCatModal.value=false } })
}

function statusColor(status) {
  const map = { serviceable: 'green', unserviceable: 'amber', disposed: 'red', transferred: 'blue', lost: 'slate' }
  return map[status] ?? 'slate'
}

// CSV Import
const showImportModal = ref(false)
const importStep = ref('upload')
const importLoading = ref(false)
const importPreview = ref([])
const importResult = ref(null)
const importError = ref('')
let pendingCsvBase64 = ''

function openImport() { showImportModal.value=true; importStep.value='upload'; importPreview.value=[]; importResult.value=null; importError.value='' }

async function onFileChange(e) {
  const file = e.target.files[0]
  if (!file) return
  importError.value = ''; importLoading.value = true
  const reader = new FileReader()
  reader.onload = async ev => {
    pendingCsvBase64 = btoa(ev.target.result)
    try {
      const { data } = await axios.post(route('property.items.import.preview'), { csv_base64: pendingCsvBase64 })
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
    const { data } = await axios.post(route('property.items.import'), { csv_base64: pendingCsvBase64 })
    importResult.value = data; importStep.value = 'done'
  } catch (err) {
    importError.value = err.response?.data?.message ?? 'Import failed.'
  } finally { importLoading.value = false }
}

function finishImport() { showImportModal.value=false; window.location.reload() }

const importHasErrors = computed(() => importPreview.value.some(r => r.errors.length > 0))
const importValidRows = computed(() => importPreview.value.filter(r => r.errors.length === 0).length)
</script>
<template>
  <Head title="Property Items" />
  <AdminLayout title="Property Items Registry">
    <div class="space-y-5">

      <AppPageHeader title="Property Items Registry" subtitle="Semi-expendable property and equipment/PPE inventory.">
        <template #actions>
          <template v-if="canManage">
            <AppButton variant="secondary" @click="showCatModal=true">
              <PlusIcon class="h-4 w-4" />
              Category
            </AppButton>
            <AppButton variant="secondary" as="a" :href="route('property.items.import.template')">
              <ArrowDownTrayIcon class="h-4 w-4" />
              Template
            </AppButton>
            <AppButton variant="success" @click="openImport">
              <ArrowUpTrayIcon class="h-4 w-4" />
              Import CSV
            </AppButton>
            <AppButton @click="openCreate">
              <PlusIcon class="h-4 w-4" />
              Add Item
            </AppButton>
          </template>
        </template>
      </AppPageHeader>

      <!-- Summary -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4"><div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Items</div><div class="text-2xl font-bold mt-1">{{ summary.total }}</div></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Serviceable</div><div class="text-2xl font-bold text-success-700 mt-1">{{ summary.serviceable }}</div></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Cost</div><div class="text-xl font-bold mt-1">₱{{ summary.total_cost.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</div></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Book Value</div><div class="text-xl font-bold text-indigo-700 mt-1">₱{{ summary.book_value.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</div></div>
      </div>

      <!-- Filters -->
      <AppFilterBar>
        <div class="relative flex-1 min-w-48 sm:min-w-64">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input v-model="search" @input="currentPage=1" type="text" placeholder="Search property number, description…"
                 class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <select v-model="filterStatus" @change="currentPage=1" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Status</option>
          <option value="serviceable">Serviceable</option>
          <option value="unserviceable">Unserviceable</option>
          <option value="disposed">Disposed</option>
          <option value="transferred">Transferred</option>
          <option value="lost">Lost</option>
        </select>
        <select v-model="filterType" @change="currentPage=1" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Types</option>
          <option value="semi_expendable">Semi-Expendable</option>
          <option value="equipment">Equipment/PPE</option>
        </select>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!displayed.length" :skeleton-cols="canManage ? 8 : 7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Property No.</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Description</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Category</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Cost</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Book Value</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Officer</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th v-if="canManage" class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="item in displayed" :key="item.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm font-mono text-indigo-700"><Link :href="route('property.items.show',item.id)" class="hover:underline">{{ item.property_number }}</Link></td>
          <td class="px-4 py-3">
            <div class="text-sm font-medium text-slate-800">{{ item.description }}</div>
            <div v-if="item.brand||item.model" class="text-xs text-slate-400">{{ [item.brand,item.model].filter(Boolean).join(' ') }}</div>
          </td>
          <td class="px-4 py-3 text-sm text-slate-600">{{ item.category_name }}</td>
          <td class="px-4 py-3 text-right text-sm text-slate-700">₱{{ item.total_cost.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</td>
          <td class="px-4 py-3 text-right text-sm font-medium text-indigo-700">₱{{ item.book_value.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</td>
          <td class="px-4 py-3 text-sm text-slate-600">{{ item.current_officer ?? '—' }}</td>
          <td class="px-4 py-3 text-center"><AppBadge :color="statusColor(item.status)">{{ item.status }}</AppBadge></td>
          <td v-if="canManage" class="px-4 py-3 text-right">
            <AppIconButton label="Edit item" variant="ghost" @click="openEdit(item)">
              <PencilIcon class="h-4 w-4" />
            </AppIconButton>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="item in displayed" :key="item.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <Link :href="route('property.items.show',item.id)" class="text-sm font-mono text-indigo-700 hover:underline">{{ item.property_number }}</Link>
                <p class="text-sm font-medium text-slate-800">{{ item.description }}</p>
                <p v-if="item.brand||item.model" class="text-xs text-slate-400">{{ [item.brand,item.model].filter(Boolean).join(' ') }}</p>
              </div>
              <AppBadge :color="statusColor(item.status)">{{ item.status }}</AppBadge>
            </div>
            <div class="flex justify-between text-xs text-slate-500">
              <span>{{ item.category_name }} &middot; {{ item.current_officer ?? '—' }}</span>
              <span class="font-semibold text-indigo-700">₱{{ item.book_value.toLocaleString('en-PH',{minimumFractionDigits:2}) }}</span>
            </div>
            <div v-if="canManage" class="flex justify-end pt-1">
              <AppIconButton label="Edit item" variant="ghost" @click="openEdit(item)">
                <PencilIcon class="h-4 w-4" />
              </AppIconButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No property items found" />
        </template>

        <template #footer>
          <PaginationControl
            v-if="totalPages > 1"
            :current-page="currentPage"
            :total-pages="totalPages"
            @prev="currentPage--"
            @next="currentPage++"
            @page="currentPage = $event"
          />
        </template>
      </AppTable>

    </div>

    <!-- Item Modal -->
    <AppModal :show="showItemModal" :title="editId ? 'Edit Property Item' : 'Add Property Item'" size="2xl" @close="showItemModal=false">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">Description *</label>
          <input v-model="form.description" type="text" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Category *</label>
          <select v-model="form.category_id" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">Select…</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Unit *</label>
          <input v-model="form.unit" type="text" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div v-if="!editId">
          <label class="block text-sm font-medium text-slate-700 mb-1">Quantity *</label>
          <input v-model="form.quantity" type="number" step="0.001" min="0.001" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Unit Cost *</label>
          <input v-model="form.unit_cost" type="number" step="0.01" min="0" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Acquisition Date *</label>
          <input v-model="form.acquisition_date" type="date" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Mode *</label>
          <select v-model="form.acquisition_mode" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="purchase">Purchase</option>
            <option value="donation">Donation</option>
            <option value="transfer">Transfer</option>
            <option value="fabricated">Fabricated</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Brand</label>
          <input v-model="form.brand" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Model</label>
          <input v-model="form.model" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Serial No.</label>
          <input v-model="form.serial_number" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Location</label>
          <input v-model="form.location" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Accountable Officer</label>
          <select v-model="form.current_officer_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">— None —</option>
            <option v-for="u in officers" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div v-if="editId">
          <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
          <select v-model="form.status" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="serviceable">Serviceable</option>
            <option value="unserviceable">Unserviceable</option>
            <option value="disposed">Disposed</option>
            <option value="transferred">Transferred</option>
            <option value="lost">Lost</option>
          </select>
        </div>
        <div class="col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">Remarks</label>
          <textarea v-model="form.remarks" rows="2" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="showItemModal=false">Cancel</AppButton>
        <AppButton :loading="form.processing" @click="submitItem">{{ editId?'Update':'Create' }}</AppButton>
      </template>
    </AppModal>

    <!-- Import CSV Modal -->
    <AppModal :show="showImportModal" title="Import Property Items from CSV" size="3xl" @close="showImportModal=false">

      <!-- Step: Upload -->
      <div v-if="importStep==='upload'">
        <p class="text-sm text-slate-600 mb-4">Upload a CSV file to bulk-import property items. <a :href="route('property.items.import.template')" class="text-indigo-600 hover:underline font-medium">Download the template</a> to see the required format.</p>
        <div class="bg-slate-50 border-2 border-dashed border-slate-300 rounded-xl p-8 text-center">
          <ArrowUpTrayIcon class="h-8 w-8 text-slate-400 mx-auto mb-2" />
          <p class="text-sm text-slate-500 mb-3">Select your filled-in CSV file</p>
          <input type="file" accept=".csv,text/csv" @change="onFileChange" class="block mx-auto text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
        </div>
        <p v-if="importError" class="text-sm text-danger-600 mt-3">{{ importError }}</p>
        <p v-if="importLoading" class="text-sm text-slate-500 mt-3 text-center">Parsing CSV…</p>
      </div>

      <!-- Step: Preview -->
      <div v-if="importStep==='preview'">
        <div class="flex items-center justify-between mb-3">
          <p class="text-sm text-slate-700"><strong>{{ importValidRows }}</strong> valid rows ready to import<span v-if="importHasErrors">, <strong class="text-warning-600">{{ importPreview.length - importValidRows }}</strong> with errors (will be skipped)</span>.</p>
          <button @click="importStep='upload'" class="text-xs text-slate-500 hover:text-slate-700 underline">Re-upload</button>
        </div>
        <div class="overflow-auto max-h-72 border border-slate-200 rounded-lg">
          <table class="min-w-full text-xs divide-y divide-slate-100">
            <thead class="bg-slate-50 sticky top-0">
              <tr>
                <th class="px-3 py-2 text-left font-semibold text-slate-500">Row</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-500">Prop. No.</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-500">Description</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-500">Category</th>
                <th class="px-3 py-2 text-right font-semibold text-slate-500">Cost</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-500">Officer</th>
                <th class="px-3 py-2 text-center font-semibold text-slate-500">Action</th>
                <th class="px-3 py-2 text-left font-semibold text-slate-500">Issues</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="r in importPreview" :key="r.row" :class="r.errors.length ? 'bg-danger-50' : 'hover:bg-slate-50'">
                <td class="px-3 py-2 text-slate-400">{{ r.row }}</td>
                <td class="px-3 py-2 font-mono text-slate-600">{{ r.property_number }}</td>
                <td class="px-3 py-2 text-slate-800">{{ r.description }}</td>
                <td class="px-3 py-2" :class="r.category_matched ? 'text-slate-600' : 'text-danger-600 font-medium'">{{ r.category_name }}</td>
                <td class="px-3 py-2 text-right">₱{{ Number(r.unit_cost).toLocaleString('en-PH',{minimumFractionDigits:2}) }}</td>
                <td class="px-3 py-2" :class="r.officer_matched ? 'text-slate-600' : 'text-warning-600'">{{ r.accountable_officer || '—' }}</td>
                <td class="px-3 py-2 text-center"><AppBadge :color="r.action==='update' ? 'amber' : 'green'">{{ r.action }}</AppBadge></td>
                <td class="px-3 py-2 text-danger-600">{{ r.errors.join('; ') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="text-xs text-warning-600 mt-2" v-if="importPreview.some(r=>!r.officer_matched && r.accountable_officer)">Unmatched officer names will be imported without an assigned officer.</p>
        <p v-if="importError" class="text-sm text-danger-600 mt-3">{{ importError }}</p>
        <div class="flex justify-end gap-3 mt-4">
          <AppButton variant="secondary" @click="showImportModal=false">Cancel</AppButton>
          <AppButton variant="success" :loading="importLoading" :disabled="importValidRows===0" @click="confirmImport">
            {{ importLoading ? 'Importing…' : `Import ${importValidRows} Row${importValidRows===1?'':'s'}` }}
          </AppButton>
        </div>
      </div>

      <!-- Step: Done -->
      <div v-if="importStep==='done'" class="text-center py-6">
        <CheckCircleIcon class="h-12 w-12 text-success-500 mx-auto mb-3" />
        <p class="text-lg font-semibold text-slate-800">Import Complete</p>
        <p class="text-sm text-slate-500 mt-1"><strong>{{ importResult.imported }}</strong> items imported<span v-if="importResult.skipped">, <strong>{{ importResult.skipped }}</strong> skipped</span>.</p>
        <ul v-if="importResult.errors?.length" class="mt-3 text-xs text-warning-700 text-left bg-warning-50 rounded-lg p-3 space-y-1 max-h-32 overflow-auto">
          <li v-for="e in importResult.errors" :key="e">{{ e }}</li>
        </ul>
        <AppButton class="mt-5" @click="finishImport">Done</AppButton>
      </div>

    </AppModal>

    <!-- Category Modal -->
    <AppModal :show="showCatModal" title="Add Property Category" @close="showCatModal=false">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Name *</label>
          <input v-model="catForm.name" type="text" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Account Code</label>
            <input v-model="catForm.account_code" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Type *</label>
            <select v-model="catForm.type" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="semi_expendable">Semi-Expendable</option>
              <option value="equipment">Equipment/PPE</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Useful Life (years) *</label>
            <input v-model="catForm.useful_life_years" type="number" min="1" max="50" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Residual Rate</label>
            <input v-model="catForm.residual_rate" type="number" step="0.01" min="0" max="1" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <p class="text-xs text-slate-400 mt-1">5% default per GAM (0.05)</p>
          </div>
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="showCatModal=false">Cancel</AppButton>
        <AppButton :loading="catForm.processing" @click="submitCategory">Create</AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>
