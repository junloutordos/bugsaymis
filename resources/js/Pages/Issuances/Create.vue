<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'
import {
  DocumentTextIcon, PaperClipIcon, UserGroupIcon,
  BuildingOfficeIcon, UserIcon, CheckCircleIcon, ChevronLeftIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  typeLabels:   Object,
  offices:      Array,
  users:        Array,
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
const recipientType = ref('all')
const selectedOfficeIds = ref([])
const selectedUserIds   = ref([])

const officeSearch = ref('')
const userSearch   = ref('')

const filteredOffices = computed(() => {
  const q = officeSearch.value.toLowerCase()
  return (props.offices ?? []).filter(o => !q || o.name.toLowerCase().includes(q))
})

const filteredUsers = computed(() => {
  const q = userSearch.value.toLowerCase()
  return (props.users ?? []).filter(u => !q || u.name.toLowerCase().includes(q) || u.position?.toLowerCase().includes(q))
})

function toggleOffice(id) {
  const idx = selectedOfficeIds.value.indexOf(id)
  if (idx === -1) selectedOfficeIds.value.push(id)
  else selectedOfficeIds.value.splice(idx, 1)
}

function toggleUser(id) {
  const idx = selectedUserIds.value.indexOf(id)
  if (idx === -1) selectedUserIds.value.push(id)
  else selectedUserIds.value.splice(idx, 1)
}

// ── Release (sign + publish) ───────────────────────────────────────────────
const showPinModal   = ref(false)
const saving         = ref(false)
const savingDraft    = ref(false)
const errors         = ref({})

function canAdvance() {
  if (step.value === 1) return type.value && title.value.trim()
  if (step.value === 2) {
    if (contentMode.value === 'editor') return content.value.trim().length > 10
    return !!scanBase64.value
  }
  return true
}

function saveDraft() {
  savingDraft.value = true
  router.post(route('issuances.store'), buildPayload(), {
    onSuccess: () => { savingDraft.value = false },
    onError: e => { errors.value = e; savingDraft.value = false },
  })
}

function buildPayload(pin = null) {
  return {
    type, title: title.value,
    content:      contentMode.value === 'editor' ? content.value : null,
    scan_base64:  contentMode.value === 'upload'  ? scanBase64.value : null,
    scan_filename:contentMode.value === 'upload'  ? scanFilename.value : null,
    scan_mime:    contentMode.value === 'upload'  ? scanMime.value : null,
    recipient_type: recipientType.value,
    office_ids:   selectedOfficeIds.value,
    user_ids:     selectedUserIds.value,
    pin,
  }
}

function openPinModal() {
  if (!canAdvance()) return
  showPinModal.value = true
}

function onPinVerified(pin) {
  showPinModal.value = false
  saving.value = true
  // First store, then release
  router.post(route('issuances.store'), buildPayload(), {
    onSuccess: (page) => {
      // Get the new issuance id from flash or redirect
      // The controller redirects to show, so we just submit release from there
      saving.value = false
    },
    onError: e => { errors.value = e; saving.value = false },
  })
}

// ── Preview / type meta ────────────────────────────────────────────────────
const typeOptions = Object.entries(props.typeLabels ?? {}).map(([k, v]) => ({ code: k, label: v }))

const editorTemplates = {
  SO:     'GREETINGS:\n\nThis Special Order is hereby issued to ...\n\nThis Order is effective immediately.\n\nAll concerned are hereby notified.',
  TO:     'This Travel Order authorizes ...\n\nPurpose of Travel: ...\n\nDestination: ...\n\nDate(s) of Travel: ...',
  MEMO:   'TO: All Concerned\nFROM: Office of the Campus Director\nSUBJECT: ...\n\n',
  OO:     'Pursuant to ..., this Office Order is hereby issued:\n\n1. ...\n\n2. ...\n\nAll concerned are hereby advised to comply.',
  AO:     '',
  CIRC:   'For the information and guidance of all concerned, ...',
  NOTICE: 'This is to inform all ...',
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
      <button @click="router.visit(route('issuances.index'))"
        class="flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 mb-5">
        <ChevronLeftIcon class="h-4 w-4" /> Back to Issuances
      </button>

      <!-- Step indicator -->
      <div class="flex items-center gap-2 mb-6">
        <div v-for="(label, i) in ['Details', 'Content', 'Release']" :key="i"
          class="flex items-center gap-2">
          <div class="flex items-center gap-1.5">
            <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
              :class="step === i+1 ? 'text-white' : step > i+1 ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-500'"
              :style="step === i+1 ? 'background:linear-gradient(135deg,#060e50 0%,#1447c0 65%,#0093b8 100%)' : ''">
              <span v-if="step > i+1">✓</span>
              <span v-else>{{ i+1 }}</span>
            </div>
            <span class="text-sm font-medium" :class="step === i+1 ? 'text-slate-800' : 'text-slate-400'">{{ label }}</span>
          </div>
          <div v-if="i < 2" class="h-px w-8 bg-slate-200"></div>
        </div>
      </div>

      <!-- ── Step 1: Details ──────────────────────────────────────────────── -->
      <div v-if="step === 1" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
        <h3 class="font-semibold text-slate-800">Issuance Details</h3>

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

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Title / Subject <span class="text-red-500">*</span></label>
          <input v-model="title" type="text" required
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            :placeholder="{ SO: 'e.g. Designation of Committee Members for...', TO: 'e.g. Official Travel of...', MEMO: 'e.g. Submission of IPCR for...' }[type] ?? 'Title or subject of the issuance'" />
          <p v-if="errors.title" class="text-xs text-red-500 mt-1">{{ errors.title }}</p>
        </div>

        <div class="flex justify-end pt-2">
          <button @click="step = 2" :disabled="!type || !title.trim()"
            class="px-5 py-2 text-sm font-medium text-white rounded-lg disabled:opacity-40"
            style="background:linear-gradient(135deg,#060e50 0%,#1447c0 65%,#0093b8 100%)">
            Next: Content →
          </button>
        </div>
      </div>

      <!-- ── Step 2: Content ──────────────────────────────────────────────── -->
      <div v-if="step === 2" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
        <h3 class="font-semibold text-slate-800">Issuance Content</h3>

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
          <textarea v-model="content" rows="16"
            class="w-full rounded-lg border border-slate-200 px-3 py-3 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y"
            :placeholder="editorTemplates[type] ?? 'Type the body of the issuance here...'" />
          <p class="text-xs text-slate-400">{{ content.length }} characters</p>
        </div>

        <!-- Upload mode -->
        <div v-else class="space-y-3">
          <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm text-slate-600">
            Upload an already-signed or scanned issuance document. The system will attach a QR verification code.
          </div>
          <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="handleScan"
            class="w-full text-sm text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
          <p class="text-xs text-slate-400">PDF, JPG, PNG · Max 20 MB</p>
          <div v-if="scanFilename" class="flex items-center gap-2 text-xs text-emerald-600">
            <CheckCircleIcon class="h-4 w-4" /> {{ scanFilename }} selected
          </div>
        </div>

        <div class="flex justify-between pt-2">
          <button @click="step = 1" class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">← Back</button>
          <button @click="step = 3" :disabled="!canAdvance()"
            class="px-5 py-2 text-sm font-medium text-white rounded-lg disabled:opacity-40"
            style="background:linear-gradient(135deg,#060e50 0%,#1447c0 65%,#0093b8 100%)">
            Next: Recipients →
          </button>
        </div>
      </div>

      <!-- ── Step 3: Recipients + Release ────────────────────────────────── -->
      <div v-if="step === 3" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
        <h3 class="font-semibold text-slate-800">Recipients & Release</h3>

        <!-- Recipient type -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-2">Who receives this issuance?</label>
          <div class="grid grid-cols-3 gap-3">
            <button v-for="opt in [
              { key:'all', icon: UserGroupIcon, label:'All Staff', sub:'Every active user' },
              { key:'office', icon: BuildingOfficeIcon, label:'By Office', sub:'Select offices' },
              { key:'individual', icon: UserIcon, label:'Individual(s)', sub:'Pick specific users' },
            ]" :key="opt.key"
              @click="recipientType = opt.key"
              class="flex flex-col items-center gap-1 p-3 rounded-xl border text-center transition-colors"
              :class="recipientType === opt.key ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200 hover:border-slate-300'">
              <component :is="opt.icon" class="h-5 w-5" :class="recipientType === opt.key ? 'text-indigo-600' : 'text-slate-400'" />
              <p class="text-xs font-semibold" :class="recipientType === opt.key ? 'text-indigo-700' : 'text-slate-700'">{{ opt.label }}</p>
              <p class="text-[10px] text-slate-400">{{ opt.sub }}</p>
            </button>
          </div>
        </div>

        <!-- Office selection -->
        <div v-if="recipientType === 'office'" class="space-y-2">
          <input v-model="officeSearch" type="text" placeholder="Search offices…"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
            <label v-for="o in filteredOffices" :key="o.id"
              class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
              <input type="checkbox" :checked="selectedOfficeIds.includes(o.id)"
                @change="toggleOffice(o.id)" class="rounded border-slate-300 text-indigo-600" />
              <span class="text-sm text-slate-700">{{ o.name }}</span>
            </label>
          </div>
          <p v-if="selectedOfficeIds.length" class="text-xs text-indigo-600 font-medium">{{ selectedOfficeIds.length }} office(s) selected</p>
        </div>

        <!-- Individual selection -->
        <div v-if="recipientType === 'individual'" class="space-y-2">
          <input v-model="userSearch" type="text" placeholder="Search by name or position…"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
            <label v-for="u in filteredUsers.slice(0, 50)" :key="u.id"
              class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
              <input type="checkbox" :checked="selectedUserIds.includes(u.id)"
                @change="toggleUser(u.id)" class="rounded border-slate-300 text-indigo-600" />
              <div>
                <p class="text-sm font-medium text-slate-700">{{ u.name }}</p>
                <p v-if="u.position" class="text-xs text-slate-400">{{ u.position }}</p>
              </div>
            </label>
          </div>
          <p v-if="selectedUserIds.length" class="text-xs text-indigo-600 font-medium">{{ selectedUserIds.length }} person(s) selected</p>
        </div>

        <!-- Summary -->
        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 text-sm space-y-1">
          <p><span class="text-slate-500">Type:</span> <strong>{{ typeLabels[type] }}</strong></p>
          <p><span class="text-slate-500">Title:</span> <strong>{{ title }}</strong></p>
          <p><span class="text-slate-500">Content:</span> {{ contentMode === 'editor' ? content.length + ' characters typed' : scanFilename }}</p>
          <p><span class="text-slate-500">Recipients:</span>
            <strong>{{ { all: 'All Staff', office: selectedOfficeIds.length + ' Office(s)', individual: selectedUserIds.length + ' Person(s)' }[recipientType] }}</strong>
          </p>
          <p class="text-xs text-amber-600 mt-2">
            ⚠ Releasing is permanent. The issuance will be signed with your digital signature and sent to all recipients.
          </p>
        </div>

        <div class="flex items-center justify-between pt-2">
          <div class="flex gap-2">
            <button @click="step = 2" class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">← Back</button>
            <button @click="saveDraft" :disabled="savingDraft"
              class="px-4 py-2 text-sm text-slate-700 border border-slate-300 rounded-lg hover:bg-slate-50 disabled:opacity-50">
              {{ savingDraft ? 'Saving…' : 'Save as Draft' }}
            </button>
          </div>
          <button @click="openPinModal"
            class="flex items-center gap-2 px-5 py-2 text-sm font-medium text-white rounded-lg"
            style="background:linear-gradient(135deg,#060e50 0%,#1447c0 65%,#0093b8 100%)">
            Sign & Release
          </button>
        </div>
      </div>

    </div>

    <!-- Digital Signature PIN modal -->
    <DigitalSignaturePin
      v-if="showPinModal"
      :show="showPinModal"
      :has-pin="hasPin"
      :signature-uri="signatureUri"
      @close="showPinModal = false"
      @verified="onPinVerified"
    />

  </AdminLayout>
</template>
