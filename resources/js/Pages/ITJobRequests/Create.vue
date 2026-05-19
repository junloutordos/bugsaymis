<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'

// Form state
const form = useForm({
  title:    '',
  category: '',
  description: '',
  priority: 'normal',
})

// Submit handler
const submit = () => {
  Swal.fire({ title: 'Submitting request...', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => { Swal.showLoading() } })
  form.post(route('jobrequests.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      Swal.fire({ icon: 'success', title: 'Submitted!', text: 'IT Job Request has been created.', timer: 2000, showConfirmButton: false })
    },
    onError: () => {
      Swal.fire({ icon: 'error', title: 'Error', text: 'Please fill all required fields.' })
    }
  })
}
</script>

<template>
  <Head title="New Job Request" />

  <AdminLayout title="Submit IT Job Request">
    <div>
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Submit IT Job Request</h1>
      </div>

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm max-w-2xl">
        <div class="px-5 py-4 border-b border-slate-100">
          <p class="text-sm text-slate-500">Fill in the details below to submit a new request.</p>
        </div>
        <div class="p-5">
          <form @submit.prevent="submit" class="space-y-4">
            <!-- Title -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Request Title</label>
              <input
                v-model="form.title"
                type="text"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                required
              />
            </div>

            <!-- Category -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Category</label>
              <select
                v-model="form.category"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                required
              >
                <option value="">-- Select --</option>
                <option value="Hardware">Hardware</option>
                <option value="Software">Software</option>
                <option value="Network">Network</option>
                <option value="Account Access">Account Access</option>
                <option value="Other">Other</option>
              </select>
            </div>

            <!-- Description -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
              <textarea
                v-model="form.description"
                rows="4"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                required
              ></textarea>
            </div>

            <!-- Urgency -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-2">Urgency</label>
              <div class="flex gap-2 flex-wrap">
                <label
                  v-for="opt in [
                    { value: 'low',    label: 'Low',    color: 'peer-checked:bg-slate-100 peer-checked:border-slate-400 peer-checked:text-slate-700' },
                    { value: 'normal', label: 'Normal', color: 'peer-checked:bg-blue-50 peer-checked:border-blue-400 peer-checked:text-blue-700' },
                    { value: 'high',   label: 'High',   color: 'peer-checked:bg-orange-50 peer-checked:border-orange-400 peer-checked:text-orange-700' },
                    { value: 'urgent', label: 'Urgent', color: 'peer-checked:bg-red-50 peer-checked:border-red-400 peer-checked:text-red-700' },
                  ]"
                  :key="opt.value"
                  class="relative cursor-pointer"
                >
                  <input
                    type="radio"
                    v-model="form.priority"
                    :value="opt.value"
                    class="peer sr-only"
                  />
                  <span
                    class="inline-flex items-center px-3 py-1.5 rounded-lg border text-sm font-medium transition-colors border-slate-200 text-slate-500 hover:bg-slate-50"
                    :class="opt.color"
                  >
                    {{ opt.label }}
                  </span>
                </label>
              </div>
              <p class="text-xs text-slate-400 mt-1">MIS may adjust the final priority based on workload.</p>
            </div>

            <!-- Submit -->
            <div class="pt-2">
              <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm w-full justify-center disabled:opacity-60"
              >
                Submit Request
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
