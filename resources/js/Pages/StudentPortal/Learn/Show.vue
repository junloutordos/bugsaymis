<script setup>
import { Head } from '@inertiajs/vue3'
import DOMPurify from 'dompurify'
import StudentPortalLayout from '@/Layouts/StudentPortalLayout.vue'
import { DocumentIcon, PaperClipIcon } from '@heroicons/vue/24/outline'

defineProps({ course: Object })

// Rich text (syllabus, page body) is stored as raw HTML — an instructor could
// bypass the RichTextEditor UI and POST a malicious payload directly to the
// API, so it must be sanitized at render time, not trusted as authored.
function sanitizeHtml(html) {
  return DOMPurify.sanitize(html || '')
}

// video_url is free-text input — reject anything but http(s) before it's
// ever used as a clickable href (blocks javascript:/data: scheme XSS).
function safeVideoUrl(url) {
  return url && /^https?:\/\//i.test(url) ? url : null
}
</script>

<template>
  <Head :title="course.subject_name" />
  <StudentPortalLayout>
    <div class="max-w-3xl mx-auto py-6 px-4 space-y-8">
      <div>
        <h1 class="text-lg font-semibold text-slate-800">{{ course.subject_name }}</h1>
        <p class="text-sm text-slate-500">{{ course.section_name }}</p>
      </div>

      <section v-if="course.syllabus_body">
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Syllabus</h2>
        <div class="prose prose-sm max-w-none" v-html="sanitizeHtml(course.syllabus_body)" />
      </section>

      <section>
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Modules</h2>
        <div v-for="module in course.modules" :key="module.id" class="border border-slate-200 rounded-lg mb-3">
          <div class="px-4 py-3 bg-slate-50 rounded-t-lg text-sm font-medium text-slate-800">{{ module.title }}</div>
          <div class="p-4 space-y-3">
            <div v-for="item in module.items" :key="item.id" class="flex items-start gap-2 border border-slate-100 rounded-lg p-3">
              <DocumentIcon v-if="item.type === 'page'" class="h-5 w-5 text-slate-400 shrink-0" />
              <PaperClipIcon v-else class="h-5 w-5 text-slate-400 shrink-0" />
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-slate-700">{{ item.title }}</p>
                <div v-if="item.type === 'page' && item.body" class="prose prose-sm max-w-none mt-1" v-html="sanitizeHtml(item.body)" />
                <a v-if="item.type === 'page' && safeVideoUrl(item.video_url)" :href="safeVideoUrl(item.video_url)" target="_blank" rel="noopener noreferrer" class="text-xs text-indigo-600 underline">Watch video</a>
                <a v-if="item.type === 'file'" :href="item.file_url" target="_blank" rel="noopener noreferrer" class="text-xs text-indigo-600 underline">Download file</a>
              </div>
            </div>
            <p v-if="module.items.length === 0" class="text-xs text-slate-400">No content yet.</p>
          </div>
        </div>
      </section>

      <section v-if="course.announcements.length">
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Announcements</h2>
        <div v-for="(announcement, i) in course.announcements" :key="i" class="border border-slate-200 rounded-lg p-4 mb-2">
          <p class="text-sm font-medium text-slate-800">{{ announcement.title }}</p>
          <p class="text-xs text-slate-500">{{ announcement.posted_by }} — {{ new Date(announcement.posted_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
          <p class="text-sm text-slate-600 mt-2 whitespace-pre-line">{{ announcement.body }}</p>
        </div>
      </section>
    </div>
  </StudentPortalLayout>
</template>
