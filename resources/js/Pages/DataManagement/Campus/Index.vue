<template>
  <Head title="Campus Information" />
  <AdminLayout title="Campus Information">
    <div>
      <div v-if="page.props.flash?.success" class="mb-4">
        <div class="px-4 py-3 rounded bg-green-50 border border-green-100 text-green-700">{{ page.props.flash.success }}</div>
      </div>

      <div class="flex items-center justify-between mb-4 gap-2">
        <h1 class="text-xl md:text-2xl font-bold text-gray-800 truncate">Campus Information</h1>
        <button v-if="!campus" @click.prevent="openModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">+ Add Campus</button>
        <button v-else @click.prevent="openModal(campus)" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">Edit Campus</button>
      </div>

      <!-- Campus Card -->
      <div v-if="campus" class="bg-white rounded-xl shadow p-6">
        <div class="flex flex-col md:flex-row gap-6">
          <!-- Logo Section -->
          <div class="flex-shrink-0">
            <div class="w-32 h-32 bg-gray-100 rounded-lg flex items-center justify-center overflow-hidden">
              <img v-if="campus.logo" :src="`/storage/${campus.logo}`" alt="Campus Logo" class="w-full h-full object-cover" />
              <div v-else class="text-gray-400 text-center">
                <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <span class="text-xs">No Logo</span>
              </div>
            </div>
          </div>

          <!-- Campus Details -->
          <div class="flex-1">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">{{ campus.name }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-if="campus.code" class="flex">
                <span class="font-medium text-gray-600 w-32">Code:</span>
                <span>{{ campus.code }}</span>
              </div>
              <div v-if="campus.year_established" class="flex">
                <span class="font-medium text-gray-600 w-32">Established:</span>
                <span>{{ campus.year_established }}</span>
              </div>
              <div v-if="campus.address" class="flex">
                <span class="font-medium text-gray-600 w-32">Address:</span>
                <span>{{ campus.address }}</span>
              </div>
              <div v-if="campus.telephone" class="flex">
                <span class="font-medium text-gray-600 w-32">Telephone:</span>
                <span>{{ campus.telephone }}</span>
              </div>
              <div v-if="campus.mobile" class="flex">
                <span class="font-medium text-gray-600 w-32">Mobile:</span>
                <span>{{ campus.mobile }}</span>
              </div>
              <div v-if="campus.email" class="flex">
                <span class="font-medium text-gray-600 w-32">Email:</span>
                <span>{{ campus.email }}</span>
              </div>
              <div v-if="campus.website" class="flex">
                <span class="font-medium text-gray-600 w-32">Website:</span>
                <a :href="campus.website" target="_blank" class="text-blue-600 hover:text-blue-800">{{ campus.website }}</a>
              </div>
              <div v-if="campus.facebook" class="flex">
                <span class="font-medium text-gray-600 w-32">Facebook:</span>
                <a :href="campus.facebook" target="_blank" class="text-blue-600 hover:text-blue-800">{{ campus.facebook }}</a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- No Campus Message -->
      <div v-else class="bg-white rounded-xl shadow p-8 text-center">
        <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
        </svg>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No Campus Information</h3>
        <p class="text-gray-500 mb-4">Add your campus information to get started.</p>
        <button @click.prevent="openModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg shadow">Add Campus Information</button>
      </div>

      <!-- Modal -->
      <div v-show="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-4 sm:p-6 relative max-h-[90vh] overflow-y-auto">
          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" @click="closeModal">✕</button>
          <h2 class="text-xl font-semibold mb-4">{{ editingId ? 'Edit Campus' : 'Add Campus' }}</h2>
          <form @submit.prevent="submitForm" class="space-y-4">
            <!-- Logo Upload -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Campus Logo</label>
              <div class="flex items-center space-x-4">
                <div class="w-20 h-20 bg-gray-100 rounded-lg flex items-center justify-center overflow-hidden">
                  <img v-if="logoPreview" :src="logoPreview" alt="Logo Preview" class="w-full h-full object-cover" />
                  <div v-else class="text-gray-400 text-center">
                    <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                  />
                  <p class="text-xs text-gray-500 mt-1">PNG, JPG, GIF up to 2MB</p>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
                <input v-model="form.name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" required />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Code</label>
                <input v-model="form.code" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Year Established</label>
                <input v-model="form.year_established" type="number" min="1800" :max="new Date().getFullYear()+1" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Address</label>
                <textarea v-model="form.address" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" rows="3"></textarea>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Telephone Number</label>
                <input v-model="form.telephone" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Mobile Number</label>
                <input v-model="form.mobile" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Official Email Address</label>
                <input v-model="form.email" type="email" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Website</label>
                <input v-model="form.website" type="url" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Facebook</label>
                <input v-model="form.facebook" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
              </div>
            </div>

            <div class="flex justify-end space-x-3 pt-4">
              <button type="button" @click="closeModal" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
              <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">{{ form.processing ? 'Saving…' : 'Save' }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style>
</style>

<script setup>
import { Head, usePage, useForm, router as inertiaRouter } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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
    logoPreview.value = c.logo ? `/storage/${c.logo}` : ''
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

    // Set the file directly to the form
    form.logo = file

    // Create preview
    const reader = new FileReader()
    reader.onload = (e) => {
      logoPreview.value = e.target.result
    }
    reader.readAsDataURL(file)
  }
}

const submitForm = () => {
  const formData = new FormData()

  // Add all form fields to FormData
  Object.keys(form.data()).forEach(key => {
    if (key === 'logo' && form.logo) {
      formData.append('logo', form.logo)
    } else if (key !== 'logo') {
      formData.append(key, form[key] || '')
    }
  })

  if (editingId.value) {
    formData.append('_method', 'PUT')
    inertiaRouter.post(`/data-management/campuses/${editingId.value}`, formData, {
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
    inertiaRouter.post('/data-management/campuses', formData, {
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