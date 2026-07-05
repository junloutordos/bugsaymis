<template>
  <Head title="Edit OED Issuance" />
  <AdminLayout title="Edit OED Issuance">
    <div class="max-w-3xl space-y-5">

      <Link :href="route('km.show', issuance.id)" class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800">
        <ChevronLeftIcon class="h-4 w-4" /> Back to Document
      </Link>

      <AppCard title="Edit OED Issuance">
        <div class="space-y-5">
          <KnowledgeManagementForm
            :form="form"
            :categories="categories"
            :offices="offices"
            :divisions="divisions"
            :users="users"
            :is-edit="true"
            :current-file-name="issuance.file_name"
          />

          <div class="flex justify-end pt-2">
            <AppButton :loading="form.processing" @click="submit">
              {{ form.processing ? 'Saving…' : 'Save Changes' }}
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
  issuance:    Object,
  categories:  Array,
  offices:     Array,
  divisions:   Array,
  users:       Array,
})

const form = useForm({
  category_code:   props.issuance.category_code ?? '',
  reference_no:    props.issuance.reference_no ?? '',
  title:           props.issuance.title ?? '',
  description:     props.issuance.description ?? '',
  issued_date:     props.issuance.issued_date ?? '',
  effective_date:  props.issuance.effective_date ?? '',
  supersedes_id:   props.issuance.superseded_by_id ?? '',
  status:          props.issuance.status ?? 'active',
  file_base64:     '',
  file_name:       '',
  file_mime:       '',
  recipient_type:  props.issuance.recipient_type ?? 'all',
  recipient_ids:   [...(props.issuance.recipient_ids ?? [])],
})

function submit() {
  form.put(route('km.update', props.issuance.id))
}
</script>
