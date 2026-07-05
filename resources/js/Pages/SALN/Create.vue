<template>
  <Head title="File New SALN" />
  <AdminLayout title="File New SALN">
    <div class="max-w-2xl mx-auto space-y-5">

      <!-- Back -->
      <Link :href="route('saln.index')" class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800">
        <ChevronLeftIcon class="h-4 w-4" />Back to My SALN
      </Link>

      <AppCard :padded="false">
        <template #header>
          <div>
            <h1 class="font-heading text-lg font-semibold text-slate-800">New SALN — {{ year }}</h1>
            <p class="text-sm text-slate-500 mt-0.5">
              Statement of Assets, Liabilities and Net Worth<br />
              Pursuant to R.A. 6713 and CSC Form No. SALN-1
            </p>
          </div>
        </template>

        <form @submit.prevent="submit" class="p-6 space-y-6">

          <!-- Year & As-of Date -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <AppInput
              v-model="form.year"
              type="number" min="2000" :max="currentYear"
              label="SALN Year" required
              :error="form.errors.year"
            />
            <div>
              <AppInput
                v-model="form.as_of_date"
                type="date"
                label='Assets/Liabilities "As of" Date' required
                :error="form.errors.as_of_date"
              />
              <p class="text-[11px] text-slate-400 mt-1">Usually December 31 of the covered year.</p>
            </div>
          </div>

          <!-- Spouse / Dependent Info -->
          <div class="border-t border-slate-100 pt-5">
            <h2 class="text-sm font-semibold text-slate-700 mb-3">
              Spouse Information
              <span class="text-slate-400 font-normal">(if applicable)</span>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="sm:col-span-2">
                <AppInput v-model="form.spouse_name" maxlength="255" placeholder="Full legal name" label="Spouse Name" />
              </div>
              <AppInput v-model="form.spouse_position" maxlength="255" label="Spouse Position" />
              <AppInput v-model="form.spouse_office" maxlength="255" label="Spouse Office / Employer" />
              <AppInput v-model="form.spouse_government_id" maxlength="100" label="Spouse Gov't ID (if government employee)" />
            </div>
          </div>

          <!-- CSC notice -->
          <div class="rounded-lg bg-warning-50 border border-warning-100 px-4 py-3 text-xs text-warning-700">
            <strong>Note:</strong> This creates a <em>draft</em> SALN. You can fill in all assets, liabilities, and other sections before submitting for review.
            Once submitted, the record cannot be edited unless returned by the SALN Committee.
          </div>

          <!-- Actions -->
          <div class="flex gap-3 justify-end pt-2">
            <AppButton as="link" variant="secondary" :href="route('saln.index')">Cancel</AppButton>
            <AppButton type="submit" :loading="form.processing">
              <DocumentPlusIcon class="h-4 w-4" />
              {{ form.processing ? 'Creating…' : 'Create Draft' }}
            </AppButton>
          </div>
        </form>
      </AppCard>

    </div>
  </AdminLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppInput from '@/Components/AppInput.vue'
import AppButton from '@/Components/AppButton.vue'
import { ChevronLeftIcon, DocumentPlusIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ year: Number, asOfDate: String, user: Object })

const currentYear = new Date().getFullYear()

const form = useForm({
  year:               props.year,
  as_of_date:         props.asOfDate,
  spouse_name:        '',
  spouse_position:    '',
  spouse_office:      '',
  spouse_government_id: '',
})

function submit() {
  form.post(route('saln.store'))
}
</script>
