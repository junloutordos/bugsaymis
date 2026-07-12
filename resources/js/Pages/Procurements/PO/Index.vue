<template>
  <Head title="Purchase Orders" />
  <AdminLayout title="Purchase Orders">
    <div class="space-y-5">

      <AppPageHeader title="Purchase Orders" subtitle="Manage POs issued to suppliers">
        <template #actions>
          <AppButton v-if="perms.canCreate" @click="openCreate">
            <PlusIcon class="h-4 w-4" />
            New PO
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ flash.success }}
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
      <AppFilterBar>
        <div class="relative w-full sm:w-72">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
          <input v-model="searchQuery" type="search" placeholder="Search PO number, supplier, PR…"
            class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
      </AppFilterBar>

      <!-- Table -->
      <AppTable :is-empty="!displayed.length" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">PO Number</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Supplier</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">PR Ref</th>
            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Amount</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Date</th>
            <th class="px-4 py-3"></th>
          </tr>
        </template>

        <tr v-for="po in displayed" :key="po.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 font-mono text-xs font-medium text-slate-800">{{ po.po_number || '(Draft)' }}</td>
          <td class="px-4 py-3 text-slate-700">{{ po.supplier_name }}</td>
          <td class="px-4 py-3 text-slate-500 text-xs">{{ po.pr?.pr_no ?? '—' }}</td>
          <td class="px-4 py-3 text-right font-medium text-slate-800">₱ {{ formatAmount(po.total_amount) }}</td>
          <td class="px-4 py-3">
            <AppBadge :color="statusBadge(po.status).color">{{ statusBadge(po.status).label }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-slate-500 text-xs">{{ po.created_at }}</td>
          <td class="px-4 py-3">
            <Link :href="route('po.show', po.id)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View</Link>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="po in displayed" :key="po.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-mono text-xs font-medium text-slate-800">{{ po.po_number || '(Draft)' }}</p>
                <p class="text-sm text-slate-700">{{ po.supplier_name }}</p>
                <p class="text-xs text-slate-400">PR: {{ po.pr?.pr_no ?? '—' }}</p>
              </div>
              <AppBadge :color="statusBadge(po.status).color">{{ statusBadge(po.status).label }}</AppBadge>
            </div>
            <div class="flex justify-between text-xs text-slate-500">
              <span>{{ po.created_at }}</span>
              <span class="font-semibold text-slate-800">₱ {{ formatAmount(po.total_amount) }}</span>
            </div>
            <div class="pt-1">
              <Link :href="route('po.show', po.id)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View</Link>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No purchase orders found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            @prev="currentPage--"
            @next="currentPage++"
            @page="currentPage = $event"
          />
        </template>
      </AppTable>

    </div>

    <!-- Create Modal -->
    <AppModal :show="showModal" title="New Purchase Order" size="xl" @close="showModal = false">
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
          <p v-if="form.errors.supplier_name" class="text-xs text-danger-600 mt-0.5">{{ form.errors.supplier_name }}</p>
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

      <p class="text-xs text-slate-500 mt-4">
        If a PR is selected, its line items will be auto-copied to the PO. You can edit them after creation.
      </p>

      <template #footer>
        <AppButton variant="secondary" @click="showModal = false">Cancel</AppButton>
        <AppButton :loading="form.processing" @click="submitCreate">Create PO</AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { PlusIcon, CheckCircleIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

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
    draft:                { label: 'Draft',                     color: 'slate' },
    pending_procurement:  { label: 'Pending Procurement',       color: 'amber' },
    pending_ocd:          { label: 'Pending Campus Director',   color: 'blue'  },
    issued:               { label: 'Issued',                    color: 'green' },
    cancelled:            { label: 'Cancelled',                 color: 'red'   },
}
function statusBadge(s) { return STATUS_MAP[s] ?? { label: s, color: 'slate' } }

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
