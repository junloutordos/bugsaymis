<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
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

// ── Assignment submission ────────────────────────────────────────────────
const submissionForms = ref({})
function submissionForm(item) {
  if (! submissionForms.value[item.id]) {
    submissionForms.value[item.id] = useForm({ text_body: '', link_url: '', title: '', file_base64: '' })
  }
  return submissionForms.value[item.id]
}

function readFileAsBase64(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(reader.result)
    reader.onerror = reject
    reader.readAsDataURL(file)
  })
}

async function pickFile(item, event) {
  const file = event.target.files[0]
  if (! file) return
  const form = submissionForm(item)
  form.file_base64 = await readFileAsBase64(file)
  form.title = file.name
}

function submitAssignment(item) {
  const form = submissionForm(item)
  form.post(route('student-portal.learn.assignments.submit', item.assignment.id), { preserveScroll: true })
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

                <div v-if="item.type === 'assignment'" class="mt-1 space-y-2">
                  <div v-if="item.assignment.instructions" class="prose prose-sm max-w-none" v-html="sanitizeHtml(item.assignment.instructions)" />
                  <p class="text-xs text-slate-500">
                    {{ item.assignment.submission_type }} submission
                    <span v-if="item.assignment.due_at"> — due {{ new Date(item.assignment.due_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}</span>
                    <span v-if="item.assignment.max_score !== null"> — {{ item.assignment.max_score }} pts</span>
                  </p>

                  <div v-if="item.assignment.submission && item.assignment.submission.is_graded" class="border border-emerald-200 bg-emerald-50 rounded-lg p-3">
                    <p class="text-sm font-medium text-emerald-800">Score: {{ item.assignment.submission.score }} / {{ item.assignment.max_score }}</p>
                    <p v-if="item.assignment.submission.feedback_comment" class="text-sm text-emerald-700 mt-1">{{ item.assignment.submission.feedback_comment }}</p>
                  </div>

                  <div v-else-if="item.assignment.submission" class="border border-slate-200 rounded-lg p-3">
                    <p class="text-xs text-slate-500">
                      Submitted {{ new Date(item.assignment.submission.submitted_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}
                      <span v-if="item.assignment.submission.is_late" class="text-amber-600">— Late</span>
                    </p>
                    <p v-if="item.assignment.submission_type === 'text'" class="text-sm text-slate-700 mt-1 whitespace-pre-line">{{ item.assignment.submission.text_body }}</p>
                    <a v-else-if="item.assignment.submission_type === 'link'" :href="item.assignment.submission.link_url" target="_blank" rel="noopener noreferrer" class="text-sm text-indigo-600 underline">{{ item.assignment.submission.link_url }}</a>
                    <a v-else-if="item.assignment.submission_type === 'file'" :href="item.assignment.submission.file_url" target="_blank" rel="noopener noreferrer" class="text-sm text-indigo-600 underline">View my submission</a>
                  </div>

                  <div v-if="!item.assignment.submission || !item.assignment.submission.is_graded" class="border-t border-slate-100 pt-2 space-y-2">
                    <textarea v-if="item.assignment.submission_type === 'text'" v-model="submissionForm(item).text_body" placeholder="Type your submission" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="3" />
                    <input v-else-if="item.assignment.submission_type === 'link'" v-model="submissionForm(item).link_url" placeholder="https://..." class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
                    <input v-else-if="item.assignment.submission_type === 'file'" type="file" @change="e => pickFile(item, e)" class="text-sm" />
                    <button @click="submitAssignment(item)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                      {{ item.assignment.submission ? 'Resubmit' : 'Submit' }}
                    </button>
                  </div>
                </div>
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
