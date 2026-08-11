<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'
import RichTextEditor from '@/Components/RichTextEditor.vue'
import AppCard from '@/Components/AppCard.vue'
import AppInput from '@/Components/AppInput.vue'
import AppButton from '@/Components/AppButton.vue'
import RecipientPicker from '@/Components/RecipientPicker.vue'
import {
  DocumentTextIcon, PaperClipIcon, CheckCircleIcon, ChevronLeftIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  typeLabels:   Object,
  offices:      Array,
  divisions:    Array,
  users:        Array,
  sections:     Array,
  gradeLevels:  Array,
  students:     Array,
  hasPin:       Boolean,
  signatureUri: String,
})

// ── Step tracking ──────────────────────────────────────────────────────────
const step = ref(1) // 1=details, 2=content, 3=recipients+release

// ── Step 1: Type + Title ───────────────────────────────────────────────────
const type  = ref('MEMO')
const title = ref('')

// ── Step 2: Content ────────────────────────────────────────────────────────
const contentMode = ref('editor') // editor | upload
const content     = ref('')
const scanBase64  = ref(null)
const scanFilename= ref('')
const scanMime    = ref('')

function handleScan(e) {
  const file = e.target.files[0]
  if (!file) return
  scanFilename.value = file.name
  scanMime.value     = file.type
  const reader = new FileReader()
  reader.onload = ev => { scanBase64.value = ev.target.result }
  reader.readAsDataURL(file)
  e.target.value = ''
}

// ── Step 3: Recipients ─────────────────────────────────────────────────────
const targeting = ref({
  all_staff: false, office_ids: [], division_ids: [], user_ids: [],
  all_students: false, section_ids: [], grade_levels: [], student_ids: [],
})

const targetingSummaryParts = computed(() => {
  const t = targeting.value
  const parts = []
  if (t.all_staff) parts.push('All Staff')
  if (t.office_ids.length) parts.push(`${t.office_ids.length} Office(s)`)
  if (t.division_ids.length) parts.push(`${t.division_ids.length} Division(s)`)
  if (t.user_ids.length) parts.push(`${t.user_ids.length} Staff Member(s)`)
  if (t.all_students) parts.push('All Students')
  if (t.section_ids.length) parts.push(`${t.section_ids.length} Section(s)`)
  if (t.grade_levels.length) parts.push(`${t.grade_levels.length} Grade Level(s)`)
  if (t.student_ids.length) parts.push(`${t.student_ids.length} Student(s)`)
  return parts.length ? parts.join(', ') : 'None selected'
})

const hasAnyRecipientSelected = computed(() => {
  const t = targeting.value
  return t.all_staff || t.all_students
    || t.office_ids.length || t.division_ids.length || t.user_ids.length
    || t.section_ids.length || t.grade_levels.length || t.student_ids.length
})

// ── Release (sign + publish) ───────────────────────────────────────────────
const showPinModal   = ref(false)
const saving         = ref(false)
const savingDraft    = ref(false)
const errors         = ref({})

const errorMessages = computed(() => Object.values(errors.value ?? {}).flat().filter(Boolean))

function canAdvance() {
  if (step.value === 1) return type.value && title.value.trim()
  if (step.value === 2) {
    if (contentMode.value === 'editor') {
      // Strip HTML tags to get plain text length
      const plain = content.value.replace(/<[^>]*>/g, '').trim()
      return plain.length > 10
    }
    return !!scanBase64.value
  }
  return true
}

function saveDraft() {
  errors.value = {}
  savingDraft.value = true
  router.post(route('issuances.store'), buildPayload(), {
    onSuccess: () => { savingDraft.value = false },
    onError: e => { errors.value = e; savingDraft.value = false },
  })
}

function buildPayload(pin = null) {
  return {
    type: type.value, title: title.value,
    content:      contentMode.value === 'editor' ? content.value : null,
    scan_base64:  contentMode.value === 'upload'  ? scanBase64.value : null,
    scan_filename:contentMode.value === 'upload'  ? scanFilename.value : null,
    scan_mime:    contentMode.value === 'upload'  ? scanMime.value : null,
    ...targeting.value,
    pin,
  }
}

function openPinModal() {
  if (!canAdvance() || !hasAnyRecipientSelected.value) return
  errors.value = {}
  showPinModal.value = true
}

