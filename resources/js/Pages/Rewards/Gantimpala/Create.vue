<template>
  <AdminLayout title="Nominate — Gantimpala Agad Award">
    <div class="mx-auto max-w-2xl space-y-6">
      <Link :href="route('rewards.gantimpala.index')" class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800">
        ← Back to Gantimpala Agad
      </Link>
      <AppPageHeader title="Nominate — Gantimpala Agad Award" subtitle="Instant recognition for a commendable act of service (PRAISE Form 4)" />

      <AppCard :padded="false">
        <div class="px-5 py-4 border-b border-slate-100">
          <p class="text-sm text-slate-500">Fill in the details below exactly as you would on the printed Form 4.</p>
        </div>
        <div class="p-5">
          <form @submit.prevent="openPinModal" class="space-y-6">
            <!-- Employee/Unit Commended -->
            <div class="space-y-3">
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Employee / Unit Commended</p>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Search Employee (optional — leave blank to type a unit/office name)</label>
                <div class="relative">
                  <input v-model="employeeQuery" type="text" autocomplete="off" placeholder="Type employee name or email…"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  <div v-if="employeeResults.length" class="absolute z-20 mt-1 w-full bg-white border border-slate-200 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                    <button v-for="e in employeeResults" :key="e.id" type="button" @click="pickEmployee(e)"
                      class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 transition-colors">
                      {{ e.name }} <span class="text-xs text-slate-400">({{ e.email }})</span>
                    </button>
                  </div>
                </div>
                <p v-if="form.nominee_user_id" class="mt-1 text-xs text-emerald-600 font-medium">
                  Linked to Atlas account: {{ form.nominee_name }} <button type="button" class="underline ml-1" @click="clearEmployee">clear</button>
                </p>
              </div>

              <AppInput v-model="form.nominee_name" label="Name (Person or Unit)" required :error="form.errors.nominee_name" />
              <div class="grid grid-cols-2 gap-4">
                <AppInput v-model="form.nominee_title" label="Title" :error="form.errors.nominee_title" />
                <AppInput v-model="form.nominee_department" label="Department" :error="form.errors.nominee_department" />
              </div>
              <div class="grid grid-cols-2 gap-4">
                <AppInput v-model="form.nominee_address" label="Address" :error="form.errors.nominee_address" />
                <AppInput v-model="form.nominee_contact_number" label="Contact Number" :error="form.errors.nominee_contact_number" />
              </div>
              <AppInput v-model="form.date_submitted" type="date" label="Date Submitted" :error="form.errors.date_submitted" />
            </div>

            <!-- Nominator -->
            <div class="space-y-3 border-t border-slate-100 pt-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Background of Commendation — Person/Entity Providing Commendation</p>
              <AppInput v-model="form.nominator_name" label="Name" required :error="form.errors.nominator_name" />
              <div class="grid grid-cols-2 gap-4">
                <AppInput v-model="form.nominator_address" label="Address" :error="form.errors.nominator_address" />
                <AppInput v-model="form.nominator_contact_number" label="Contact Number" :error="form.errors.nominator_contact_number" />
              </div>
              <AppInput v-model="form.nominator_email" type="email" label="E-mail Address" :error="form.errors.nominator_email" />
            </div>

            <!-- Commendable Action -->
            <div class="space-y-3 border-t border-slate-100 pt-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Details of Commendable Action</p>
              <AppInput v-model="form.activity_conducted" label="Activity Conducted / Service Provided" required :error="form.errors.activity_conducted" />
              <div class="grid grid-cols-2 gap-4">
                <AppInput v-model="form.activity_date" type="date" label="Date" :error="form.errors.activity_date" />
                <AppInput v-model="form.venue_location" label="Venue / Location" :error="form.errors.venue_location" />
              </div>
              <AppTextarea v-model="form.other_information" label="Other Information" :rows="4"
                placeholder="Briefly state the commendable action provided by the person/unit…" :error="form.errors.other_information" />
            </div>

            <!-- Certification -->
            <div class="space-y-3 border-t border-slate-100 pt-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Certification</p>
              <p class="text-sm text-slate-600">I certify to the correctness of the information provided herewith.</p>

              <div v-if="signatureUri" class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 flex items-center gap-4">
                <img :src="signatureUri" alt="Your signature on file" class="max-h-14 max-w-[180px] object-contain" />
                <p class="text-xs text-slate-500">Your Digital Signature on file will be applied to this nomination.</p>
              </div>
              <div v-else class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                You have not set up a signature image yet. You can still submit — set one up anytime in
                <strong>Profile → Digital Signature</strong> for future nominations.
              </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
              <AppButton as="link" variant="secondary" :href="route('rewards.gantimpala.index')">Cancel</AppButton>
              <AppButton type="button" @click="openPinModal" :disabled="form.processing" :loading="form.processing">
                {{ form.processing ? 'Submitting…' : 'Submit Nomination' }}
              </AppButton>
            </div>
          </form>
        </div>
      </AppCard>
    </div>

    <DigitalSignaturePin
      :show="showSubmitPin"
      :hasPin="hasPin"
      :signatureUri="signatureUri"
      :loading="form.processing"
      confirmLabel="Sign & Submit"
      @confirm="handlePinConfirm"
      @cancel="handlePinCancel"
    />
  </AdminLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppButton from '@/Components/AppButton.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'

const props = defineProps({
  employees: Array,
  hasPin: { type: Boolean, default: false },
  signatureUri: { type: String, default: null },
})

const form = useForm({
  nominee_user_id: '',
  nominee_name: '',
  nominee_title: '',
  nominee_department: '',
  nominee_address: '',
  nominee_contact_number: '',
  date_submitted: new Date().toISOString().slice(0, 10),

  nominator_name: '',
  nominator_address: '',
  nominator_contact_number: '',
  nominator_email: '',

  activity_conducted: '',
  activity_date: '',
  venue_location: '',
  other_information: '',

  pin: null,
})

// ── Employee search-and-select (client-side over the preloaded active roster) ──
const employeeQuery = ref('')
const employeeResults = ref([])

watch(employeeQuery, (val) => {
  if (form.nominee_user_id) return // already picked, ignore further typing until cleared
  const term = val.trim().toLowerCase()
  employeeResults.value = term.length < 2
    ? []
    : props.employees.filter(e => e.name.toLowerCase().includes(term) || e.email.toLowerCase().includes(term)).slice(0, 8)
})

function pickEmployee(e) {
  form.nominee_user_id = e.id
  form.nominee_name = e.name
  employeeQuery.value = e.name
  employeeResults.value = []
}

function clearEmployee() {
  form.nominee_user_id = ''
  form.nominee_name = ''
  employeeQuery.value = ''
}

const showSubmitPin = ref(false)

const openPinModal = () => { showSubmitPin.value = true }
const handlePinCancel = () => { showSubmitPin.value = false }
const handlePinConfirm = (pin) => {
  form.pin = pin || null
  showSubmitPin.value = false
  form.post(route('rewards.gantimpala.store'))
}
</script>
