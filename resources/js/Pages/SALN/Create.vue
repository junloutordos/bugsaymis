<template>
  <Head title="File New SALN" />
  <AdminLayout title="File New SALN">
    <div class="max-w-2xl mx-auto space-y-5">

      <!-- Back -->
      <Link :href="route('saln.index')" class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800">
        <ChevronLeftIcon class="h-4 w-4" />Back to My SALN
      </Link>

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-6 py-5 border-b border-slate-100">
          <h1 class="text-lg font-semibold text-slate-800">New SALN — {{ year }}</h1>
          <p class="text-sm text-slate-500 mt-0.5">
            Statement of Assets, Liabilities and Net Worth<br />
            Pursuant to R.A. 6713 and CSC Form No. SALN-1
          </p>
        </div>

        <form @submit.prevent="submit" class="p-6 space-y-6">

          <!-- Year & As-of Date -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">
                SALN Year <span class="text-red-500">*</span>
              </label>
              <input v-model="form.year" type="number" min="2000" :max="currentYear"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                :class="{ 'border-red-400': form.errors.year }" />
              <p v-if="form.errors.year" class="text-xs text-red-500 mt-1">{{ form.errors.year }}</p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">
                Assets/Liabilities "As of" Date <span class="text-red-500">*</span>
              </label>
              <input v-model="form.as_of_date" type="date"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                :class="{ 'border-red-400': form.errors.as_of_date }" />
              <p v-if="form.errors.as_of_date" class="text-xs text-red-500 mt-1">{{ form.errors.as_of_date }}</p>
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
                <label class="block text-xs font-medium text-slate-600 mb-1">Spouse Name</label>
                <input v-model="form.spouse_name" type="text" maxlength="255"
                  placeholder="Full legal name"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Spouse Position</label>
                <input v-model="form.spouse_position" type="text" maxlength="255"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Spouse Office / Employer</label>
                <input v-model="form.spouse_office" type="text" maxlength="255"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Spouse Gov't ID (if government employee)</label>
                <input v-model="form.spouse_government_id" type="text" maxlength="100"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
            </div>
          </div>

          <!-- CSC notice -->
          <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-700">
            <strong>Note:</strong> This creates a <em>draft</em> SALN. You can fill in all assets, liabilities, and other sections before submitting for review.
            Once submitted, the record cannot be edited unless returned by the SALN Committee.
          </div>

          <!-- Actions -->
          <div class="flex gap-3 justify-end pt-2">
            <Link :href="route('saln.index')"
              class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">
              Cancel
            </Link>
            <button type="submit" :disabled="form.processing"
              class="inline-flex items-center gap-2 px-5 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg font-medium transition-colors">
              <DocumentPlusIcon class="h-4 w-4" />
              {{ form.processing ? 'Creating…' : 'Create Draft' }}
            </button>
          </div>
        </form>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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
