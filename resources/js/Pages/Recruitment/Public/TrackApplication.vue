<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { storageUrl } from "@/Composables/useStorage.js"

const props = defineProps({
  result: { type: Object, default: null },
})

const form = useForm({
  email:          '',
  application_id: '',
})

const submit = () => {
  form.post(route('recruitment.public.track'), { preserveState: false })
}

const stageIcons = {
  submitted: '📋',
  screening: '🔍',
  exam:      '📝',
  interview: '🎤',
  ranking:   '📊',
  selection: '✅',
  placement: '🎉',
}

const stageColors = {
  completed: { dot: 'bg-green-500', line: 'bg-green-400', badge: 'bg-green-100 text-green-700', label: 'text-gray-800' },
  current:   { dot: 'bg-blue-600 ring-4 ring-blue-200 animate-pulse', line: 'bg-gray-200', badge: 'bg-blue-100 text-blue-700', label: 'text-blue-700 font-bold' },
  pending:   { dot: 'bg-gray-200', line: 'bg-gray-200', badge: 'bg-gray-100 text-gray-400', label: 'text-gray-400' },
}

const terminalBadge = computed(() => {
  if (!props.result) return null
  const s = props.result.current_stage
  if (s === 'rejected')  return { text: 'Application Not Selected', cls: 'bg-red-100 text-red-700' }
  if (s === 'withdrawn') return { text: 'Application Withdrawn',    cls: 'bg-gray-100 text-gray-600' }
  if (s === 'placement') return { text: 'Congratulations — Placement Confirmed!', cls: 'bg-green-100 text-green-700' }
  return null
})

const docStatusColors = {
  pending:  'bg-yellow-100 text-yellow-700',
  verified: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
}
</script>

