<script setup>
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import StudentPortalLayout from '@/Layouts/StudentPortalLayout.vue'
import { CheckCircleIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    existing:       Object,
    submitted:      Object,
    school_year:    String,
    school_year_id: Number,
})

const flash  = usePage().props.flash ?? {}
const saving = ref(false)
const activeTab = ref('allergies')

const tabs = [
    { key: 'allergies',        label: 'Allergies' },
    { key: 'immunizations',    label: 'Immunizations' },
    { key: 'medical_history',  label: 'Medical History' },
    { key: 'vitamins',         label: 'Vitamins' },
]

// ── Form state ─────────────────────────────────────────────────────────────

const allergies = ref(
    props.existing.allergies.length
        ? props.existing.allergies.map(r => ({ allergy: r.allergy, medication: r.medication ?? '' }))
        : [{ allergy: 'no', medication: '' }]
)

const immunizations = ref(
    props.existing.immunizations.length
        ? props.existing.immunizations.map(r => ({ vaccine: r.vaccine ?? '', date_administered: r.date_administered ?? '' }))
        : [{ vaccine: '', date_administered: '' }]
)

const history = ref(
    props.existing.medical_history.length
        ? props.existing.medical_history.map(r => ({ disease: r.disease ?? '', date_sustained: r.date_sustained ?? '', opd: !!r.opd, hospital_confinement: !!r.hospital_confinement }))
        : [{ disease: '', date_sustained: '', opd: false, hospital_confinement: false }]
)

const vitamins = ref(
    props.existing.vitamins.length
        ? props.existing.vitamins.map(r => ({ vitamin: r.vitamin ?? '', date_taken: r.date_taken ?? '' }))
        : [{ vitamin: '', date_taken: '' }]
)

// ── Save ───────────────────────────────────────────────────────────────────

function saveTab(section) {
    saving.value = true
    const payloads = {
        allergies:       { allergies: allergies.value },
        immunizations:   { immunizations: immunizations.value },
        medical_history: { history: history.value },
        vitamins:        { vitamins: vitamins.value },
    }
    router.post(route('student-portal.medical.save', section), payloads[section], {
        preserveScroll: true,
        onFinish: () => { saving.value = false },
    })
}

function addRow(list, tmpl) { list.value.push({ ...tmpl }) }
function removeRow(list, i) { if (list.value.length > 1) list.value.splice(i, 1) }
</script>

