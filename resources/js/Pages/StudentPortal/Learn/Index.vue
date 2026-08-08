<script setup>
import { Head, Link } from '@inertiajs/vue3'
import StudentPortalLayout from '@/Layouts/StudentPortalLayout.vue'
import { BookOpenIcon } from '@heroicons/vue/24/outline'

defineProps({ courses: Array })
</script>

<template>
  <Head title="My Courses" />
  <StudentPortalLayout>
    <div class="max-w-3xl mx-auto py-6 px-4">
      <h1 class="text-lg font-semibold text-slate-800 mb-4">My Courses</h1>

      <div v-if="courses.length === 0" class="text-sm text-slate-500 border border-dashed border-slate-200 rounded-lg p-8 text-center">
        No published courses yet.
      </div>

      <div v-else class="grid gap-3 sm:grid-cols-2">
        <Link
          v-for="course in courses"
          :key="course.id"
          :href="route('student-portal.learn.show', course.id)"
          class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white p-4 hover:border-indigo-300 hover:shadow-sm transition"
        >
          <BookOpenIcon class="h-6 w-6 text-indigo-600 shrink-0" />
          <div class="min-w-0">
            <p class="text-sm font-medium text-slate-800 truncate">{{ course.subject_name }}</p>
            <p class="text-xs text-slate-500">{{ course.section_name }}</p>
          </div>
        </Link>
      </div>
    </div>
  </StudentPortalLayout>
</template>
