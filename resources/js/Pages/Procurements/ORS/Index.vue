<template>
    <Head title="Obligation Request Status" />
    <AdminLayout title="Obligation Request Status">
        <div class="space-y-5">

            <AppPageHeader title="Obligation Request Status (ORS)" subtitle="Track obligation of funds for approved purchase requests">
                <template #actions>
                    <AppButton v-if="perms.canCreate" @click="openCreate">
                        <PlusIcon class="w-4 h-4" />
                        New ORS
                    </AppButton>
                </template>
            </AppPageHeader>

            <!-- Flash -->
            <div v-if="flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
                <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ flash.success }}
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

            <!-- Search -->
            <AppFilterBar>
                <div class="relative w-full sm:w-72">
                    <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                    <input v-model="searchQuery" type="text" placeholder="Search ORS No, activity, supplier…"
                        class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
            </AppFilterBar>

            <!-- Table -->
            <AppTable :is-empty="!displayed.length" :skeleton-cols="8">
                <template #head>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">ORS No</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Activity</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Supplier</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Amount</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Created By</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </template>

                <tr v-for="o in displayed" :key="o.id" class="hover:bg-indigo-50/40">
                    <td class="px-4 py-3 text-sm font-mono text-slate-700 whitespace-nowrap">{{ o.ors_number || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700 max-w-xs truncate">{{ o.activity_title || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ o.supplier_name || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-800 font-medium text-right whitespace-nowrap">₱{{ formatPeso(o.amount) }}</td>
                    <td class="px-4 py-3">
                        <AppBadge :color="statusColor(o.status)">{{ o.status_label || o.status }}</AppBadge>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">{{ o.creator?.name || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-500 whitespace-nowrap">{{ formatDate(o.created_at) }}</td>
                    <td class="px-4 py-3">
                        <Link :href="route('ors.show', o.id)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View</Link>
                    </td>
                </tr>

                <template #mobileCard>
                    <div v-for="o in displayed" :key="o.id" class="p-4 space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-mono text-xs text-slate-500">{{ o.ors_number || '—' }}</p>
                                <p class="font-medium text-slate-800">{{ o.activity_title || '—' }}</p>
                                <p class="text-xs text-slate-500">{{ o.supplier_name || '—' }}</p>
                            </div>
                            <AppBadge :color="statusColor(o.status)">{{ o.status_label || o.status }}</AppBadge>
                        </div>
                        <div class="flex justify-between text-xs text-slate-500">
                            <span>{{ o.creator?.name || '—' }} &middot; {{ formatDate(o.created_at) }}</span>
                            <span class="font-semibold text-slate-800">₱{{ formatPeso(o.amount) }}</span>
                        </div>
                        <div class="pt-1">
                            <Link :href="route('ors.show', o.id)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View</Link>
                        </div>
                    </div>
                </template>

                <template #empty>
                    <EmptyState title="No ORS records found" />
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

        <!-- Create Modal -->
        <AppModal :show="showModal" title="New ORS" size="lg" @close="showModal = false">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Linked PR (optional)</label>
                    <select v-model="form.procurement_id"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option :value="null">— None —</option>
                        <option v-for="pr in prs" :key="pr.id" :value="pr.id">{{ pr.label }}</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">PO Number</label>
                        <input v-model="form.po_number"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Supplier Name</label>
                        <input v-model="form.supplier_name"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Account Title</label>
                        <input v-model="form.account_title"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Object Code</label>
                        <input v-model="form.object_code"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Activity Title</label>
                    <input v-model="form.activity_title"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Activity Date</label>
                        <input type="date" v-model="form.activity_date"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Amount <span class="text-danger-500">*</span></label>
                        <input type="number" step="0.01" v-model="form.amount"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        <p v-if="form.errors.amount" class="text-danger-600 text-xs mt-1">{{ form.errors.amount }}</p>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Division</label>
                    <select v-model="form.division_id"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option :value="null">— Select —</option>
                        <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.division_name }}</option>
                    </select>
                </div>
            </div>

            <template #footer>
                <AppButton variant="secondary" @click="showModal = false">Cancel</AppButton>
                <AppButton :loading="form.processing" @click="submitForm">{{ form.processing ? 'Saving…' : 'Create ORS' }}</AppButton>
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
    orsRecords: Array,
    pendingAction: Array,
    prs: Array,
    divisions: Array,
    currentUser: Object,
})

const page = usePage()
const flash = computed(() => page.props.flash)
const perms = computed(() => props.currentUser?.permissions ?? {})

// ── Tabs ───────────────────────────────────────────────────────────────────
const activeTab = ref('mine')
const tabs = computed(() => {
    const t = [{ key: 'mine', label: 'My ORS' }]
    if ((props.pendingAction || []).length > 0 || perms.value.canDcSign || perms.value.canBudgetSign
        || perms.value.canBookkeep || perms.value.canAccount || perms.value.canOcdSign) {
        t.push({ key: 'pending', label: `Pending My Action (${(props.pendingAction || []).length})` })
    }
    if (perms.value.canDcSign || perms.value.canBudgetSign || perms.value.canBookkeep
        || perms.value.canAccount || perms.value.canOcdSign) {
        t.push({ key: 'all', label: 'All ORS' })
    }
    return t
})

const sourceList = computed(() => {
    if (activeTab.value === 'pending') return props.pendingAction || []
    if (activeTab.value === 'all') return props.orsRecords || []
    return (props.orsRecords || []).filter(o => o.creator?.id == props.currentUser?.id)
})

// ── Search + pagination ────────────────────────────────────────────────────
const searchQuery = ref('')
const currentPage = ref(1)
const PER_PAGE = 15

watch([searchQuery, activeTab], () => { currentPage.value = 1 })

const filtered = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    return sourceList.value.filter(o =>
        (o.ors_number || '').toLowerCase().includes(q)
        || (o.activity_title || '').toLowerCase().includes(q)
        || (o.supplier_name || '').toLowerCase().includes(q)
        || (o.status_label || '').toLowerCase().includes(q)
        || (o.creator?.name || '').toLowerCase().includes(q)
    )
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => {
    const start = (currentPage.value - 1) * PER_PAGE
    return filtered.value.slice(start, start + PER_PAGE)
})

const statusColor = (status) => {
    if (status === 'completed') return 'green'
    if (status === 'draft') return 'slate'
    if (['returned_bo', 'returned_bookkeeper'].includes(status)) return 'red'
    return 'amber'
}

const formatDate = (d) => {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

const formatPeso = (v) => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

// ── Create modal ───────────────────────────────────────────────────────────
const showModal = ref(false)
const form = useForm({
    procurement_id: null,
    po_number: '',
    supplier_name: '',
    account_title: '',
    object_code: '',
    activity_title: '',
    activity_date: '',
    amount: '',
    division_id: props.currentUser?.division_id ?? null,
})

const openCreate = () => {
    form.reset()
    form.division_id = props.currentUser?.division_id ?? null
    showModal.value = true
}

const submitForm = () => {
    form.post(route('ors.store'), {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false },
    })
}
</script>
