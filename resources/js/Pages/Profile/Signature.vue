<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTabs from '@/Components/AppTabs.vue'
import {
    PencilSquareIcon,
    ArrowUpTrayIcon,
    KeyIcon,
    CheckCircleIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
    hasSignature: Boolean,
    hasPin: Boolean,
    signatureUri: { type: String, default: null },
})

// ── Tab ──────────────────────────────────────────────────────────────────────
const activeTab = ref('draw')
const tabs = [
    { key: 'draw',   label: 'Draw',   icon: PencilSquareIcon },
    { key: 'upload', label: 'Upload', icon: ArrowUpTrayIcon },
]

function selectTab(key) {
    activeTab.value = key
    if (key === 'draw') clearCanvas()
}

// ── Canvas drawing ────────────────────────────────────────────────────────────
const canvasRef   = ref(null)
const isDrawing   = ref(false)
const hasDraw     = ref(false)
let   ctx         = null
let   lastX       = 0
let   lastY       = 0

function initCanvas() {
    if (!canvasRef.value) return
    ctx = canvasRef.value.getContext('2d')
    ctx.strokeStyle = '#1e293b'
    ctx.lineWidth   = 2
    ctx.lineCap     = 'round'
    ctx.lineJoin    = 'round'
    clearCanvas()
}

function clearCanvas() {
    if (!ctx || !canvasRef.value) return
    ctx.clearRect(0, 0, canvasRef.value.width, canvasRef.value.height)
    hasDraw.value = false
}

function getPos(e) {
    const rect = canvasRef.value.getBoundingClientRect()
    const src  = e.touches ? e.touches[0] : e
    return {
        x: (src.clientX - rect.left) * (canvasRef.value.width / rect.width),
        y: (src.clientY - rect.top)  * (canvasRef.value.height / rect.height),
    }
}

function startDraw(e) {
    e.preventDefault()
    isDrawing.value = true
    const { x, y } = getPos(e)
    lastX = x; lastY = y
    ctx.beginPath()
    ctx.moveTo(x, y)
}

function draw(e) {
    if (!isDrawing.value) return
    e.preventDefault()
    const { x, y } = getPos(e)
    ctx.lineTo(x, y)
    ctx.stroke()
    lastX = x; lastY = y
    hasDraw.value = true
}

function stopDraw() {
    isDrawing.value = false
}

onMounted(() => {
    initCanvas()
})

// ── File upload tab ───────────────────────────────────────────────────────────
const uploadPreview = ref(null)
const fileInputRef  = ref(null)

function onFileChange(e) {
    const file = e.target.files[0]
    if (!file) return
    const reader = new FileReader()
    reader.onload = ev => { uploadPreview.value = ev.target.result }
    reader.readAsDataURL(file)
}

// ── Save signature ────────────────────────────────────────────────────────────
const saving        = ref(false)
const saveError     = ref('')
const saveSuccess   = ref('')

async function saveSignature() {
    saveError.value = ''
    saveSuccess.value = ''

    let base64 = null

    if (activeTab.value === 'draw') {
        if (!hasDraw.value) { saveError.value = 'Please draw your signature first.'; return }
        base64 = canvasRef.value.toDataURL('image/png')
    } else {
        if (!uploadPreview.value) { saveError.value = 'Please select an image file.'; return }
        base64 = uploadPreview.value
    }

    saving.value = true
    try {
        await axios.post(route('profile.signature.save'), { signature_base64: base64 })
        saveSuccess.value = 'Signature saved successfully.'
        router.reload({ only: ['hasSignature', 'signatureUri'] })
    } catch (err) {
        saveError.value = err.response?.data?.message ?? 'Failed to save signature.'
    } finally {
        saving.value = false
    }
}

// ── PIN setup ─────────────────────────────────────────────────────────────────
const pin             = ref('')
const pinConfirmation = ref('')
const pinError        = ref('')
const pinSuccess      = ref('')
const savingPin       = ref(false)
const showPin         = ref(false)

async function savePin() {
    pinError.value   = ''
    pinSuccess.value = ''

    if (!/^\d{6}$/.test(pin.value))              { pinError.value = 'PIN must be exactly 6 digits.'; return }
    if (pin.value !== pinConfirmation.value)      { pinError.value = 'PINs do not match.'; return }

    savingPin.value = true
    try {
        await axios.post(route('profile.signature.pin'), {
            pin:              pin.value,
            pin_confirmation: pinConfirmation.value,
        })
        pinSuccess.value = 'Signature PIN set successfully.'
        pin.value = ''
        pinConfirmation.value = ''
        router.reload({ only: ['hasPin'] })
    } catch (err) {
        pinError.value = err.response?.data?.errors?.pin?.[0] ?? 'Failed to set PIN.'
    } finally {
        savingPin.value = false
    }
}
</script>

