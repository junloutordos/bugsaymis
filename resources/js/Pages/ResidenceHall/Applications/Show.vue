<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import { confirmAction } from '@/Composables/useConfirm.js'
import { ArrowLeftIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  application: Object,
  student: Object,
  existingIntern: Object,
})

const evaluateForm = ref({
  status: props.application.status === 'evaluated' ? 'evaluated' : '',
  evaluation_notes: props.application.evaluation_notes || '',
  rejection_reason: props.application.rejection_reason || '',
})

const approveForm = ref({
  status: '',
  rejection_reason: '',
})

function submitEvaluation() {
  router.post(route('rh.applications.evaluate', props.application.id), evaluateForm.value, {
    preserveScroll: true,
  })
}

async function submitApproval() {
  const action = approveForm.value.status === 'approved' ? 'Approve' : 'Reject'
  const confirmed = await confirmAction({ title: `${action} this application?`, confirmText: action })
  if (!confirmed) return
  router.post(route('rh.applications.approve', props.application.id), approveForm.value, {
    preserveScroll: true,
  })
}

const fmtDate = (d) => d
  ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
  : '—'

const statusColor = (s) => ({
  pending:   'amber',
  evaluated: 'blue',
  approved:  'green',
  rejected:  'red',
  waitlisted: 'slate',
}[s] || 'slate')
</script>

<template>
  <Head title="Application Review" />
  <AdminLayout title="Residence Hall">
    <div class="space-y-5 max-w-3xl">

      <!-- Back + Title -->
      <div class="flex items-center gap-3">
        <AppButton as="link" variant="secondary" size="sm" :href="route('rh.applications.index')">
          <ArrowLeftIcon class="w-4 h-4" />
        </AppButton>
        <div>
          <h1 class="font-heading text-xl font-semibold text-slate-800">Application Review</h1>
          <p class="text-sm text-slate-500">SSM 5.1 — Evaluation of RH Accommodation Application</p>
        </div>
        <AppBadge :color="statusColor(application.status)" class="ml-auto capitalize">{{ application.status }}</AppBadge>
      </div>

      <!-- Student Info -->
      <AppCard title="Student Information">
        <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
          <div>
            <span class="text-slate-500">Name</span>
            <p class="font-medium text-slate-800 mt-0.5">{{ student.name || '—' }}</p>
          </div>
          <div>
            <span class="text-slate-500">PISAY ID</span>
            <p class="font-medium text-slate-800 mt-0.5">{{ student.barcode || '—' }}</p>
          </div>
          <div>
            <span class="text-slate-500">Grade & Section</span>
            <p class="font-medium text-slate-800 mt-0.5">{{ student.grade_section || application.grade_level || '—' }}</p>
          </div>
          <div>
            <span class="text-slate-500">Sex</span>
            <p class="font-medium text-slate-800 mt-0.5">{{ student.sex || '—' }}</p>
          </div>
        </div>
      </AppCard>

      <!-- Application Details -->
      <AppCard title="Application Details">
        <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
          <div>
            <span class="text-slate-500">Preferred Hall</span>
            <p class="font-medium mt-0.5">
              <AppBadge :color="application.preferred_hall === 'BRH' ? 'indigo' : 'purple'">{{ application.preferred_hall }}</AppBadge>
            </p>
          </div>
          <div>
            <span class="text-slate-500">Scholarship Category</span>
            <p class="font-medium text-slate-800 mt-0.5">{{ application.scholarship_category || '—' }}</p>
          </div>
          <div>
            <span class="text-slate-500">Home Province</span>
            <p class="font-medium text-slate-800 mt-0.5">{{ application.home_province || '—' }}</p>
          </div>
          <div>
            <span class="text-slate-500">Estimated Distance</span>
            <p class="font-medium text-slate-800 mt-0.5">
              {{ application.estimated_distance_km != null ? application.estimated_distance_km + ' km' : '—' }}
            </p>
          </div>
          <div class="col-span-2">
            <span class="text-slate-500">Foster Parent (Required)</span>
            <p class="font-medium text-slate-800 mt-0.5">
              {{ application.foster_parent_name || '—' }}
              <span v-if="application.foster_parent_contact" class="text-slate-500 font-normal"> · {{ application.foster_parent_contact }}</span>
            </p>
            <p v-if="application.foster_parent_address" class="text-slate-500 text-xs mt-0.5">{{ application.foster_parent_address }}</p>
          </div>
          <div>
            <span class="text-slate-500">Date Filed</span>
            <p class="font-medium text-slate-800 mt-0.5">{{ fmtDate(application.created_at) }}</p>
          </div>
        </div>
      </AppCard>

      <!-- Already an intern -->
      <div v-if="existingIntern" class="flex items-center gap-3 bg-success-50 border border-success-100 rounded-xl p-4">
        <CheckCircleIcon class="w-5 h-5 text-success-600 flex-shrink-0" />
        <p class="text-sm text-success-700">
          This student has already been enrolled as an intern.
          <Link :href="route('rh.interns.show', existingIntern.id)" class="font-medium underline">View intern record</Link>
        </p>
      </div>

      <!-- Evaluation Panel (RH Committee) -->
      <AppCard v-if="!existingIntern && ['pending', 'evaluated', 'waitlisted'].includes(application.status)"
           title="RH Committee Evaluation">
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Recommendation</label>
            <select v-model="evaluateForm.status"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">— Select —</option>
              <option value="evaluated">Recommend for Approval</option>
              <option value="waitlisted">Waitlist</option>
              <option value="rejected">Reject</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Evaluation Notes</label>
            <textarea v-model="evaluateForm.evaluation_notes" rows="3"
                      class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500"
                      placeholder="Notes on accommodation priority, grade level, distance, etc."></textarea>
          </div>
          <div v-if="evaluateForm.status === 'rejected'">
            <label class="block text-xs font-medium text-slate-600 mb-1">Rejection Reason</label>
            <textarea v-model="evaluateForm.rejection_reason" rows="2"
                      class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
          </div>
          <div class="flex justify-end">
            <AppButton :disabled="!evaluateForm.status" @click="submitEvaluation">Submit Evaluation</AppButton>
          </div>
        </div>
      </AppCard>

      <!-- Approval Panel (Campus Director) -->
      <AppCard v-if="!existingIntern && application.status === 'evaluated'" title="Campus Director Approval">
        <div v-if="application.evaluation_notes" class="mb-3 p-3 bg-slate-50 rounded-lg text-sm text-slate-700">
          <span class="text-xs text-slate-500 block mb-1">Committee Notes</span>
          {{ application.evaluation_notes }}
        </div>
        <div class="flex gap-3">
          <AppButton block variant="success" @click="approveForm.status = 'approved'; submitApproval()">
            <CheckCircleIcon class="w-4 h-4" /> Approve
          </AppButton>
          <AppButton block variant="danger" @click="approveForm.status = 'rejected'; submitApproval()">
            <XCircleIcon class="w-4 h-4" /> Reject
          </AppButton>
        </div>
      </AppCard>

      <!-- Approved — enroll as intern -->
      <div v-if="!existingIntern && application.status === 'approved'"
           class="bg-success-50 border border-success-100 rounded-xl p-5">
        <h2 class="text-sm font-semibold text-success-700 mb-2">Application Approved</h2>
        <p class="text-sm text-success-700 mb-4">
          Approved on {{ fmtDate(application.approved_at) }}. You can now assign a room and enroll this student as an intern.
        </p>
        <AppButton as="link" variant="success" :href="route('rh.interns.index')">Go to Intern Roster</AppButton>
      </div>

    </div>
  </AdminLayout>
</template>
