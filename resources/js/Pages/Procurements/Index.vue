<script setup>
import { ref, computed, watch } from 'vue'
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
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
import { confirmAction, confirmDelete } from '@/Composables/useConfirm.js'
import {
    PlusIcon, TrashIcon, ListBulletIcon,
    EyeIcon, PaperAirplaneIcon, DocumentArrowUpIcon,
} from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
    procurements: Array,
    pendingAction: Array,
    units: Array,
    divisions: Array,
    availablePpmps: { type: Array, default: () => [] },
    canViewAll: Boolean,
    currentUser: Object,
})

const page = usePage()
const flash = computed(() => page.props.flash)

// ── Tabs ───────────────────────────────────────────────────────────────────
const activeTab = ref('mine')
const tabs = computed(() => {
    const t = [{ key: 'mine', label: 'My PRs' }]
    if ((props.pendingAction || []).length > 0 || props.currentUser?.permissions?.canDcSign
        || props.currentUser?.permissions?.canNumber || props.currentUser?.permissions?.canOcdSign
        || props.currentUser?.permissions?.canBoInitial) {
        t.push({ key: 'pending', label: `Pending My Action (${(props.pendingAction || []).length})` })
    }
    if (props.canViewAll) t.push({ key: 'all', label: 'All PRs' })
    return t
})

const sourceList = computed(() => {
    if (activeTab.value === 'pending') return props.pendingAction || []
    if (activeTab.value === 'all') return props.procurements || []
    return (props.procurements || []).filter(p => p.requested_by == props.currentUser?.id)
})

// ── Search + pagination ────────────────────────────────────────────────────
const searchQuery = ref('')
const currentPage = ref(1)
const PER_PAGE = 15

watch([searchQuery, activeTab], () => { currentPage.value = 1 })

const filtered = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    return sourceList.value.filter(p =>
        (p.pr_no || '').toLowerCase().includes(q)
        || (p.purpose || '').toLowerCase().includes(q)
        || (p.requester?.name || '').toLowerCase().includes(q)
        || (p.status_label || '').toLowerCase().includes(q)
    )
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => {
    const start = (currentPage.value - 1) * PER_PAGE
    return filtered.value.slice(start, start + PER_PAGE)
})

// ── Status badge ───────────────────────────────────────────────────────────
const statusColor = (status) => {
    if (!status || status === 'approved') return 'green'
    if (status === 'rejected') return 'red'
    if (status === 'draft') return 'slate'
    return 'amber'
}

