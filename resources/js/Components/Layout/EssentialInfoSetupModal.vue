<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppModal from '@/Components/AppModal.vue'
import AppButton from '@/Components/AppButton.vue'
import AppInput from '@/Components/AppInput.vue'
import AddressPicker from '@/Components/AddressPicker.vue'
import { CalendarDaysIcon, HomeIcon, PhoneIcon, CheckCircleIcon, ShieldExclamationIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  show: { type: Boolean, default: false },
  missingFields: { type: Array, default: () => [] },
})
const emit = defineEmits(['done'])

const needsDob = computed(() => props.missingFields.includes('date_of_birth'))
const needsAddress = computed(() => props.missingFields.includes('residential_address'))
const needsEmergencyContact = computed(() => props.missingFields.includes('emergency_contact'))

const form = useForm({
  date_of_birth: '',
  residential_house: '',
  residential_street: '',
  residential_subdivision: '',
  residential_barangay: '',
  residential_city: '',
  residential_province: '',
  residential_region: '',
  residential_zip_code: '',
  emergency_contact_name: '',
  emergency_contact_phone: '',
  emergency_contact_address: '',
})

const residentialAddress = computed({
  get: () => ({
    house: form.residential_house,
    street: form.residential_street,
    subdivision: form.residential_subdivision,
    barangay: form.residential_barangay,
    city: form.residential_city,
    province: form.residential_province,
    region: form.residential_region,
    zip: form.residential_zip_code,
  }),
  set: (val) => {
    form.residential_house = val.house ?? ''
    form.residential_street = val.street ?? ''
    form.residential_subdivision = val.subdivision ?? ''
    form.residential_barangay = val.barangay ?? ''
    form.residential_city = val.city ?? ''
    form.residential_province = val.province ?? ''
    form.residential_region = val.region ?? ''
    form.residential_zip_code = val.zip ?? ''
  },
})

const justCompleted = ref(false)

const canSubmit = computed(() => {
  if (form.processing) return false
  if (needsDob.value && !form.date_of_birth) return false
  if (needsAddress.value && (!form.residential_house || !form.residential_barangay || !form.residential_city || !form.residential_province)) return false
  if (needsEmergencyContact.value && (!form.emergency_contact_name || !form.emergency_contact_phone || !form.emergency_contact_address)) return false
  return true
})

function submit() {
  form.post(route('essential-info.setup'), {
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
    size="lg"
    :close-on-backdrop="false"
    :show-close-button="false"
    body-class="p-0"
  >
    <div class="rounded-t-2xl px-6 py-5 text-white"
      style="background: linear-gradient(135deg, #060e50 0%, #1447c0 65%, #0093b8 100%)">
      <div class="flex items-center gap-3">
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/15 backdrop-blur">
          <ShieldExclamationIcon class="h-6 w-6 text-white" />
        </div>
        <div>
          <p class="text-[11px] font-semibold uppercase tracking-widest text-indigo-100">Required Step</p>
          <h2 class="font-heading text-base font-bold leading-tight">Complete Your Essential Information</h2>
        </div>
      </div>
    </div>

    <div class="px-6 py-5">
      <Transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        mode="out-in"
      >
        <div v-if="justCompleted" key="success" class="flex flex-col items-center gap-3 py-6 text-center">
          <div class="flex h-14 w-14 items-center justify-center rounded-full bg-success-50">
            <CheckCircleIcon class="h-8 w-8 text-success-600" />
          </div>
          <p class="text-sm font-semibold text-slate-800">Your information has been saved.</p>
          <p class="text-xs text-slate-500">You're all set — this won't be asked again.</p>
        </div>

        <div v-else key="form" class="space-y-5">
          <div class="flex items-start gap-3 rounded-xl border border-indigo-100 bg-indigo-50/60 px-4 py-3">
            <ShieldExclamationIcon class="h-5 w-5 shrink-0 text-indigo-500" />
            <p class="text-xs leading-relaxed text-indigo-800">
              We're missing a few essential details required for your employee ID card and emergency
              preparedness. This information is not yet on file in your PDS — please provide it below.
              This is a one-time step and cannot be skipped.
            </p>
          </div>

          <form @submit.prevent="submit" class="space-y-5">
            <!-- Date of Birth -->
            <div v-if="needsDob" class="space-y-2">
              <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                <CalendarDaysIcon class="h-4 w-4" /> Date of Birth
              </div>
              <AppInput v-model="form.date_of_birth" label="Date of Birth" type="date" required :error="form.errors.date_of_birth" />
            </div>

            <!-- Residential Address -->
            <div v-if="needsAddress" class="space-y-2">
              <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                <HomeIcon class="h-4 w-4" /> Residential Address
              </div>
              <AddressPicker v-model="residentialAddress" />
              <p v-if="form.errors.residential_house || form.errors.residential_barangay || form.errors.residential_city || form.errors.residential_province"
                class="text-xs text-danger-500">
                Please complete house/purok, barangay, city, and province.
              </p>
            </div>

            <!-- Emergency Contact -->
            <div v-if="needsEmergencyContact" class="space-y-2">
              <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                <PhoneIcon class="h-4 w-4" /> In Case of Emergency, Notify
              </div>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <AppInput v-model="form.emergency_contact_name" label="Contact Person Name" required :error="form.errors.emergency_contact_name" />
                <AppInput v-model="form.emergency_contact_phone" label="Mobile No." required :error="form.errors.emergency_contact_phone" />
              </div>
              <AppInput v-model="form.emergency_contact_address" label="Address of Contact Person" required :error="form.errors.emergency_contact_address" />
            </div>

            <AppButton type="submit" class="w-full justify-center" :disabled="!canSubmit">
              <span v-if="form.processing">Saving…</span>
              <span v-else>Save My Information</span>
            </AppButton>
          </form>
        </div>
      </Transition>
    </div>
  </AppModal>
</template>
