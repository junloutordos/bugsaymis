<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import EmptyState from '@/Components/EmptyState.vue'
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
      <div class="space-y-5">

        <AppPageHeader title="PS-DBM Catalogue (Part I)"
                        subtitle="Upload and browse the official PS-DBM APP-CSE price list used for PPMP costing." />

        <!-- Flash -->
        <div v-if="flash.success" class="rounded-lg bg-success-50 border border-success-100 px-4 py-3 text-sm text-success-700">{{ flash.success }}</div>
        <div v-if="flash.error"   class="rounded-lg bg-danger-50  border border-danger-100  px-4 py-3 text-sm text-danger-600">{{ flash.error }}</div>
        <div v-if="uploadErrors.length" class="rounded-lg bg-warning-50 border border-warning-100 px-4 py-3">
            <p class="text-sm font-semibold text-warning-700 mb-1">Row-level warnings during upload:</p>
            <ul class="text-xs text-warning-600 list-disc list-inside space-y-0.5">
                <li v-for="(e, i) in uploadErrors" :key="i">{{ e }}</li>
            </ul>
        </div>

        <!-- Upload card -->
        <AppCard title="Upload PS-DBM Price List">
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
                    <AppButton @click="triggerUpload" :disabled="uploading" :loading="uploading">
                        <ArrowUpTrayIcon class="w-4 h-4" />
                        {{ uploading ? 'Uploading…' : 'Select & Upload File' }}
                    </AppButton>
                </div>
            </div>
        </AppCard>

        <!-- Catalogue header + filters -->
        <div class="flex flex-wrap items-center gap-3">
            <h3 class="text-sm font-semibold text-slate-700">FY {{ fiscalYear }} Catalogue</h3>
            <AppBadge color="indigo">Part I: {{ part1Count }}</AppBadge>
            <AppBadge color="green">Part II: {{ part2Count }}</AppBadge>
        </div>

        <AppFilterBar>
            <select v-model="selectedYear" @change="changeYear"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option v-for="y in availableYears" :key="y" :value="y">FY {{ y }}</option>
            </select>
            <div class="relative">
                <MagnifyingGlassIcon class="w-4 h-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                <input v-model="search" placeholder="Search stock no. or description…"
                       class="pl-8 pr-3 py-2 rounded-lg border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-64" />
            </div>
        </AppFilterBar>

        <!-- Catalogue table -->
        <AppTable :is-empty="!filtered.length" :skeleton-cols="6">
            <template #head>
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-32">Stock Number</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Description</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-20">Unit</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 uppercase w-28">Unit Cost</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold text-slate-500 uppercase w-28">Price Valid Until</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase w-32">Uploaded By</th>
                </tr>
            </template>

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

            <template #empty>
                <EmptyState :title="items.length ? 'No items match your search.' : 'No catalogue uploaded for this fiscal year yet.'" />
            </template>
        </AppTable>

      </div>
    </AdminLayout>
</template>
