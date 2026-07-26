<script setup>
import { Head } from '@inertiajs/vue3'
import TapResultCard from '@/Components/TapResultCard.vue'

defineProps({
  tapStatus:   String,
  tappedAt:    String,
  lateMinutes: Number,
  classroom:   Object,
  schedule:    Object,
  teacherName: String,
})
</script>

<template>
  <Head title="Class Tap-In" />

  <div class="min-h-screen bg-slate-50 flex flex-col items-center justify-center px-4 py-8">
    <div class="w-full max-w-sm">

      <!-- School branding -->
      <div class="text-center mb-8">
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-widest">Atlas</p>
        <p class="text-sm text-slate-500 mt-1">Class Attendance</p>
      </div>

      <TapResultCard
        :tap-status="tapStatus"
        :tapped-at="tappedAt"
        :late-minutes="lateMinutes"
        :classroom="classroom"
        :schedule="schedule"
        :teacher-name="teacherName"
      />

      <!-- Footer note -->
      <p class="text-center text-xs text-slate-400 mt-6">
        You may close this tab.
      </p>

    </div>
  </div>
</template>
