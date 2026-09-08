<template>
  <AdminLayout :title="'Gantimpala Agad — ' + (nomination.reference_no ?? ('#' + nomination.id))">
    <div class="mx-auto max-w-4xl space-y-6">
      <!-- Back + Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
          <AppButton as="link" variant="ghost" size="sm" :href="route('rewards.gantimpala.index')" aria-label="Back to Gantimpala Agad">←</AppButton>
          <div>
            <h1 class="font-heading text-xl font-semibold text-slate-800">{{ nomination.nominee_name }}</h1>
            <p class="text-sm text-slate-500">{{ nomination.reference_no ?? ('#' + nomination.id) }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <AppBadge :color="nomination.source === 'kiosk' ? 'purple' : 'slate'" class="capitalize">{{ nomination.source === 'kiosk' ? 'Public Kiosk' : 'Atlas' }}</AppBadge>
          <AppBadge :color="statusColor(nomination.status)" class="capitalize">{{ nomination.status.replace('_', ' ') }}</AppBadge>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Main Info -->
        <div class="space-y-4 lg:col-span-2">
          <!-- Employee/Unit Commended -->
          <AppCard title="Employee / Unit Commended" :padded="false">
            <div class="p-5">
              <dl class="grid grid-cols-2 gap-3 text-sm">
                <div>
                  <dt class="text-slate-500 text-xs">Name</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ nomination.nominee_name }}</dd>
                </div>
                <div v-if="nomination.nominee">
                  <dt class="text-slate-500 text-xs">Linked Atlas Account</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ nomination.nominee.name }} ({{ nomination.nominee.email }})</dd>
                </div>
                <div>
                  <dt class="text-slate-500 text-xs">Title</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ nomination.nominee_title ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-slate-500 text-xs">Department</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ nomination.nominee_department ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-slate-500 text-xs">Address</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ nomination.nominee_address ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-slate-500 text-xs">Contact Number</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ nomination.nominee_contact_number ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-slate-500 text-xs">Date Submitted</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ formatDate(nomination.date_submitted) }}</dd>
                </div>
              </dl>
            </div>
          </AppCard>

          <!-- Nominator / Commendation Source -->
          <AppCard title="Background of Commendation" :padded="false">
            <div class="p-5">
              <dl class="grid grid-cols-2 gap-3 text-sm">
                <div>
                  <dt class="text-slate-500 text-xs">Nominator Name</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ nomination.nominator_name }}</dd>
                </div>
                <div v-if="nomination.nominator">
                  <dt class="text-slate-500 text-xs">Linked Atlas Account</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ nomination.nominator.name }}</dd>
                </div>
                <div>
                  <dt class="text-slate-500 text-xs">Address</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ nomination.nominator_address ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-slate-500 text-xs">Contact Number</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ nomination.nominator_contact_number ?? '—' }}</dd>
                </div>
                <div>
                  <dt class="text-slate-500 text-xs">E-mail</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ nomination.nominator_email ?? '—' }}</dd>
                </div>
              </dl>
            </div>
          </AppCard>

          <!-- Commendable Action -->
          <AppCard title="Details of Commendable Action" :padded="false">
            <div class="p-5">
              <dl class="grid grid-cols-2 gap-3 text-sm">
                <div class="col-span-2">
                  <dt class="text-slate-500 text-xs">Activity Conducted / Service Provided</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ nomination.activity_conducted }}</dd>
                </div>
                <div>
                  <dt class="text-slate-500 text-xs">Date</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ formatDate(nomination.activity_date) }}</dd>
                </div>
                <div>
                  <dt class="text-slate-500 text-xs">Venue / Location</dt>
                  <dd class="font-medium text-slate-800 mt-0.5">{{ nomination.venue_location ?? '—' }}</dd>
                </div>
              </dl>
              <div class="mt-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Other Information</p>
                <p class="mt-1 text-sm text-slate-700 whitespace-pre-wrap">{{ nomination.other_information ?? '—' }}</p>
              </div>
            </div>
          </AppCard>

          <!-- Signatures -->
          <AppCard title="Certification & Signatures" :padded="false">
            <div class="p-5 grid grid-cols-2 gap-4">
              <div class="text-center">
                <div class="h-16 flex items-center justify-center border border-dashed border-slate-200 rounded-lg mb-2">
                  <img v-if="nomination.nominator_signature_path" :src="signatureUrl('nominator')" class="max-h-14" alt="Nominator signature" />
                  <span v-else class="text-xs text-slate-400">No signature captured</span>
                </div>
                <p class="text-sm font-medium text-slate-800">{{ nomination.nominator_name }}</p>
                <p class="text-xs text-slate-500">Nominator</p>
              </div>
              <div class="text-center">
                <div class="h-16 flex items-center justify-center border border-dashed border-slate-200 rounded-lg mb-2">
                  <img v-if="nomination.supervisor_signature_path" :src="signatureUrl('supervisor')" class="max-h-14" alt="Supervisor signature" />
                  <span v-else class="text-xs text-slate-400">Not yet endorsed</span>
                </div>
                <p class="text-sm font-medium text-slate-800">{{ nomination.supervisor_name ?? '—' }}</p>
                <p class="text-xs text-slate-500">Endorsing Supervisor</p>
              </div>
            </div>
          </AppCard>

          <!-- HR Review Actions -->
          <AppCard v-if="canManage" title="HR Review" :padded="false">
            <div class="p-5 space-y-4">
              <div v-if="nomination.status === 'pending'">
                <AppButton size="sm" @click="markUnderReview">Move to Under Review</AppButton>
              </div>

              <div v-if="['pending','under_review'].includes(nomination.status)" class="space-y-3 border-t border-slate-100 pt-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Endorse (Supervisor Sign-off)</p>
                <form @submit.prevent="submitEndorse" class="space-y-3">
                  <AppInput v-model="endorseForm.supervisor_name" label="Supervisor Name" required :error="endorseForm.errors.supervisor_name" />
                  <SignaturePad ref="endorseSigPad" label="Supervisor Signature" />
                  <AppTextarea v-model="endorseForm.remarks" label="Remarks (optional)" :rows="2" />
                  <AppButton type="submit" :loading="endorseForm.processing">Endorse Nomination</AppButton>
                </form>
              </div>

              <div v-if="nomination.status === 'endorsed'" class="space-y-3 border-t border-slate-100 pt-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Final Decision</p>
                <form @submit.prevent="submitDecide" class="space-y-3">
                  <div class="flex gap-2">
                    <AppButton type="button" variant="success" @click="decide('approved')" :loading="decideForm.processing">Approve</AppButton>
                    <AppButton type="button" variant="danger" @click="decide('rejected')" :loading="decideForm.processing">Reject</AppButton>
                  </div>
                  <AppTextarea v-model="decideForm.remarks" label="Remarks (optional)" :rows="2" />
                </form>
              </div>

              <div v-if="['approved','rejected'].includes(nomination.status)" class="border-t border-slate-100 pt-4">
                <AppButton size="sm" variant="secondary" @click="archive">Archive</AppButton>
              </div>
            </div>
          </AppCard>
        </div>

        <!-- Sidebar -->
        <div class="space-y-4">
          <AppCard title="Actions" :padded="false">
            <div class="p-5 space-y-2">
              <AppButton as="link" class="w-full justify-center" :href="route('rewards.gantimpala.pdf', nomination.id)" target="_blank">
                🖨️ Print Form 4 (PDF)
              </AppButton>
            </div>
          </AppCard>

          <AppCard title="Activity Log" :padded="false">
            <div class="p-5">
              <div v-if="nomination.logs?.length" class="space-y-3">
                <div v-for="log in nomination.logs" :key="log.id" class="border-l-2 border-indigo-200 pl-3">
                  <p class="text-xs font-semibold text-indigo-700 capitalize">{{ log.action.replace(/_/g, ' ') }}</p>
                  <p class="text-xs text-slate-600">{{ log.actor?.name ?? 'External / Kiosk' }}</p>
                  <p v-if="log.notes" class="text-xs text-slate-500 italic">{{ log.notes }}</p>
                  <p class="text-xs text-slate-400">{{ formatDateTime(log.acted_at) }}</p>
                </div>
              </div>
              <p v-else class="text-xs text-slate-400">No activity yet.</p>
            </div>
          </AppCard>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { usePage, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import SignaturePad from '@/Components/SignaturePad.vue'

const props = defineProps({ nomination: Object })

const page = usePage()
const canManage = page.props.auth?.user?.permissions?.includes('rewards.gantimpala.manage') ?? false

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}

