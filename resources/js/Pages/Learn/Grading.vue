<script setup>
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ assignment: Object, roster: Array })

const expandedStudentId = ref(null)
const gradeForms = ref({})

// link_url is student-submitted content — reject anything but http(s) before
// it's ever used as a clickable href (blocks javascript:/data: scheme XSS
// against the grading instructor's authenticated session).
function safeLinkUrl(url) {
  return url && /^https?:\/\//i.test(url) ? url : null
}

function statusLabel(status) {
  return { not_submitted: 'Not submitted', submitted: 'Submitted', late: 'Late', graded: 'Graded' }[status]
}
function statusClass(status) {
  return {
    not_submitted: 'bg-slate-100 text-slate-600',
    submitted: 'bg-blue-50 text-blue-700',
    late: 'bg-amber-50 text-amber-700',
    graded: 'bg-emerald-50 text-emerald-700',
  }[status]
}

function toggleExpand(row) {
  if (! row.submission_id) return
  expandedStudentId.value = expandedStudentId.value === row.student_id ? null : row.student_id

  if (! gradeForms.value[row.student_id]) {
    const rubricDefaults = {}
    if (props.assignment.rubric) {
      for (const c of props.assignment.rubric.criteria) {
        rubricDefaults[c.id] = row.rubric_scores[c.id] ?? ''
      }
    }
    gradeForms.value[row.student_id] = useForm({
      score: row.score ?? '',
      rubric_scores: rubricDefaults,
      feedback_comment: row.feedback_comment ?? '',
    })
  }
}

function submitGrade(row) {
  const form = gradeForms.value[row.student_id]
  const payload = props.assignment.rubric
    ? { rubric_scores: form.rubric_scores, feedback_comment: form.feedback_comment }
    : { score: form.score, feedback_comment: form.feedback_comment }

  router.put(route('learn.submissions.grade', row.submission_id), payload, { preserveScroll: true })
}

function reopen(row) {
  router.post(route('learn.submissions.reopen', row.submission_id), {}, { preserveScroll: true })
}
</script>

<template>
  <Head :title="`Grading — ${assignment.title}`" />
  <AdminLayout :title="assignment.title">
    <div class="max-w-4xl mx-auto py-6 px-4 space-y-4">
      <div>
        <h1 class="text-lg font-semibold text-slate-800">{{ assignment.title }}</h1>
        <p class="text-sm text-slate-500">{{ assignment.submission_type }} submission — {{ assignment.max_score }} pts</p>
      </div>

      <div v-for="row in roster" :key="row.student_id" class="border border-slate-200 rounded-lg">
        <button
          class="w-full flex items-center justify-between px-4 py-3 text-left"
          :class="row.submission_id ? 'cursor-pointer hover:bg-slate-50' : 'cursor-default'"
          @click="toggleExpand(row)"
        >
          <span class="text-sm font-medium text-slate-800">{{ row.name }}</span>
          <div class="flex items-center gap-2">
            <span v-if="row.score !== null" class="text-xs text-slate-500">{{ row.score }} / {{ assignment.max_score }}</span>
            <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', statusClass(row.status)]">
              {{ statusLabel(row.status) }}
            </span>
          </div>
        </button>

        <div v-if="expandedStudentId === row.student_id" class="border-t border-slate-100 p-4 space-y-3">
          <div v-if="assignment.submission_type === 'text'" class="prose prose-sm max-w-none whitespace-pre-line">{{ row.text_body }}</div>
          <a v-else-if="assignment.submission_type === 'link' && safeLinkUrl(row.link_url)" :href="safeLinkUrl(row.link_url)" target="_blank" rel="noopener noreferrer" class="text-sm text-indigo-600 underline">{{ row.link_url }}</a>
          <p v-else-if="assignment.submission_type === 'link'" class="text-sm text-red-600">Submitted link uses an unsupported/unsafe scheme.</p>
          <a v-else-if="assignment.submission_type === 'file'" :href="row.file_url" target="_blank" rel="noopener noreferrer" class="text-sm text-indigo-600 underline">Download submission</a>

          <div v-if="!row.is_graded">
            <div v-if="!assignment.rubric">
              <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Score</label>
              <input v-model="gradeForms[row.student_id].score" type="number" min="0" :max="assignment.max_score" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full mt-1" />
            </div>
            <div v-else class="space-y-2">
              <div v-for="criterion in assignment.rubric.criteria" :key="criterion.id" class="flex items-center gap-2">
                <span class="text-sm text-slate-700 flex-1">{{ criterion.description }}</span>
                <input v-model="gradeForms[row.student_id].rubric_scores[criterion.id]" type="number" min="0" :max="criterion.max_points" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-24" />
                <span class="text-xs text-slate-400">/ {{ criterion.max_points }}</span>
              </div>
            </div>
            <textarea v-model="gradeForms[row.student_id].feedback_comment" placeholder="Feedback (optional)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full mt-2" rows="2" />
            <button @click="submitGrade(row)" class="mt-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Save grade</button>
          </div>
          <div v-else>
            <p class="text-sm text-slate-600">{{ row.feedback_comment }}</p>
            <button @click="reopen(row)" class="mt-2 bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium">Reopen for resubmission</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
