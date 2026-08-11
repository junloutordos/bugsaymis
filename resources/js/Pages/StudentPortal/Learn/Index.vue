<script setup>
import { Head, Link } from '@inertiajs/vue3'
import StudentPortalLayout from '@/Layouts/StudentPortalLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import CourseCover from '@/Components/CourseCover.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { BookOpenIcon } from '@heroicons/vue/24/outline'

defineProps({ courses: Array })

function initialsFor(course) {
  return (course.subject_name || '').trim().split(/\s+/).map(w => w[0]).join('').slice(0, 3).toUpperCase()
}
</script>

<template>
  <Head title="My Courses" />
  <StudentPortalLayout>
    <div class="max-w-3xl mx-auto py-6 px-4">
      <h1 class="mb-4 font-heading text-lg font-semibold text-slate-800">My Courses</h1>

      <EmptyState v-if="courses.length === 0" :icon="BookOpenIcon" title="No published courses yet" />

      <div v-else class="grid gap-4 sm:grid-cols-2">
        <Link v-for="course in courses" :key="course.id" :href="route('student-portal.learn.show', course.id)" class="block">
          <AppCard :padded="false" class="h-full transition hover:shadow-md hover:ring-indigo-200">
            <CourseCover :photo-url="course.cover_photo_url" :preset="course.cover_preset" :initials="initialsFor(course)" class="h-24 w-full" />
            <div class="p-4">
              <p class="truncate text-sm font-medium text-slate-800">{{ course.subject_name }}</p>
              <p class="text-xs text-slate-500">{{ course.section_name }}</p>
            </div>
          </AppCard>
        </Link>
      </div>
    </div>
  </StudentPortalLayout>
</template>
