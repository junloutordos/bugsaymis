<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import { useConfirm } from '@/Composables/useConfirm'
import { ArrowPathIcon, DocumentArrowDownIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    items:         Array,
    consolidated:  Array,
    totals:        Object,
    fiscalYear:    Number,
    approvedCount: Number,
    categories:    Object,
    methods:       Object,
})

const page  = usePage()
const flash = computed(() => page.props.flash)

const months      = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec']
const monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']

const formatPeso = (v) => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

// ── View toggle: "by-unit" or "consolidated" ──────────────────────────────
const viewMode = ref('consolidated')

// ── By-unit search ────────────────────────────────────────────────────────
const search = ref('')
const filteredItems = computed(() => {
    if (!search.value) return props.items
    const q = search.value.toLowerCase()
    return props.items.filter(i =>
        (i.description || '').toLowerCase().includes(q) ||
        (i.division_name || '').toLowerCase().includes(q)
    )
})

// ── Consolidated search ───────────────────────────────────────────────────
const search2 = ref('')
const filteredConsolidated = computed(() => {
    const list = props.consolidated.filter(c => c.total_quantity > 0) // only items with any quantities
    if (!search2.value) return list
    const q = search2.value.toLowerCase()
    return list.filter(c =>
        (c.description || '').toLowerCase().includes(q) ||
        (c.stock_number || '').toLowerCase().includes(q) ||
        (c.category_group || '').toLowerCase().includes(q)
    )
})

// Part totals from consolidated
const part1Total = computed(() => props.consolidated.filter(c => c.part === 1).reduce((s, c) => s + Number(c.total_quantity) * Number(c.unit_price), 0))
const part2Total = computed(() => props.consolidated.filter(c => c.part === 2).reduce((s, c) => s + Number(c.total_quantity) * Number(c.unit_price), 0))

const { confirmAction } = useConfirm()

const consolidate = async () => {
    const ok = await confirmAction({
        title: 'Consolidate APP',
        text: `Consolidate all approved PPMPs for FY ${props.fiscalYear} into the APP?`,
        confirmText: 'Consolidate',
    })
    if (!ok) return
    router.post(route('ppmp.app.consolidate'), { fiscal_year: props.fiscalYear }, { preserveScroll: true })
}
</script>

