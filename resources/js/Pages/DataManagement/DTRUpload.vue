<script setup>
import { ref } from 'vue'
import Swal from 'sweetalert2'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const fileContent = ref('')
const startDate = ref('')
const endDate = ref('')
const category = ref('All Category')

async function onFileChange(e) {
  const files = e.target.files
  if (!files || files.length === 0) return

  // display preview of first file immediately
  const first = files[0]
  const reader = new FileReader()
  reader.onload = (ev) => {
    fileContent.value = ev.target.result
  }
  reader.readAsText(first)

  // upload files to server for parsing and insertion
  try {
    const form = new FormData()
    for (let i = 0; i < files.length; i++) {
      form.append('files[]', files[i])
    }
    if (startDate.value) form.append('start_date', startDate.value)
    if (endDate.value) form.append('end_date', endDate.value)
    if (category.value) form.append('category', category.value)

    const tokenMeta = document.querySelector('meta[name="csrf-token"]')
    const headers = {}
    if (tokenMeta) headers['X-CSRF-TOKEN'] = tokenMeta.getAttribute('content')

    const res = await fetch('/data-management/dtr-upload', {
      method: 'POST',
      body: form,
      headers
    })

    const data = await res.json()
    if (res.ok) {
      Swal.fire({
        icon: 'success',
        title: `${data.inserted} rows inserted`,
        text: data.skipped_by_category ? `${data.skipped_by_category} rows skipped by category` : undefined,
        timer: 2000,
        showConfirmButton: false
      })
      fileContent.value = ''
    } else {
      Swal.fire({
        icon: 'error',
        title: data.message || 'Upload failed',
        text: data.errors ? JSON.stringify(data.errors) : res.statusText
      })
    }
  } catch (err) {
    fileContent.value = 'Upload failed: ' + err.message
  }
}
</script>

<template>
  <AdminLayout title="DTR Upload">
    <div class="p-6">
      <h1 class="text-2xl font-bold mb-4">DTR Upload</h1>
      <div class="bg-white rounded-xl shadow p-6">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium mb-2">Start Date</label>
            <input type="date" v-model="startDate" class="mt-1 block w-full border rounded px-2 py-1" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-2">End Date</label>
            <input type="date" v-model="endDate" class="mt-1 block w-full border rounded px-2 py-1" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-2">Employee Category</label>
            <select v-model="category" class="mt-1 block w-full border rounded px-2 py-1">
              <option>Plantilla Teaching</option>
              <option>Plantilla Non-Teaching</option>
              <option>COS Teaching</option>
              <option>COD Non-Teaching</option>
              <option>All Category</option>
            </select>
          </div>
        </div>
        <label class="block text-sm font-medium mb-2">Select .dat file(s)</label>
        <input type="file" accept=".dat" multiple @change="onFileChange" />
        <!-- Preview removed; success/failure reported via SweetAlert -->
      </div>
    </div>
  </AdminLayout>
</template>