<template>
    <Head title="Student Portal — Medical Records" />
    <StudentPortalLayout>
        <div class="space-y-5">
            <div>
                <h1 class="text-lg font-semibold text-slate-800">Medical Records</h1>
                <p class="text-sm text-slate-500">S.Y. {{ school_year }} · All records are confidential and handled by the school nurse.</p>
            </div>

            <!-- Flash -->
            <div v-if="flash.success" class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-lg px-4 py-3">
                <CheckCircleIcon class="w-4 h-4 shrink-0" /> {{ flash.success }}
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <!-- Tabs -->
                <div class="flex overflow-x-auto border-b border-slate-200">
                    <button
                        v-for="tab in tabs" :key="tab.key"
                        @click="activeTab = tab.key"
                        class="relative flex items-center gap-1.5 px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors"
                        :class="activeTab === tab.key ? 'border-rose-500 text-rose-600 bg-rose-50' : 'border-transparent text-slate-500 hover:text-slate-700'"
                    >
                        {{ tab.label }}
                        <CheckCircleIcon v-if="submitted[tab.key]" class="w-3.5 h-3.5 text-emerald-500" />
                    </button>
                </div>

                <div class="p-6 space-y-4">

                    <!-- ALLERGIES -->
                    <template v-if="activeTab === 'allergies'">
                        <p class="text-sm text-slate-500">List any allergies you have. Select "No" if you have none.</p>
                        <div v-for="(row, i) in allergies" :key="i" class="flex gap-3 items-start flex-wrap">
                            <div class="w-28">
                                <label class="block text-xs text-slate-500 mb-1">Has allergy?</label>
                                <select v-model="row.allergy" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-400 w-full">
                                    <option value="yes">Yes</option>
                                    <option value="no">No</option>
                                </select>
                            </div>
                            <div class="flex-1 min-w-0">
                                <label class="block text-xs text-slate-500 mb-1">Medication / Details</label>
                                <input v-model="row.medication" :disabled="row.allergy === 'no'" placeholder="e.g. Amoxicillin, antihistamine" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-400 w-full disabled:bg-slate-50 disabled:text-slate-400" />
                            </div>
                            <button @click="removeRow(allergies, i)" class="mt-6 p-2 text-slate-400 hover:text-red-500"><TrashIcon class="w-4 h-4" /></button>
                        </div>
                        <button @click="addRow(allergies, { allergy: 'no', medication: '' })" class="flex items-center gap-1 text-sm text-rose-600 hover:text-rose-800">
                            <PlusIcon class="w-4 h-4" /> Add entry
                        </button>
                        <button @click="saveTab('allergies')" :disabled="saving" class="bg-rose-500 hover:bg-rose-600 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">
                            {{ saving ? 'Saving…' : 'Save Allergies' }}
                        </button>
                    </template>

                    <!-- IMMUNIZATIONS -->
                    <template v-if="activeTab === 'immunizations'">
                        <p class="text-sm text-slate-500">List all vaccines you have received.</p>
                        <div v-for="(row, i) in immunizations" :key="i" class="flex gap-3 items-start flex-wrap">
                            <div class="flex-1 min-w-0">
                                <label class="block text-xs text-slate-500 mb-1">Vaccine name</label>
                                <input v-model="row.vaccine" placeholder="e.g. COVID-19 (Pfizer), HPV Dose 1" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-400 w-full" />
                            </div>
                            <div class="w-44">
                                <label class="block text-xs text-slate-500 mb-1">Date administered</label>
                                <input v-model="row.date_administered" type="date" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-400 w-full" />
                            </div>
                            <button @click="removeRow(immunizations, i)" class="mt-6 p-2 text-slate-400 hover:text-red-500"><TrashIcon class="w-4 h-4" /></button>
                        </div>
                        <button @click="addRow(immunizations, { vaccine: '', date_administered: '' })" class="flex items-center gap-1 text-sm text-rose-600 hover:text-rose-800">
                            <PlusIcon class="w-4 h-4" /> Add vaccine
                        </button>
                        <button @click="saveTab('immunizations')" :disabled="saving" class="bg-rose-500 hover:bg-rose-600 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">
                            {{ saving ? 'Saving…' : 'Save Immunizations' }}
                        </button>
                    </template>

                    <!-- MEDICAL HISTORY -->
                    <template v-if="activeTab === 'medical_history'">
                        <p class="text-sm text-slate-500">List past illnesses, injuries, or hospitalizations.</p>
                        <div v-for="(row, i) in history" :key="i" class="border border-slate-100 rounded-lg p-3 space-y-3">
                            <div class="flex gap-3 flex-wrap">
                                <div class="flex-1 min-w-0">
                                    <label class="block text-xs text-slate-500 mb-1">Disease / Condition</label>
                                    <input v-model="row.disease" placeholder="e.g. Dengue, Chicken Pox" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-400 w-full" />
                                </div>
                                <div class="w-44">
                                    <label class="block text-xs text-slate-500 mb-1">Date</label>
                                    <input v-model="row.date_sustained" type="date" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-400 w-full" />
                                </div>
                                <button @click="removeRow(history, i)" class="mt-5 p-2 text-slate-400 hover:text-red-500"><TrashIcon class="w-4 h-4" /></button>
                            </div>
                            <div class="flex gap-5">
                                <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                                    <input type="checkbox" v-model="row.opd" class="rounded" /> OPD Visit
                                </label>
                                <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                                    <input type="checkbox" v-model="row.hospital_confinement" class="rounded" /> Hospital Confinement
                                </label>
                            </div>
                        </div>
                        <button @click="addRow(history, { disease: '', date_sustained: '', opd: false, hospital_confinement: false })" class="flex items-center gap-1 text-sm text-rose-600 hover:text-rose-800">
                            <PlusIcon class="w-4 h-4" /> Add record
                        </button>
                        <button @click="saveTab('medical_history')" :disabled="saving" class="bg-rose-500 hover:bg-rose-600 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">
                            {{ saving ? 'Saving…' : 'Save Medical History' }}
                        </button>
                    </template>

                    <!-- VITAMINS -->
                    <template v-if="activeTab === 'vitamins'">
                        <p class="text-sm text-slate-500">List vitamins or supplements you regularly take.</p>
                        <div v-for="(row, i) in vitamins" :key="i" class="flex gap-3 items-start flex-wrap">
                            <div class="flex-1 min-w-0">
                                <label class="block text-xs text-slate-500 mb-1">Supplement / Vitamin</label>
                                <input v-model="row.vitamin" placeholder="e.g. Vitamin C, Cherifer with Zinc" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-400 w-full" />
                            </div>
                            <div class="w-44">
                                <label class="block text-xs text-slate-500 mb-1">Date started</label>
                                <input v-model="row.date_taken" type="date" class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-400 w-full" />
                            </div>
                            <button @click="removeRow(vitamins, i)" class="mt-6 p-2 text-slate-400 hover:text-red-500"><TrashIcon class="w-4 h-4" /></button>
                        </div>
                        <button @click="addRow(vitamins, { vitamin: '', date_taken: '' })" class="flex items-center gap-1 text-sm text-rose-600 hover:text-rose-800">
                            <PlusIcon class="w-4 h-4" /> Add supplement
                        </button>
                        <button @click="saveTab('vitamins')" :disabled="saving" class="bg-rose-500 hover:bg-rose-600 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">
                            {{ saving ? 'Saving…' : 'Save Vitamins' }}
                        </button>
                    </template>

                </div>
            </div>
        </div>
    </StudentPortalLayout>
</template>