function onPinConfirm(pin) {
  showPinModal.value = false
  saving.value = true
  errors.value = {}
  // Store + release in one request (controller handles both when should_release=true)
  router.post(route('issuances.store'), { ...buildPayload(pin), should_release: true }, {
    onError:  e  => { errors.value = e; saving.value = false },
    onFinish: () => { saving.value = false },
  })
}

// ── Preview / type meta ────────────────────────────────────────────────────
const typeOptions = Object.entries(props.typeLabels ?? {}).map(([k, v]) => ({ code: k, label: v }))

const editorTemplates = {
  SO:     '<p>GREETINGS:</p><p>This Special Order is hereby issued to ...</p><p>This Order is effective immediately.</p><p>All concerned are hereby notified.</p>',
  TO:     '<p>This Travel Order authorizes ...</p><p><strong>Purpose of Travel:</strong> ...</p><p><strong>Destination:</strong> ...</p><p><strong>Date(s) of Travel:</strong> ...</p>',
  MEMO:   '<p><strong>TO:</strong> All Concerned<br><strong>FROM:</strong> Office of the Campus Director<br><strong>SUBJECT:</strong> ...</p><p></p>',
  OO:     '<p>Pursuant to ..., this Office Order is hereby issued:</p><ol><li>...</li><li>...</li></ol><p>All concerned are hereby advised to comply.</p>',
  AO:     '',
  CIRC:   '<p>For the information and guidance of all concerned, ...</p>',
  NOTICE: '<p>This is to inform all ...</p>',
}

watch(type, (t) => {
  if (contentMode.value === 'editor' && !content.value.trim()) {
    content.value = editorTemplates[t] ?? ''
  }
})
</script>

