<script setup>
import { ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import StudentPortalLayout from '@/Layouts/StudentPortalLayout.vue'
import { CheckCircleIcon, ClockIcon, XCircleIcon, HomeModernIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    student:       Object,
    school_year:   String,
    application:   Object,
    intern:        Object,
    suggestedHall: String,
})

const flash = usePage().props.flash ?? {}

const form = ref({
    preferred_hall:        props.suggestedHall || 'BRH',
    home_province:         props.student?.province || '',
    estimated_distance_km: '',
    scholarship_category:  props.student?.schocat || '',
    foster_parent_name:    '',
    foster_parent_contact: '',
    foster_parent_address: '',
})

const submitting = ref(false)

function submit() {
    submitting.value = true
    router.post(route('student-portal.rh-application.store'), form.value, {
        preserveScroll: true,
        onFinish: () => { submitting.value = false },
    })
}

const fmtDate = (d) => d
    ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
    : '—'
</script>

<template>
    <Head title="Residence Hall Application" />
    <StudentPortalLayout>
        <div class="max-w-2xl mx-auto space-y-5">

            <!-- Back -->
            <a href="/student-portal/dashboard"
               class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600 transition-colors">
                <ArrowLeftIcon class="w-4 h-4" /> Back to Dashboard
            </a>

            <!-- Title -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center shrink-0">
                    <HomeModernIcon class="w-5 h-5 text-indigo-600" />
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-slate-800">Residence Hall Application</h1>
                    <p class="text-sm text-slate-500">S.Y. {{ school_year }} — SSM 5.1 Accommodation Request</p>
                </div>
            </div>

            <!-- Flash -->
            <div v-if="flash.success" class="flex items-start gap-3 bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                <CheckCircleIcon class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" />
                <p class="text-sm text-emerald-800">{{ flash.success }}</p>
            </div>
            <div v-if="flash.error" class="flex items-start gap-3 bg-rose-50 border border-rose-200 rounded-xl p-4">
                <XCircleIcon class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" />
                <p class="text-sm text-rose-800">{{ flash.error }}</p>
            </div>

            <!-- Already an active dormer -->
            <div v-if="intern" class="flex items-start gap-3 bg-emerald-50 border border-emerald-200 rounded-xl p-5">
                <CheckCircleIcon class="w-6 h-6 text-emerald-500 shrink-0 mt-0.5" />
                <div>
                    <p class="font-semibold text-emerald-800">You are currently an active dormer.</p>
                    <p class="text-sm text-emerald-700 mt-1">You are already checked in to the Residence Hall for S.Y. {{ school_year }}. Contact your Dorm Manager for room details.</p>
                </div>
            </div>

            <!-- Approved status -->
            <template v-else-if="application?.status === 'approved'">
                <div class="flex items-start gap-3 bg-emerald-50 border border-emerald-200 rounded-xl p-5">
                    <CheckCircleIcon class="w-6 h-6 text-emerald-500 shrink-0 mt-0.5" />
                    <div>
                        <p class="font-semibold text-emerald-800">Application Approved</p>
                        <p class="text-sm text-emerald-700 mt-1">
                            Your application for <strong>{{ application.preferred_hall }}</strong> has been approved
                            on {{ fmtDate(application.approved_at) }}.
                            Please coordinate with the Residence Hall office for your room assignment and check-in schedule.
                        </p>
                    </div>
                </div>
            </template>

            <!-- Pending / evaluated / waitlisted status -->
            <template v-else-if="application && ['pending','evaluated','waitlisted'].includes(application.status)">
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                    <div class="flex items-center gap-3 mb-4">
                        <ClockIcon class="w-6 h-6 text-amber-500 shrink-0" />
                        <div>
                            <p class="font-semibold text-slate-800">Application Under Review</p>
                            <p class="text-sm text-slate-500">Filed {{ fmtDate(application.created_at) }}</p>
                        </div>
                        <span :class="['ml-auto text-xs px-2 py-1 rounded-full font-semibold capitalize',
                            application.status === 'waitlisted' ? 'bg-slate-100 text-slate-600' : 'bg-amber-100 text-amber-700']">
                            {{ application.status === 'waitlisted' ? 'Waitlisted' : 'Under Review' }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                        <div>
                            <span class="text-slate-500">Preferred Hall</span>
                            <p class="font-medium mt-0.5">
                                <span :class="['text-xs px-2 py-0.5 rounded-full font-semibold',
                                    application.preferred_hall === 'BRH' ? 'bg-indigo-100 text-indigo-700' : 'bg-pink-100 text-pink-700']">
                                    {{ application.preferred_hall }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <span class="text-slate-500">Home Province</span>
                            <p class="font-medium mt-0.5">{{ application.home_province || '—' }}</p>
                        </div>
                        <div>
                            <span class="text-slate-500">Distance</span>
                            <p class="font-medium mt-0.5">{{ application.estimated_distance_km != null ? application.estimated_distance_km + ' km' : '—' }}</p>
                        </div>
                        <div>
                            <span class="text-slate-500">Foster Parent</span>
                            <p class="font-medium mt-0.5">{{ application.foster_parent_name || '—' }}</p>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 mt-4">
                        The Residence Hall Committee will notify you of the result. You cannot modify a submitted application.
                    </p>
                </div>
            </template>

            <!-- Rejected — show reason, no re-apply for same SY -->
            <template v-else-if="application?.status === 'rejected'">
                <div class="bg-white rounded-xl border border-rose-200 shadow-sm p-5">
                    <div class="flex items-center gap-3 mb-3">
                        <XCircleIcon class="w-6 h-6 text-rose-500 shrink-0" />
                        <p class="font-semibold text-slate-800">Application Not Approved</p>
                    </div>
                    <p v-if="application.rejection_reason" class="text-sm text-slate-600 bg-rose-50 rounded-lg p-3">
                        {{ application.rejection_reason }}
                    </p>
                    <p v-else class="text-sm text-slate-500">No reason provided. Please contact the Residence Hall office for details.</p>
                    <p class="text-xs text-slate-400 mt-3">You may apply again in the next school year.</p>
                </div>
            </template>

            <!-- Application Form (no application yet) -->
            <template v-else>
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6">
                    <h2 class="text-sm font-semibold text-slate-700 mb-4">Application Form — F-RHU-01</h2>

                    <div class="space-y-4">

                        <!-- Preferred Hall -->
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Preferred Residence Hall <span class="text-rose-500">*</span></label>
                            <div class="flex gap-3">
                                <label :class="['flex-1 flex items-center gap-3 rounded-xl border-2 p-3 cursor-pointer transition-colors',
                                    form.preferred_hall === 'BRH' ? 'border-indigo-400 bg-indigo-50' : 'border-slate-200 hover:border-slate-300']">
                                    <input type="radio" v-model="form.preferred_hall" value="BRH" class="sr-only" />
                                    <span class="text-xs font-bold text-indigo-700">BRH</span>
                                    <span class="text-xs text-slate-600">Boys Residence Hall</span>
                                </label>
                                <label :class="['flex-1 flex items-center gap-3 rounded-xl border-2 p-3 cursor-pointer transition-colors',
                                    form.preferred_hall === 'GRH' ? 'border-pink-400 bg-pink-50' : 'border-slate-200 hover:border-slate-300']">
                                    <input type="radio" v-model="form.preferred_hall" value="GRH" class="sr-only" />
                                    <span class="text-xs font-bold text-pink-700">GRH</span>
                                    <span class="text-xs text-slate-600">Girls Residence Hall</span>
                                </label>
                            </div>
                        </div>

                        <!-- Province + Distance -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Home Province <span class="text-rose-500">*</span></label>
                                <input v-model="form.home_province" type="text" placeholder="e.g. Agusan del Norte"
                                       class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Estimated Distance from Campus (km) <span class="text-rose-500">*</span></label>
                                <input v-model.number="form.estimated_distance_km" type="number" min="0" placeholder="e.g. 120"
                                       class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                        </div>

                        <!-- Scholarship Category -->
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Scholarship Category</label>
                            <input v-model="form.scholarship_category" type="text" placeholder="e.g. Full Merit, Regional"
                                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>

                        <!-- Foster Parent -->
                        <div class="border border-slate-100 rounded-xl p-4 space-y-3 bg-slate-50">
                            <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Foster Parent Information <span class="text-rose-500">*</span></p>
                            <p class="text-xs text-slate-500 -mt-1">Required per SSM. A foster parent is a trusted adult in Butuan City who can be contacted in emergencies.</p>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Full Name</label>
                                <input v-model="form.foster_parent_name" type="text" placeholder="Last Name, First Name"
                                       class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Contact Number</label>
                                    <input v-model="form.foster_parent_contact" type="text" placeholder="09XXXXXXXXX"
                                           class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Address</label>
                                    <input v-model="form.foster_parent_address" type="text" placeholder="Complete address"
                                           class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="mt-6 flex gap-3">
                        <a href="/student-portal/dashboard"
                           class="flex-1 text-center px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm hover:bg-slate-50">
                            Cancel
                        </a>
                        <button @click="submit"
                                :disabled="submitting || !form.preferred_hall || !form.home_province || !form.estimated_distance_km || !form.foster_parent_name || !form.foster_parent_contact || !form.foster_parent_address"
                                class="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium">
                            {{ submitting ? 'Submitting…' : 'Submit Application' }}
                        </button>
                    </div>
                </div>

                <p class="text-xs text-slate-400 text-center">
                    Your application will be reviewed by the Residence Hall Committee. You will be notified of the result.
                </p>
            </template>

        </div>
    </StudentPortalLayout>
</template>
