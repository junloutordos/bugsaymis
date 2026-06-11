<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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

<template>
  <Head title="Edit OED Issuance" />
  <AdminLayout title="Edit OED Issuance">
    <div class="max-w-3xl">

      <button @click="router.visit(route('km.show', issuance.id))"
        class="flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 mb-5">
        <ChevronLeftIcon class="h-4 w-4" /> Back to Document
      </button>

      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
        <h3 class="font-semibold text-slate-800">Edit OED Issuance</h3>

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
          <button @click="submit" :disabled="form.processing"
            class="px-5 py-2 text-sm font-medium text-white rounded-lg disabled:opacity-40 bg-indigo-600 hover:bg-indigo-700">
            {{ form.processing ? 'Saving…' : 'Save Changes' }}
          </button>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