<template>
    <Head title="Digital Signature" />
    <AdminLayout title="Digital Signature">
        <div class="max-w-2xl mx-auto space-y-5">

            <!-- Status Banner -->
            <div class="flex gap-4">
                <div class="flex-1 rounded-xl border px-4 py-3 flex items-center gap-3"
                     :class="props.hasSignature ? 'border-success-100 bg-success-50' : 'border-warning-100 bg-warning-50'">
                    <CheckCircleIcon v-if="props.hasSignature" class="w-5 h-5 text-success-600 shrink-0" />
                    <span v-if="props.hasSignature" class="text-sm text-success-700 font-medium">Signature image saved</span>
                    <span v-else class="text-sm text-warning-700 font-medium">No signature image yet</span>
                </div>
                <div class="flex-1 rounded-xl border px-4 py-3 flex items-center gap-3"
                     :class="props.hasPin ? 'border-success-100 bg-success-50' : 'border-warning-100 bg-warning-50'">
                    <CheckCircleIcon v-if="props.hasPin" class="w-5 h-5 text-success-600 shrink-0" />
                    <span v-if="props.hasPin" class="text-sm text-success-700 font-medium">Signature PIN set</span>
                    <span v-else class="text-sm text-warning-700 font-medium">No PIN set yet</span>
                </div>
            </div>

            <!-- Current Signature Preview -->
            <AppCard v-if="props.signatureUri">
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">Current Signature</p>
                <div class="bg-slate-50 rounded-lg border border-slate-200 p-4 flex items-center justify-center min-h-[80px]">
                    <img :src="props.signatureUri" alt="Your signature" class="max-h-20 max-w-xs object-contain" />
                </div>
                <div v-if="!props.hasPin" class="mt-3 rounded-lg bg-indigo-50 border border-indigo-200 px-4 py-3 text-sm text-indigo-800">
                    Your existing signature is loaded. <strong>Just set your 6-digit PIN below</strong> to activate digital signing — no need to re-upload your signature.
                </div>
                <div v-else class="mt-3 rounded-lg bg-success-50 border border-success-100 px-4 py-3 text-sm text-success-700">
                    Your signature is active. You can update the image anytime using the form below.
                </div>
            </AppCard>

            <!-- Signature Setup Card -->
            <AppCard>
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-4">
                    {{ props.hasSignature ? 'Update Signature' : 'Set Up Signature' }}
                </p>

                <!-- Tabs -->
                <AppTabs :tabs="tabs" :model-value="activeTab" @update:modelValue="selectTab" />

                <div class="pt-4">
                    <!-- Draw Tab -->
                    <div v-if="activeTab === 'draw'">
                        <p class="text-xs text-slate-500 mb-2">Draw your signature in the box below using your mouse or finger.</p>
                        <div class="border-2 border-dashed border-slate-300 rounded-lg bg-slate-50 relative">
                            <canvas
                                ref="canvasRef"
                                width="560"
                                height="160"
                                class="w-full touch-none cursor-crosshair rounded-lg"
                                @mousedown="startDraw"
                                @mousemove="draw"
                                @mouseup="stopDraw"
                                @mouseleave="stopDraw"
                                @touchstart="startDraw"
                                @touchmove="draw"
                                @touchend="stopDraw"
                            />
                            <button v-if="hasDraw"
                                    @click="clearCanvas"
                                    class="absolute top-2 right-2 text-slate-400 hover:text-danger-500 transition-colors"
                                    title="Clear">
                                <TrashIcon class="w-4 h-4" />
                            </button>
                        </div>
                        <p v-if="!hasDraw" class="text-xs text-slate-400 mt-1 text-center">Sign above</p>
                    </div>

                    <!-- Upload Tab -->
                    <div v-if="activeTab === 'upload'">
                        <p class="text-xs text-slate-500 mb-2">Upload a PNG image of your signature (transparent background recommended).</p>
                        <input ref="fileInputRef" type="file" accept="image/png,image/jpeg"
                               class="block w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                               @change="onFileChange" />
                        <div v-if="uploadPreview" class="mt-3 border border-slate-200 rounded-lg bg-slate-50 p-4 flex items-center justify-center min-h-[80px]">
                            <img :src="uploadPreview" alt="Preview" class="max-h-20 max-w-xs object-contain" />
                        </div>
                    </div>
                </div>

                <!-- Feedback -->
                <p v-if="saveError"   class="mt-3 text-sm text-danger-600">{{ saveError }}</p>
                <p v-if="saveSuccess" class="mt-3 text-sm text-success-600">{{ saveSuccess }}</p>

                <div class="mt-4 flex justify-end">
                    <AppButton @click="saveSignature" :loading="saving" :disabled="saving">
                        {{ saving ? 'Saving…' : 'Save Signature' }}
                    </AppButton>
                </div>
            </AppCard>

            <!-- PIN Setup Card -->
            <AppCard>
                <div class="flex items-center gap-2 mb-4">
                    <KeyIcon class="w-4 h-4 text-slate-500" />
                    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                        {{ props.hasPin ? 'Change Signature PIN' : 'Set Signature PIN' }}
                    </p>
                </div>
                <p class="text-sm text-slate-600 mb-4">
                    Your 6-digit PIN is required each time you digitally sign a document. It is separate from your login password.
                </p>
                <div class="grid grid-cols-2 gap-4">
                    <AppInput v-model="pin"
                           :type="showPin ? 'text' : 'password'"
                           maxlength="6"
                           inputmode="numeric"
                           pattern="\d*"
                           placeholder="6 digits"
                           label="New PIN"
                           class="tracking-widest" />
                    <AppInput v-model="pinConfirmation"
                           :type="showPin ? 'text' : 'password'"
                           maxlength="6"
                           inputmode="numeric"
                           pattern="\d*"
                           placeholder="6 digits"
                           label="Confirm PIN"
                           class="tracking-widest" />
                </div>
                <label class="flex items-center gap-2 mt-2 cursor-pointer select-none">
                    <input type="checkbox" v-model="showPin" class="rounded" />
                    <span class="text-xs text-slate-500">Show PIN</span>
                </label>

                <p v-if="pinError"   class="mt-2 text-sm text-danger-600">{{ pinError }}</p>
                <p v-if="pinSuccess" class="mt-2 text-sm text-success-600">{{ pinSuccess }}</p>

                <div class="mt-4 flex justify-end">
                    <AppButton @click="savePin" :loading="savingPin" :disabled="savingPin">
                        {{ savingPin ? 'Saving…' : (props.hasPin ? 'Update PIN' : 'Set PIN') }}
                    </AppButton>
                </div>
            </AppCard>

        </div>
    </AdminLayout>
</template>
