<script setup>
import { useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'

// No payload needed, backend handles user_id
const form = useForm({})

const createPds = () => {
  form.post(route('pds.newpds'), {
    preserveScroll: true,
    onSuccess: () => {
      Swal.fire({
        icon: 'success',
        title: 'PDS Created',
        text: 'Your Personal Data Sheet has been created.',
        timer: 1500,
        showConfirmButton: false,
      })
    },
    onError: () => {
      Swal.fire('Error', 'Failed to create PDS. Please try again.', 'error')
    },
  })
}
</script>

<template>
  <AdminLayout title="New Personal Data Sheet">
    <div class="max-w-2xl mx-auto mt-20">
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-8 text-center">
        <h1 class="text-xl font-semibold text-slate-800 mb-3">
          Create Personal Data Sheet
        </h1>

        <p class="text-sm text-slate-500 mb-8">
          You don't have a Personal Data Sheet yet.<br />
          Click the button below to create one.
        </p>

        <button
          @click="createPds"
          :disabled="form.processing"
          class="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <span v-if="!form.processing">Create New PDS</span>
          <span v-else>Creating...</span>
        </button>
      </div>
    </div>
  </AdminLayout>
</template>
