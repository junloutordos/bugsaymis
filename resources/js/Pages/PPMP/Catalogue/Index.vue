<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowUpTrayIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    items:          Array,
    fiscalYear:     Number,
    availableYears: Array,
    part1Count:     Number,
    part2Count:     Number,
})

const page  = usePage()
const flash = computed(() => page.props.flash)

const formatPeso = (v) => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

// ── Fiscal year filter ────────────────────────────────────────────────────
const selectedYear = ref(props.fiscalYear)
const changeYear   = () => router.get(route('ppmp.catalogue.index'), { fiscal_year: selectedYear.value }, { preserveState: true })

// ── Upload ────────────────────────────────────────────────────────────────
const uploadYear   = ref(props.fiscalYear)
const uploading    = ref(false)
const uploadErrors = computed(() => page.props.flash?.upload_errors ?? [])

const triggerUpload = () => document.getElementById('catalogue-file').click()

const handleFile = (e) => {
    const file = e.target.files[0]
    if (!file) return

    const reader = new FileReader()
    reader.onload = () => {
        uploading.value = true
        router.post(route('ppmp.catalogue.upload'), {
            fiscal_year: uploadYear.value,
            file_base64: reader.result,
        }, {
            onFinish: () => {
                uploading.value = false
                e.target.value = ''
            },
        })
    }
    reader.readAsDataURL(file)
}

// ── Local search ──────────────────────────────────────────────────────────
const search   = ref('')
const filtered = computed(() => {
    if (!search.value) return props.items
    const q = search.value.toLowerCase()
    return props.items.filter(i =>
        i.stock_number.toLowerCase().includes(q) ||
        i.description.toLowerCase().includes(q)
    )
})
</script>

<template>
    <Head title="PS-DBM Catalogue — Part I" />
    <AdminLayout title="PS-DBM Catalogue (Part I)">

        <!-- Flash -->
        <div v-if="flash.success" class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ flash.success }}</div>
        <div v-if="flash.error"   class="mb-4 rounded-lg bg-red-50   border border-red-200   px-4 py-3 text-sm text-red-800">{{ flash.error }}</div>
        <div v-if="uploadErrors.length" class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3">
            <p class="text-sm font-semibold text-amber-800 mb-1">Row-level warnings during upload:</p>
            <ul class="text-xs text-amber-700 list-disc list-inside space-y-0.5">
                <li v-for="(e, i) in uploadErrors" :key="i">{{ e }}</li>
            </ul>
        </div>

        <!-- Upload card -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-4">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Upload PS-DBM Price List</h3>
            <p class="text-xs text-slate-500 mb-3">
                Upload the official <strong>APP-CSE Excel template</strong> from PS-DBM (the "APP-CSE YYYY FORM" sheet).
                Column layout: <strong>A</strong> Seq# · <strong>B</strong> UNSPSC/Stock Number · <strong>C</strong> Description · <strong>D</strong> Unit · <strong>Z</strong> Unit Price.
                Data rows start at row 32. Category group headers, Part II boundary, and summary rows are detected automatically.
                Existing items for the selected fiscal year are deactivated and replaced on each upload.
            </p>
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Fiscal Year</label>
                    <select v-model="uploadYear" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
                        <option :value="new Date().getFullYear() + 1">{{ new Date().getFullYear() + 1 }}</option>
                    </select>
                </div>
                <div>
                    <input id="catalogue-file" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="handleFile" />
                    <button @click="triggerUpload" :disabled="uploading"
                            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        <ArrowUpTrayIcon class="w-4 h-4" />
                        {{ uploading ? 'Uploading…' : 'Select & Upload File' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Catalogue table -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <h3 class="text-sm font-semibold text-slate-700">FY {{ fiscalYear }} Catalogue</h3>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">Part I: {{ part1Count }}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">Part II: {{ part2Count }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <select v-model="selectedYear" @change="changeYear"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option v-for="y in availableYears" :key="y" :value="y">FY {{ y }}</option>
                    </select>
                    <div class="relative">
                        <MagnifyingGlassIcon class="w-4 h-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                        <input v-model="search" placeholder="Search stock no. or description…"
                               class="pl-8 pr-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-64" />
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-32">Stock Number</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Description</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-20">Unit</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 uppercase w-28">Unit Cost</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-28">Price Valid Until</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-32">Uploaded By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in filtered" :key="item.id" class="hover:bg-slate-50">
                            <td class="px-3 py-2 font-mono text-xs text-slate-700">{{ item.stock_number }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ item.description }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ item.unit }}</td>
                            <td class="px-3 py-2 text-right text-slate-700">₱{{ formatPeso(item.unit_cost) }}</td>
                            <td class="px-3 py-2 text-center text-slate-500 text-xs">
                                {{ item.price_validity_date ? new Date(item.price_validity_date).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—' }}
                            </td>
                            <td class="px-3 py-2 text-slate-500 text-xs">{{ item.uploader?.name ?? '—' }}</td>
                        </tr>
                        <tr v-if="!filtered.length">
                            <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                {{ items.length ? 'No items match your search.' : 'No catalogue uploaded for this fiscal year yet.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </AdminLayout>
</template>
