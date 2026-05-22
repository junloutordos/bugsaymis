<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { storageUrl } from '@/Composables/useStorage.js'
import FlashMessage from '@/Components/FlashMessage.vue'
import AppInput from '@/Components/AppInput.vue'
import {
  UserCircleIcon, PencilSquareIcon,
  CameraIcon, FingerPrintIcon, ShieldCheckIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({ profile: Object })

const page  = usePage()
const flash = computed(() => page.props.flash ?? {})

// ── Photo upload ───────────────────────────────────────────────────────────
const photoPreview   = ref(storageUrl(props.profile.profile_picture))
const photoBase64    = ref(null)
const photoMime      = ref(null)
const photoInputRef  = ref(null)

function triggerPhotoInput() { photoInputRef.value?.click() }

function onPhotoSelect(e) {
  const file = e.target.files[0]
  if (!file) return
  if (file.size > 5 * 1024 * 1024) { alert('Photo must be under 5 MB.'); return }
  photoMime.value = file.type
  const reader = new FileReader()
  reader.onload = ev => {
    photoPreview.value = ev.target.result
    photoBase64.value  = ev.target.result
  }
  reader.readAsDataURL(file)
  e.target.value = ''
}

// ── Form ───────────────────────────────────────────────────────────────────
const name           = ref(props.profile.name ?? '')
const specialization = ref(props.profile.specialization ?? '')
const submitting     = ref(false)
const errors         = ref({})

function save() {
  submitting.value = true
  errors.value     = {}
  router.patch(route('profile.update'), {
    name:                 name.value,
    specialization:       specialization.value,
    profile_photo_base64: photoBase64.value,
    profile_photo_mime:   photoMime.value,
  }, {
    onSuccess: () => { photoBase64.value = null; photoMime.value = null },
    onError:   e  => { errors.value = e },
    onFinish:  () => { submitting.value = false },
    preserveScroll: true,
  })
}

// ── Helpers ────────────────────────────────────────────────────────────────
function initials(n) {
  return (n ?? '').split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2)
}

const empCategoryLabel = {
  regular:     'Permanent / Regular',
  permanent:   'Permanent / Regular',
  cos:         'Contract of Service',
  jo:          'Job Order',
  casual:      'Casual',
  contractual: 'Contractual',
}
</script>

