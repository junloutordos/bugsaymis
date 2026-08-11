<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import CourseCover from '@/Components/CourseCover.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { LockClosedIcon, BookOpenIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ courses: Array })

function statusColor(status) {
  return status === 'published' ? 'green' : 'slate'
}

function initialsFor(course) {
  return (course.subject_name || '').trim().split(/\s+/).map(w => w[0]).join('').slice(0, 3).toUpperCase()
}
</script>

<template>
  <Head title="Learn — My Courses" />
  <AdminLayout title="Learn">
    <div class="max-w-5xl mx-auto py-6 px-4">
      <AppPageHeader title="My Courses" subtitle="Courses appear automatically once you have a teaching load for the current school year." />

      <EmptyState v-if="courses.length === 0" :icon="BookOpenIcon" title="No courses yet" subtitle="Courses appear automatically once you have a teaching load for the current school year." />

      <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <Link
          v-for="course in courses"
          :key="course.id"
          :href="route('learn.show', course.id)"
          class="block"
        >
          <AppCard :padded="false" class="h-full transition hover:shadow-md hover:ring-indigo-200">
            <CourseCover :photo-url="course.cover_photo_url" :preset="course.cover_preset" :initials="initialsFor(course)" class="h-28 w-full" />
            <div class="p-4">
              <p class="text-sm font-medium text-slate-800 truncate">{{ course.subject_name }}</p>
              <p class="text-xs text-slate-500">Grade {{ course.grade_level }} — {{ course.section_name }}</p>
              <div class="mt-2 flex items-center gap-2">
                <AppBadge :color="statusColor(course.status)">{{ course.status === 'published' ? 'Published' : 'Draft' }}</AppBadge>
                <span v-if="course.is_read_only" class="inline-flex items-center gap-1 text-xs text-slate-400">
                  <LockClosedIcon class="h-3.5 w-3.5" /> Read-only
                </span>
              </div>
              <div v-if="!course.is_read_only && course.setup_percent < 100" class="mt-3">
                <div class="h-1 w-full overflow-hidden rounded-full bg-slate-100">
                  <div class="h-full rounded-full bg-indigo-600" :style="{ width: course.setup_percent + '%' }" />
                </div>
                <p class="mt-1 text-[11px] text-slate-400">{{ course.setup_percent }}% set up</p>
              </div>
            </div>
          </AppCard>
        </Link>
      </div>
    </div>
  </AdminLayout>
</template>
