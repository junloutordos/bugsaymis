<script setup>
import { ref, computed, watch } from 'vue'
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
    PlusIcon, TrashIcon, ListBulletIcon, XMarkIcon,
    EyeIcon, PaperAirplaneIcon, CheckIcon, DocumentArrowUpIcon,
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
const statusClass = (status) => {
    if (!status || status === 'approved') return 'bg-emerald-100 text-emerald-700'
    if (status === 'rejected') return 'bg-red-100 text-red-700'
    if (status === 'draft') return 'bg-slate-100 text-slate-600'
    return 'bg-amber-100 text-amber-700'
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

const destroy = (p) => {
    if (!confirm(`Delete PR ${p.pr_no}? This cannot be undone.`)) return
    router.delete(route('procurements.destroy', p.id), { preserveScroll: true })
}

// ── Submit for approval ────────────────────────────────────────────────────
const submitting = ref(null)
const submitForApproval = async (p) => {
    if (!confirm(`Submit PR ${p.pr_no} for Division Chief approval?`)) return
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
    if (!confirm('Remove this item?')) return
    try {
        await axios.delete(route('procurements.items.destroy', [currentPR.value.id, item.id]))
        itemsList.value.splice(idx, 1)
    } catch { alert('Failed to remove item.') }
}
</script>

<template>
    <Head title="Purchase Requests" />
    <AdminLayout title="Purchase Requests">

        <!-- Flash -->
        <div v-if="flash?.success" class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ flash.success }}
        </div>

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Purchase Requests</h1>
                <p class="text-sm text-slate-500 mt-0.5">Manage and track all purchase requests</p>
            </div>
            <button v-if="currentUser?.permissions?.canCreate" @click="openCreate"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <PlusIcon class="w-4 h-4" />
                New Purchase Request
            </button>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-slate-200 mb-4 gap-1">
            <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
                :class="['px-4 py-2.5 text-sm font-medium border-b-2 transition-colors -mb-px',
                    activeTab === tab.key
                        ? 'border-indigo-600 text-indigo-600'
                        : 'border-transparent text-slate-500 hover:text-slate-700']">
                {{ tab.label }}
            </button>
        </div>

        <!-- Table card -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100">
                <input v-model="searchQuery" type="text" placeholder="Search PR No, purpose, requester…"
                    class="w-full sm:w-72 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">PR No</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Requested By</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Purpose</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Items</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="p in displayed" :key="p.id" class="hover:bg-slate-50/60">
                            <td class="px-4 py-3 text-sm font-mono text-slate-700 whitespace-nowrap">
                                {{ p.assigned_pr_number || p.pr_no }}
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap">{{ formatDate(p.pr_date) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ p.requester?.name || '—' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700 max-w-xs truncate">{{ p.purpose }}</td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium', statusClass(p.status)]">
                                    {{ p.status_label || p.status }}
                                </span>
                                <span v-if="p.is_supplemental" class="ml-1 inline-flex px-1.5 py-0.5 rounded text-[10px] font-medium bg-purple-100 text-purple-700">SUPP</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ p.items_count ?? 0 }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <a :href="route('procurements.show', p.id)"
                                        class="p-1.5 rounded-lg hover:bg-indigo-50 text-slate-500 hover:text-indigo-600 transition-colors" title="View">
                                        <EyeIcon class="w-4 h-4" />
                                    </a>
                                    <button v-if="p.status === 'draft' && p.requested_by == currentUser?.id"
                                        @click="openEdit(p)"
                                        class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit">
                                        <ListBulletIcon class="w-4 h-4" />
                                    </button>
                                    <button v-if="p.status === 'draft' && p.requested_by == currentUser?.id"
                                        @click="openItemsModal(p)"
                                        class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Manage Items">
                                        <PlusIcon class="w-4 h-4" />
                                    </button>
                                    <button v-if="p.status === 'draft' && p.requested_by == currentUser?.id"
                                        @click="submitForApproval(p)" :disabled="submitting === p.id"
                                        class="p-1.5 rounded-lg hover:bg-amber-50 text-slate-500 hover:text-amber-600 transition-colors disabled:opacity-40" title="Submit for Approval">
                                        <PaperAirplaneIcon class="w-4 h-4" />
                                    </button>
                                    <button v-if="p.status === 'draft' && p.requested_by == currentUser?.id"
                                        @click="destroy(p)"
                                        class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors" title="Delete">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!displayed.length">
                            <td colspan="7" class="px-4 py-12 text-center text-sm text-slate-400">No purchase requests found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
                <span>Page {{ currentPage }} of {{ totalPages }}</span>
                <div class="flex gap-2">
                    <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1"
                        class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Prev</button>
                    <button @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages"
                        class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white w-full max-w-lg rounded-2xl shadow-xl flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-800">{{ editId ? 'Edit Purchase Request' : 'New Purchase Request' }}</h3>
                    <button @click="closeModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">PR Date</label>
                        <input type="date" v-model="form.pr_date"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        <p v-if="form.errors.pr_date" class="text-red-600 text-xs mt-1">{{ form.errors.pr_date }}</p>
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
                        <p v-if="form.errors.purpose" class="text-red-600 text-xs mt-1">{{ form.errors.purpose }}</p>
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
                <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
                    <button @click="closeModal" class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button @click="submitForm" :disabled="form.processing"
                        class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
                        {{ form.processing ? 'Saving…' : 'Save' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Items Modal -->
        <div v-if="showItemsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white w-full max-w-4xl rounded-2xl shadow-xl flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-800">
                        Items — <span class="text-indigo-600 font-mono">{{ currentPR?.pr_no }}</span>
                    </h3>
                    <button @click="closeItemsModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>
                <div class="overflow-y-auto px-6 py-5 space-y-4">
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
                                <button @click="addItem" :disabled="addingItem"
                                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
                                    Add
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- Items table -->
                    <table class="min-w-full">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">PPMP No</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Description</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Qty</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit Cost</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(it, idx) in itemsList" :key="idx" class="hover:bg-slate-50/60">
                                <td class="px-4 py-3 text-sm text-slate-700">{{ it.ppmp_line_item_no || '—' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ it.unit }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ it.description }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ it.quantity }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ Number(it.unit_cost).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td>
                                <td class="px-4 py-3">
                                    <button @click="removeItem(it, idx)"
                                        class="p-1.5 rounded-lg hover:bg-red-50 text-slate-400 hover:text-red-600">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!itemsList.length">
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">No items yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4 border-t border-slate-100 flex justify-end">
                    <button @click="closeItemsModal"
                        class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50">Close</button>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>
