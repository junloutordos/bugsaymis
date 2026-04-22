<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowPathIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    items: Array,
    totals: Object,
    fiscalYear: Number,
    approvedCount: Number,
    categories: Object,
    methods: Object,
})

const months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec']
const monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']

const formatPeso = (v) => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const search = ref('')
const filtered = computed(() => {
    if (!search.value) return props.items
    const q = search.value.toLowerCase()
    return props.items.filter(i =>
        (i.description || '').toLowerCase().includes(q) ||
        (i.division_name || '').toLowerCase().includes(q)
    )
})

const consolidate = () => {
    if (!confirm(`Consolidate all approved PPMPs for FY ${props.fiscalYear} into the APP?`)) return
    router.post(route('ppmp.app.consolidate'), { fiscal_year: props.fiscalYear }, { preserveScroll: true })
}
</script>

<template>
    <Head :title="`APP — FY ${fiscalYear}`" />
    <AdminLayout :title="`Annual Procurement Plan — FY ${fiscalYear}`">
        <!-- Summary -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-4">
                <div class="bg-white rounded-lg border border-slate-200 px-4 py-2">
                    <span class="text-xs text-slate-500">Approved PPMPs</span>
                    <span class="ml-2 text-lg font-bold text-slate-800">{{ approvedCount }}</span>
                </div>
                <div class="bg-indigo-50 rounded-lg border border-indigo-200 px-4 py-2">
                    <span class="text-xs text-indigo-500">Grand Total</span>
                    <span class="ml-2 text-lg font-bold text-indigo-800">₱{{ formatPeso(totals.grand_total) }}</span>
                </div>
            </div>
            <div class="flex gap-2">
                <input v-model="search" type="text" placeholder="Search items..."
                       class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-48" />
                <button @click="consolidate"
                        class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    <ArrowPathIcon class="w-4 h-4" /> Consolidate
                </button>
            </div>
        </div>

        <!-- Category totals -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
            <div v-for="cat in (totals.categories || [])" :key="cat.category" class="bg-white rounded-lg border border-slate-200 p-3">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ cat.label }}</p>
                <p class="text-lg font-bold text-slate-800">₱{{ formatPeso(cat.subtotal) }}</p>
                <p class="text-xs text-slate-500">{{ cat.item_count }} item(s)</p>
            </div>
        </div>

        <!-- Items table -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-[1500px] w-full text-sm">
                    <thead class="bg-slate-50">
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
                        <template v-for="(item, idx) in filtered" :key="item.id">
                            <tr v-if="idx === 0 || item.category !== filtered[idx - 1].category">
                                <td colspan="19" class="px-2 py-2 bg-slate-100 font-semibold text-xs text-slate-700 uppercase tracking-wide">
                                    {{ categories[item.category] || item.category }}
                                </td>
                            </tr>
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-2 py-1.5 text-slate-600 text-xs">{{ item.division_acronym || item.division_name }}</td>
                                <td class="px-2 py-1.5 text-slate-600">{{ item.code }}</td>
                                <td class="px-2 py-1.5 text-slate-700">{{ item.description }}</td>
                                <td class="px-2 py-1.5 text-slate-600">{{ item.unit }}</td>
                                <td v-for="m in months" :key="m" class="px-1 py-1.5 text-center text-slate-600">{{ item[m] > 0 ? item[m] : '—' }}</td>
                                <td class="px-2 py-1.5 text-center font-medium text-slate-700">{{ item.total_quantity }}</td>
                                <td class="px-2 py-1.5 text-right text-slate-700">{{ formatPeso(item.unit_cost) }}</td>
                                <td class="px-2 py-1.5 text-right font-medium text-slate-800">{{ formatPeso(item.total_cost) }}</td>
                                <td class="px-2 py-1.5 text-slate-600 text-xs">{{ methods[item.procurement_method] || item.procurement_method }}</td>
                            </tr>
                        </template>
                        <tr v-if="!filtered.length">
                            <td colspan="19" class="px-4 py-8 text-center text-slate-400">No items found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
