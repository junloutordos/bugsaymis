<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  tapStatus:   String,
  tappedAt:    String,
  lateMinutes: Number,
  classroom:   Object,
  schedule:    Object,
  teacherName: String,
})

const statusConfig = computed(() => {
  switch (props.tapStatus) {
    case 'on_time':
      return {
        icon: '✓',
        iconBg: 'bg-emerald-100',
        iconColor: 'text-emerald-600',
        title: 'Tapped In',
        titleColor: 'text-emerald-700',
        badge: 'On Time',
        badgeBg: 'bg-emerald-100 text-emerald-700',
      }
    case 'late':
      return {
        icon: '⏱',
        iconBg: 'bg-amber-100',
        iconColor: 'text-amber-600',
        title: 'Tapped In — Late',
        titleColor: 'text-amber-700',
        badge: `Late by ${props.lateMinutes} min`,
        badgeBg: 'bg-amber-100 text-amber-700',
      }
    case 'already_tapped':
      return {
        icon: '✓',
        iconBg: 'bg-blue-100',
        iconColor: 'text-blue-600',
        title: 'Already Tapped In',
        titleColor: 'text-blue-700',
        badge: `At ${props.tappedAt}`,
        badgeBg: 'bg-blue-100 text-blue-700',
      }
    case 'no_match':
      return {
        icon: '?',
        iconBg: 'bg-slate-100',
        iconColor: 'text-slate-500',
        title: 'No Class Scheduled',
        titleColor: 'text-slate-700',
        badge: 'No match found',
        badgeBg: 'bg-slate-100 text-slate-600',
      }
    case 'room_not_found':
      return {
        icon: '!',
        iconBg: 'bg-red-100',
        iconColor: 'text-red-600',
        title: 'Unknown Room',
        titleColor: 'text-red-700',
        badge: 'Invalid NFC tag',
        badgeBg: 'bg-red-100 text-red-700',
      }
    default:
      return {
        icon: '?',
        iconBg: 'bg-slate-100',
        iconColor: 'text-slate-500',
        title: 'Unknown Status',
        titleColor: 'text-slate-700',
        badge: '',
        badgeBg: '',
      }
  }
})

function formatTime(t) {
  if (!t) return ''
  const [h, m] = t.split(':')
  const hour = parseInt(h)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const h12  = hour % 12 || 12
  return `${h12}:${m} ${ampm}`
}
</script>

<template>
  <Head title="Class Tap-In" />

  <div class="min-h-screen bg-slate-50 flex flex-col items-center justify-center px-4 py-8">
    <div class="w-full max-w-sm">

      <!-- School branding -->
      <div class="text-center mb-8">
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-widest">PSHS-CRC MIS</p>
        <p class="text-sm text-slate-500 mt-1">Class Attendance</p>
      </div>

      <!-- Status card -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 text-center">

        <!-- Icon -->
        <div :class="['mx-auto w-20 h-20 rounded-full flex items-center justify-center text-3xl mb-4', statusConfig.iconBg]">
          <span :class="statusConfig.iconColor">{{ statusConfig.icon }}</span>
        </div>

        <!-- Title -->
        <h1 :class="['text-xl font-bold mb-2', statusConfig.titleColor]">
          {{ statusConfig.title }}
        </h1>

        <!-- Status badge -->
        <span
          v-if="statusConfig.badge"
          :class="['inline-flex px-3 py-1 rounded-full text-sm font-medium mb-5', statusConfig.badgeBg]"
        >
          {{ statusConfig.badge }}
        </span>

        <!-- Teacher name -->
        <p class="text-sm text-slate-500 mb-5">{{ teacherName }}</p>

        <!-- Divider -->
        <hr class="border-slate-100 mb-5" />

        <!-- Classroom info -->
        <div v-if="classroom" class="mb-4">
          <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold mb-1">Room</p>
          <p class="text-base font-semibold text-slate-800">{{ classroom.name }}</p>
          <p class="text-sm text-slate-500">{{ classroom.code }}</p>
        </div>

        <!-- Schedule info -->
        <div v-if="schedule" class="bg-slate-50 rounded-xl p-4 text-left space-y-2">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold">Subject</p>
              <p class="text-sm font-semibold text-slate-800">{{ schedule.subject }}</p>
              <p class="text-xs text-slate-500">{{ schedule.subjectCode }}</p>
            </div>
            <div class="text-right">
              <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold">Section</p>
              <p class="text-sm font-semibold text-slate-800">{{ schedule.section }}</p>
            </div>
          </div>
          <div class="pt-2 border-t border-slate-200">
            <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold mb-1">Time</p>
            <p class="text-sm font-medium text-slate-700">
              {{ formatTime(schedule.startTime) }} – {{ formatTime(schedule.endTime) }}
            </p>
          </div>
        </div>

        <!-- No match explanation -->
        <div
          v-if="tapStatus === 'no_match'"
          class="bg-slate-50 rounded-xl p-4 text-left"
        >
          <p class="text-sm text-slate-600">
            No class schedule was found for you in this room at this time.
            Please check your schedule or contact your Academic Unit Head.
          </p>
        </div>

        <!-- Tap time -->
        <div v-if="tappedAt && tapStatus !== 'no_match'" class="mt-4">
          <p class="text-xs text-slate-400">Recorded at {{ tappedAt }}</p>
        </div>

      </div>

      <!-- Footer note -->
      <p class="text-center text-xs text-slate-400 mt-6">
        You may close this tab.
      </p>

    </div>
  </div>
</template>
