<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { BookOpenIcon, LockClosedIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ courses: Array })

function statusBadgeClass(status) {
  return status === 'published'
    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
    : 'bg-slate-100 text-slate-600 border-slate-200'
}
</script>

<template>
  <Head title="Learn — My Courses" />
  <AdminLayout title="Learn">
    <div class="max-w-5xl mx-auto py-6 px-4">
      <h1 class="text-lg font-semibold text-slate-800 mb-4">My Courses</h1>

      <div v-if="courses.length === 0" class="text-sm text-slate-500 border border-dashed border-slate-200 rounded-lg p-8 text-center">
        No courses yet — courses appear automatically once you have a teaching load for the current school year.
      </div>

      <div v-else class="grid gap-3 sm:grid-cols-2">
        <Link
          v-for="course in courses"
          :key="course.id"
          :href="route('learn.show', course.id)"
          class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white p-4 hover:border-indigo-300 hover:shadow-sm transition"
        >
          <BookOpenIcon class="h-6 w-6 text-indigo-600 shrink-0" />
          <div class="min-w-0">
            <p class="text-sm font-medium text-slate-800 truncate">{{ course.subject_name }}</p>
            <p class="text-xs text-slate-500">Grade {{ course.grade_level }} — {{ course.section_name }}</p>
            <div class="mt-2 flex items-center gap-2">
              <span :class="['inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium', statusBadgeClass(course.status)]">
                {{ course.status === 'published' ? 'Published' : 'Draft' }}
              </span>
              <span v-if="course.is_read_only" class="inline-flex items-center gap-1 text-xs text-slate-400">
                <LockClosedIcon class="h-3.5 w-3.5" /> Read-only
              </span>
            </div>
          </div>
        </Link>
      </div>
    </div>
  </AdminLayout>
</template>
