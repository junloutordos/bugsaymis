<template>
  <Head title="New Payroll Run" />
  <AdminLayout title="New Payroll Run">
    <div class="max-w-lg mx-auto space-y-5">

      <!-- Back -->
      <Link :href="route('payroll.index')" class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800">
        <ChevronLeftIcon class="h-4 w-4" />Back to Payroll Runs
      </Link>

      <AppCard :padded="false">
        <template #header>
          <div>
            <h1 class="font-heading text-lg font-semibold text-slate-800">Create Payroll Run</h1>
            <p class="text-sm text-slate-500 mt-0.5">Set up a new semi-monthly payroll period.</p>
          </div>
        </template>

        <form @submit.prevent="submit" class="p-6 space-y-5">

          <!-- Year / Month / Period -->
          <div class="grid grid-cols-3 gap-4">
            <AppInput
              v-model="form.year"
              type="number" min="2020" max="2099"
              label="Year" required
              :error="form.errors.year"
            />

            <AppSelect v-model="form.month" label="Month" required :error="form.errors.month" :show-blank="false">
              <option v-for="(name, idx) in MONTHS.slice(1)" :key="idx+1" :value="idx+1">{{ name }}</option>
            </AppSelect>

            <AppSelect v-model="form.period" label="Period" required :error="form.errors.period" :show-blank="false">
              <option value="1st">1st Half (1–15)</option>
              <option value="2nd">2nd Half (16–EOM)</option>
            </AppSelect>
          </div>

          <!-- Period Preview -->
          <div class="bg-slate-50 rounded-lg px-4 py-3 text-sm text-slate-600">
            <span class="font-medium">Period:</span>
            {{ periodLabel }}
          </div>

          <!-- Pay Date -->
          <AppInput
            v-model="form.pay_date"
            type="date"
            label="Pay Date (optional)"
          />

          <div v-if="form.errors.period && form.errors.period.includes('already')" class="rounded-lg bg-danger-50 border border-danger-100 px-4 py-3 text-sm text-danger-600">
            {{ form.errors.period }}
          </div>

          <!-- Actions -->
          <div class="flex gap-3 justify-end pt-2">
            <AppButton as="link" variant="secondary" :href="route('payroll.index')">Cancel</AppButton>
            <AppButton type="submit" :loading="form.processing">
              {{ form.processing ? 'Creating…' : 'Create Payroll Run' }}
            </AppButton>
          </div>
        </form>
      </AppCard>

    </div>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppButton from '@/Components/AppButton.vue'
import { ChevronLeftIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ suggestion: Object })

const MONTHS = ['','January','February','March','April','May','June','July','August','September','October','November','December']

const form = useForm({
  year:     props.suggestion?.year  ?? new Date().getFullYear(),
  month:    props.suggestion?.month ?? new Date().getMonth() + 1,
  period:   props.suggestion?.period ?? '1st',
  pay_date: '',
})

const periodLabel = computed(() => {
  const y = form.year
  const m = String(form.month).padStart(2, '0')
  if (form.period === '1st') return `${y}-${m}-01 to ${y}-${m}-15`
  const last = new Date(y, form.month, 0).getDate()
  return `${y}-${m}-16 to ${y}-${m}-${last}`
})

function submit () {
  form.post(route('payroll.store'))
}
</script>
