<template>
  <div v-if="open" class="fixed inset-0 z-[9999] flex items-start justify-center bg-black/40 backdrop-blur-sm p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 space-y-4 my-8">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <div class="h-8 w-8 rounded-full bg-red-50 flex items-center justify-center">
            <BugAntIcon class="h-4 w-4 text-red-500" />
          </div>
          <div>
            <h2 class="text-base font-semibold text-slate-800">Report an Error</h2>
            <p class="text-xs text-slate-500">The MIS team will be notified immediately</p>
          </div>
        </div>
        <button @click="close" class="p-1.5 text-slate-400 hover:text-slate-600 rounded">
          <XMarkIcon class="h-5 w-5" />
        </button>
      </div>

      <!-- Success state -->
      <div v-if="submitted" class="py-8 text-center space-y-3">
        <CheckCircleIcon class="mx-auto h-12 w-12 text-emerald-400" />
        <p class="text-sm font-semibold text-slate-800">Report submitted!</p>
        <p class="text-xs text-slate-500">Reference: <span class="font-mono font-semibold text-indigo-600">{{ submittedNo }}</span></p>
        <p class="text-xs text-slate-400">The MIS team will look into it and notify you when resolved.</p>
        <button @click="close" class="mt-2 px-5 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium">Close</button>
      </div>

      <!-- Form -->
      <template v-else>
        <div class="space-y-3">
          <!-- Title -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Brief title <span class="text-red-500">*</span></label>
            <input v-model="form.title" type="text" maxlength="255" placeholder="e.g. Cannot submit leave application"
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-400 focus:border-transparent focus:outline-none" />
            <p v-if="errors.title" class="text-xs text-red-500 mt-1">{{ errors.title }}</p>
          </div>

          <!-- Description -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">What happened? <span class="text-red-500">*</span></label>
            <textarea v-model="form.description" rows="4" maxlength="5000"
              placeholder="Describe what you were doing and what went wrong. Include any error messages you saw."
              class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-400 focus:border-transparent focus:outline-none resize-none"></textarea>
            <p v-if="errors.description" class="text-xs text-red-500 mt-1">{{ errors.description }}</p>
          </div>

          <!-- Screenshot -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Screenshot <span class="text-slate-400">(optional)</span></label>
            <div v-if="!form.screenshot_b64"
              class="border-2 border-dashed border-slate-200 rounded-lg p-4 text-center cursor-pointer hover:border-red-300 hover:bg-red-50/30 transition-colors"
              @click="$refs.fileInput.click()"
              @dragover.prevent
              @drop.prevent="handleDrop">
              <PhotoIcon class="mx-auto h-7 w-7 text-slate-300 mb-1.5" />
              <p class="text-xs text-slate-400">Click or drag a screenshot here</p>
              <p class="text-[11px] text-slate-300 mt-0.5">PNG, JPG, WEBP — max 5 MB</p>
              <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="handleFile" />
            </div>
            <div v-else class="relative">
              <img :src="form.screenshot_b64" alt="Screenshot preview"
                class="w-full rounded-lg border border-slate-200 max-h-48 object-contain bg-slate-50" />
              <button @click="form.screenshot_b64 = null"
                class="absolute top-1.5 right-1.5 p-1 bg-white/80 hover:bg-white rounded-full shadow text-slate-500 hover:text-red-500">
                <XMarkIcon class="h-4 w-4" />
              </button>
            </div>
            <p v-if="errors.screenshot_b64" class="text-xs text-red-500 mt-1">{{ errors.screenshot_b64 }}</p>
          </div>

          <!-- Auto-captured context (visible to user for transparency) -->
          <div class="rounded-lg bg-slate-50 border border-slate-100 px-3 py-2 text-xs text-slate-400 space-y-0.5">
            <p class="font-medium text-slate-500 mb-1">Auto-captured context</p>
            <p>Page: <span class="text-slate-600 truncate inline-block max-w-xs align-bottom">{{ form.page_url }}</span></p>
            <p>Browser: {{ browserLabel }}</p>
          </div>
        </div>

        <!-- Error banner -->
        <div v-if="submitError" class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-600">
          {{ submitError }}
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-2 pt-1">
          <button @click="close" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="submit" :disabled="loading"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded-lg font-medium transition-colors">
            <ArrowPathIcon v-if="loading" class="h-4 w-4 animate-spin" />
            <BugAntIcon v-else class="h-4 w-4" />
            {{ loading ? 'Submitting…' : 'Submit Report' }}
          </button>
        </div>
      </template>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import axios from 'axios'
