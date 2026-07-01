<script setup>
import { ref, computed, watch } from 'vue'
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { PlusIcon, EyeIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    dvRecords: Array,
    pendingAction: Array,
    orsRecords: Array,
    currentUser: Object,
})

const page = usePage()
const flash = computed(() => page.props.flash)
const perms = computed(() => props.currentUser?.permissions ?? {})

// ── Tabs ───────────────────────────────────────────────────────────────────
const activeTab = ref('mine')
const tabs = computed(() => {
    const t = [{ key: 'mine', label: 'My DVs' }]
    if ((props.pendingAction || []).length > 0 || perms.value.canDelivery || perms.value.canBookkeep
        || perms.value.canAccount || perms.value.canOcdSign || perms.value.canCashier) {
        t.push({ key: 'pending', label: `Pending My Action (${(props.pendingAction || []).length})` })
    }
    if (perms.value.canDelivery || perms.value.canBookkeep || perms.value.canAccount
        || perms.value.canOcdSign || perms.value.canCashier) {
        t.push({ key: 'all', label: 'All DVs' })
    }
    return t
})

const sourceList = computed(() => {
    if (activeTab.value === 'pending') return props.pendingAction || []
    if (activeTab.value === 'all') return props.dvRecords || []
    return (props.dvRecords || []).filter(d => d.creator?.id == props.currentUser?.id)
})

const searchQuery = ref('')
const currentPage = ref(1)
const PER_PAGE = 15

watch([searchQuery, activeTab], () => { currentPage.value = 1 })

const filtered = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    return sourceList.value.filter(d =>
        (d.dv_number || '').toLowerCase().includes(q)
        || (d.activity_title || '').toLowerCase().includes(q)
        || (d.po_number || '').toLowerCase().includes(q)
        || (d.status_label || '').toLowerCase().includes(q)
        || (d.creator?.name || '').toLowerCase().includes(q)
    )
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => {
    const start = (currentPage.value - 1) * PER_PAGE
    return filtered.value.slice(start, start + PER_PAGE)
})

const statusClass = (status) => {
    if (status === 'released') return 'bg-emerald-100 text-emerald-700'
    if (status === 'draft') return 'bg-slate-100 text-slate-600'
    if (['returned_bookkeeper', 'returned_accountant'].includes(status)) return 'bg-red-100 text-red-700'
    return 'bg-amber-100 text-amber-700'
}

const formatDate = (d) => {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}
const formatPeso = (v) => '₱' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

// ── Create modal ───────────────────────────────────────────────────────────
const showModal = ref(false)
const form = useForm({
    ors_id: null,
    po_number: '',
    activity_title: '',
    activity_date: '',
    gross_amount: '',
    tax_amount: '',
})

const openCreate = () => {
    form.reset()
    showModal.value = true
}

const submitForm = () => {
    form.post(route('dv.store'), {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false },
    })
}
</script>

<template>
    <Head title="Disbursement Vouchers" />
    <AdminLayout title="Disbursement Vouchers">

        <div v-if="flash?.success" class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ flash.success }}
        </div>

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Disbursement Vouchers</h1>
                <p class="text-sm text-slate-500 mt-0.5">Track payment processing for obligation requests</p>
            </div>
            <button v-if="perms.canCreate" @click="openCreate"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <PlusIcon class="w-4 h-4" />
                New DV
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
                <input v-model="searchQuery" type="text" placeholder="Search DV No, activity, PO number…"
                    class="w-full sm:w-72 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">DV No</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Activity</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">ORS No</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Gross</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Net</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Payment</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Created</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="d in displayed" :key="d.id" class="hover:bg-slate-50/60">
                            <td class="px-4 py-3 text-sm font-mono text-slate-700 whitespace-nowrap">{{ d.dv_number || '—' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700 max-w-xs truncate">{{ d.activity_title || '—' }}</td>
                            <td class="px-4 py-3 text-sm font-mono text-slate-600">{{ d.ors?.ors_number || '—' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-800 font-medium text-right whitespace-nowrap">{{ formatPeso(d.gross_amount) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700 text-right whitespace-nowrap">{{ formatPeso(d.net_amount) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                <span v-if="d.payment_method" class="uppercase font-medium">{{ d.payment_method }}</span>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium', statusClass(d.status)]">
                                    {{ d.status_label || d.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-500 whitespace-nowrap">{{ formatDate(d.created_at) }}</td>
                            <td class="px-4 py-3">
                                <a :href="route('dv.show', d.id)"
                                    class="p-1.5 rounded-lg hover:bg-indigo-50 text-slate-500 hover:text-indigo-600 transition-colors inline-flex" title="View">
                                    <EyeIcon class="w-4 h-4" />
                                </a>
                            </td>
                        </tr>
                        <tr v-if="!displayed.length">
                            <td colspan="9" class="px-4 py-12 text-center text-sm text-slate-400">No disbursement vouchers found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <PaginationControl
                :current-page="currentPage"
                :total-pages="totalPages"
                @prev="currentPage = Math.max(1, currentPage - 1)"
                @next="currentPage = Math.min(totalPages, currentPage + 1)"
                @page="currentPage = $event"
            />
        </div>

        <!-- Create Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white w-full max-w-lg rounded-2xl shadow-xl flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-800">New Disbursement Voucher</h3>
                    <button @click="showModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Linked ORS (optional)</label>
                        <select v-model="form.ors_id"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option :value="null">— None —</option>
                            <option v-for="o in orsRecords" :key="o.id" :value="o.id">{{ o.label }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">PO Number</label>
                            <input v-model="form.po_number"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Activity Date</label>
                            <input type="date" v-model="form.activity_date"
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
                            <label class="block text-xs font-medium text-slate-600 mb-1">Gross Amount <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" v-model="form.gross_amount"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            <p v-if="form.errors.gross_amount" class="text-red-600 text-xs mt-1">{{ form.errors.gross_amount }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Tax Amount</label>
                            <input type="number" step="0.01" v-model="form.tax_amount"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
                    <button @click="showModal = false" class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button @click="submitForm" :disabled="form.processing"
                        class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
                        {{ form.processing ? 'Saving…' : 'Create DV' }}
                    </button>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>
