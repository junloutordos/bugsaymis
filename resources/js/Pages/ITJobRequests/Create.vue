<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppButton from '@/Components/AppButton.vue'
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
      <AppPageHeader title="Submit IT Job Request" />

      <AppCard :padded="false" class="max-w-2xl">
        <div class="px-5 py-4 border-b border-slate-100">
          <p class="text-sm text-slate-500">Fill in the details below to submit a new request.</p>
        </div>
        <div class="p-5">
          <form @submit.prevent="submit" class="space-y-4">
            <AppInput v-model="form.title" label="Request Title" required />

            <AppSelect v-model="form.category" label="Category" required>
              <option value="">-- Select --</option>
              <option value="Hardware">Hardware</option>
              <option value="Software">Software</option>
              <option value="Network">Network</option>
              <option value="Account Access">Account Access</option>
              <option value="Other">Other</option>
            </AppSelect>

            <AppTextarea v-model="form.description" label="Description" :rows="4" required />

            <!-- Urgency -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-2">Urgency</label>
              <div class="flex gap-2 flex-wrap">
                <label
                  v-for="opt in [
                    { value: 'low',    label: 'Low',    color: 'peer-checked:bg-slate-100 peer-checked:border-slate-400 peer-checked:text-slate-700' },
                    { value: 'normal', label: 'Normal', color: 'peer-checked:bg-blue-50 peer-checked:border-blue-400 peer-checked:text-blue-700' },
                    { value: 'high',   label: 'High',   color: 'peer-checked:bg-orange-50 peer-checked:border-orange-400 peer-checked:text-orange-700' },
                    { value: 'urgent', label: 'Urgent', color: 'peer-checked:bg-danger-50 peer-checked:border-danger-500 peer-checked:text-danger-600' },
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
              <AppButton type="submit" :loading="form.processing" block>Submit Request</AppButton>
            </div>
          </form>
        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>
