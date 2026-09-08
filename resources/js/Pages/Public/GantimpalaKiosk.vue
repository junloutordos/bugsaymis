<template>
  <Head title="Gantimpala Agad Award — PSHS-CRC" />
  <div class="kiosk-shell">
    <!-- Header -->
    <header class="kiosk-header">
      <img src="/images/pshslogo.png" alt="PSHS-CRC" class="h-14 w-14" @error="hideLogo" v-show="logoVisible" />
      <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-indigo-100">PSHS-CRC · PRAISE Program</p>
        <h1 class="text-2xl font-bold text-white leading-tight">Gantimpala Agad Award</h1>
        <p class="text-sm text-indigo-100">Nominate an employee or unit for outstanding service — right now.</p>
      </div>
    </header>

    <main class="kiosk-main">
      <!-- Success screen -->
      <div v-if="submitted" class="kiosk-card text-center py-14">
        <div class="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100">
          <CheckCircleIcon class="h-12 w-12 text-emerald-600" />
        </div>
        <h2 class="text-2xl font-bold text-slate-800">Thank you for your commendation!</h2>
        <p class="mt-2 text-slate-500">Your nomination has been received and will be reviewed by HR.</p>
        <div class="mt-6 inline-flex items-center gap-2 rounded-xl bg-indigo-50 px-6 py-3">
          <span class="text-sm text-indigo-500">Reference No.</span>
          <span class="text-lg font-bold text-indigo-700">{{ referenceNo }}</span>
        </div>
        <div class="mt-10">
          <button type="button" class="kiosk-btn-primary" @click="resetForm">Submit Another Nomination</button>
        </div>
        <p class="mt-4 text-xs text-slate-400">This screen will reset automatically in {{ countdown }}s…</p>
      </div>

      <!-- Form -->
      <form v-else @submit.prevent="submit" class="kiosk-card space-y-8">
        <!-- honeypot -->
        <input v-model="honeypot" type="text" name="website" autocomplete="off" class="hidden" tabindex="-1" />

        <!-- Step 1 — Employee/Unit Commended -->
        <section>
          <h2 class="kiosk-section-title">1. Who are you commending?</h2>
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2 relative">
              <label class="kiosk-label">Search Employee (optional)</label>
              <input v-model="employeeQuery" type="text" autocomplete="off" placeholder="Type employee name…" class="kiosk-input" />
              <span v-if="searchingEmployees" class="absolute right-3 top-10 text-xs text-slate-400">Searching…</span>
              <div v-if="employeeResults.length" class="absolute z-20 mt-1 w-full bg-white border border-slate-200 rounded-xl shadow-lg max-h-56 overflow-y-auto">
                <button v-for="e in employeeResults" :key="e.id" type="button" @click="pickEmployee(e)"
                  class="w-full text-left px-4 py-3 text-base text-slate-700 hover:bg-indigo-50 hover:text-indigo-700">
                  {{ e.name }}
                </button>
              </div>
              <p v-if="form.nominee_user_id" class="mt-1 text-xs text-emerald-600 font-medium">
                Selected: {{ form.nominee_name }} <button type="button" class="underline ml-1" @click="clearEmployee">change</button>
              </p>
            </div>
            <div class="sm:col-span-2">
              <label class="kiosk-label">Name (Person or Unit/Office) <span class="text-red-500">*</span></label>
              <input v-model="form.nominee_name" type="text" required class="kiosk-input" placeholder="e.g. Juan Dela Cruz or Registrar's Office" />
            </div>
            <div>
              <label class="kiosk-label">Title</label>
              <input v-model="form.nominee_title" type="text" class="kiosk-input" />
            </div>
            <div>
              <label class="kiosk-label">Department</label>
              <input v-model="form.nominee_department" type="text" class="kiosk-input" />
            </div>
          </div>
        </section>

        <!-- Step 2 — Your details -->
        <section>
          <h2 class="kiosk-section-title">2. Your details (Person providing the commendation)</h2>
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
              <label class="kiosk-label">Your Name <span class="text-red-500">*</span></label>
              <input v-model="form.nominator_name" type="text" required class="kiosk-input" />
            </div>
            <div>
              <label class="kiosk-label">Address</label>
              <input v-model="form.nominator_address" type="text" class="kiosk-input" />
            </div>
            <div>
              <label class="kiosk-label">Contact Number</label>
              <input v-model="form.nominator_contact_number" type="text" class="kiosk-input" />
            </div>
            <div class="sm:col-span-2">
              <label class="kiosk-label">E-mail Address</label>
              <input v-model="form.nominator_email" type="email" class="kiosk-input" />
            </div>
          </div>
        </section>

        <!-- Step 3 — Commendable action -->
        <section>
          <h2 class="kiosk-section-title">3. What did they do?</h2>
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
              <label class="kiosk-label">Activity Conducted / Service Provided <span class="text-red-500">*</span></label>
              <input v-model="form.activity_conducted" type="text" required class="kiosk-input" placeholder="e.g. Assisted with urgent document request" />
            </div>
            <div>
              <label class="kiosk-label">Date</label>
              <input v-model="form.activity_date" type="date" class="kiosk-input" />
            </div>
            <div>
              <label class="kiosk-label">Venue / Location</label>
              <input v-model="form.venue_location" type="text" class="kiosk-input" />
            </div>
            <div class="sm:col-span-2">
              <label class="kiosk-label">Tell us more</label>
              <textarea v-model="form.other_information" rows="4" class="kiosk-input resize-y"
                placeholder="Briefly describe the commendable action…"></textarea>
            </div>
          </div>
        </section>

        <!-- Step 4 — Signature -->
        <section>
          <h2 class="kiosk-section-title">4. Confirm and sign</h2>
          <p class="text-sm text-slate-500 mb-2">I certify to the correctness of the information provided herewith.</p>
          <SignaturePad ref="sigPad" label="Your Signature" :height="180" />
        </section>

        <p v-if="errorMessage" class="text-sm text-red-600 bg-red-50 rounded-lg px-4 py-3">{{ errorMessage }}</p>

        <div class="pt-2">
          <button type="submit" class="kiosk-btn-primary w-full" :disabled="submitting">
            {{ submitting ? 'Submitting…' : 'Submit Nomination' }}
          </button>
        </div>
      </form>
    </main>

    <footer class="kiosk-footer">
      Philippine Science High School — Caraga Region Campus · Program on Awards and Incentives for Service Excellence (PRAISE)
    </footer>
  </div>
