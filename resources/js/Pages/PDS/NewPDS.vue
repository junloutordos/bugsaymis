<script setup>
import { useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
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
      <AppCard>
        <div class="text-center">
          <h1 class="font-heading text-xl font-semibold text-slate-800 mb-3">
            Create Personal Data Sheet
          </h1>

          <p class="text-sm text-slate-500 mb-8">
            You don't have a Personal Data Sheet yet.<br />
            Click the button below to create one.
          </p>

          <AppButton size="lg" :loading="form.processing" @click="createPds">
            {{ form.processing ? 'Creating...' : 'Create New PDS' }}
          </AppButton>
        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>
