<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
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

function submitApproval() {
  if (!confirm(`${approveForm.value.status === 'approved' ? 'Approve' : 'Reject'} this application?`)) return
  router.post(route('rh.applications.approve', props.application.id), approveForm.value, {
    preserveScroll: true,
  })
}

const fmtDate = (d) => d
  ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
  : '—'

const statusClass = (s) => ({
  pending:   'bg-amber-100 text-amber-700',
  evaluated: 'bg-sky-100 text-sky-700',
  approved:  'bg-emerald-100 text-emerald-700',
  rejected:  'bg-rose-100 text-rose-700',
  waitlisted: 'bg-slate-100 text-slate-600',
}[s] || 'bg-slate-100 text-slate-600')
</script>

<template>
  <Head title="Application Review" />
  <AdminLayout title="Residence Hall">
    <div class="space-y-5 max-w-3xl">

      <!-- Back + Title -->
      <div class="flex items-center gap-3">
        <Link :href="route('rh.applications.index')"
              class="p-2 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600">
          <ArrowLeftIcon class="w-4 h-4" />
        </Link>
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Application Review</h1>
          <p class="text-sm text-slate-500">SSM 5.1 — Evaluation of RH Accommodation Application</p>
        </div>
        <span :class="['ml-auto text-xs px-3 py-1 rounded-full font-semibold capitalize', statusClass(application.status)]">
          {{ application.status }}
        </span>
      </div>

      <!-- Student Info -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Student Information</h2>
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
      </div>

      <!-- Application Details -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Application Details</h2>
        <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
          <div>
            <span class="text-slate-500">Preferred Hall</span>
            <p class="font-medium mt-0.5">
              <span :class="['text-xs px-2 py-0.5 rounded-full font-medium',
                application.preferred_hall === 'BRH' ? 'bg-indigo-100 text-indigo-700' : 'bg-pink-100 text-pink-700']">
                {{ application.preferred_hall }}
              </span>
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
      </div>

      <!-- Already an intern -->
      <div v-if="existingIntern" class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 rounded-xl p-4">
        <CheckCircleIcon class="w-5 h-5 text-emerald-600 flex-shrink-0" />
        <p class="text-sm text-emerald-800">
          This student has already been enrolled as an intern.
          <Link :href="route('rh.interns.show', existingIntern.id)" class="font-medium underline">View intern record</Link>
        </p>
      </div>

      <!-- Evaluation Panel (RH Committee) -->
      <div v-if="!existingIntern && ['pending', 'evaluated', 'waitlisted'].includes(application.status)"
           class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">RH Committee Evaluation</h2>
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
            <button @click="submitEvaluation" :disabled="!evaluateForm.status"
                    class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium">
              Submit Evaluation
            </button>
          </div>
        </div>
      </div>

      <!-- Approval Panel (Campus Director) -->
      <div v-if="!existingIntern && application.status === 'evaluated'"
           class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Campus Director Approval</h2>
        <div v-if="application.evaluation_notes" class="mb-3 p-3 bg-slate-50 rounded-lg text-sm text-slate-700">
          <span class="text-xs text-slate-500 block mb-1">Committee Notes</span>
          {{ application.evaluation_notes }}
        </div>
        <div class="flex gap-3">
          <button @click="approveForm.status = 'approved'; submitApproval()"
                  class="flex-1 inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
            <CheckCircleIcon class="w-4 h-4" /> Approve
          </button>
          <button @click="approveForm.status = 'rejected'; submitApproval()"
                  class="flex-1 inline-flex items-center justify-center gap-2 border border-rose-200 text-rose-600 hover:bg-rose-50 px-4 py-2 rounded-lg text-sm font-medium">
            <XCircleIcon class="w-4 h-4" /> Reject
          </button>
        </div>
      </div>

      <!-- Approved — enroll as intern -->
      <div v-if="!existingIntern && application.status === 'approved'"
           class="bg-emerald-50 border border-emerald-200 rounded-xl p-5">
        <h2 class="text-sm font-semibold text-emerald-800 mb-2">Application Approved</h2>
        <p class="text-sm text-emerald-700 mb-4">
          Approved on {{ fmtDate(application.approved_at) }}. You can now assign a room and enroll this student as an intern.
        </p>
        <Link :href="route('rh.interns.index')"
              class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          Go to Intern Roster
        </Link>
      </div>

    </div>
  </AdminLayout>
</template>
