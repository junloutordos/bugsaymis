<script setup>
import { ref, watch } from 'vue'
import { FingerPrintIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    show:          { type: Boolean, default: false },
    hasPin:        { type: Boolean, default: false },
    signatureUri:  { type: String,  default: null },
    loading:       { type: Boolean, default: false },
    confirmLabel:  { type: String,  default: 'Confirm' },
})

const emit = defineEmits(['confirm', 'cancel'])

const pin   = ref('')
const error = ref('')

watch(() => props.show, (val) => {
    if (!val) { pin.value = ''; error.value = '' }
})

function confirm() {
    error.value = ''
    if (props.hasPin && !/^\d{6}$/.test(pin.value)) {
        error.value = 'Enter your 6-digit signature PIN.'
        return
    }
    emit('confirm', props.hasPin ? pin.value : null)
}

function cancel() {
    pin.value = ''; error.value = ''
    emit('cancel')
}
</script>

<template>
    <Teleport to="body">
        <div v-if="props.show"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4">

                <!-- Header -->
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center shrink-0">
                        <FingerPrintIcon class="w-5 h-5 text-indigo-600" />
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800 text-sm">Digital Signature</p>
                        <p class="text-xs text-slate-500">
                            {{ props.hasPin ? 'Enter your PIN to sign this document.' : 'Your signature image will be applied.' }}
                        </p>
                    </div>
                </div>

                <!-- Signature preview -->
                <div v-if="props.signatureUri"
                     class="bg-slate-50 border border-slate-200 rounded-lg py-3 flex items-center justify-center min-h-[60px]">
                    <img :src="props.signatureUri" alt="Your signature"
                         class="max-h-14 max-w-[200px] object-contain" />
                </div>
                <div v-else class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-xs text-amber-800">
                    No signature image set. Go to <strong>Digital Signature</strong> in your profile to set one up.
                </div>

                <!-- PIN input (only if user has PIN) -->
                <div v-if="props.hasPin">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Signature PIN</label>
                    <input v-model="pin"
                           type="password"
                           maxlength="6"
                           inputmode="numeric"
                           pattern="\d*"
                           placeholder="••••••"
                           @keyup.enter="confirm"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm tracking-[0.4em] focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
                </div>

                <!-- No PIN notice -->
                <div v-else class="flex items-start gap-2 text-xs text-slate-500 bg-slate-50 rounded-lg px-3 py-2">
                    <ShieldCheckIcon class="w-4 h-4 text-slate-400 shrink-0 mt-0.5" />
                    <span>You have not set a signature PIN. Your signature image will still be embedded in the document. Set up your PIN in <strong>Profile → Digital Signature</strong> to enable cryptographic signing.</span>
                </div>

                <!-- Actions -->
                <div class="flex gap-2 justify-end pt-1">
                    <button @click="cancel" :disabled="props.loading"
                            class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800 disabled:opacity-50">
                        Cancel
                    </button>
                    <button @click="confirm" :disabled="props.loading"
                            class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                        {{ props.loading ? 'Processing…' : props.confirmLabel }}
                    </button>
                </div>

            </div>
        </div>
    </Teleport>
</template>
