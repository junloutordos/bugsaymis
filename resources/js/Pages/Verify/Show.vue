<script setup>
import { Head } from '@inertiajs/vue3'
import { CheckBadgeIcon, XCircleIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    valid:               Boolean,
    record:              { type: Object, default: null },
    signerSignatureUri:  { type: String, default: null },
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
    <Head title="Document Verification — PSHS-CRC MIS" />

    <div class="min-h-screen bg-slate-50 flex flex-col items-center justify-center px-4 py-12">

        <!-- Header -->
        <div class="flex items-center gap-3 mb-8">
            <ShieldCheckIcon class="w-8 h-8 text-indigo-600" />
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Philippine Science High School</p>
                <p class="text-lg font-bold text-slate-800 leading-tight">CRCMIS Document Verification</p>
            </div>
        </div>

        <!-- Valid -->
        <div v-if="props.valid && props.record" class="bg-white rounded-2xl border border-slate-200 shadow-sm w-full max-w-lg overflow-hidden">
            <!-- Green banner -->
            <div class="bg-emerald-600 px-6 py-5 flex items-center gap-3">
                <CheckBadgeIcon class="w-8 h-8 text-white shrink-0" />
                <div>
                    <p class="text-white font-bold text-lg leading-tight">Document Verified</p>
                    <p class="text-emerald-100 text-sm">This document is authentic and has not been altered.</p>
                </div>
            </div>

            <!-- Details -->
            <div class="px-6 py-5 space-y-4">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Document</p>
                    <p class="text-slate-800 font-semibold">{{ props.record.document_title }}</p>
                </div>

                <div class="border-t border-slate-100 pt-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Signed By</p>
                    <div class="flex items-center gap-4">
                        <div v-if="props.signerSignatureUri"
                             class="border border-slate-200 rounded-lg bg-slate-50 px-4 py-2 flex items-center justify-center min-h-[56px]">
                            <img :src="props.signerSignatureUri" alt="Signature" class="max-h-12 max-w-[160px] object-contain" />
                        </div>
                        <div>
                            <p class="text-slate-800 font-semibold">{{ props.record.signer.name }}</p>
                            <p class="text-slate-500 text-sm">{{ props.record.signer.position }}</p>
                            <p class="text-slate-400 text-xs">Badge No. {{ props.record.signer.badge_id }}</p>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Date Signed</p>
                    <p class="text-slate-700">{{ formatDate(props.record.signed_at) }}</p>
                </div>

                <!-- Metadata -->
                <div v-if="props.record.metadata && Object.keys(props.record.metadata).length"
                     class="border-t border-slate-100 pt-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Document Details</p>
                    <dl class="space-y-1">
                        <div v-for="(val, key) in props.record.metadata" :key="key" class="flex gap-2 text-sm">
                            <dt class="text-slate-500 capitalize shrink-0">{{ String(key).replace(/_/g,' ') }}:</dt>
                            <dd class="text-slate-700">{{ val }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="border-t border-slate-100 pt-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Verification Token</p>
                    <p class="text-slate-400 text-xs font-mono break-all">{{ props.record.verification_token }}</p>
                </div>
            </div>
        </div>

        <!-- Invalid -->
        <div v-else class="bg-white rounded-2xl border border-slate-200 shadow-sm w-full max-w-lg overflow-hidden">
            <div class="bg-red-600 px-6 py-5 flex items-center gap-3">
                <XCircleIcon class="w-8 h-8 text-white shrink-0" />
                <div>
                    <p class="text-white font-bold text-lg leading-tight">Invalid or Unknown Document</p>
                    <p class="text-red-100 text-sm">This document could not be verified. It may have been altered or the link is invalid.</p>
                </div>
            </div>
            <div class="px-6 py-5">
                <p class="text-slate-600 text-sm">
                    If you believe this is an error, please contact the PSHS-CRC MIS administrator.
                </p>
            </div>
        </div>

        <p class="mt-6 text-xs text-slate-400">PSHS-CRC MIS &mdash; Document Integrity Verification System</p>
    </div>
</template>
