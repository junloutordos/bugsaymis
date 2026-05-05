<script setup>
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  CalendarDaysIcon,
  MapPinIcon,
  ClockIcon,
  UserIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClipboardDocumentCheckIcon,
  ClipboardDocumentListIcon,
  ArrowDownTrayIcon,
  ArrowLeftIcon,
  PhotoIcon,
  DocumentTextIcon,
  PaperClipIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  activity:     Object,
  participant:  Object,
  evaluated:    Boolean,
  evaluate_url: String,
  cert_url:     String,
})

function formatDate(d) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', {
    year: 'numeric', month: 'long', day: 'numeric',
  })
}

function dateRange(a) {
  if (!a.end_date || a.end_date === a.start_date) return formatDate(a.start_date)
  return `${formatDate(a.start_date)} – ${formatDate(a.end_date)}`
}

function timeRange(a) {
  if (!a.start_time) return null
  const fmt = (t) => {
    const [h, m] = t.split(':')
    const hr = parseInt(h)
    return `${hr % 12 || 12}:${m} ${hr < 12 ? 'AM' : 'PM'}`
  }
  return a.end_time ? `${fmt(a.start_time)} – ${fmt(a.end_time)}` : fmt(a.start_time)
}

function storageUrl(path) {
  if (!path) return null
  if (path.startsWith('http')) return path
  return `https://crcmis-mis-storage.s3.ap-southeast-1.amazonaws.com/${path}`
}
</script>

