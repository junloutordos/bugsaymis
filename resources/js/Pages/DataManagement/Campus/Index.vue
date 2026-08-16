<template>
  <Head title="Campus Information" />
  <AdminLayout title="Campus Information">
    <div class="space-y-5">

      <!-- Flash -->
      <div v-if="page.props.flash?.success" class="px-4 py-3 rounded-lg bg-success-50 border border-success-100 text-success-700 text-sm">
        {{ page.props.flash.success }}
      </div>

      <AppPageHeader title="Campus Information" subtitle="Manage campus details and contact information">
        <template #actions>
          <AppButton v-if="!campus" @click.prevent="openModal()">+ Add Campus</AppButton>
          <AppButton v-else variant="secondary" @click.prevent="openModal(campus)">Edit Campus</AppButton>
        </template>
      </AppPageHeader>

      <!-- Campus Card -->
      <AppCard v-if="campus">
        <div class="flex flex-col md:flex-row gap-6">
          <!-- Logo Section -->
          <div class="flex-shrink-0">
            <div class="w-32 h-32 bg-slate-50 rounded-xl border border-slate-100 flex items-center justify-center overflow-hidden">
              <img v-if="campus.logo" :src="storageUrl(campus.logo)" alt="Campus Logo" class="w-full h-full object-cover" />
              <div v-else class="text-slate-400 text-center">
                <svg class="w-10 h-10 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <span class="text-xs">No Logo</span>
              </div>
            </div>
          </div>

          <!-- Campus Details -->
          <div class="flex-1">
            <h2 class="text-xl font-semibold text-slate-800 mb-4">{{ campus.name }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div v-if="campus.code" class="flex gap-2 text-sm">
                <span class="font-medium text-slate-500 w-28 shrink-0">Code:</span>
                <span class="text-slate-700">{{ campus.code }}</span>
              </div>
              <div v-if="campus.year_established" class="flex gap-2 text-sm">
                <span class="font-medium text-slate-500 w-28 shrink-0">Established:</span>
                <span class="text-slate-700">{{ campus.year_established }}</span>
              </div>
              <div v-if="campus.address" class="flex gap-2 text-sm">
                <span class="font-medium text-slate-500 w-28 shrink-0">Address:</span>
                <span class="text-slate-700">{{ campus.address }}</span>
              </div>
              <div v-if="campus.telephone" class="flex gap-2 text-sm">
                <span class="font-medium text-slate-500 w-28 shrink-0">Telephone:</span>
                <span class="text-slate-700">{{ campus.telephone }}</span>
              </div>
              <div v-if="campus.mobile" class="flex gap-2 text-sm">
                <span class="font-medium text-slate-500 w-28 shrink-0">Mobile:</span>
                <span class="text-slate-700">{{ campus.mobile }}</span>
              </div>
              <div v-if="campus.email" class="flex gap-2 text-sm">
                <span class="font-medium text-slate-500 w-28 shrink-0">Email:</span>
                <span class="text-slate-700">{{ campus.email }}</span>
              </div>
              <div v-if="campus.website" class="flex gap-2 text-sm">
                <span class="font-medium text-slate-500 w-28 shrink-0">Website:</span>
                <a :href="campus.website" target="_blank" class="text-indigo-600 hover:text-indigo-800">{{ campus.website }}</a>
              </div>
              <div v-if="campus.facebook" class="flex gap-2 text-sm">
                <span class="font-medium text-slate-500 w-28 shrink-0">Facebook:</span>
                <a :href="campus.facebook" target="_blank" class="text-indigo-600 hover:text-indigo-800">{{ campus.facebook }}</a>
              </div>
            </div>
          </div>
        </div>
      </AppCard>

      <!-- No Campus Message -->
      <AppCard v-else>
        <EmptyState title="No Campus Information" subtitle="Add your campus information to get started.">
          <AppButton class="mt-4" @click.prevent="openModal()">Add Campus Information</AppButton>
        </EmptyState>
      </AppCard>

      <!-- Modal -->
      <AppModal :show="showModal" :title="editingId ? 'Edit Campus' : 'Add Campus'" size="2xl" @close="closeModal">
        <form @submit.prevent="submitForm" class="space-y-4">
          <!-- Logo Upload -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-2">Campus Logo</label>
            <div class="flex items-center space-x-4">
              <div class="w-20 h-20 bg-slate-50 border border-slate-100 rounded-xl flex items-center justify-center overflow-hidden">
                <img v-if="logoPreview" :src="logoPreview" alt="Logo Preview" class="w-full h-full object-cover" />
                <div v-else class="text-slate-400 text-center">
                  <svg class="w-7 h-7 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                  </svg>
                </div>
              </div>
              <div class="flex-1">
                <input
                  ref="logoInput"
                  type="file"
                  @change="handleLogoChange"
                  accept="image/*"
                  class="block w-full text-sm text-slate-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                />
                <p class="text-xs text-slate-400 mt-1">PNG, JPG, GIF up to 2MB</p>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Name <span class="text-danger-600">*</span></label>
              <input v-model="form.name" type="text"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
                required />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Code</label>
              <input v-model="form.code" type="text"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Year Established</label>
              <input v-model="form.year_established" type="number" min="1800" :max="new Date().getFullYear()+1"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Address</label>
              <textarea v-model="form.address" rows="3"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"></textarea>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Telephone Number</label>
              <input v-model="form.telephone" type="text"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Mobile Number</label>
              <input v-model="form.mobile" type="text"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Official Email Address</label>
              <input v-model="form.email" type="email"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Website</label>
              <input v-model="form.website" type="url"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Facebook</label>
              <input v-model="form.facebook" type="text"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <AppButton type="button" variant="secondary" @click="closeModal">Cancel</AppButton>
            <AppButton type="submit" :disabled="form.processing" :loading="form.processing">
              {{ form.processing ? 'Saving…' : 'Save' }}
            </AppButton>
          </div>
        </form>
      </AppModal>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Head, usePage, useForm, router as inertiaRouter } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { storageUrl } from "@/Composables/useStorage.js"
