<script setup>
import { Head } from '@inertiajs/vue3'
import { CheckBadgeIcon, XCircleIcon, ShieldCheckIcon, ExclamationTriangleIcon, UserIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    valid:       Boolean,
    employee:    { type: Object, default: null },
    photoUri:    { type: String, default: null },
    verified_at: { type: String, default: null },
})

function formatDate(iso) {
    if (!iso) return ''
    return new Date(iso).toLocaleDateString('en-PH', {
        year: 'numeric', month: 'long', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    })
}
</script>

<template>
    <Head title="Employee ID Verification — Atlas" />

    <div class="min-h-screen bg-slate-50 flex flex-col items-center justify-center px-4 py-12">

        <!-- Header -->
        <div class="flex items-center gap-3 mb-8">
            <ShieldCheckIcon class="w-8 h-8 text-indigo-600" />
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Philippine Science High School — Caraga Region Campus</p>
                <p class="text-lg font-bold text-slate-800 leading-tight">Employee ID Verification</p>
            </div>
        </div>

        <!-- Valid -->
        <div v-if="valid && employee" class="bg-white rounded-2xl border border-slate-200 shadow-sm w-full max-w-lg overflow-hidden">
            <!-- Status banner -->
            <div v-if="employee.is_active" class="bg-success-600 px-6 py-5 flex items-center gap-3">
                <CheckBadgeIcon class="w-8 h-8 text-white shrink-0" />
                <div>
                    <p class="text-white font-bold text-lg leading-tight">Verified — Active Employee</p>
                    <p class="text-success-100 text-sm">This ID belongs to a current PSHS-CRC employee.</p>
                </div>
            </div>
            <div v-else class="bg-warning-500 px-6 py-5 flex items-center gap-3">
                <ExclamationTriangleIcon class="w-8 h-8 text-white shrink-0" />
                <div>
                    <p class="text-white font-bold text-lg leading-tight">No Longer Active</p>
                    <p class="text-warning-50 text-sm">This ID is genuine but the holder is no longer an active PSHS-CRC employee.</p>
                </div>
            </div>

            <!-- Details -->
            <div class="px-6 py-5">
                <div class="flex items-start gap-4">
                    <div class="w-24 h-24 rounded-xl border border-slate-200 bg-slate-50 overflow-hidden flex items-center justify-center shrink-0">
                        <img v-if="photoUri" :src="photoUri" alt="Photo" class="w-full h-full object-cover" />
                        <UserIcon v-else class="w-10 h-10 text-slate-300" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-slate-800 font-bold text-lg leading-tight">{{ employee.name }}</p>
                        <p class="text-slate-600 text-sm mt-0.5">{{ employee.position || '—' }}</p>
                        <p v-if="employee.office || employee.division" class="text-slate-500 text-sm">
                            {{ employee.office || employee.division }}
                        </p>
                        <p v-if="employee.employee_no" class="text-slate-400 text-xs mt-1.5">
                            Employee No. <span class="font-semibold text-slate-600">{{ employee.employee_no }}</span>
                        </p>
                    </div>
                </div>

                <div class="border-t border-slate-100 mt-5 pt-4 flex items-center justify-between text-xs text-slate-400">
                    <span>Verified {{ formatDate(verified_at) }}</span>
                    <span class="inline-flex items-center gap-1">
                        <ShieldCheckIcon class="w-3.5 h-3.5" /> Live status from PSHS-CRC MIS
                    </span>
                </div>
            </div>
        </div>

        <!-- Invalid -->
        <div v-else class="bg-white rounded-2xl border border-slate-200 shadow-sm w-full max-w-lg overflow-hidden">
            <div class="bg-danger-600 px-6 py-5 flex items-center gap-3">
                <XCircleIcon class="w-8 h-8 text-white shrink-0" />
                <div>
                    <p class="text-white font-bold text-lg leading-tight">Not Verified</p>
                    <p class="text-danger-100 text-sm">This code does not match any PSHS-CRC employee ID.</p>
                </div>
            </div>
            <div class="px-6 py-5 text-sm text-slate-600 leading-relaxed">
                The QR code you scanned is not recognized. The card may be counterfeit, damaged,
                or its verification code may have been revoked. If you believe this is an error,
                contact the PSHS-CRC Records Office.
            </div>
        </div>

        <p class="text-xs text-slate-400 mt-6 max-w-lg text-center">
            This page confirms employee identity in real time. It displays only the information
            printed on the ID card and the holder's current employment status.
        </p>
    </div>
</template>
