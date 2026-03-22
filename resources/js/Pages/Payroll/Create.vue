<template>
  <Head title="New Payroll Run" />
  <AdminLayout title="New Payroll Run">
    <div class="max-w-lg mx-auto space-y-5">

      <!-- Header -->
      <div>
        <h1 class="text-xl font-semibold text-slate-800">Create Payroll Run</h1>
        <p class="text-sm text-slate-500 mt-0.5">Set up a new semi-monthly payroll period.</p>
      </div>

      <!-- Form Card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6 space-y-5">

        <!-- Year / Month / Period -->
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Year</label>
            <input
              v-model.number="form.year"
              type="number"
              min="2020"
              max="2099"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
              :class="{ 'border-red-400': form.errors.year }"
            />
            <p v-if="form.errors.year" class="text-red-500 text-xs mt-1">{{ form.errors.year }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Month</label>
            <select
              v-model.number="form.month"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
              :class="{ 'border-red-400': form.errors.month }"
            >
              <option v-for="(name, idx) in MONTHS.slice(1)" :key="idx+1" :value="idx+1">{{ name }}</option>
            </select>
            <p v-if="form.errors.month" class="text-red-500 text-xs mt-1">{{ form.errors.month }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Period</label>
            <select
              v-model="form.period"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
              :class="{ 'border-red-400': form.errors.period }"
            >
              <option value="1st">1st Half (1–15)</option>
              <option value="2nd">2nd Half (16–EOM)</option>
            </select>
            <p v-if="form.errors.period" class="text-red-500 text-xs mt-1">{{ form.errors.period }}</p>
          </div>
        </div>

        <!-- Period Preview -->
        <div class="bg-slate-50 rounded-lg px-4 py-3 text-sm text-slate-600">
          <span class="font-medium">Period:</span>
          {{ periodLabel }}
        </div>

        <!-- Pay Date -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Pay Date <span class="font-normal text-slate-400">(optional)</span></label>
          <input
            v-model="form.pay_date"
            type="date"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
          />
        </div>

        <p v-if="form.errors.period && form.errors.period.includes('already')" class="text-red-500 text-sm bg-red-50 border border-red-200 rounded-lg px-3 py-2">
          {{ form.errors.period }}
        </p>

        <!-- Actions -->
        <div class="flex gap-3 justify-end pt-2">
          <Link :href="route('payroll.index')" class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">
            Cancel
          </Link>
          <button
            @click="submit"
            :disabled="form.processing"
            class="px-6 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg transition-colors font-medium"
          >
            {{ form.processing ? 'Creating…' : 'Create Payroll Run' }}
          </button>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

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
