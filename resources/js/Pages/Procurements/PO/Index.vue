<script setup>
import { ref, computed, watch } from 'vue'
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon, EyeIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    poRecords:     Array,
    pendingAction: Array,
    prs:           Array,
    currentUser:   Object,
})

const page  = usePage()
const flash = computed(() => page.props.flash)
const perms = computed(() => props.currentUser?.permissions ?? {})

// ── Tabs ──────────────────────────────────────────────────────────────────
const activeTab = ref('mine')
const tabs = computed(() => {
    const t = [{ key: 'mine', label: 'My POs' }]
    if ((props.pendingAction || []).length > 0 || perms.value.canReview || perms.value.canSign) {
        t.push({ key: 'pending', label: `Pending My Action (${(props.pendingAction || []).length})` })
    }
    if (perms.value.canReview || perms.value.canSign) {
        t.push({ key: 'all', label: 'All POs' })
    }
    return t
})

const sourceList = computed(() => {
    if (activeTab.value === 'pending') return props.pendingAction || []
    if (activeTab.value === 'all')     return props.poRecords     || []
    return (props.poRecords || []).filter(o => o.creator?.id == props.currentUser?.id)
})

// ── Search + pagination ───────────────────────────────────────────────────
const searchQuery = ref('')
const currentPage = ref(1)
const PER_PAGE    = 15
watch([searchQuery, activeTab], () => { currentPage.value = 1 })

const filtered = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    return sourceList.value.filter(o =>
        (o.po_number    || '').toLowerCase().includes(q) ||
        (o.supplier_name|| '').toLowerCase().includes(q) ||
        (o.pr?.pr_no    || '').toLowerCase().includes(q)
    )
})
const totalPages  = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed   = computed(() => filtered.value.slice((currentPage.value - 1) * PER_PAGE, currentPage.value * PER_PAGE))

// ── Status badge ──────────────────────────────────────────────────────────
const STATUS_MAP = {
    draft:                { label: 'Draft',                     cls: 'bg-slate-100 text-slate-600' },
    pending_procurement:  { label: 'Pending Procurement',       cls: 'bg-amber-100 text-amber-700' },
    pending_ocd:          { label: 'Pending Campus Director',   cls: 'bg-blue-100 text-blue-700'   },
    issued:               { label: 'Issued',                    cls: 'bg-emerald-100 text-emerald-700' },
    cancelled:            { label: 'Cancelled',                 cls: 'bg-red-100 text-red-600'     },
}
function statusBadge(s) { return STATUS_MAP[s] ?? { label: s, cls: 'bg-slate-100 text-slate-600' } }

