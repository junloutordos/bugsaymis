<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import KnowledgeManagementForm from './KnowledgeManagementForm.vue'
import { ChevronLeftIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  categories:       Array,
  supersedeOptions: Array,
  offices:          Array,
  divisions:        Array,
  users:            Array,
})

const form = useForm({
  category_code:   '',
  reference_no:    '',
  title:           '',
  description:     '',
  issued_date:     '',
  effective_date:  '',
  supersedes_id:   '',
  file_base64:     '',
  file_name:       '',
  file_mime:       '',
  recipient_type:  'all',
  recipient_ids:   [],
})

function submit() {
  form.post(route('km.store'))
}
</script>

<template>
  <Head title="Upload OED Issuance" />
  <AdminLayout title="Upload OED Issuance">
    <div class="max-w-3xl">

      <button @click="router.visit(route('km.index'))"
        class="flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 mb-5">
        <ChevronLeftIcon class="h-4 w-4" /> Back to Knowledge Management
      </button>

      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
        <h3 class="font-semibold text-slate-800">Upload OED Issuance</h3>

        <KnowledgeManagementForm
          :form="form"
          :categories="categories"
          :supersede-options="supersedeOptions"
          :offices="offices"
          :divisions="divisions"
          :users="users"
        />

        <div class="flex justify-end pt-2">
          <button @click="submit" :disabled="form.processing"
            class="px-5 py-2 text-sm font-medium text-white rounded-lg disabled:opacity-40 bg-indigo-600 hover:bg-indigo-700">
            {{ form.processing ? 'Uploading…' : 'Upload Document' }}
          </button>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