<template>
  <Head :title="activity.title" />
  <AdminLayout :title="activity.title">
    <div class="p-6 space-y-5 max-w-4xl">

      <!-- Back -->
      <div>
        <a :href="route('ams.my-activities.index')"
           class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-indigo-600 transition-colors">
          <ArrowLeftIcon class="w-4 h-4" />
          Back to My Activities
        </a>
      </div>

      <!-- Status banner -->
      <div class="bg-white rounded-xl border shadow-sm overflow-hidden"
           :class="participant.attended === 'yes' ? 'border-green-200' : 'border-red-200'">
        <div class="px-5 py-4 flex flex-wrap items-center gap-4"
             :class="participant.attended === 'yes' ? 'bg-green-50' : 'bg-red-50'">

          <!-- Attendance -->
          <div class="flex items-center gap-2">
            <CheckCircleIcon v-if="participant.attended === 'yes'" class="w-5 h-5 text-green-600" />
            <XCircleIcon     v-else                                class="w-5 h-5 text-red-500" />
            <span class="text-sm font-semibold"
                  :class="participant.attended === 'yes' ? 'text-green-700' : 'text-red-600'">
              {{ participant.attended === 'yes' ? 'You attended this activity' : 'Marked absent for this activity' }}
            </span>
          </div>

          <div class="flex-1" />

          <!-- Hours -->
          <div v-if="participant.attended === 'yes' && participant.hours_attended > 0"
               class="flex items-center gap-1.5 text-sm text-slate-600">
            <ClockIcon class="w-4 h-4 text-slate-400" />
            {{ participant.hours_attended }} hrs attended
          </div>

          <!-- Evaluated badge -->
          <span v-if="evaluated"
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700">
            <ClipboardDocumentCheckIcon class="w-3.5 h-3.5" /> Evaluated
          </span>
          <span v-else-if="participant.attended === 'yes'"
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
            <ClipboardDocumentListIcon class="w-3.5 h-3.5" /> Not Yet Evaluated
          </span>

          <!-- Certificate badge -->
          <span v-if="participant.has_certificate"
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
            Certificate Ready
          </span>
        </div>

        <!-- Action buttons -->
        <div v-if="evaluate_url && !evaluated || cert_url"
             class="px-5 py-3 border-t flex flex-wrap gap-3"
             :class="participant.attended === 'yes' ? 'border-green-100' : 'border-red-100'">
          <a v-if="!evaluated && evaluate_url && participant.attended === 'yes'"
             :href="evaluate_url"
             class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition-colors">
            <ClipboardDocumentListIcon class="w-4 h-4" />
            Evaluate This Activity
          </a>
          <a v-if="cert_url"
             :href="cert_url"
             target="_blank"
             class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition-colors">
            <ArrowDownTrayIcon class="w-4 h-4" />
            Download Certificate
          </a>
        </div>
      </div>

      <!-- Activity details card -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">

        <!-- Banner -->
        <div v-if="activity.banner" class="w-full h-52 bg-slate-100 overflow-hidden">
          <img :src="storageUrl(activity.banner)"
               alt="Activity banner"
               class="w-full h-full object-contain" />
        </div>
        <div v-else class="w-full h-32 bg-slate-50 flex items-center justify-center border-b border-slate-100">
          <PhotoIcon class="w-12 h-12 text-slate-300" />
        </div>

        <!-- Header -->
        <div class="bg-gradient-to-r from-indigo-600 to-indigo-500 px-6 py-4 text-white">
          <h1 class="text-lg font-bold leading-snug">{{ activity.title }}</h1>
        </div>

        <!-- Details grid -->
        <div class="divide-y divide-slate-50">

          <!-- Date -->
          <div class="flex items-start gap-3 px-6 py-4">
            <CalendarDaysIcon class="w-5 h-5 text-slate-400 mt-0.5 flex-shrink-0" />
            <div>
              <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-0.5">Date</p>
              <p class="text-sm text-slate-700">{{ dateRange(activity) }}</p>
            </div>
          </div>

          <!-- Time -->
          <div v-if="timeRange(activity)" class="flex items-start gap-3 px-6 py-4">
            <ClockIcon class="w-5 h-5 text-slate-400 mt-0.5 flex-shrink-0" />
            <div>
              <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-0.5">Time</p>
              <p class="text-sm text-slate-700">{{ timeRange(activity) }}</p>
            </div>
          </div>

          <!-- Hours -->
          <div v-if="activity.total_hours" class="flex items-start gap-3 px-6 py-4">
            <ClockIcon class="w-5 h-5 text-slate-400 mt-0.5 flex-shrink-0" />
            <div>
              <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-0.5">Total Hours</p>
              <p class="text-sm text-slate-700">{{ activity.total_hours }} hrs</p>
            </div>
          </div>

          <!-- Venue -->
          <div v-if="activity.venue" class="flex items-start gap-3 px-6 py-4">
            <MapPinIcon class="w-5 h-5 text-slate-400 mt-0.5 flex-shrink-0" />
            <div>
              <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-0.5">Venue</p>
              <p class="text-sm text-slate-700">{{ activity.venue }}</p>
            </div>
          </div>

          <!-- Resource Person -->
          <div v-if="activity.resource_person" class="flex items-start gap-3 px-6 py-4">
            <UserIcon class="w-5 h-5 text-slate-400 mt-0.5 flex-shrink-0" />
            <div>
              <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-0.5">Resource Person</p>
              <p class="text-sm text-slate-700">{{ activity.resource_person }}</p>
            </div>
          </div>

        </div>
      </div>

      <!-- Attachments -->
      <div v-if="activity.special_order || activity.activity_report || activity.official_documentation"
           class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-3 bg-slate-50 border-b border-slate-100">
          <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Attachments</h2>
        </div>
        <div class="divide-y divide-slate-50">
          <div v-if="activity.special_order" class="flex items-center gap-3 px-6 py-3">
            <PaperClipIcon class="w-4 h-4 text-slate-400 flex-shrink-0" />
            <span class="text-sm text-slate-600 flex-1">Special Order</span>
            <a :href="storageUrl(activity.special_order)" target="_blank"
               class="text-indigo-600 hover:text-indigo-800 text-sm font-medium transition-colors">View</a>
          </div>
          <div v-if="activity.activity_report" class="flex items-center gap-3 px-6 py-3">
            <DocumentTextIcon class="w-4 h-4 text-slate-400 flex-shrink-0" />
            <span class="text-sm text-slate-600 flex-1">Activity Report</span>
            <a :href="storageUrl(activity.activity_report)" target="_blank"
               class="text-indigo-600 hover:text-indigo-800 text-sm font-medium transition-colors">View</a>
          </div>
          <div v-if="activity.official_documentation" class="flex items-center gap-3 px-6 py-3">
            <PaperClipIcon class="w-4 h-4 text-slate-400 flex-shrink-0" />
            <span class="text-sm text-slate-600 flex-1">Official Documentation</span>
            <a :href="storageUrl(activity.official_documentation)" target="_blank"
               class="text-indigo-600 hover:text-indigo-800 text-sm font-medium transition-colors">View</a>
          </div>
        </div>
      </div>

      <!-- Certificate notice when not yet evaluated -->
      <div v-if="participant.attended === 'yes' && !evaluated && !cert_url"
           class="bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 text-sm text-amber-800">
        <strong>Certificate not yet available.</strong>
        Your certificate will be available for download once you have completed the evaluation for this activity.
      </div>

    </div>
  </AdminLayout>
</template>
