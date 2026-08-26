<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppModal from '@/Components/AppModal.vue'
import AppButton from '@/Components/AppButton.vue'
import AppSelect from '@/Components/AppSelect.vue'
import { IdentificationIcon, CalendarDaysIcon, SparklesIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'

defineProps({
  show: { type: Boolean, default: false },
})
const emit = defineEmits(['done'])

const currentYear = new Date().getFullYear()
const years = Array.from({ length: currentYear - 1980 + 1 }, (_, i) => currentYear - i)
const months = [
  { value: 1,  label: 'January' },
  { value: 2,  label: 'February' },
  { value: 3,  label: 'March' },
  { value: 4,  label: 'April' },
  { value: 5,  label: 'May' },
  { value: 6,  label: 'June' },
  { value: 7,  label: 'July' },
  { value: 8,  label: 'August' },
  { value: 9,  label: 'September' },
  { value: 10, label: 'October' },
  { value: 11, label: 'November' },
  { value: 12, label: 'December' },
]

const form = useForm({
  hired_year: '',
  hired_month: '',
})

const justCompleted = ref(false)

const canSubmit = computed(() => !!form.hired_year && !!form.hired_month && !form.processing)

function submit() {
  form.post(route('employee-id.setup'), {
    preserveScroll: true,
    onSuccess: () => {
      justCompleted.value = true
      setTimeout(() => emit('done'), 1100)
    },
  })
}
</script>

<template>
  <AppModal
    :show="show"
    size="md"
    :close-on-backdrop="false"
    :show-close-button="false"
    body-class="p-0"
  >
    <!-- Gradient banner (replaces the default plain title bar entirely) -->
    <div class="rounded-t-2xl px-6 py-5 text-white"
      style="background: linear-gradient(135deg, #060e50 0%, #1447c0 65%, #0093b8 100%)">
      <div class="flex items-center gap-3">
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/15 backdrop-blur">
          <IdentificationIcon class="h-6 w-6 text-white" />
        </div>
        <div>
          <p class="text-[11px] font-semibold uppercase tracking-widest text-indigo-100">Required Step</p>
          <h2 class="font-heading text-base font-bold leading-tight">Employee ID Number Setup</h2>
        </div>
      </div>
    </div>

    <div class="space-y-5 px-6 py-5">
      <Transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        mode="out-in"
      >
        <!-- Success state -->
        <div v-if="justCompleted" key="success" class="flex flex-col items-center gap-3 py-6 text-center">
          <div class="flex h-14 w-14 items-center justify-center rounded-full bg-success-50">
            <CheckCircleIcon class="h-8 w-8 text-success-600" />
          </div>
          <p class="text-sm font-semibold text-slate-800">Your employee ID number has been generated.</p>
          <p class="text-xs text-slate-500">You're all set — this won't be asked again.</p>
        </div>

        <!-- Form state -->
        <div v-else key="form" class="space-y-5">
          <div class="flex items-start gap-3 rounded-xl border border-indigo-100 bg-indigo-50/60 px-4 py-3">
            <SparklesIcon class="h-5 w-5 shrink-0 text-indigo-500" />
            <p class="text-xs leading-relaxed text-indigo-800">
              We need the year and month you were <strong>hired at PSHS-CRC</strong> to generate your official
              employee ID number for your ID card. This is a one-time step and cannot be skipped.
            </p>
          </div>

          <form @submit.prevent="submit" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
              <AppSelect
                v-model="form.hired_year"
                label="Year Hired"
                placeholder="Select year"
                required
                :error="form.errors.hired_year"
              >
                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
              </AppSelect>

              <AppSelect
                v-model="form.hired_month"
                label="Month Hired"
                placeholder="Select month"
                required
                :error="form.errors.hired_month"
              >
                <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
              </AppSelect>
            </div>

            <div class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-[11px] text-slate-500">
              <CalendarDaysIcon class="h-4 w-4 shrink-0 text-slate-400" />
              Not sure of the exact month? Choose your best estimate — HR can correct this later if needed.
            </div>

            <AppButton type="submit" class="w-full justify-center" :disabled="!canSubmit">
              <span v-if="form.processing">Generating your ID number…</span>
              <span v-else>Confirm &amp; Generate My Employee ID</span>
            </AppButton>
          </form>
        </div>
      </Transition>
    </div>
  </AppModal>
</template>
