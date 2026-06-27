<script setup>
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import StudentPortalLayout from '@/Layouts/StudentPortalLayout.vue'
import {
    AcademicCapIcon, TrophyIcon, UserGroupIcon, BriefcaseIcon,
    HomeIcon, HeartIcon, ShieldCheckIcon, ExclamationTriangleIcon,
    ClipboardDocumentListIcon, BeakerIcon, CheckCircleIcon, ClockIcon,
    HomeModernIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    student:         Object,
    grade_level:     Number,
    school_year:     String,
    completion:      Array,
    rhApplication:   Object,
    total_done:  Number,
    total:       Number,
})

const pct = computed(() => Math.round((props.total_done / props.total) * 100))

const iconMap = {
    academic:       AcademicCapIcon,
    activities:     TrophyIcon,
    social:         UserGroupIcon,
    career:         BriefcaseIcon,
    residence:      HomeIcon,
    health:         HeartIcon,
    allergies:      ExclamationTriangleIcon,
    immunizations:  ShieldCheckIcon,
    medical_history:ClipboardDocumentListIcon,
    vitamins:       BeakerIcon,
}

const profileSections = computed(() => props.completion.filter(s => s.group === 'profile'))
const medicalSections  = computed(() => props.completion.filter(s => s.group === 'medical'))

function goTo(section) {
    const isProfile = ['academic','activities','social','career','residence','health'].includes(section)
    router.visit(isProfile ? route('student-portal.profile') : route('student-portal.medical'))
}

function fmtDate(d) {
    if (!d) return null
    return new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>

<template>
    <Head title="Student Portal — Dashboard" />
    <StudentPortalLayout>
        <div class="space-y-6">

            <!-- Student banner -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-wrap gap-4 items-center">
                <div class="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center shrink-0">
                    <span class="text-indigo-700 font-bold text-lg">{{ student?.firstname?.[0] ?? '?' }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-slate-800 truncate">{{ student?.lastname }}, {{ student?.firstname }}</p>
                    <p class="text-sm text-slate-500">{{ student?.pisaysystemID }} · Grade {{ grade_level }} · S.Y. {{ school_year }}</p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-2xl font-bold text-indigo-600">{{ pct }}%</p>
                    <p class="text-xs text-slate-400">{{ total_done }} of {{ total }} sections done</p>
                </div>
            </div>

            <!-- Progress bar -->
            <div class="w-full bg-slate-100 rounded-full h-2">
                <div
                    class="h-2 rounded-full transition-all duration-500"
                    :class="pct === 100 ? 'bg-emerald-500' : 'bg-indigo-500'"
                    :style="{ width: pct + '%' }"
                ></div>
            </div>

            <!-- All done banner -->
            <div v-if="pct === 100" class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                <CheckCircleIcon class="w-6 h-6 text-emerald-500 shrink-0" />
                <p class="text-sm text-emerald-800 font-medium">All sections complete for S.Y. {{ school_year }}. Thank you!</p>
            </div>

            <!-- Guidance Profile sections -->
            <section>
                <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Guidance Profile</h2>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <button
                        v-for="s in profileSections"
                        :key="s.key"
                        @click="goTo(s.key)"
                        class="flex items-start gap-3 bg-white border rounded-xl p-4 text-left hover:shadow-md transition-all group"
                        :class="s.submitted ? 'border-emerald-200 hover:border-emerald-300' : 'border-slate-200 hover:border-indigo-300'"
                    >
                        <div
                            class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
                            :class="s.submitted ? 'bg-emerald-100' : 'bg-slate-100 group-hover:bg-indigo-50'"
                        >
                            <component :is="iconMap[s.key]" class="w-5 h-5" :class="s.submitted ? 'text-emerald-600' : 'text-slate-500 group-hover:text-indigo-600'" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-800">{{ s.label }}</p>
                            <p v-if="s.submitted" class="text-xs text-emerald-600 mt-0.5">
                                Done · {{ fmtDate(s.submitted_at) }}
                            </p>
                            <p v-else class="text-xs text-slate-400 mt-0.5">Not yet submitted</p>
                        </div>
                        <CheckCircleIcon v-if="s.submitted" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
                        <ClockIcon v-else class="w-4 h-4 text-slate-300 shrink-0 mt-0.5" />
                    </button>
                </div>
            </section>

            <!-- Medical sections -->
            <section>
                <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Medical Records</h2>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <button
                        v-for="s in medicalSections"
                        :key="s.key"
                        @click="goTo(s.key)"
                        class="flex items-start gap-3 bg-white border rounded-xl p-4 text-left hover:shadow-md transition-all group"
                        :class="s.submitted ? 'border-emerald-200 hover:border-emerald-300' : 'border-slate-200 hover:border-rose-300'"
                    >
                        <div
                            class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
                            :class="s.submitted ? 'bg-emerald-100' : 'bg-slate-100 group-hover:bg-rose-50'"
                        >
                            <component :is="iconMap[s.key]" class="w-5 h-5" :class="s.submitted ? 'text-emerald-600' : 'text-slate-500 group-hover:text-rose-500'" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-800">{{ s.label }}</p>
                            <p v-if="s.submitted" class="text-xs text-emerald-600 mt-0.5">Done</p>
                            <p v-else class="text-xs text-slate-400 mt-0.5">Pending</p>
                        </div>
                        <CheckCircleIcon v-if="s.submitted" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
                    </button>
                </div>
            </section>

            <!-- Residence Hall -->
            <section>
                <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Residence Hall</h2>
                <a href="/student-portal/rh-application"
                   class="flex items-start gap-3 bg-white border rounded-xl p-4 text-left hover:shadow-md transition-all group"
                   :class="rhApplication?.status === 'approved' ? 'border-emerald-200 hover:border-emerald-300' : 'border-slate-200 hover:border-indigo-300'">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
                         :class="rhApplication?.status === 'approved' ? 'bg-emerald-100' : 'bg-slate-100 group-hover:bg-indigo-50'">
                        <HomeModernIcon class="w-5 h-5" :class="rhApplication?.status === 'approved' ? 'text-emerald-600' : 'text-slate-500 group-hover:text-indigo-600'" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-800">Residence Hall Application</p>
                        <p v-if="!rhApplication" class="text-xs text-slate-400 mt-0.5">Apply for accommodation</p>
                        <p v-else-if="rhApplication.status === 'approved'" class="text-xs text-emerald-600 mt-0.5">Approved — {{ rhApplication.preferred_hall }}</p>
                        <p v-else-if="rhApplication.status === 'rejected'" class="text-xs text-rose-500 mt-0.5">Not approved</p>
                        <p v-else class="text-xs text-amber-600 mt-0.5">{{ rhApplication.status === 'waitlisted' ? 'Waitlisted' : 'Under review' }}</p>
                    </div>
                    <CheckCircleIcon v-if="rhApplication?.status === 'approved'" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
                    <ClockIcon v-else-if="rhApplication && rhApplication.status !== 'rejected'" class="w-4 h-4 text-amber-400 shrink-0 mt-0.5" />
                </a>
            </section>

        </div>
    </StudentPortalLayout>
</template>
