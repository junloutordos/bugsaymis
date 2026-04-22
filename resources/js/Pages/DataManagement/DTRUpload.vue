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
    <div>
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">DTR Upload</h1>
          <p class="text-sm text-slate-500 mt-0.5">Upload daily time record files for processing</p>
        </div>
      </div>

      <!-- Upload Card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-700">Upload Configuration</h2>
        </div>
        <div class="p-5">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Start Date</label>
              <input type="date" v-model="startDate"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">End Date</label>
              <input type="date" v-model="endDate"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Employee Category</label>
              <select v-model="category"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option>Plantilla Teaching</option>
                <option>Plantilla Non-Teaching</option>
                <option>COS Teaching</option>
                <option>COD Non-Teaching</option>
                <option>All Category</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-2">Select .dat file(s)</label>
            <input type="file" accept=".dat" multiple @change="onFileChange"
              class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer" />
            <!-- Preview removed; success/failure reported via SweetAlert -->
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