<template>
  <Head title="Track Application — BugsayMIS" />

  <div class="min-h-screen relative">
    <div class="fixed inset-0 bg-cover bg-center -z-10" :style="{ backgroundImage: `url(${storageUrl('bg.jpg')})` }" />
    <div class="fixed inset-0 bg-black/55 -z-10" />
    <!-- Header -->
    <div class="text-white py-12 px-4 text-center">
      <h1 class="text-3xl font-bold tracking-tight">Application Status Tracker</h1>
      <p class="mt-2 text-blue-200 text-sm">Enter your email and reference number to check your application status.</p>
      <div class="mt-4">
        <Link :href="route('recruitment.public.vacancies.index')"
              class="text-blue-200 hover:text-white text-sm underline">
          ← Back to Job Openings
        </Link>
      </div>
    </div>

    <div class="max-w-2xl mx-auto px-4 pb-16 space-y-6">

      <!-- Search form -->
      <div class="bg-white rounded-2xl shadow-xl p-6">
        <h2 class="text-base font-semibold text-gray-700 mb-4">Look up your application</h2>
        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Email Address</label>
            <input v-model="form.email" required type="email" placeholder="your@email.com"
                   class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
            <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">{{ form.errors.email }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Application Reference No.</label>
            <input v-model="form.application_id" required type="number" min="1" placeholder="e.g. 12"
                   class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
            <p v-if="form.errors.application_id" class="text-red-500 text-xs mt-1">{{ form.errors.application_id }}</p>
          </div>
          <button type="submit" :disabled="form.processing"
                  class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-lg font-semibold text-sm">
            {{ form.processing ? 'Searching…' : 'Track Application' }}
          </button>
        </form>
      </div>

      <!-- Results -->
      <template v-if="result">

        <!-- Application summary card -->
        <div class="bg-white rounded-2xl shadow-xl p-6">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Application #{{ result.application.id }}</p>
              <h2 class="text-xl font-bold text-gray-900 mt-0.5">
                {{ result.application.applicant?.last_name }}, {{ result.application.applicant?.first_name }}
              </h2>
              <p class="text-sm text-gray-600 mt-1 font-medium">{{ result.position }}</p>
              <p class="text-xs text-gray-400">{{ result.office }}</p>
              <p class="text-xs text-gray-400 mt-1">Applied: {{ result.applied_on }}</p>
            </div>
            <!-- Terminal badge -->
            <div v-if="terminalBadge"
                 class="px-3 py-1.5 rounded-full text-sm font-semibold"
                 :class="terminalBadge.cls">
              {{ terminalBadge.text }}
            </div>
            <div v-else
                 class="px-3 py-1.5 rounded-full text-sm font-semibold bg-blue-100 text-blue-700 capitalize">
              {{ result.current_stage.replace('_', ' ') }}
            </div>
          </div>

          <!-- Remarks if any -->
          <div v-if="result.remarks && result.is_terminal"
               class="mt-4 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 text-sm text-gray-600">
            <strong>Remarks:</strong> {{ result.remarks }}
          </div>
        </div>

        <!-- Timeline -->
        <div class="bg-white rounded-2xl shadow-xl p-6">
          <h3 class="text-base font-semibold text-gray-700 mb-6">Application Progress</h3>

          <!-- Terminal overlay for rejected/withdrawn -->
          <div v-if="result.is_terminal && result.current_stage !== 'placement'"
               class="mb-5 rounded-xl px-4 py-3 text-sm font-medium"
               :class="result.current_stage === 'rejected' ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-gray-50 border border-gray-200 text-gray-600'">
            <span v-if="result.current_stage === 'rejected'">
              ✗ Your application was not selected to proceed further. Thank you for your interest.
            </span>
            <span v-else>
              Your application has been marked as withdrawn.
            </span>
          </div>

          <div class="relative">
            <div v-for="(step, idx) in result.timeline" :key="step.stage"
                 class="flex gap-4 mb-0"
                 :class="idx < result.timeline.length - 1 ? 'pb-0' : ''">

              <!-- Dot + line -->
              <div class="flex flex-col items-center">
                <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 text-base z-10"
                     :class="stageColors[step.status].dot">
                  <span v-if="step.status === 'completed'" class="text-white text-sm font-bold">✓</span>
                  <span v-else-if="step.status === 'current'" class="text-white text-base">{{ stageIcons[step.stage] }}</span>
                  <span v-else class="text-gray-300 text-base">{{ stageIcons[step.stage] }}</span>
                </div>
                <div v-if="idx < result.timeline.length - 1"
                     class="w-0.5 h-10 flex-shrink-0 mt-1"
                     :class="step.status === 'completed' ? 'bg-green-400' : 'bg-gray-200'" />
              </div>

              <!-- Content -->
              <div class="pb-8 flex-1 min-w-0 pt-1.5">
                <div class="flex items-center gap-2">
                  <p class="text-sm font-medium" :class="stageColors[step.status].label">
                    {{ step.label }}
                  </p>
                  <span v-if="step.status === 'current'" class="text-xs px-2 py-0.5 rounded-full font-semibold"
                        :class="stageColors[step.status].badge">
                    Current Stage
                  </span>
                  <span v-else-if="step.status === 'completed'" class="text-xs px-2 py-0.5 rounded-full"
                        :class="stageColors[step.status].badge">
                    Completed
                  </span>
                </div>
                <p v-if="step.status === 'current' && !result.is_terminal" class="text-xs text-blue-600 mt-0.5">
                  Your application is currently being processed at this stage.
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Submitted documents -->
        <div v-if="result.documents?.length" class="bg-white rounded-2xl shadow-xl p-6">
          <h3 class="text-base font-semibold text-gray-700 mb-4">Submitted Documents</h3>
          <div class="space-y-2">
            <div v-for="doc in result.documents" :key="doc.id"
                 class="flex items-center justify-between p-3 rounded-lg border border-gray-100 bg-gray-50">
              <div class="flex items-center gap-3 min-w-0">
                <span class="text-xl flex-shrink-0">📄</span>
                <div class="min-w-0">
                  <p class="text-sm font-medium text-gray-700 capitalize truncate">
                    {{ doc.document_type.replace(/_/g, ' ') }}
                  </p>
                  <p v-if="doc.original_name" class="text-xs text-gray-400 truncate">{{ doc.original_name }}</p>
                </div>
              </div>
              <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                <span class="text-xs px-2 py-0.5 rounded-full font-medium capitalize"
                      :class="docStatusColors[doc.status] ?? 'bg-gray-100 text-gray-500'">
                  {{ doc.status }}
                </span>
                <a v-if="doc.drive_url" :href="doc.drive_url" target="_blank"
                   class="text-xs text-blue-600 hover:underline">View →</a>
              </div>
            </div>
          </div>
          <p class="mt-3 text-xs text-gray-400">Documents are reviewed by the HR Office. Status will update as each document is verified.</p>
        </div>

        <!-- Try another -->
        <div class="text-center">
          <button @click="form.reset(); $inertia.visit(route('recruitment.public.track'))"
                  class="text-white text-sm underline hover:text-blue-200">
            Track a different application
          </button>
        </div>

      </template>
    </div>
  </div>
</template>
