<script setup>
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ discussion: Object, roster: Array })

const gradeForms = ref({})
function gradeForm(studentId) {
  if (! gradeForms.value[studentId]) {
    const row = props.roster.find(r => r.student_id === studentId)
    gradeForms.value[studentId] = useForm({
      points_earned: row?.points_earned ?? '',
      feedback_comment: row?.feedback_comment ?? '',
    })
  }
  return gradeForms.value[studentId]
}
function saveGrade(studentId) {
  gradeForm(studentId).put(route('learn.discussions.grade', [props.discussion.id, studentId]), { preserveScroll: true })
}

const selectedClassRecordId = ref('')
const selectedQuarterId = ref('')
const selectedAssessmentId = ref('')

const availableQuarters = computed(() => {
  const cr = (props.discussion.class_record_options || []).find(c => c.id === Number(selectedClassRecordId.value))
  return cr ? cr.quarters : []
})
const availableAssessments = computed(() => {
  const q = availableQuarters.value.find(q => q.id === Number(selectedQuarterId.value))
  return q ? q.assessments : []
})

function linkAssessment() {
  if (! selectedAssessmentId.value) return
  router.put(route('learn.discussions.link', props.discussion.id), {
    class_record_assessment_id: selectedAssessmentId.value,
  }, { preserveScroll: true })
}
function pushToClassRecord() {
  router.post(route('learn.discussions.push', props.discussion.id), {}, { preserveScroll: true })
}
</script>

<template>
  <Head :title="`Discussion Grading — ${discussion.title}`" />
  <AdminLayout :title="discussion.title">
    <div class="max-w-3xl mx-auto py-6 px-4 space-y-4">
      <div>
        <h1 class="text-lg font-semibold text-slate-800">{{ discussion.title }}</h1>
        <p class="text-sm text-slate-500">{{ discussion.max_score }} pts total</p>
      </div>

      <div class="border border-slate-200 rounded-lg p-4 space-y-2">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Class Record</p>

        <div v-if="discussion.class_record_link">
          <p class="text-sm text-slate-700">
            Linked to <strong>{{ discussion.class_record_link.class_record_name }}</strong> —
            Q{{ discussion.class_record_link.quarter }} — {{ discussion.class_record_link.category_name }} —
            "{{ discussion.class_record_link.assessment_title }}"
          </p>
          <p class="text-xs text-slate-500 mt-1">
            {{ discussion.class_record_link.pushed_at ? `Last pushed ${new Date(discussion.class_record_link.pushed_at).toLocaleString('en-PH')}` : 'Not pushed yet' }}
          </p>
          <button @click="pushToClassRecord" class="mt-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
            Push graded scores
          </button>
        </div>

        <div v-else class="space-y-2">
          <p class="text-xs text-slate-500">Not linked yet. Pick the Class Record assessment to push scores into.</p>
          <div class="flex gap-2">
            <select v-model="selectedClassRecordId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1">
              <option value="" disabled>Class Record</option>
              <option v-for="cr in discussion.class_record_options" :key="cr.id" :value="cr.id">{{ cr.display_name }}</option>
            </select>
            <select v-model="selectedQuarterId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
              <option value="" disabled>Quarter</option>
              <option v-for="q in availableQuarters" :key="q.id" :value="q.id">Q{{ q.quarter }}</option>
            </select>
            <select v-model="selectedAssessmentId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1">
              <option value="" disabled>Assessment</option>
              <option v-for="a in availableAssessments" :key="a.id" :value="a.id">{{ a.category_name }} — {{ a.title }} ({{ a.max_score }} pts)</option>
            </select>
          </div>
          <button @click="linkAssessment" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium">Link</button>
        </div>
      </div>

      <div class="border border-slate-200 rounded-lg divide-y divide-slate-100">
        <div v-for="row in roster" :key="row.student_id" class="p-3 flex items-center gap-3">
          <p class="text-sm text-slate-700 flex-1">{{ row.name }}</p>
          <input v-model="gradeForm(row.student_id).points_earned" type="number" min="0" :max="discussion.max_score" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-20" />
          <input v-model="gradeForm(row.student_id).feedback_comment" placeholder="Feedback (optional)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1" />
          <button @click="saveGrade(row.student_id)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">Save</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
