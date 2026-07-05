<template>
  <Head title="Upload OED Issuance" />
  <AdminLayout title="Upload OED Issuance">
    <div class="max-w-3xl space-y-5">

      <Link :href="route('km.index')" class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800">
        <ChevronLeftIcon class="h-4 w-4" /> Back to Knowledge Management
      </Link>

      <AppCard title="Upload OED Issuance">
        <div class="space-y-5">
          <KnowledgeManagementForm
            :form="form"
            :categories="categories"
            :supersede-options="supersedeOptions"
            :offices="offices"
            :divisions="divisions"
            :users="users"
          />

          <div class="flex justify-end pt-2">
            <AppButton :loading="form.processing" @click="submit">
              {{ form.processing ? 'Uploading…' : 'Upload Document' }}
            </AppButton>
          </div>
        </div>
      </AppCard>

    </div>
  </AdminLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
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
