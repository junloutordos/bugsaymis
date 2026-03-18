<template>
  <div class="space-y-4">
    <h4 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Add Accomplishment</h4>

    <!-- Title -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
      <input
        v-model="form.title"
        type="text"
        maxlength="255"
        placeholder="e.g. Completed project report"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
        :class="{ 'border-red-400': errors.title }"
      />
      <p v-if="errors.title" class="text-red-500 text-xs mt-1">{{ errors.title }}</p>
    </div>

    <!-- Description -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
      <textarea
        v-model="form.description"
        rows="3"
        maxlength="2000"
        placeholder="Brief description of what you accomplished…"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"
      />
    </div>

    <!-- Proof Type -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Proof (optional)</label>
      <div class="flex gap-3">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="radio" v-model="form.proof_type" value="" class="accent-indigo-600" />
          <span class="text-sm text-gray-600">None</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="radio" v-model="form.proof_type" value="photo" class="accent-indigo-600" />
          <span class="text-sm text-gray-600">📷 Photo</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="radio" v-model="form.proof_type" value="link" class="accent-indigo-600" />
          <span class="text-sm text-gray-600">🔗 Link</span>
        </label>
      </div>
    </div>

    <!-- Photo upload -->
    <div v-if="form.proof_type === 'photo'" class="space-y-2">
      <label class="block text-sm font-medium text-gray-700">Upload Photo</label>

      <!-- Drop zone -->
      <div
        class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-indigo-400 transition"
        :class="{ 'border-indigo-500 bg-indigo-50': isDragging }"
        @click="photoInputEl?.click()"
        @dragover.prevent="isDragging = true"
        @dragleave="isDragging = false"
        @drop.prevent="onDrop"
      >
        <div v-if="!photoPreview">
          <p class="text-sm text-gray-400">Click or drag & drop an image here</p>
          <p class="text-xs text-gray-300 mt-1">JPEG · PNG · WebP — max 10 MB</p>
        </div>
        <div v-else class="relative inline-block">
          <img :src="photoPreview" class="max-h-40 rounded-lg mx-auto" alt="Preview" />
          <button @click.stop="clearPhoto"
                  class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center">
            ✕
          </button>
        </div>
      </div>

      <input
        ref="photoInputEl"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        class="hidden"
        @change="onFileSelected"
      />
      <p v-if="errors.photo" class="text-red-500 text-xs">{{ errors.photo }}</p>
    </div>

    <!-- Link input -->
    <div v-if="form.proof_type === 'link'" class="space-y-1">
      <label class="block text-sm font-medium text-gray-700">Proof URL</label>
      <input
        v-model="form.proof_link"
        type="url"
        placeholder="https://drive.google.com/…"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
        :class="{ 'border-red-400': errors.proof_link }"
      />
      <p v-if="errors.proof_link" class="text-red-500 text-xs">{{ errors.proof_link }}</p>
    </div>

    <!-- Submit -->
    <button
      @click="submit"
      :disabled="loading"
      class="w-full py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg text-sm font-semibold transition"
    >
      {{ loading ? 'Saving…' : 'Save Accomplishment' }}
    </button>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

// ── Props & Emits ─────────────────────────────────────────────────────────────
const props = defineProps({
  attendanceId: { type: Number, required: true },
})
const emit = defineEmits(['saved'])

// ── State ─────────────────────────────────────────────────────────────────────
const form = reactive({
  title:       '',
  description: '',
  proof_type:  '',   // '' | 'photo' | 'link'
  proof_link:  '',
})

const errors       = ref({})
const loading      = ref(false)
const isDragging   = ref(false)
const photoFile    = ref(null)
const photoPreview = ref(null)
const photoInputEl = ref(null)

// ── File handling ─────────────────────────────────────────────────────────────
function onFileSelected(e) {
  const file = e.target.files?.[0]
  if (file) setPhoto(file)
}

function onDrop(e) {
  isDragging.value = false
  const file = e.dataTransfer.files?.[0]
  if (file) setPhoto(file)
}

function setPhoto(file) {
  if (file.size > 10 * 1024 * 1024) {
    errors.value = { photo: 'Photo must not exceed 10 MB.' }
    return
  }
  photoFile.value    = file
  photoPreview.value = URL.createObjectURL(file)
  errors.value       = {}
}

function clearPhoto() {
  photoFile.value    = null
  photoPreview.value = null
  if (photoInputEl.value) photoInputEl.value.value = ''
}

// ── Submit ────────────────────────────────────────────────────────────────────
async function submit() {
  errors.value = {}

  if (!form.title.trim()) {
    errors.value = { title: 'Title is required.' }
    return
  }
  if (form.proof_type === 'link' && !form.proof_link.trim()) {
    errors.value = { proof_link: 'A URL is required when proof type is set to link.' }
    return
  }
  if (form.proof_type === 'photo' && !photoFile.value) {
    errors.value = { photo: 'Please select a photo.' }
    return
  }

  loading.value = true

  try {
    const fd = new FormData()
    fd.append('title',       form.title.trim())
    fd.append('description', form.description.trim())
    if (form.proof_type) fd.append('proof_type', form.proof_type)
    if (form.proof_type === 'link')  fd.append('proof_link', form.proof_link.trim())
    if (form.proof_type === 'photo') fd.append('photo', photoFile.value)

    const { data } = await axios.post(route('hr.wfh.accomplishments.store'), fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    emit('saved', data.accomplishment)
    resetForm()

    Swal.fire({ icon: 'success', title: 'Accomplishment saved.', timer: 1400, showConfirmButton: false })
  } catch (err) {
    const serverErrors = err.response?.data?.errors
    if (serverErrors) {
      errors.value = Object.fromEntries(
        Object.entries(serverErrors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
      )
    } else {
      Swal.fire('Error', err.response?.data?.message ?? 'Could not save accomplishment.', 'error')
    }
  } finally {
    loading.value = false
  }
}

// ── Reset ─────────────────────────────────────────────────────────────────────
function resetForm() {
  form.title       = ''
  form.description = ''
  form.proof_type  = ''
  form.proof_link  = ''
  clearPhoto()
  errors.value = {}
}
</script>