function formatAmount(v) {
    return Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// ── Create form modal ─────────────────────────────────────────────────────
const showModal = ref(false)
const form = useForm({
    procurement_id:  null,
    supplier_name:   '',
    supplier_address:'',
    supplier_tin:    '',
    delivery_place:  '',
    delivery_date:   '',
    payment_terms:   '30 days',
    delivery_terms:  'FOB Destination',
    remarks:         '',
})

function openCreate() { form.reset(); showModal.value = true }

function submitCreate() {
    form.post(route('po.store'), {
        onSuccess: () => { showModal.value = false },
    })
}
</script>

<template>
  <Head title="Purchase Orders" />
  <AdminLayout title="Purchase Orders">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Purchase Orders</h1>
          <p class="text-sm text-slate-500 mt-0.5">Manage POs issued to suppliers</p>
        </div>
        <button v-if="perms.canCreate" @click="openCreate"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium shrink-0">
          <PlusIcon class="h-4 w-4" /> New PO
        </button>
      </div>

      <!-- Flash -->
      <div v-if="flash?.success" class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm">
        {{ flash.success }}
      </div>

      <!-- Tabs -->
      <div class="flex gap-1 border-b border-slate-200">
        <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
          :class="['px-4 py-2 text-sm font-medium transition-colors',
            activeTab === tab.key ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-slate-500 hover:text-slate-700']">
          {{ tab.label }}
        </button>
      </div>

      <!-- Search -->
      <input v-model="searchQuery" type="search" placeholder="Search PO number, supplier, PR…"
        class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 w-64 focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />

      <!-- Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div v-if="displayed.length === 0" class="py-16 text-center text-slate-400 text-sm">
          No purchase orders found.
        </div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide border-b border-slate-100">
              <th class="text-left py-3 px-4">PO Number</th>
              <th class="text-left py-3 px-4">Supplier</th>
              <th class="text-left py-3 px-4">PR Ref</th>
              <th class="text-right py-3 px-4">Amount</th>
              <th class="text-left py-3 px-4">Status</th>
              <th class="text-left py-3 px-4">Date</th>
              <th class="py-3 px-4"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="po in displayed" :key="po.id" class="border-b border-slate-50 hover:bg-slate-50">
              <td class="py-3 px-4 font-mono text-xs font-medium text-slate-800">
                {{ po.po_number || '(Draft)' }}
              </td>
              <td class="py-3 px-4 text-slate-700">{{ po.supplier_name }}</td>
              <td class="py-3 px-4 text-slate-500 text-xs">{{ po.pr?.pr_no ?? '—' }}</td>
              <td class="py-3 px-4 text-right font-medium text-slate-800">
                ₱ {{ formatAmount(po.total_amount) }}
              </td>
              <td class="py-3 px-4">
                <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', statusBadge(po.status).cls]">
                  {{ statusBadge(po.status).label }}
                </span>
              </td>
              <td class="py-3 px-4 text-slate-500 text-xs">{{ po.created_at }}</td>
              <td class="py-3 px-4">
                <a :href="route('po.show', po.id)"
                  class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                  <EyeIcon class="h-3.5 w-3.5" /> View
                </a>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex justify-between items-center px-4 py-3 border-t border-slate-100 text-sm text-slate-500">
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <div class="flex gap-2">
            <button @click="currentPage--" :disabled="currentPage === 1"
              class="px-3 py-1 rounded text-sm bg-slate-100 hover:bg-slate-200 disabled:opacity-40">Prev</button>
            <button @click="currentPage++" :disabled="currentPage === totalPages"
              class="px-3 py-1 rounded text-sm bg-slate-100 hover:bg-slate-200 disabled:opacity-40">Next</button>
          </div>
        </div>
      </div>

    </div>

    <!-- Create Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-800">New Purchase Order</h2>
          <button @click="showModal = false"><XMarkIcon class="h-5 w-5 text-slate-400" /></button>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Link to Approved PR (optional)</label>
            <select v-model="form.procurement_id"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500">
              <option :value="null">— No PR (standalone PO) —</option>
              <option v-for="pr in prs" :key="pr.id" :value="pr.id">{{ pr.label }}</option>
            </select>
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Supplier Name *</label>
            <input v-model="form.supplier_name" type="text"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500" />
            <p v-if="form.errors.supplier_name" class="text-xs text-red-500 mt-0.5">{{ form.errors.supplier_name }}</p>
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Supplier Address</label>
            <input v-model="form.supplier_address" type="text"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">TIN</label>
            <input v-model="form.supplier_tin" type="text"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Delivery Place</label>
            <input v-model="form.delivery_place" type="text"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Delivery Date</label>
            <input v-model="form.delivery_date" type="date"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Payment Terms</label>
            <input v-model="form.payment_terms" type="text"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Delivery Terms</label>
            <input v-model="form.delivery_terms" type="text"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
            <textarea v-model="form.remarks" rows="2"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 resize-none" />
          </div>
        </div>

        <p class="text-xs text-slate-500">
          If a PR is selected, its line items will be auto-copied to the PO. You can edit them after creation.
        </p>

        <div class="flex justify-end gap-3 pt-1">
          <button @click="showModal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="submitCreate" :disabled="form.processing"
            class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium disabled:opacity-50">
            Create PO
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>