</template>

<script setup>
import { ref, reactive, watch, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { CheckCircleIcon } from '@heroicons/vue/24/solid'
import SignaturePad from '@/Components/SignaturePad.vue'

const props = defineProps({
  formToken: String,
})

const logoVisible = ref(true)
function hideLogo() { logoVisible.value = false }

const honeypot = ref('')

const form = reactive({
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

  nominator_signature_path: '',
})

// ── Employee search (server-side, public endpoint) ─────────────────────────
const employeeQuery = ref('')
const employeeResults = ref([])
const searchingEmployees = ref(false)
let searchTimer = null

watch(employeeQuery, (val) => {
  if (form.nominee_user_id) return
  clearTimeout(searchTimer)
  const term = val.trim()
  if (term.length < 2) { employeeResults.value = []; return }
  searchTimer = setTimeout(async () => {
    try {
      searchingEmployees.value = true
      const res = await fetch(`${route('kiosk.gantimpala.employees.search')}?q=${encodeURIComponent(term)}`, {
        headers: { Accept: 'application/json' },
      })
      const data = await res.json().catch(() => ({}))
      employeeResults.value = res.ok ? (data.employees || []) : []
    } catch {
      employeeResults.value = []
    } finally {
      searchingEmployees.value = false
    }
  }, 300)
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

// ── Submission ───────────────────────────────────────────────────────────────
const sigPad = ref(null)
const submitting = ref(false)
const submitted = ref(false)
const referenceNo = ref('')
const errorMessage = ref('')

async function submit() {
  errorMessage.value = ''
  submitting.value = true
  try {
    form.nominator_signature_path = sigPad.value?.getDataUrl() ?? ''

    const res = await fetch(route('kiosk.gantimpala.store'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
      },
      body: JSON.stringify({ ...form, website: honeypot.value, form_token: props.formToken }),
    })

    const data = await res.json().catch(() => ({}))

    if (!res.ok) {
      const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : null
      errorMessage.value = firstError || data?.message || 'Something went wrong. Please try again.'
      return
    }

    referenceNo.value = data.reference_no
    submitted.value = true
    startCountdown()
  } catch {
    errorMessage.value = 'Network error. Please check your connection and try again.'
  } finally {
    submitting.value = false
  }
}

// ── Auto-reset after success (kiosk mode — next visitor gets a clean form) ──
const countdown = ref(20)
let countdownTimer = null

function startCountdown() {
  countdown.value = 20
  countdownTimer = setInterval(() => {
    countdown.value -= 1
    if (countdown.value <= 0) resetForm()
  }, 1000)
}

function resetForm() {
  clearInterval(countdownTimer)
  submitted.value = false
  referenceNo.value = ''
  errorMessage.value = ''
  honeypot.value = ''
  employeeQuery.value = ''
  employeeResults.value = []
  Object.assign(form, {
    nominee_user_id: '', nominee_name: '', nominee_title: '', nominee_department: '',
    nominee_address: '', nominee_contact_number: '', date_submitted: new Date().toISOString().slice(0, 10),
    nominator_name: '', nominator_address: '', nominator_contact_number: '', nominator_email: '',
    activity_conducted: '', activity_date: '', venue_location: '', other_information: '',
    nominator_signature_path: '',
  })
  sigPad.value?.clear()
  // Reload to get a fresh formToken (min-fill-time check resets for the next visitor).
  window.location.reload()
}

onUnmounted(() => clearInterval(countdownTimer))
</script>

<style scoped>
.kiosk-shell {
  min-height: 100vh;
  background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 40%);
  display: flex;
  flex-direction: column;
}
.kiosk-header {
  background: linear-gradient(135deg, #1447c0, #4338ca);
  padding: 28px 32px;
  display: flex;
  align-items: center;
  gap: 16px;
}
.kiosk-main {
  flex: 1;
  display: flex;
  justify-content: center;
  padding: 32px 16px 48px;
}
.kiosk-card {
  width: 100%;
  max-width: 780px;
  background: #fff;
  border-radius: 24px;
  box-shadow: 0 20px 50px -12px rgba(30, 41, 59, 0.15);
  padding: 40px;
}
.kiosk-section-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 14px;
  padding-bottom: 10px;
  border-bottom: 2px solid #eef2ff;
}
.kiosk-label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  color: #475569;
  margin-bottom: 4px;
}
.kiosk-input {
  width: 100%;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px 16px;
  font-size: 1rem;
  background: #fff;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.kiosk-input:focus {
  outline: none;
  border-color: #4f46e5;
  box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
}
.kiosk-btn-primary {
  background: linear-gradient(135deg, #4338ca, #4f46e5);
  color: #fff;
  font-weight: 700;
  font-size: 1.05rem;
  padding: 16px 28px;
  border-radius: 14px;
  border: none;
  cursor: pointer;
  transition: transform 0.1s, box-shadow 0.15s;
  box-shadow: 0 10px 25px -8px rgba(67, 56, 202, 0.5);
}
.kiosk-btn-primary:hover:not(:disabled) { transform: translateY(-1px); }
.kiosk-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.kiosk-footer {
  text-align: center;
  font-size: 0.75rem;
  color: #94a3b8;
  padding: 20px;
}
</style>
