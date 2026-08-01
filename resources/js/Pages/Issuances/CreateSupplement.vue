<script setup>
import { ref, computed } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppInput from '@/Components/AppInput.vue'
import AppButton from '@/Components/AppButton.vue'
import RichTextEditor from '@/Components/RichTextEditor.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'
import { ChevronLeftIcon, DocumentTextIcon, PaperClipIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  parentIssuance: Object,
  kindLabels: Object,
  hasPin: Boolean,
  signatureUri: String,
})

const kind = ref('addendum')
const title = ref('')
const contentMode = ref('editor')
const content = ref('')
const scanBase64 = ref(null)
const scanFilename = ref('')
const scanMime = ref('')
const errors = ref({})
const processing = ref(false)
const showPinModal = ref(false)

const kindHelp = {
  addendum: 'Adds provisions or information without replacing the original issuance.',
  erratum: 'Corrects a minor typographical or clerical error.',
  corrigendum: 'Corrects substantive wording, names, dates, figures, or instructions.',
  appendix: 'Adds a supporting schedule, list, table, or reference document.',
}

const canSubmit = computed(() => {
  if (!title.value.trim()) return false
  return contentMode.value === 'editor'
    ? content.value.replace(/<[^>]*>/g, '').trim().length > 10
    : !!scanBase64.value
})

function handleScan(event) {
  const file = event.target.files[0]
  if (!file) return
  if (file.size > 20 * 1024 * 1024) {
    errors.value.scan_base64 = 'The document may not exceed 20 MB.'
    event.target.value = ''
    return
  }
  scanFilename.value = file.name
  scanMime.value = file.type
  const reader = new FileReader()
  reader.onload = e => { scanBase64.value = e.target.result }
  reader.readAsDataURL(file)
  event.target.value = ''
}

function payload(pin = null, shouldRelease = false) {
  return {
    document_kind: kind.value,
    title: title.value,
    content: contentMode.value === 'editor' ? content.value : null,
    scan_base64: contentMode.value === 'upload' ? scanBase64.value : null,
    scan_filename: contentMode.value === 'upload' ? scanFilename.value : null,
    scan_mime: contentMode.value === 'upload' ? scanMime.value : null,
    should_release: shouldRelease,
    pin,
  }
}

function submit(data) {
  processing.value = true
  errors.value = {}
  router.post(route('issuances.supplements.store', props.parentIssuance.id), data, {
    onError: e => { errors.value = e },
    onFinish: () => { processing.value = false },
  })
}

function saveDraft() {
  if (canSubmit.value) submit(payload())
}

function release() {
  if (canSubmit.value) showPinModal.value = true
}

function onPinConfirm(pin) {
  showPinModal.value = false
  submit(payload(pin, true))
}
</script>

<template>
  <Head title="Add Related Document" />
  <AdminLayout title="Add Related Document">
    <div class="max-w-3xl space-y-5">
      <Link :href="route('issuances.show', parentIssuance.id)" class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800">
        <ChevronLeftIcon class="h-4 w-4" /> Back to {{ parentIssuance.control_number }}
      </Link>

      <AppCard title="Original Issuance">
        <p class="font-mono text-xs font-bold text-indigo-700">{{ parentIssuance.control_number }}</p>
        <p class="mt-1 text-sm font-medium text-slate-800">{{ parentIssuance.title }}</p>
        <p class="mt-2 text-xs text-slate-500">The related document will be sent to the original {{ parentIssuance.recipients_count }} recipient(s).</p>
      </AppCard>

      <AppCard title="Related Document">
        <div class="space-y-5">
          <div>
            <label class="mb-2 block text-xs font-medium text-slate-600">Document type</label>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
              <button v-for="(label, code) in kindLabels" :key="code" type="button" @click="kind = code"
                class="rounded-xl border p-3 text-left transition-colors"
                :class="kind === code ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200 hover:bg-slate-50'">
                <span class="block text-sm font-semibold" :class="kind === code ? 'text-indigo-700' : 'text-slate-700'">{{ label }}</span>
                <span class="mt-1 block text-xs text-slate-500">{{ kindHelp[code] }}</span>
              </button>
            </div>
          </div>

          <AppInput v-model="title" label="Title / Subject" :required="true" maxlength="500" :error="errors.title" />

          <div class="flex gap-2">
            <button type="button" @click="contentMode = 'editor'" class="flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium"
              :class="contentMode === 'editor' ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600'">
              <DocumentTextIcon class="h-4 w-4" /> Type in editor
            </button>
            <button type="button" @click="contentMode = 'upload'" class="flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium"
              :class="contentMode === 'upload' ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600'">
              <PaperClipIcon class="h-4 w-4" /> Upload scan
            </button>
          </div>

          <RichTextEditor v-if="contentMode === 'editor'" v-model="content" placeholder="Type the related document body…" />
          <div v-else class="space-y-2">
            <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="handleScan"
              class="w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700" />
            <p class="text-xs text-slate-400">PDF, JPG, or PNG · Maximum 20 MB</p>
            <p v-if="scanFilename" class="flex items-center gap-2 text-xs text-success-600"><CheckCircleIcon class="h-4 w-4" /> {{ scanFilename }}</p>
            <p v-if="errors.scan_base64 || errors.scan_mime" class="text-xs text-red-600">{{ errors.scan_base64 || errors.scan_mime }}</p>
          </div>

          <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
            Releasing creates an independently signed and QR-verifiable document. The original issuance remains unchanged.
          </div>

          <div class="flex justify-end gap-2">
            <AppButton variant="secondary" :disabled="!canSubmit || processing" @click="saveDraft">Save as Draft</AppButton>
            <AppButton :disabled="!canSubmit || processing" @click="release">Sign & Release</AppButton>
          </div>
        </div>
      </AppCard>
    </div>

    <DigitalSignaturePin v-if="showPinModal" :show="showPinModal" :has-pin="hasPin" :signature-uri="signatureUri"
      @cancel="showPinModal = false" @close="showPinModal = false" @confirm="onPinConfirm" />
  </AdminLayout>
</template>