// ── Date format ────────────────────────────────────────────────────────────
const formatDate = (d) => {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

// ── Create / Edit modal ────────────────────────────────────────────────────
const showModal = ref(false)
const editId = ref(null)
const today = new Date().toISOString().slice(0, 10)
const form = useForm({
    pr_date: today,
    purpose: '',
    is_supplemental: false,
    ppmp_checked: true,
    division_id: props.currentUser?.division_id ?? null,
    ppmp_id: null,
    market_study_base64: null,
    market_study_name: null,
})

const marketStudyLabel = ref('')
const onMarketStudyChange = (e) => {
    const file = e.target.files[0]
    if (!file) return
    marketStudyLabel.value = file.name
    const reader = new FileReader()
    reader.onload = (ev) => {
        form.market_study_base64 = ev.target.result
        form.market_study_name = file.name
    }
    reader.readAsDataURL(file)
}

const openCreate = () => {
    editId.value = null
    form.reset()
    form.pr_date = today
    form.ppmp_checked = true
    form.division_id = props.currentUser?.division_id ?? null
    form.ppmp_id = null
    form.market_study_base64 = null
    form.market_study_name = null
    marketStudyLabel.value = ''
    showModal.value = true
}

const openEdit = (p) => {
    editId.value = p.id
    form.pr_date = p.pr_date || today
    form.purpose = p.purpose || ''
    form.is_supplemental = p.is_supplemental ?? false
    form.ppmp_checked = p.ppmp_checked ?? true
    form.division_id = p.division?.id ?? props.currentUser?.division_id ?? null
    form.ppmp_id = p.ppmp_id ?? null
    showModal.value = true
}

const closeModal = () => { showModal.value = false; editId.value = null }

const submitForm = () => {
    if (editId.value) {
        form.put(route('procurements.update', editId.value), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        })
    } else {
        form.post(route('procurements.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        })
    }
}

const destroy = async (p) => {
    if (!(await confirmDelete(`Delete PR ${p.pr_no}? This cannot be undone.`))) return
    router.delete(route('procurements.destroy', p.id), { preserveScroll: true })
}

// ── Submit for approval ────────────────────────────────────────────────────
const submitting = ref(null)
const submitForApproval = async (p) => {
    if (!(await confirmAction({ title: 'Submit for approval?', text: `Submit PR ${p.pr_no} for Division Chief approval?`, confirmText: 'Submit' }))) return
    submitting.value = p.id
    try {
        await axios.post(route('procurements.submit', p.id))
        router.reload({ preserveScroll: true })
    } catch { /* server flash handled */ } finally { submitting.value = null }
}

// ── Items modal ────────────────────────────────────────────────────────────
const showItemsModal = ref(false)
const currentPR = ref(null)
const itemsList = ref([])
const newItem = ref({ ppmp_line_item_no: '', unit: '', description: '', quantity: 1, unit_cost: '' })
const addingItem = ref(false)

const openItemsModal = (p) => {
    currentPR.value = p
    itemsList.value = p.items ? [...p.items] : []
    newItem.value = { ppmp_line_item_no: '', unit: '', description: '', quantity: 1, unit_cost: '' }
    showItemsModal.value = true
}

const closeItemsModal = () => {
    showItemsModal.value = false
    currentPR.value = null
    router.reload({ preserveScroll: true })
}

const addItem = async () => {
    if (!newItem.value.unit || !newItem.value.description || !newItem.value.quantity || !newItem.value.unit_cost) {
        alert('Unit, description, quantity, and unit cost are required.')
        return
    }
    addingItem.value = true
    try {
        const { data } = await axios.post(route('procurements.items.store', currentPR.value.id), newItem.value)
        itemsList.value.push(data.item)
        newItem.value = { ppmp_line_item_no: '', unit: '', description: '', quantity: 1, unit_cost: '' }
    } catch (e) {
        alert('Failed to add item.')
    } finally { addingItem.value = false }
}

const removeItem = async (item, idx) => {
    if (!item.id) { itemsList.value.splice(idx, 1); return }
    if (!(await confirmDelete('Remove this item?'))) return
    try {
        await axios.delete(route('procurements.items.destroy', [currentPR.value.id, item.id]))
        itemsList.value.splice(idx, 1)
    } catch { alert('Failed to remove item.') }
}
</script>

<template>
    <Head title="Purchase Requests" />
    <AdminLayout title="Purchase Requests">
        <div class="space-y-5">

            <AppPageHeader title="Purchase Requests" subtitle="Manage and track all purchase requests">
                <template #actions>
                    <AppButton v-if="currentUser?.permissions?.canCreate" @click="openCreate">
                        <PlusIcon class="w-4 h-4" />
                        New Purchase Request
                    </AppButton>
                </template>
            </AppPageHeader>

            <!-- Flash -->
            <div v-if="flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">
                {{ flash.success }}
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-slate-200 gap-1">
                <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
                    :class="['px-4 py-2.5 text-sm font-medium border-b-2 transition-colors -mb-px',
                        activeTab === tab.key
                            ? 'border-indigo-600 text-indigo-600'
                            : 'border-transparent text-slate-500 hover:text-slate-700']">
                    {{ tab.label }}
                </button>
            </div>

            <!-- Filter -->
            <AppFilterBar>
                <input v-model="searchQuery" type="text" placeholder="Search PR No, purpose, requester…"
                    class="w-full sm:w-72 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </AppFilterBar>

            <!-- Table -->
            <AppTable :is-empty="!displayed.length" :skeleton-cols="7">
                <template #head>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">PR No</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Requested By</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Purpose</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Items</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </template>

                <tr v-for="p in displayed" :key="p.id" class="hover:bg-slate-50/60">
                    <td class="px-4 py-3 text-sm font-mono text-slate-700 whitespace-nowrap">
                        {{ p.assigned_pr_number || p.pr_no }}
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap">{{ formatDate(p.pr_date) }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ p.requester?.name || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700 max-w-xs truncate">{{ p.purpose }}</td>
                    <td class="px-4 py-3">
                        <AppBadge :color="statusColor(p.status)">{{ p.status_label || p.status }}</AppBadge>
                        <AppBadge v-if="p.is_supplemental" color="purple" class="ml-1">SUPP</AppBadge>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ p.items_count ?? 0 }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1">
                            <a :href="route('procurements.show', p.id)"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-colors" title="View" aria-label="View">
                                <EyeIcon class="w-4 h-4" />
                            </a>
                            <AppIconButton v-if="p.status === 'draft' && p.requested_by == currentUser?.id"
                                label="Edit" @click="openEdit(p)">
                                <ListBulletIcon class="w-4 h-4" />
                            </AppIconButton>
                            <AppIconButton v-if="p.status === 'draft' && p.requested_by == currentUser?.id"
                                label="Manage Items" @click="openItemsModal(p)">
                                <PlusIcon class="w-4 h-4" />
                            </AppIconButton>
                            <AppIconButton v-if="p.status === 'draft' && p.requested_by == currentUser?.id"
                                label="Submit for Approval" variant="warning" :disabled="submitting === p.id" @click="submitForApproval(p)">
                                <PaperAirplaneIcon class="w-4 h-4" />
                            </AppIconButton>
                            <AppIconButton v-if="p.status === 'draft' && p.requested_by == currentUser?.id"
                                label="Delete" variant="danger" @click="destroy(p)">
                                <TrashIcon class="w-4 h-4" />
                            </AppIconButton>
                        </div>
                    </td>
                </tr>

                <template #mobileCard>
                    <div v-for="p in displayed" :key="p.id" class="p-4 space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-mono text-sm font-medium text-slate-800">{{ p.assigned_pr_number || p.pr_no }}</p>
                                <p class="text-sm text-slate-700">{{ p.requester?.name || '—' }}</p>
                                <p class="text-xs text-slate-400">{{ formatDate(p.pr_date) }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <AppBadge :color="statusColor(p.status)">{{ p.status_label || p.status }}</AppBadge>
                                <AppBadge v-if="p.is_supplemental" color="purple">SUPP</AppBadge>
                            </div>
                        </div>
                        <p class="text-sm text-slate-700">{{ p.purpose }}</p>
                        <div class="flex items-center justify-between text-xs text-slate-500 pt-1">
                            <span>{{ p.items_count ?? 0 }} item(s)</span>
                            <div class="flex items-center gap-1">
                                <a :href="route('procurements.show', p.id)"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-colors" title="View" aria-label="View">
                                    <EyeIcon class="w-4 h-4" />
                                </a>
                                <AppIconButton v-if="p.status === 'draft' && p.requested_by == currentUser?.id"
                                    label="Edit" @click="openEdit(p)">
                                    <ListBulletIcon class="w-4 h-4" />
                                </AppIconButton>
                                <AppIconButton v-if="p.status === 'draft' && p.requested_by == currentUser?.id"
                                    label="Manage Items" @click="openItemsModal(p)">
                                    <PlusIcon class="w-4 h-4" />
                                </AppIconButton>
                                <AppIconButton v-if="p.status === 'draft' && p.requested_by == currentUser?.id"
                                    label="Submit for Approval" variant="warning" :disabled="submitting === p.id" @click="submitForApproval(p)">
                                    <PaperAirplaneIcon class="w-4 h-4" />
                                </AppIconButton>
                                <AppIconButton v-if="p.status === 'draft' && p.requested_by == currentUser?.id"
                                    label="Delete" variant="danger" @click="destroy(p)">
                                    <TrashIcon class="w-4 h-4" />
                                </AppIconButton>
                            </div>
                        </div>
                    </div>
                </template>

                <template #empty>
                    <EmptyState title="No purchase requests found" />
                </template>

                <template #footer>
                    <PaginationControl
                        :current-page="currentPage"
                        :total-pages="totalPages"
                        @prev="currentPage = Math.max(1, currentPage - 1)"
                        @next="currentPage = Math.min(totalPages, currentPage + 1)"
                        @page="currentPage = $event"
                    />
                </template>
            </AppTable>
        </div>

        <!-- Create/Edit Modal -->
        <AppModal :show="showModal" :title="editId ? 'Edit Purchase Request' : 'New Purchase Request'" size="lg" @close="closeModal">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">PR Date</label>
                    <input type="date" v-model="form.pr_date"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p v-if="form.errors.pr_date" class="text-danger-600 text-xs mt-1">{{ form.errors.pr_date }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Division</label>
                    <select v-model="form.division_id"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option :value="null">— Select division —</option>
                        <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.division_name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Purpose</label>
                    <textarea v-model="form.purpose" rows="3"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 placeholder-slate-400"
                        placeholder="Brief description of what is being purchased…"></textarea>
                    <p v-if="form.errors.purpose" class="text-danger-600 text-xs mt-1">{{ form.errors.purpose }}</p>
                </div>
                <!-- Link to PPMP -->
                <div v-if="availablePpmps.length">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Link to PPMP (optional)</label>
                    <select v-model="form.ppmp_id"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option :value="null">— No PPMP link —</option>
                        <option v-for="p in availablePpmps" :key="p.id" :value="p.id">{{ p.label }}</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Linking to a PPMP tracks budget utilization.</p>
                </div>
                <!-- Market Study / Pre-canvass -->
                <div v-if="!editId">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Market Study / Pre-canvass (optional)</label>
                    <label class="flex items-center gap-2 cursor-pointer rounded-lg border border-dashed border-slate-300 px-3 py-2.5 hover:bg-slate-50 transition-colors">
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" class="sr-only" @change="onMarketStudyChange" />
                        <DocumentArrowUpIcon class="w-4 h-4 text-slate-400 shrink-0" />
                        <span class="text-sm text-slate-500 truncate">
                            {{ marketStudyLabel || 'Click to attach file (PDF/image, max 15 MB)' }}
                        </span>
                    </label>
                </div>
                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                        <input type="checkbox" v-model="form.is_supplemental" class="rounded text-indigo-600 focus:ring-indigo-500" />
                        Supplemental PR
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                        <input type="checkbox" v-model="form.ppmp_checked" class="rounded text-indigo-600 focus:ring-indigo-500" />
                        PPMP verified
                    </label>
                </div>
            </div>
            <template #footer>
                <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
                <AppButton :loading="form.processing" @click="submitForm">Save</AppButton>
            </template>
        </AppModal>

        <!-- Items Modal -->
        <AppModal :show="showItemsModal" :title="`Items — ${currentPR?.pr_no ?? ''}`" size="4xl" @close="closeItemsModal">
            <div class="space-y-4">
                <!-- Add row -->
                <div class="bg-slate-50 rounded-xl p-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Add Item</p>
                    <div class="grid grid-cols-12 gap-2 items-end">
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-slate-600 mb-1">PPMP No</label>
                            <input v-model="newItem.ppmp_line_item_no"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Unit</label>
                            <select v-model="newItem.unit"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">Select</option>
                                <option v-for="u in units" :key="u.id" :value="u.name">{{ u.name }}</option>
                            </select>
                        </div>
                        <div class="col-span-4">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
                            <input v-model="newItem.description"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div class="col-span-1">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Qty</label>
                            <input type="number" v-model.number="newItem.quantity" min="1"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Unit Cost</label>
                            <input type="number" step="0.01" v-model="newItem.unit_cost"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div class="col-span-1">
                            <AppButton block :loading="addingItem" @click="addItem">Add</AppButton>
                        </div>
                    </div>
                </div>
                <!-- Items table -->
                <AppTable :is-empty="!itemsList.length" :skeleton-cols="6" :card="false">
                    <template #head>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">PPMP No</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Description</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Qty</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit Cost</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </template>

                    <tr v-for="(it, idx) in itemsList" :key="idx" class="hover:bg-slate-50/60">
                        <td class="px-4 py-3 text-sm text-slate-700">{{ it.ppmp_line_item_no || '—' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ it.unit }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ it.description }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ it.quantity }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ Number(it.unit_cost).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td>
                        <td class="px-4 py-3">
                            <AppIconButton label="Remove" variant="danger" @click="removeItem(it, idx)">
                                <TrashIcon class="w-4 h-4" />
                            </AppIconButton>
                        </td>
                    </tr>

                    <template #empty>
                        <EmptyState title="No items yet" />
                    </template>
                </AppTable>
            </div>
            <template #footer>
                <AppButton variant="secondary" @click="closeItemsModal">Close</AppButton>
            </template>
        </AppModal>
    </AdminLayout>
</template>