<template>
  <Head title="My Profile" />
  <AdminLayout title="My Profile">

    <FlashMessage :success="flash.success" :error="flash.error" />

    <div class="max-w-3xl space-y-5">

      <!-- ── Photo + Name card ─────────────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6">

          <!-- Photo -->
          <div class="relative shrink-0">
            <div class="w-24 h-24 rounded-full overflow-hidden ring-4 ring-slate-100 bg-slate-100 flex items-center justify-center">
              <img v-if="photoPreview" :src="photoPreview" alt="Profile photo"
                class="w-full h-full object-cover" />
              <span v-else class="text-2xl font-bold text-slate-400 select-none">
                {{ initials(name) }}
              </span>
            </div>
            <!-- Camera overlay -->
            <button @click="triggerPhotoInput"
              class="absolute bottom-0 right-0 w-8 h-8 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full flex items-center justify-center shadow-md transition-colors"
              title="Change photo">
              <CameraIcon class="h-4 w-4" />
            </button>
            <input ref="photoInputRef" type="file" accept="image/jpeg,image/png" class="hidden"
              @change="onPhotoSelect" />
          </div>

          <!-- Name + role -->
          <div class="flex-1 text-center sm:text-left">
            <h2 class="text-xl font-bold text-slate-800">{{ profile.name }}</h2>
            <p v-if="profile.position" class="text-sm text-slate-500 mt-0.5">{{ profile.position }}</p>
            <div class="flex flex-wrap gap-1.5 mt-2 justify-center sm:justify-start">
              <span v-for="role in profile.roles" :key="role"
                class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-100 text-indigo-700">
                {{ role }}
              </span>
            </div>
            <p v-if="photoBase64" class="text-xs text-amber-600 mt-2 flex items-center gap-1">
              <span class="inline-block w-2 h-2 rounded-full bg-amber-500"></span>
              New photo selected — save to apply
            </p>
          </div>
        </div>
        <p class="text-xs text-slate-400 mt-3">JPEG or PNG · Max 5 MB</p>
      </div>

      <!-- ── Editable Info ─────────────────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
          <PencilSquareIcon class="h-4 w-4 text-indigo-500" />
          Personal Information
        </h3>

        <div class="space-y-4">
          <AppInput v-model="name" label="Full Name" :required="true" :error="errors.name" placeholder="Your full name" />
          <AppInput v-model="specialization" label="Specialization / Field" :error="errors.specialization" placeholder="e.g. Biology, Mathematics, Guidance Counseling" />

          <!-- Email (read-only — HR managed) -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Email Address</label>
            <div class="flex items-center gap-2">
              <AppInput :model-value="profile.email" :disabled="true" class="flex-1" />
              <span class="text-xs text-slate-400 whitespace-nowrap shrink-0">HR-managed</span>
            </div>
          </div>
        </div>

        <!-- Save button -->
        <div class="mt-5 flex justify-end">
          <button @click="save" :disabled="submitting"
            class="flex items-center gap-2 px-5 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50 transition-colors">
            <span v-if="submitting">Saving…</span>
            <span v-else>Save Changes</span>
          </button>
        </div>
      </div>

      <!-- ── Employment Details (read-only) ───────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
          <ShieldCheckIcon class="h-4 w-4 text-slate-400" />
          Employment Details
          <span class="text-[11px] font-normal text-slate-400 ml-1">managed by HR / Admin</span>
        </h3>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
          <div>
            <dt class="text-xs text-slate-400 mb-0.5">Employee No.</dt>
            <dd class="font-medium text-slate-700">{{ profile.employee_no ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-slate-400 mb-0.5">Employment Category</dt>
            <dd class="font-medium text-slate-700">
              {{ empCategoryLabel[profile.emp_category?.toLowerCase()] ?? profile.emp_category ?? '—' }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-slate-400 mb-0.5">Position / Designation</dt>
            <dd class="font-medium text-slate-700">{{ profile.position ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-slate-400 mb-0.5">Sex</dt>
            <dd class="font-medium text-slate-700">{{ profile.sex ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-slate-400 mb-0.5">Division</dt>
            <dd class="font-medium text-slate-700">{{ profile.division?.name ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-slate-400 mb-0.5">Office</dt>
            <dd class="font-medium text-slate-700">{{ profile.office?.name ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-slate-400 mb-0.5">Salary Grade</dt>
            <dd class="font-medium text-slate-700">
              {{ profile.salary_grade ? `SG ${profile.salary_grade} – Step ${profile.salary_step ?? 1}` : '—' }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-slate-400 mb-0.5">Account Status</dt>
            <dd>
              <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold"
                :class="profile.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600'">
                {{ profile.status ?? 'active' }}
              </span>
            </dd>
          </div>
        </dl>
      </div>

      <!-- ── Digital Signature ─────────────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-1 flex items-center gap-2">
          <FingerPrintIcon class="h-4 w-4 text-indigo-500" />
          Digital Signature
        </h3>
        <p class="text-xs text-slate-500 mb-4">
          Used on official documents — leave requests, IT job requests, endorsements.
          {{ profile.has_signature ? 'You have a signature on file.' : 'No signature uploaded yet.' }}
        </p>
        <a :href="route('profile.signature')"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50 transition-colors">
          <FingerPrintIcon class="h-4 w-4" />
          {{ profile.has_signature ? 'Manage Signature & PIN' : 'Upload Signature' }}
        </a>
      </div>

    </div>
  </AdminLayout>
</template>