<template>
  <Head title="New Issuance" />
  <AdminLayout title="New Issuance">

    <div class="max-w-3xl">

      <!-- Back -->
      <Link :href="route('issuances.index')"
        class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 mb-5">
        <ChevronLeftIcon class="h-4 w-4" /> Back to Issuances
      </Link>

      <!-- Step indicator -->
      <div class="flex items-center gap-2 mb-6">
        <div v-for="(label, i) in ['Details', 'Content', 'Release']" :key="i"
          class="flex items-center gap-2">
          <div class="flex items-center gap-1.5">
            <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
              :class="step === i+1 ? 'bg-indigo-600 text-white' : step > i+1 ? 'bg-success-500 text-white' : 'bg-slate-100 text-slate-500'">
              <span v-if="step > i+1">✓</span>
              <span v-else>{{ i+1 }}</span>
            </div>
            <span class="text-sm font-medium" :class="step === i+1 ? 'text-slate-800' : 'text-slate-400'">{{ label }}</span>
          </div>
          <div v-if="i < 2" class="h-px w-8 bg-slate-200"></div>
        </div>
      </div>

      <!-- ── Step 1: Details ──────────────────────────────────────────────── -->
      <AppCard v-if="step === 1" title="Issuance Details">
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Issuance Type <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
              <button v-for="opt in typeOptions" :key="opt.code"
                @click="type = opt.code"
                class="px-3 py-2 rounded-lg border text-sm font-medium transition-colors text-left"
                :class="type === opt.code
                  ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                  : 'border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50'">
                <span class="block text-xs font-bold">{{ opt.code }}</span>
                <span class="block text-[11px] text-slate-500 leading-tight">{{ opt.label }}</span>
              </button>
            </div>
          </div>

          <AppInput
            v-model="title"
            label="Title / Subject"
            :required="true"
            :error="errors.title"
            maxlength="500"
            :placeholder="{ SO: 'e.g. Designation of Committee Members for...', TO: 'e.g. Official Travel of...', MEMO: 'e.g. Submission of IPCR for...' }[type] ?? 'Title or subject of the issuance'"
          />
          <p class="text-xs -mt-2" :class="title.length > 400 ? 'text-warning-600 font-medium' : 'text-slate-400'">
            {{ title.length }} / 500 characters
          </p>

          <div class="flex justify-end pt-2">
            <AppButton @click="step = 2" :disabled="!type || !title.trim()">
              Next: Content →
            </AppButton>
          </div>
        </div>
      </AppCard>

      <!-- ── Step 2: Content ──────────────────────────────────────────────── -->
      <AppCard v-if="step === 2" title="Issuance Content">
        <div class="space-y-4">
          <!-- Mode toggle -->
          <div class="flex gap-2">
            <button @click="contentMode = 'editor'"
              class="flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition-colors"
              :class="contentMode === 'editor' ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'">
              <DocumentTextIcon class="h-4 w-4" /> Type in editor
            </button>
            <button @click="contentMode = 'upload'"
              class="flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition-colors"
              :class="contentMode === 'upload' ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'">
              <PaperClipIcon class="h-4 w-4" /> Upload scan
            </button>
          </div>

          <!-- Editor mode -->
          <div v-if="contentMode === 'editor'" class="space-y-2">
            <div class="bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs text-slate-500 flex gap-3 flex-wrap">
              <span>The system auto-generates the official header ({{ type }} No., year) and signature block.</span>
              <span>Type only the body content below.</span>
            </div>
            <RichTextEditor v-model="content" :placeholder="editorTemplates[type] ? undefined : 'Type the body of the issuance here…'" />
          </div>

          <!-- Upload mode -->
          <div v-else class="space-y-3">
            <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm text-slate-600">
              Upload an already-signed or scanned issuance document. The system will attach a QR verification code.
            </div>
            <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="handleScan"
              class="w-full text-sm text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
            <p class="text-xs text-slate-400">PDF, JPG, PNG · Max 20 MB</p>
            <div v-if="scanFilename" class="flex items-center gap-2 text-xs text-success-600">
              <CheckCircleIcon class="h-4 w-4" /> {{ scanFilename }} selected
            </div>
          </div>

          <div class="flex justify-between pt-2">
            <AppButton variant="secondary" @click="step = 1">← Back</AppButton>
            <AppButton @click="step = 3" :disabled="!canAdvance()">
              Next: Recipients →
            </AppButton>
          </div>
        </div>
      </AppCard>

      <!-- ── Step 3: Recipients + Release ────────────────────────────────── -->
      <AppCard v-if="step === 3" title="Recipients & Release">
        <div class="space-y-5">
          <div v-if="errorMessages.length" class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700 space-y-1">
            <p v-for="(msg, i) in errorMessages" :key="i">{{ msg }}</p>
          </div>

          <RecipientPicker
            v-model="targeting"
            :offices="offices" :divisions="divisions" :users="users"
            :sections="sections" :grade-levels="gradeLevels" :students="students"
          />

          <!-- Summary -->
          <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 text-sm space-y-1">
            <p><span class="text-slate-500">Type:</span> <strong>{{ typeLabels[type] }}</strong></p>
            <p><span class="text-slate-500">Title:</span> <strong>{{ title }}</strong></p>
            <p><span class="text-slate-500">Content:</span> {{ contentMode === 'editor' ? content.length + ' characters typed' : scanFilename }}</p>
            <p><span class="text-slate-500">Recipients:</span> <strong>{{ targetingSummaryParts }}</strong></p>
            <p v-if="!hasAnyRecipientSelected" class="text-xs text-red-600 mt-2">
              ⚠ Select at least one recipient before releasing or saving as draft.
            </p>
            <p class="text-xs text-warning-700 mt-2">
              ⚠ Releasing is permanent. The issuance will be signed with your digital signature and sent to all recipients.
            </p>
          </div>

          <div class="flex items-center justify-between pt-2">
            <div class="flex gap-2">
              <AppButton variant="secondary" @click="step = 2">← Back</AppButton>
              <AppButton variant="secondary" :disabled="savingDraft || !hasAnyRecipientSelected" :loading="savingDraft" @click="saveDraft">
                {{ savingDraft ? 'Saving…' : 'Save as Draft' }}
              </AppButton>
            </div>
            <AppButton @click="openPinModal" :disabled="!hasAnyRecipientSelected">
              Sign & Release
            </AppButton>
          </div>
        </div>
      </AppCard>

    </div>

    <!-- Digital Signature PIN modal -->
    <DigitalSignaturePin
      v-if="showPinModal"
      :show="showPinModal"
      :has-pin="hasPin"
      :signature-uri="signatureUri"
      @cancel="showPinModal = false"
      @confirm="onPinConfirm"
    />

  </AdminLayout>
</template>