<template>
    <Head :title="`APP — FY ${fiscalYear}`" />
    <AdminLayout :title="`Annual Procurement Plan — FY ${fiscalYear}`">
      <div class="space-y-5">

        <AppPageHeader :title="`Annual Procurement Plan — FY ${fiscalYear}`"
                        subtitle="Consolidated APP-CSE view of all approved PPMPs.">
            <template #actions>
                <AppButton as="a" variant="secondary" :href="route('ppmp.app.export.excel') + '?fiscal_year=' + fiscalYear">
                    <DocumentArrowDownIcon class="w-4 h-4" /> Export APP-CSE Excel
                </AppButton>
                <AppButton @click="consolidate">
                    <ArrowPathIcon class="w-4 h-4" /> Consolidate
                </AppButton>
            </template>
        </AppPageHeader>

        <!-- Flash -->
        <div v-if="flash.success" class="rounded-lg bg-success-50 border border-success-100 px-4 py-3 text-sm text-success-700">{{ flash.success }}</div>
        <div v-if="flash.error"   class="rounded-lg bg-danger-50  border border-danger-100  px-4 py-3 text-sm text-danger-600">{{ flash.error }}</div>

        <!-- Summary bar -->
        <div class="flex flex-wrap items-center gap-3">
            <div class="bg-white rounded-lg border border-slate-200 px-4 py-2">
                <span class="text-xs text-slate-500">Approved PPMPs</span>
                <span class="ml-2 text-lg font-bold text-slate-800">{{ approvedCount }}</span>
            </div>
            <div class="bg-indigo-50 rounded-lg border border-indigo-200 px-4 py-2">
                <span class="text-xs text-indigo-500">Grand Total</span>
                <span class="ml-2 text-lg font-bold text-indigo-800">₱{{ formatPeso(totals.grand_total) }}</span>
            </div>
            <div class="bg-blue-50 rounded-lg border border-blue-200 px-3 py-2">
                <span class="text-xs text-blue-600 font-semibold">Part I:</span>
                <span class="ml-1 text-sm font-bold text-blue-800">₱{{ formatPeso(part1Total) }}</span>
            </div>
            <div class="bg-success-50 rounded-lg border border-success-100 px-3 py-2">
                <span class="text-xs text-success-600 font-semibold">Part II:</span>
                <span class="ml-1 text-sm font-bold text-success-700">₱{{ formatPeso(part2Total) }}</span>
            </div>
        </div>

        <!-- Category totals -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <AppCard v-for="cat in (totals.categories || [])" :key="cat.category">
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">{{ cat.label }}</p>
                <p class="text-lg font-bold text-slate-800">₱{{ formatPeso(cat.subtotal) }}</p>
                <p class="text-xs text-slate-500">{{ cat.item_count }} item(s)</p>
            </AppCard>
        </div>

        <!-- View mode tabs -->
        <div class="flex gap-1">
            <button @click="viewMode = 'consolidated'"
                    :class="viewMode === 'consolidated' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                    class="px-4 py-2 rounded-lg text-sm font-medium">
                Consolidated (APP-CSE View)
            </button>
            <button @click="viewMode = 'by-unit'"
                    :class="viewMode === 'by-unit' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                    class="px-4 py-2 rounded-lg text-sm font-medium">
                By Division/Unit
            </button>
        </div>

        <!-- ══ CONSOLIDATED VIEW ════════════════════════════════════════════════ -->
        <template v-if="viewMode === 'consolidated'">
          <div class="space-y-4">
            <div class="flex gap-2">
                <input v-model="search2" type="text" placeholder="Search items…"
                       class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-72" />
                <span class="self-center text-xs text-slate-500">Showing only items with quantity > 0</span>
            </div>

            <!-- Part I -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 bg-blue-50">
                    <h3 class="text-sm font-semibold text-blue-800">Part I — PS-DBM Items (Agency-to-Agency)</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[1500px] w-full text-sm">
                        <thead class="bg-slate-50/80">
                            <tr>
                                <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-28">Stock No.</th>
                                <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Description</th>
                                <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-14">Unit</th>
                                <th v-for="ml in monthLabels" :key="ml" class="px-1 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-10">{{ ml }}</th>
                                <th class="px-2 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-14">Total</th>
                                <th class="px-2 py-2 text-right text-xs font-semibold text-slate-500 uppercase w-24">Unit Cost</th>
                                <th class="px-2 py-2 text-right text-xs font-semibold text-slate-500 uppercase w-28">Total Amt</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template v-for="(c, idx) in filteredConsolidated.filter(c => c.part === 1)" :key="c.id">
                                <tr v-if="idx === 0 || c.category_group !== filteredConsolidated.filter(c2 => c2.part === 1)[idx-1]?.category_group">
                                    <td colspan="17" class="px-2 py-1.5 bg-blue-50 font-semibold text-xs text-blue-700 uppercase tracking-wide">{{ c.category_group }}</td>
                                </tr>
                                <tr class="hover:bg-blue-50/30">
                                    <td class="px-2 py-1.5 font-mono text-xs text-slate-600">{{ c.stock_number }}</td>
                                    <td class="px-2 py-1.5 text-slate-700">{{ c.description }}</td>
                                    <td class="px-2 py-1.5 text-slate-600">{{ c.unit }}</td>
                                    <td v-for="m in months" :key="m" class="px-1 py-1.5 text-center text-xs text-slate-600">{{ c[m] > 0 ? c[m] : '—' }}</td>
                                    <td class="px-2 py-1.5 text-center font-bold text-slate-700">{{ c.total_quantity }}</td>
                                    <td class="px-2 py-1.5 text-right text-xs text-slate-700">₱{{ formatPeso(c.unit_price) }}</td>
                                    <td class="px-2 py-1.5 text-right font-bold text-blue-800">₱{{ formatPeso(c.total_quantity * c.unit_price) }}</td>
                                </tr>
                            </template>
                            <tr v-if="!filteredConsolidated.filter(c => c.part === 1).length">
                                <td colspan="17" class="px-4 py-6 text-center text-slate-400 text-sm">No Part I items with quantities yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Part II -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 bg-emerald-50">
                    <h3 class="text-sm font-semibold text-emerald-800">Part II — Non-PS-DBM Items</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[1500px] w-full text-sm">
                        <thead class="bg-slate-50/80">
                            <tr>
                                <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-28">Stock No.</th>
                                <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Description</th>
                                <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-14">Unit</th>
                                <th v-for="ml in monthLabels" :key="ml" class="px-1 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-10">{{ ml }}</th>
                                <th class="px-2 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-14">Total</th>
                                <th class="px-2 py-2 text-right text-xs font-semibold text-slate-500 uppercase w-24">Avg Price</th>
                                <th class="px-2 py-2 text-right text-xs font-semibold text-slate-500 uppercase w-28">Est. Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template v-for="(c, idx) in filteredConsolidated.filter(c => c.part === 2)" :key="c.id">
                                <tr v-if="idx === 0 || c.category_group !== filteredConsolidated.filter(c2 => c2.part === 2)[idx-1]?.category_group">
                                    <td colspan="17" class="px-2 py-1.5 bg-emerald-50 font-semibold text-xs text-emerald-700 uppercase tracking-wide">{{ c.category_group }}</td>
                                </tr>
                                <tr class="hover:bg-emerald-50/30">
                                    <td class="px-2 py-1.5 font-mono text-xs text-slate-600">{{ c.stock_number }}</td>
                                    <td class="px-2 py-1.5 text-slate-700">{{ c.description }}</td>
                                    <td class="px-2 py-1.5 text-slate-600">{{ c.unit }}</td>
                                    <td v-for="m in months" :key="m" class="px-1 py-1.5 text-center text-xs text-slate-600">{{ c[m] > 0 ? c[m] : '—' }}</td>
                                    <td class="px-2 py-1.5 text-center font-bold text-slate-700">{{ c.total_quantity }}</td>
                                    <td class="px-2 py-1.5 text-right text-xs text-slate-600">
                                        {{ c.unit_price > 0 ? '₱' + formatPeso(c.unit_price) : '—' }}
                                        <span v-if="c.unit_price > 0" class="block text-[9px] text-emerald-600">avg</span>
                                    </td>
                                    <td class="px-2 py-1.5 text-right font-bold text-emerald-800">
                                        {{ c.unit_price > 0 ? '₱' + formatPeso(c.total_quantity * c.unit_price) : '—' }}
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="!filteredConsolidated.filter(c => c.part === 2).length">
                                <td colspan="17" class="px-4 py-6 text-center text-slate-400 text-sm">No Part II items with quantities yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
          </div>
        </template>

        <!-- ══ BY-UNIT VIEW ════════════════════════════════════════════════════ -->
        <template v-if="viewMode === 'by-unit'">
          <div class="space-y-3">
            <div>
                <input v-model="search" type="text" placeholder="Search by description or unit…"
                       class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-72" />
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-[1500px] w-full text-sm">
                        <thead class="bg-slate-50/80">
                            <tr>
                                <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Source Unit</th>
                                <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-16">Code</th>
                                <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Description</th>
                                <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-14">Unit</th>
                                <th v-for="ml in monthLabels" :key="ml" class="px-1 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-10">{{ ml }}</th>
                                <th class="px-2 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-14">Total</th>
                                <th class="px-2 py-2 text-right text-xs font-semibold text-slate-500 uppercase w-20">Unit Cost</th>
                                <th class="px-2 py-2 text-right text-xs font-semibold text-slate-500 uppercase w-24">Total Cost</th>
                                <th class="px-2 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-28">Method</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template v-for="(item, idx) in filteredItems" :key="item.id">
                                <tr v-if="idx === 0 || item.category !== filteredItems[idx - 1].category">
                                    <td colspan="19" class="px-2 py-2 bg-slate-100 font-semibold text-xs text-slate-700 uppercase tracking-wide">
                                        {{ categories[item.category] || item.category }}
                                    </td>
                                </tr>
                                <tr class="hover:bg-indigo-50/40">
                                    <td class="px-2 py-1.5 text-slate-600 text-xs">{{ item.division_acronym || item.division_name }}</td>
                                    <td class="px-2 py-1.5 text-slate-600 font-mono text-xs">{{ item.code }}</td>
                                    <td class="px-2 py-1.5 text-slate-700">{{ item.description }}</td>
                                    <td class="px-2 py-1.5 text-slate-600">{{ item.unit }}</td>
                                    <td v-for="m in months" :key="m" class="px-1 py-1.5 text-center text-slate-600">{{ item[m] > 0 ? item[m] : '—' }}</td>
                                    <td class="px-2 py-1.5 text-center font-medium text-slate-700">{{ item.total_quantity }}</td>
                                    <td class="px-2 py-1.5 text-right text-slate-700">₱{{ formatPeso(item.unit_cost) }}</td>
                                    <td class="px-2 py-1.5 text-right font-medium text-slate-800">₱{{ formatPeso(item.total_cost) }}</td>
                                    <td class="px-2 py-1.5 text-slate-600 text-xs">{{ methods[item.procurement_method] || item.procurement_method }}</td>
                                </tr>
                            </template>
                            <tr v-if="!filteredItems.length">
                                <td colspan="19" class="px-4 py-8 text-center text-slate-400">No items found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
          </div>
        </template>

      </div>
    </AdminLayout>
</template>
