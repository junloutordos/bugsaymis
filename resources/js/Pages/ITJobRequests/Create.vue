<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

// Form state
const form = useForm({
  title: '',
  category: '',
  description: ''
})

// Submit handler
const submit = () => {
  form.post(route('jobrequests.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      console.log('✅ Job Request Saved')
    },
    onError: (errors) => {
      console.error(errors)
    }
  })
}
</script>

<template>
  <Head title="New Job Request" />

  <AdminLayout title="Submit IT Job Request">
    <div class="p-6">
      <h1 class="text-2xl font-bold text-gray-800 mb-6">Submit IT Job Request</h1>

      <div class="bg-white p-6 rounded-xl shadow max-w-2xl">
        <form @submit.prevent="submit" class="space-y-4">
          <!-- Title -->
          <div>
            <label class="block text-sm font-medium text-gray-700">Request Title</label>
            <input
              v-model="form.title"
              type="text"
              class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
              required
            />
          </div>

          <!-- Category -->
          <div>
            <label class="block text-sm font-medium text-gray-700">Category</label>
            <select
              v-model="form.category"
              class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
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
            <label class="block text-sm font-medium text-gray-700">Description</label>
            <textarea
              v-model="form.description"
              rows="4"
              class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
              required
            ></textarea>
          </div>

          <!-- Submit -->
          <div class="pt-4">
            <button
              type="submit"
              :disabled="form.processing"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow"
            >
              Submit Request
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
