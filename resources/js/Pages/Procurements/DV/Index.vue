<script setup>
import { ref, computed, watch } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { PlusIcon, EyeIcon } from '@heroicons/vue/24/outline'

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

const statusColor = (status) => {
    if (status === 'released') return 'green'
    if (status === 'draft') return 'slate'
    if (['returned_bookkeeper', 'returned_accountant'].includes(status)) return 'red'
    return 'amber'
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
        <div class="space-y-5">

            <AppPageHeader title="Disbursement Vouchers" subtitle="Track payment processing for obligation requests.">
                <template #actions>
                    <AppButton v-if="perms.canCreate" @click="openCreate">
                        <PlusIcon class="w-4 h-4" />
                        New DV
                    </AppButton>
                </template>
            </AppPageHeader>

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

            <AppFilterBar>
                <input v-model="searchQuery" type="text" placeholder="Search DV No, activity, PO number…"
                    class="w-full sm:w-72 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </AppFilterBar>

            <AppTable :is-empty="!displayed.length" :skeleton-cols="9">
                <template #head>
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
                </template>

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
                        <AppBadge :color="statusColor(d.status)">{{ d.status_label || d.status }}</AppBadge>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-500 whitespace-nowrap">{{ formatDate(d.created_at) }}</td>
                    <td class="px-4 py-3">
                        <a :href="route('dv.show', d.id)" title="View"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-colors">
                            <EyeIcon class="w-4 h-4" />
                        </a>
                    </td>
                </tr>

                <template #mobileCard>
                    <div v-for="d in displayed" :key="d.id" class="p-4 space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-mono font-medium text-slate-800">{{ d.dv_number || '—' }}</p>
                                <p class="text-sm text-slate-700 truncate">{{ d.activity_title || '—' }}</p>
                            </div>
                            <AppBadge :color="statusColor(d.status)">{{ d.status_label || d.status }}</AppBadge>
                        </div>
                        <div class="flex justify-between text-xs text-slate-500">
                            <span>ORS {{ d.ors?.ors_number || '—' }}</span>
                            <span class="font-semibold text-slate-800">Net {{ formatPeso(d.net_amount) }}</span>
                        </div>
                        <div class="flex items-center justify-between pt-1">
                            <span class="text-xs text-slate-400">{{ formatDate(d.created_at) }}</span>
                            <a :href="route('dv.show', d.id)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View</a>
                        </div>
                    </div>
                </template>

                <template #empty>
                    <EmptyState title="No disbursement vouchers found" />
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
        <AppModal :show="showModal" title="New Disbursement Voucher" size="lg" @close="showModal = false">
            <div class="space-y-4">
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
                        <label class="block text-xs font-medium text-slate-600 mb-1">Gross Amount <span class="text-danger-500">*</span></label>
                        <input type="number" step="0.01" v-model="form.gross_amount"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        <p v-if="form.errors.gross_amount" class="text-danger-600 text-xs mt-1">{{ form.errors.gross_amount }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Tax Amount</label>
                        <input type="number" step="0.01" v-model="form.tax_amount"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                </div>
            </div>

            <template #footer>
                <AppButton variant="secondary" @click="showModal = false">Cancel</AppButton>
                <AppButton :loading="form.processing" @click="submitForm">
                    {{ form.processing ? 'Saving…' : 'Create DV' }}
                </AppButton>
            </template>
        </AppModal>

    </AdminLayout>
</template>
