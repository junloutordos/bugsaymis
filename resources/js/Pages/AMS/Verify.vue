<script setup>
import { Head } from '@inertiajs/vue3'
import { CheckBadgeIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  activity: Object,
  name:     String,
  verified: Boolean,
})

function formatDate(d) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', {
    year: 'numeric', month: 'long', day: 'numeric',
  })
}
</script>

<template>
  <Head title="Certificate Verification" />

  <div class="min-h-screen bg-gradient-to-br from-indigo-50 to-blue-100 flex items-center justify-center p-6">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-8 text-center">

      <div class="flex justify-center mb-5">
        <div class="w-16 h-16 rounded-full bg-success-100 flex items-center justify-center">
          <CheckBadgeIcon class="w-9 h-9 text-success-600" />
        </div>
      </div>

      <h1 class="text-xl font-bold text-slate-800 mb-1">Certificate Verified</h1>
      <p class="text-sm text-slate-500 mb-6">This certificate is authentic and system-generated.</p>

      <div class="text-left bg-slate-50 rounded-xl p-4 space-y-3 text-sm">
        <div>
          <span class="text-xs text-slate-400 uppercase tracking-wide block">Awardee</span>
          <span class="font-semibold text-slate-800">{{ name }}</span>
        </div>
        <div>
          <span class="text-xs text-slate-400 uppercase tracking-wide block">Activity</span>
          <span class="font-medium text-slate-700">{{ activity.title }}</span>
        </div>
        <div v-if="activity.venue">
          <span class="text-xs text-slate-400 uppercase tracking-wide block">Venue</span>
          <span class="text-slate-700">{{ activity.venue }}</span>
        </div>
        <div>
          <span class="text-xs text-slate-400 uppercase tracking-wide block">Date</span>
          <span class="text-slate-700">
            {{ formatDate(activity.start_date) }}
            <span v-if="activity.end_date && activity.end_date !== activity.start_date">
              – {{ formatDate(activity.end_date) }}
            </span>
          </span>
        </div>
      </div>

      <p class="mt-6 text-xs text-slate-400">
        Philippine Science High School – Caraga Region Campus
      </p>

    </div>
  </div>
</template>
