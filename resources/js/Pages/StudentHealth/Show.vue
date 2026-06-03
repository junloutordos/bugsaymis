<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
    UserIcon, ArrowLeftIcon, ExclamationTriangleIcon,
    ShieldCheckIcon, ClipboardDocumentListIcon, BeakerIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    student: Object,
    health: Object,
})

const activeTab = ref('allergies')

const tabs = [
    { key: 'allergies',       label: 'Allergies',       icon: ExclamationTriangleIcon },
    { key: 'immunizations',   label: 'Immunizations',   icon: ShieldCheckIcon },
    { key: 'medical_history', label: 'Medical History', icon: ClipboardDocumentListIcon },
    { key: 'vitamins',        label: 'Vitamins',        icon: BeakerIcon },
]

const studentName = `${props.student.lastname}, ${props.student.firstname}${props.student.middlename ? ' ' + props.student.middlename : ''}`

function fmtDate(d) {
    if (!d || d === '0000-00-00') return '—'
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}
</script>

<template>
    <Head :title="`${studentName} — Medical Records`" />
    <AdminLayout :title="`${studentName} — Medical Records`">
        <div class="space-y-5">
            <!-- Back -->
            <button @click="router.visit(route('students.health.index'))" class="flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
                <ArrowLeftIcon class="w-4 h-4" /> Back to list
            </button>

            <!-- Student Card -->
            <div class="bg-white rounded-xl border border-slate-200 p-5 flex flex-wrap items-center gap-6">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-rose-100 flex items-center justify-center">
                        <UserIcon class="w-6 h-6 text-rose-500" />
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800 text-base">{{ studentName }}</p>
                        <p class="text-sm text-slate-500">{{ student.pisaysystemID || 'No barcode' }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-8 gap-y-1 text-sm">
                    <div><span class="text-slate-400">Batch</span><p class="font-medium text-slate-700">{{ student.batch || '—' }}</p></div>
                    <div><span class="text-slate-400">Status</span><p class="font-medium text-slate-700">{{ student.status || '—' }}</p></div>
                    <div><span class="text-slate-400">Sex</span><p class="font-medium text-slate-700">{{ student.sex || '—' }}</p></div>
                </div>
                <a
                    :href="route('guidance.cumulative.show', student.pisaysystemID)"
                    class="ml-auto text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                >
                    Cumulative Profile →
                </a>
            </div>

            <!-- Tabs -->
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="flex overflow-x-auto border-b border-slate-200">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="activeTab = tab.key"
                        class="flex items-center gap-1.5 px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors"
                        :class="activeTab === tab.key
                            ? 'border-rose-500 text-rose-600 bg-rose-50'
                            : 'border-transparent text-slate-500 hover:text-slate-700'"
                    >
                        <component :is="tab.icon" class="w-4 h-4" />
                        {{ tab.label }}
                        <span
                            v-if="health[tab.key]?.length"
                            class="ml-1 px-1.5 py-0.5 rounded-full text-xs font-semibold"
                            :class="activeTab === tab.key ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-500'"
                        >{{ health[tab.key].length }}</span>
                    </button>
                </div>

                <div class="p-5">

                    <!-- ALLERGIES -->
                    <template v-if="activeTab === 'allergies'">
                        <div v-if="!health.allergies.length" class="text-sm text-slate-400">No allergy records.</div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="r in health.allergies"
                                :key="r.id"
                                class="flex flex-wrap items-start gap-4 bg-slate-50 rounded-lg p-4 text-sm"
                                :class="r.allergy === 'yes' ? 'border-l-4 border-rose-400' : ''"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-slate-800">
                                        {{ r.allergy === 'yes' ? 'Has allergy' : 'No known allergy' }}
                                    </p>
                                    <p v-if="r.medication" class="text-slate-600 mt-0.5">Medication: {{ r.medication }}</p>
                                </div>
                                <p class="text-xs text-slate-400 shrink-0">{{ fmtDate(r.created_at) }}</p>
                            </div>
                        </div>
                    </template>

                    <!-- IMMUNIZATIONS -->
                    <template v-if="activeTab === 'immunizations'">
                        <div v-if="!health.immunizations.length" class="text-sm text-slate-400">No immunization records.</div>
                        <table v-else class="min-w-full text-sm">
                            <thead>
                                <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                    <th class="text-left pb-2">Vaccine</th>
                                    <th class="text-left pb-2">Date Administered</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="r in health.immunizations" :key="r.id" class="py-2">
                                    <td class="py-2 pr-4 font-medium text-slate-700">{{ r.vaccine || '—' }}</td>
                                    <td class="py-2 text-slate-500">{{ fmtDate(r.date_administered) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </template>

                    <!-- MEDICAL HISTORY -->
                    <template v-if="activeTab === 'medical_history'">
                        <div v-if="!health.medical_history.length" class="text-sm text-slate-400">No medical history recorded.</div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="r in health.medical_history"
                                :key="r.id"
                                class="bg-slate-50 rounded-lg p-4 text-sm"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <p class="font-medium text-slate-800">{{ r.disease || 'Unspecified' }}</p>
                                    <p class="text-xs text-slate-400">{{ fmtDate(r.date_sustained) }}</p>
                                </div>
                                <div class="mt-1 flex gap-4 text-xs text-slate-500">
                                    <span v-if="r.opd" class="text-amber-600 font-medium">OPD</span>
                                    <span v-if="r.hospital_confinement" class="text-rose-600 font-medium">Hospital confinement</span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- VITAMINS -->
                    <template v-if="activeTab === 'vitamins'">
                        <div v-if="!health.vitamins.length" class="text-sm text-slate-400">No vitamins/supplements recorded.</div>
                        <table v-else class="min-w-full text-sm">
                            <thead>
                                <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                    <th class="text-left pb-2">Supplement</th>
                                    <th class="text-left pb-2">Date Taken</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="r in health.vitamins" :key="r.id">
                                    <td class="py-2 pr-4 font-medium text-slate-700">{{ r.vitamin || '—' }}</td>
                                    <td class="py-2 text-slate-500">{{ fmtDate(r.date_taken) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </template>

                </div>
            </div>
        </div>
    </AdminLayout>
</template>
