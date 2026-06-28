<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import StudentPortalLayout from '@/Layouts/StudentPortalLayout.vue'
import {
    ArrowLeftIcon, PlusIcon, CheckCircleIcon, ClockIcon,
    XCircleIcon, HomeModernIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    intern:      Object,
    leavePasses: Array,
    schoolYear:  String,
})

const flash = usePage().props.flash ?? {}

const showModal  = ref(false)
const submitting = ref(false)

const form = ref({
    purpose:            'go_home',
    destination:        '',
    expected_return_at: '',
    with_companion:     false,
    companion_name:     '',
    companion_contact:  '',
})

function openModal() {
    Object.assign(form.value, {
        purpose: 'go_home', destination: '', expected_return_at: '',
        with_companion: false, companion_name: '', companion_contact: '',
    })
    showModal.value = true
}

function submit() {
    submitting.value = true
    router.post(route('student-portal.rh-leave-passes.store'), form.value, {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false },
        onFinish:  () => { submitting.value = false },
    })
}

const purposeLabel = {
    go_home:          'Go Home',
    school_activity:  'School Activity',
    other:            'Other',
}

const statusMeta = (s) => ({
    pending:   { label: 'Pending',   cls: 'bg-amber-100 text-amber-700' },
    approved:  { label: 'Approved',  cls: 'bg-emerald-100 text-emerald-700' },
    rejected:  { label: 'Rejected',  cls: 'bg-rose-100 text-rose-700' },
    departed:  { label: 'Departed',  cls: 'bg-sky-100 text-sky-700' },
    returned:  { label: 'Returned',  cls: 'bg-slate-100 text-slate-600' },
    overdue:   { label: 'Overdue',   cls: 'bg-orange-100 text-orange-700' },
}[s] || { label: s, cls: 'bg-slate-100 text-slate-500' })

const fmtDt = (d) => d
    ? new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : '—'

const canSubmit = computed(() =>
    form.value.purpose && form.value.destination && form.value.expected_return_at &&
    (!form.value.with_companion || (form.value.companion_name && form.value.companion_contact))
)
</script>

<template>
    <Head title="Leave Passes" />
    <StudentPortalLayout>
        <div class="max-w-2xl mx-auto space-y-5">

            <!-- Back -->
            <a href="/student-portal/dashboard"
               class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600 transition-colors">
                <ArrowLeftIcon class="w-4 h-4" /> Back to Dashboard
            </a>

            <!-- Title -->
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center shrink-0">
                        <HomeModernIcon class="w-5 h-5 text-indigo-600" />
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold text-slate-800">Leave Passes</h1>
                        <p class="text-sm text-slate-500">S.Y. {{ schoolYear }}</p>
                    </div>
                </div>
                <button v-if="intern" @click="openModal"
                        class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    <PlusIcon class="w-4 h-4" />
                    File Leave Pass
                </button>
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

            <!-- Not a dormer -->
            <div v-if="!intern" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 text-center">
                <HomeModernIcon class="w-10 h-10 text-slate-300 mx-auto mb-3" />
                <p class="font-medium text-slate-700">You are not currently an active dormer.</p>
                <p class="text-sm text-slate-400 mt-1">Leave passes are only available to enrolled dormers for the current school year.</p>
                <a href="/student-portal/rh-application"
                   class="mt-4 inline-block text-sm text-indigo-600 hover:underline font-medium">
                    View Residence Hall Application →
                </a>
            </div>

            <!-- Leave pass history -->
            <template v-else>
                <div v-if="!leavePasses.length" class="bg-white rounded-xl border border-slate-100 shadow-sm p-10 text-center">
                    <ClockIcon class="w-10 h-10 text-slate-300 mx-auto mb-3" />
                    <p class="text-slate-500 font-medium">No leave passes yet.</p>
                    <p class="text-sm text-slate-400 mt-1">File your first leave pass using the button above.</p>
                </div>

                <div v-else class="space-y-3">
                    <div v-for="lp in leavePasses" :key="lp.id"
                         class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
                        <div class="flex items-start justify-between gap-3 flex-wrap">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">{{ purposeLabel[lp.purpose] || lp.purpose }}</p>
                                <p class="text-xs text-slate-500 mt-0.5">{{ lp.destination }}</p>
                            </div>
                            <span :class="['text-xs px-2 py-0.5 rounded-full font-semibold', statusMeta(lp.status).cls]">
                                {{ statusMeta(lp.status).label }}
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-x-6 gap-y-1 text-xs text-slate-500">
                            <div><span class="text-slate-400">Filed</span> · {{ fmtDt(lp.created_at) }}</div>
                            <div><span class="text-slate-400">Expected return</span> · {{ fmtDt(lp.expected_return_at) }}</div>
                            <div v-if="lp.approved_at"><span class="text-slate-400">Approved</span> · {{ fmtDt(lp.approved_at) }}</div>
                        </div>
                        <p v-if="lp.remarks" class="mt-2 text-xs text-slate-500 bg-slate-50 rounded-lg px-3 py-2">
                            {{ lp.remarks }}
                        </p>
                    </div>
                </div>
            </template>

        </div>

        <!-- File Leave Pass Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 overflow-y-auto py-8">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-800">File Leave Pass</h3>
                    <button @click="showModal = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500">
                        <XMarkIcon class="w-4 h-4" />
                    </button>
                </div>

                <!-- Purpose -->
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Purpose <span class="text-rose-500">*</span></label>
                    <select v-model="form.purpose"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="go_home">Go Home</option>
                        <option value="school_activity">School Activity</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <!-- Destination -->
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Destination <span class="text-rose-500">*</span></label>
                    <input v-model="form.destination" type="text" placeholder="e.g. Tagum City, Davao del Norte"
                           class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>

                <!-- Expected return -->
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Expected Return <span class="text-rose-500">*</span></label>
                    <input v-model="form.expected_return_at" type="datetime-local"
                           class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>

                <!-- With companion -->
                <label class="flex items-center gap-2 cursor-pointer">
                    <input v-model="form.with_companion" type="checkbox"
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                    <span class="text-sm text-slate-700">I will be with a companion</span>
                </label>

                <!-- Companion fields -->
                <template v-if="form.with_companion">
                    <div class="border border-slate-100 rounded-xl p-4 space-y-3 bg-slate-50">
                        <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Companion Details</p>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Full Name <span class="text-rose-500">*</span></label>
                            <input v-model="form.companion_name" type="text" placeholder="Last Name, First Name"
                                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Contact Number <span class="text-rose-500">*</span></label>
                            <input v-model="form.companion_contact" type="text" placeholder="09XXXXXXXXX"
                                   class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                </template>

                <p class="text-xs text-slate-400">Submitting this form serves as your digital signature. Contact your Dorm Manager if you need to cancel.</p>

                <div class="flex gap-3 pt-1">
                    <button @click="showModal = false"
                            class="flex-1 px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm hover:bg-slate-50">
                        Cancel
                    </button>
                    <button @click="submit" :disabled="submitting || !canSubmit"
                            class="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        {{ submitting ? 'Submitting…' : 'Submit Leave Pass' }}
                    </button>
                </div>
            </div>
        </div>

    </StudentPortalLayout>
</template>