function formatDateTime(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function statusColor(s) {
  return {
    pending: 'amber',
    under_review: 'blue',
    endorsed: 'indigo',
    approved: 'green',
    rejected: 'red',
    archived: 'slate',
  }[s] ?? 'slate'
}

function signatureUrl(who) {
  // Signatures are private S3 objects — served through the existing auth-gated storage proxy.
  const path = who === 'nominator' ? props.nomination.nominator_signature_path : props.nomination.supervisor_signature_path
  return path ? route('storage.proxy', { path }) : ''
}

// ── Move to Under Review ───────────────────────────────────────────────────
function markUnderReview() {
  useForm({}).post(route('rewards.gantimpala.under-review', props.nomination.id))
}

// ── Endorse ─────────────────────────────────────────────────────────────────
const endorseSigPad = ref(null)
const endorseForm = useForm({
  supervisor_name: '',
  supervisor_signature_path: '',
  remarks: '',
})

async function submitEndorse() {
  endorseForm.supervisor_signature_path = endorseSigPad.value?.getDataUrl() ?? ''
  endorseForm.post(route('rewards.gantimpala.endorse', props.nomination.id))
}

// ── Decide ──────────────────────────────────────────────────────────────────
const decideForm = useForm({ decision: '', remarks: '' })

function decide(decision) {
  decideForm.decision = decision
  decideForm.post(route('rewards.gantimpala.decide', props.nomination.id))
}

// ── Archive ─────────────────────────────────────────────────────────────────
function archive() {
  useForm({}).post(route('rewards.gantimpala.archive', props.nomination.id))
}
</script>