import { BugAntIcon, XMarkIcon, CheckCircleIcon, PhotoIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ open: Boolean })
const emit  = defineEmits(['close'])

const loading     = ref(false)
const submitted   = ref(false)
const submittedNo = ref('')
const submitError = ref(null)
const errors      = ref({})

const form = ref({
  title:          '',
  description:    '',
  page_url:       '',
  browser_info:   {},
  screenshot_b64: null,
})

const browserLabel = computed(() => {
  const bi = form.value.browser_info
  if (!bi) return '—'
  return [bi.browser, bi.os].filter(Boolean).join(' on ') || '—'
})

watch(() => props.open, (val) => {
  if (val) initContext()
})

function initContext() {
  submitted.value   = false
  submittedNo.value = ''
  submitError.value = null
  errors.value      = {}
  form.value = {
    title:          '',
    description:    '',
    page_url:       window.location.href,
    browser_info:   detectBrowser(),
    screenshot_b64: null,
  }
}

function detectBrowser() {
  const ua = navigator.userAgent
  let browser = 'Unknown'
  let os = 'Unknown'

  if (ua.includes('Chrome') && !ua.includes('Edg'))      browser = 'Chrome'
  else if (ua.includes('Firefox'))                        browser = 'Firefox'
  else if (ua.includes('Safari') && !ua.includes('Chrome')) browser = 'Safari'
  else if (ua.includes('Edg'))                            browser = 'Edge'

  if (ua.includes('Windows'))      os = 'Windows'
  else if (ua.includes('Mac'))     os = 'macOS'
  else if (ua.includes('Linux'))   os = 'Linux'
  else if (ua.includes('Android')) os = 'Android'
  else if (ua.includes('iPhone') || ua.includes('iPad')) os = 'iOS'

  return {
    browser,
    os,
    viewport: `${window.innerWidth}×${window.innerHeight}`,
    user_agent: ua.slice(0, 200),
  }
}

function handleFile(e) {
  const file = e.target.files[0]
  if (file) readFile(file)
}

function handleDrop(e) {
  const file = e.dataTransfer.files[0]
  if (file && file.type.startsWith('image/')) readFile(file)
}

function readFile(file) {
  if (file.size > 5 * 1024 * 1024) {
    errors.value.screenshot_b64 = 'Screenshot must be under 5 MB.'
    return
  }
  errors.value.screenshot_b64 = null
  const reader = new FileReader()
  reader.onload = (e) => { form.value.screenshot_b64 = e.target.result }
  reader.readAsDataURL(file)
}

async function submit() {
  errors.value = {}
  submitError.value = null

  if (!form.value.title.trim())       { errors.value.title = 'Title is required.'; return }
  if (!form.value.description.trim()) { errors.value.description = 'Description is required.'; return }

  loading.value = true
  try {
    const { data } = await axios.post(route('error-reports.store'), {
      title:          form.value.title,
      description:    form.value.description,
      page_url:       form.value.page_url,
      browser_info:   form.value.browser_info,
      screenshot_b64: form.value.screenshot_b64,
    })
    // Extract report_no from flash message or response
    const match = (data?.message ?? '').match(/ERR-\d{4}-\d{4}/)
    submittedNo.value = match ? match[0] : ''
    submitted.value   = true
  } catch (err) {
    if (err.response?.status === 422) {
      errors.value = err.response.data.errors ?? {}
    } else {
      submitError.value = 'Something went wrong. Please try again or contact the MIS team directly.'
    }
  } finally {
    loading.value = false
  }
}

function close() {
  emit('close')
  setTimeout(() => { submitted.value = false }, 300)
}
</script>