import Swal from 'sweetalert2'

const props = defineProps({ campus: Object })
const page = usePage()

const campus = ref(props.campus || null)
const showModal = ref(false)
const editingId = ref(null)
const logoPreview = ref('')
const logoInput = ref(null)

const form = useForm({
  name: '',
  code: '',
  year_established: '',
  address: '',
  telephone: '',
  mobile: '',
  email: '',
  website: '',
  facebook: '',
  logo: null,
})

const openModal = (c = null) => {
  editingId.value = c ? c.id : null
  if (c) {
    form.reset()
    form.name = c.name
    form.code = c.code
    form.year_established = c.year_established ?? ''
    form.address = c.address ?? ''
    form.telephone = c.telephone ?? ''
    form.mobile = c.mobile ?? ''
    form.email = c.email ?? ''
    form.website = c.website ?? ''
    form.facebook = c.facebook ?? ''
    logoPreview.value = storageUrl(c.logo) ?? ''
  } else {
    form.reset()
    logoPreview.value = ''
  }
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
  editingId.value = null
  form.reset()
  logoPreview.value = ''
}

const handleLogoChange = (event) => {
  const file = event.target.files[0]
  if (file) {
    // Validate file type
    if (!file.type.match('image.*')) {
      Swal.fire({ icon: 'error', title: 'Invalid file type', text: 'Please select an image file.' })
      return
    }

    // Validate file size (2MB)
    if (file.size > 2 * 1024 * 1024) {
      Swal.fire({ icon: 'error', title: 'File too large', text: 'Please select an image smaller than 2MB.' })
      return
    }

    // Read as base64 data URI — sent as JSON, never multipart/form-data (Cloudflare WAF blocks it)
    const reader = new FileReader()
    reader.onload = (e) => {
      form.logo = e.target.result
      logoPreview.value = e.target.result
    }
    reader.readAsDataURL(file)
  }
}

const submitForm = () => {
  const payload = { ...form.data() }

  if (editingId.value) {
    inertiaRouter.put(`/data-management/campuses/${editingId.value}`, payload, {
      onSuccess: () => {
        closeModal()
        Swal.fire({ icon: 'success', title: 'Campus updated', timer: 1200, showConfirmButton: false }).then(() => {
          window.location.reload()
        })
      },
      onError: (errors) => {
        Swal.fire({ icon: 'error', title: 'Failed to update', text: Object.values(errors).flat().join('\n') })
      }
    })
  } else {
    inertiaRouter.post('/data-management/campuses', payload, {
      onSuccess: () => {
        closeModal()
        Swal.fire({ icon: 'success', title: 'Campus added', timer: 1200, showConfirmButton: false }).then(() => {
          window.location.reload()
        })
      },
      onError: (errors) => {
        Swal.fire({ icon: 'error', title: 'Failed to add', text: Object.values(errors).flat().join('\n') })
      }
    })
  }
}
</script>
