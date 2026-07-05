<script setup>
import { Head } from '@inertiajs/vue3'
import { CheckCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  application: Object,
})
</script>

<template>
  <Head title="Application Submitted — PSHS-CRC" />

  <div class="min-h-screen bg-gradient-to-br from-indigo-50 to-blue-50 flex items-center justify-center px-4">
    <div class="max-w-md w-full">
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 text-center">

        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-success-100 mb-5">
          <CheckCircleIcon class="w-9 h-9 text-success-600" />
        </div>

        <h1 class="text-xl font-bold text-slate-800 mb-2">Application Submitted!</h1>
        <p class="text-slate-500 text-sm mb-6">
          Your enrollment application has been received and is now under review by the Registrar's Office.
        </p>

        <div class="rounded-xl bg-indigo-50 border border-indigo-100 px-5 py-4 mb-6">
          <p class="text-xs text-indigo-500 font-medium uppercase tracking-wide mb-1">Reference Number</p>
          <p class="text-2xl font-bold text-indigo-700 font-mono tracking-wider">
            {{ application.reference_no }}
          </p>
        </div>

        <div class="text-left space-y-2 text-sm text-slate-600 mb-6">
          <div class="flex justify-between">
            <span class="text-slate-400">Applicant</span>
            <span class="font-medium">{{ application.full_name ?? `${application.lastname}, ${application.firstname}` }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-400">Grade</span>
            <span class="font-medium">Grade {{ application.grade_level }}</span>
          </div>
          <div v-if="application.email" class="flex justify-between">
            <span class="text-slate-400">Notification sent to</span>
            <span class="font-medium">{{ application.email }}</span>
          </div>
        </div>

        <div class="rounded-lg bg-warning-50 border border-warning-100 px-4 py-3 text-xs text-warning-700 text-left mb-6">
          <strong>Keep your reference number.</strong> You will need it for follow-up inquiries with the Registrar's Office.
          You will be notified by email once a decision has been made.
        </div>

        <a href="/enroll"
           class="inline-block text-sm text-indigo-600 hover:text-indigo-800 font-medium">
          Submit another application
        </a>

      </div>
      <p class="text-center text-xs text-slate-400 mt-4">
        Philippine Science High School – Caraga Region Campus
      </p>
    </div>
  </div>
</template>
