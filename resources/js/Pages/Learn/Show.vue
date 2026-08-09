<script setup>
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import DOMPurify from 'dompurify'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import RichTextEditor from '@/Components/RichTextEditor.vue'
import MathContent from '@/Components/MathContent.vue'
import {
  PlusIcon, TrashIcon, EyeIcon, EyeSlashIcon,
  ArrowUpIcon, ArrowDownIcon, DocumentIcon, PaperClipIcon, AcademicCapIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({ course: Object, rubric_templates: Array, quiz_question_bank: Array })

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

// ── Syllabus ──────────────────────────────────────────────────────────────
const syllabus = ref(props.course.syllabus_body || '')
function saveSyllabus() {
  router.put(route('learn.syllabus.update', props.course.id), { syllabus_body: syllabus.value }, { preserveScroll: true })
}

// ── Publish toggle ───────────────────────────────────────────────────────
function toggleCourseStatus() {
  const next = props.course.status === 'published' ? 'draft' : 'published'
  router.patch(route('learn.status.update', props.course.id), { status: next }, { preserveScroll: true })
}

// ── Modules ──────────────────────────────────────────────────────────────
const newModuleTitle = ref('')
function addModule() {
  if (! newModuleTitle.value.trim()) return
  router.post(route('learn.modules.store', props.course.id), { title: newModuleTitle.value }, {
    preserveScroll: true,
    onSuccess: () => { newModuleTitle.value = '' },
  })
}
function toggleModulePublish(moduleId) {
  router.patch(route('learn.modules.publish', moduleId), {}, { preserveScroll: true })
}
function deleteModule(moduleId) {
  router.delete(route('learn.modules.destroy', moduleId), { preserveScroll: true })
}
function moveModule(index, direction) {
  const ids = props.course.modules.map(m => m.id)
  const target = index + direction
  if (target < 0 || target >= ids.length) return
  ;[ids[index], ids[target]] = [ids[target], ids[index]]
  router.put(route('learn.modules.reorder', props.course.id), { module_ids: ids }, { preserveScroll: true })
}

// ── Module items ─────────────────────────────────────────────────────────
const pageForms = ref({})
function pageForm(moduleId) {
  if (! pageForms.value[moduleId]) {
    pageForms.value[moduleId] = useForm({ title: '', body: '', video_url: '' })
  }
  return pageForms.value[moduleId]
}
function addPage(moduleId) {
  pageForm(moduleId).post(route('learn.items.store-page', moduleId), {
    preserveScroll: true,
    onSuccess: () => pageForm(moduleId).reset(),
  })
}

function readFileAsBase64(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(reader.result)
    reader.onerror = reject
    reader.readAsDataURL(file)
  })
}

const fileTitles = ref({})
async function addFile(moduleId, event) {
  const file = event.target.files[0]
  if (! file) return
  const base64 = await readFileAsBase64(file)
  router.post(route('learn.items.store-file', moduleId), {
    title: fileTitles.value[moduleId] || file.name,
    file_base64: base64,
  }, { preserveScroll: true, onSuccess: () => { fileTitles.value[moduleId] = '' } })
  event.target.value = ''
}

const assignmentForms = ref({})
function assignmentForm(moduleId) {
  if (! assignmentForms.value[moduleId]) {
    assignmentForms.value[moduleId] = useForm({
      title: '', instructions: '', submission_type: 'text',
      points_possible: '', due_at: '', rubric_criteria: [],
      save_as_template: false, template_name: '',
    })
  }
  return assignmentForms.value[moduleId]
}
function addRubricCriterion(moduleId) {
  assignmentForm(moduleId).rubric_criteria.push({ description: '', max_points: 10 })
}
function removeRubricCriterion(moduleId, index) {
  assignmentForm(moduleId).rubric_criteria.splice(index, 1)
}
function addAssignment(moduleId) {
  assignmentForm(moduleId).post(route('learn.items.store-assignment', moduleId), {
    preserveScroll: true,
    onSuccess: () => { assignmentForms.value[moduleId] = null },
  })
}

function applyTemplate(moduleId, templateId) {
  const template = props.rubric_templates.find(t => t.id === Number(templateId))
  if (! template) return
  assignmentForm(moduleId).rubric_criteria = template.criteria.map(c => ({
    description: c.description, max_points: c.max_points,
  }))
}

const renameTemplateDrafts = ref({})
function startRenameTemplate(template) {
  renameTemplateDrafts.value[template.id] = template.name
}
function saveTemplateRename(template) {
  router.put(route('learn.rubric-templates.update', template.id), {
    name: renameTemplateDrafts.value[template.id],
  }, {
    preserveScroll: true,
    onSuccess: () => { delete renameTemplateDrafts.value[template.id] },
  })
}
function deleteTemplate(template) {
  router.delete(route('learn.rubric-templates.destroy', template.id), { preserveScroll: true })
}

// ── Quiz authoring ───────────────────────────────────────────────────────
const quizForms = ref({})
function quizForm(moduleId) {
  if (! quizForms.value[moduleId]) {
    quizForms.value[moduleId] = useForm({
      title: '', instructions: '', time_limit_minutes: '', max_attempts: '',
      questions_to_draw: '', shuffle_questions: false, shuffle_options: false, due_at: '',
      questions: [],
    })
  }
  return quizForms.value[moduleId]
}
function addQuizQuestion(moduleId) {
  quizForm(moduleId).questions.push({
    question_type: 'multiple_choice', prompt: '', points: 5, difficulty: '',
    options: [{ option_text: '', is_correct: true }, { option_text: '', is_correct: false }],
    accepted_answers: [],
    save_to_bank: false, bank_name: '',
  })
}
function removeQuizQuestion(moduleId, index) {
  quizForm(moduleId).questions.splice(index, 1)
}
function addQuizQuestionOption(moduleId, qIndex) {
  quizForm(moduleId).questions[qIndex].options.push({ option_text: '', is_correct: false })
}
function removeQuizQuestionOption(moduleId, qIndex, oIndex) {
  quizForm(moduleId).questions[qIndex].options.splice(oIndex, 1)
}
function addAcceptedAnswer(moduleId, qIndex) {
  quizForm(moduleId).questions[qIndex].accepted_answers.push('')
}
function removeAcceptedAnswer(moduleId, qIndex, aIndex) {
  quizForm(moduleId).questions[qIndex].accepted_answers.splice(aIndex, 1)
}
function applyQuizBankItem(moduleId, qIndex, bankItemId) {
  const item = props.quiz_question_bank.find(b => b.id === Number(bankItemId))
  if (! item) return
  const q = quizForm(moduleId).questions[qIndex]
  q.question_type = item.question_type
  q.prompt = item.prompt
  q.points = item.points
  q.difficulty = item.difficulty || ''
  if (['multiple_choice', 'true_false', 'multiple_select'].includes(item.question_type)) {
    q.options = item.options.map(o => ({ option_text: o.option_text, is_correct: o.is_correct }))
    q.accepted_answers = []
  } else if (item.question_type === 'short_answer') {
    q.accepted_answers = item.options.map(o => o.option_text)
    q.options = []
  } else {
    q.options = []
    q.accepted_answers = []
  }
}
function addQuiz(moduleId) {
  quizForm(moduleId).post(route('learn.items.store-quiz', moduleId), {
    preserveScroll: true,
    onSuccess: () => { quizForms.value[moduleId] = null },
  })
}

// Adding/deleting questions on an ALREADY-CREATED quiz — separate form state from quizForm
// above (which only ever builds a brand-new quiz's initial question set in one POST).
const newQuestionForms = ref({})
function newQuestionForm(quizId) {
  if (! newQuestionForms.value[quizId]) {
    newQuestionForms.value[quizId] = useForm({
      question_type: 'multiple_choice', prompt: '', points: 5, difficulty: '',
      options: [{ option_text: '', is_correct: true }, { option_text: '', is_correct: false }],
      accepted_answers: [],
      save_to_bank: false, bank_name: '',
    })
  }
  return newQuestionForms.value[quizId]
}
function addNewQuestionOption(quizId) {
  newQuestionForm(quizId).options.push({ option_text: '', is_correct: false })
}
function removeNewQuestionOption(quizId, index) {
  newQuestionForm(quizId).options.splice(index, 1)
}
function addNewAcceptedAnswer(quizId) {
  newQuestionForm(quizId).accepted_answers.push('')
}
function removeNewAcceptedAnswer(quizId, index) {
  newQuestionForm(quizId).accepted_answers.splice(index, 1)
}
function submitNewQuestion(quizId) {
  newQuestionForm(quizId).post(route('learn.quiz-questions.store', quizId), {
    preserveScroll: true,
    onSuccess: () => { newQuestionForms.value[quizId] = null },
  })
}
function deleteQuizQuestion(questionId) {
  router.delete(route('learn.quiz-questions.destroy', questionId), { preserveScroll: true })
}

const renameBankItemDrafts = ref({})
function startRenameBankItem(item) {
  renameBankItemDrafts.value[item.id] = item.name
}
function saveBankItemRename(item) {
  router.put(route('learn.quiz-question-bank.update', item.id), {
    name: renameBankItemDrafts.value[item.id],
  }, {
    preserveScroll: true,
    onSuccess: () => { delete renameBankItemDrafts.value[item.id] },
  })
}
function deleteBankItem(item) {
  router.delete(route('learn.quiz-question-bank.destroy', item.id), { preserveScroll: true })
}

function toggleItemPublish(itemId) {
  router.patch(route('learn.items.publish', itemId), {}, { preserveScroll: true })
}
function deleteItem(itemId) {
  router.delete(route('learn.items.destroy', itemId), { preserveScroll: true })
}
function moveItem(module, index, direction) {
  const ids = module.items.map(i => i.id)
  const target = index + direction
  if (target < 0 || target >= ids.length) return
  ;[ids[index], ids[target]] = [ids[target], ids[index]]
  router.put(route('learn.items.reorder', module.id), { item_ids: ids }, { preserveScroll: true })
}

// ── Announcements ────────────────────────────────────────────────────────
const announcementForm = useForm({ title: '', body: '' })
function postAnnouncement() {
  announcementForm.post(route('learn.announcements.store', props.course.id), {
    preserveScroll: true,
    onSuccess: () => announcementForm.reset(),
  })
}
function deleteAnnouncement(id) {
  router.delete(route('learn.announcements.destroy', id), { preserveScroll: true })
}
</script>

<template>
  <Head :title="`Learn — ${course.subject_name}`" />
  <AdminLayout :title="course.subject_name">
    <div class="max-w-4xl mx-auto py-6 px-4 space-y-8">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-lg font-semibold text-slate-800">{{ course.subject_name }}</h1>
          <p class="text-sm text-slate-500">Grade {{ course.grade_level }} — {{ course.section_name }}</p>
        </div>
        <div v-if="course.can_edit" class="flex items-center gap-3">
          <Link :href="route('learn.course-trend', course.id)" class="text-xs text-indigo-600 underline">Quiz trend</Link>
          <button
            @click="toggleCourseStatus"
            class="rounded-lg px-4 py-2 text-sm font-medium"
            :class="course.status === 'published' ? 'bg-slate-100 text-slate-700' : 'bg-indigo-600 hover:bg-indigo-700 text-white'"
          >
            {{ course.status === 'published' ? 'Unpublish' : 'Publish course' }}
          </button>
        </div>
      </div>

      <p v-if="course.is_read_only" class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
        This course is from a past school year and is read-only.
      </p>

      <!-- Syllabus -->
      <section>
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Syllabus</h2>
        <RichTextEditor v-if="course.can_edit" v-model="syllabus" />
        <div v-else class="prose prose-sm max-w-none" v-html="sanitizeHtml(course.syllabus_body) || '<p class=\'text-slate-400\'>No syllabus yet.</p>'" />
        <button v-if="course.can_edit" @click="saveSyllabus" class="mt-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          Save syllabus
        </button>
      </section>

      <!-- Modules -->
      <section>
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Modules</h2>

        <div v-for="(module, index) in course.modules" :key="module.id" class="border border-slate-200 rounded-lg mb-3">
          <div class="flex items-center justify-between px-4 py-3 bg-slate-50 rounded-t-lg">
            <span class="text-sm font-medium text-slate-800">{{ module.title }}</span>
            <div v-if="course.can_edit" class="flex items-center gap-1">
              <button @click="moveModule(index, -1)" class="p-1 text-slate-400 hover:text-slate-700"><ArrowUpIcon class="h-4 w-4" /></button>
              <button @click="moveModule(index, 1)" class="p-1 text-slate-400 hover:text-slate-700"><ArrowDownIcon class="h-4 w-4" /></button>
              <button @click="toggleModulePublish(module.id)" class="p-1 text-slate-400 hover:text-slate-700">
                <EyeIcon v-if="!module.is_published" class="h-4 w-4" />
                <EyeSlashIcon v-else class="h-4 w-4" />
              </button>
              <button @click="deleteModule(module.id)" class="p-1 text-red-400 hover:text-red-600"><TrashIcon class="h-4 w-4" /></button>
            </div>
          </div>

          <div class="p-4 space-y-3">
            <div v-for="(item, itemIndex) in module.items" :key="item.id" class="flex items-start gap-2 border border-slate-100 rounded-lg p-3">
              <DocumentIcon v-if="item.type === 'page'" class="h-5 w-5 text-slate-400 shrink-0" />
              <AcademicCapIcon v-else-if="item.type === 'quiz'" class="h-5 w-5 text-slate-400 shrink-0" />
              <PaperClipIcon v-else class="h-5 w-5 text-slate-400 shrink-0" />
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-slate-700">{{ item.title }}</p>
                <div v-if="item.type === 'page' && item.body" class="prose prose-sm max-w-none mt-1" v-html="sanitizeHtml(item.body)" />
                <a v-if="item.type === 'page' && safeVideoUrl(item.video_url)" :href="safeVideoUrl(item.video_url)" target="_blank" rel="noopener noreferrer" class="text-xs text-indigo-600 underline">Watch video</a>
                <a v-if="item.type === 'file'" :href="item.file_url" target="_blank" rel="noopener noreferrer" class="text-xs text-indigo-600 underline">Download file</a>
                <div v-if="item.type === 'assignment'" class="mt-1 space-y-1">
                  <div v-if="item.assignment.instructions" class="prose prose-sm max-w-none" v-html="sanitizeHtml(item.assignment.instructions)" />
                  <p class="text-xs text-slate-500">
                    {{ item.assignment.submission_type }} submission
                    <span v-if="item.assignment.due_at"> — due {{ new Date(item.assignment.due_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}</span>
                    <span v-if="item.assignment.max_score !== null"> — {{ item.assignment.max_score }} pts{{ item.assignment.has_rubric ? ' (rubric)' : '' }}</span>
                  </p>
                  <Link :href="route('learn.assignments.submissions', item.assignment.id)" class="text-xs text-indigo-600 underline">View submissions</Link>
                </div>
                <div v-if="item.type === 'quiz'" class="mt-1 space-y-1">
                  <p class="text-xs text-slate-500">
                    {{ item.quiz.question_count }} question{{ item.quiz.question_count === 1 ? '' : 's' }}
                    <span v-if="item.quiz.time_limit_minutes"> — {{ item.quiz.time_limit_minutes }} min</span>
                    <span v-if="item.quiz.due_at"> — due {{ new Date(item.quiz.due_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}</span>
                    <span v-if="item.quiz.max_score !== null"> — {{ item.quiz.max_score }} pts</span>
                  </p>
                  <div class="flex gap-2">
                    <Link :href="route('learn.quizzes.attempts', item.quiz.id)" class="text-xs text-indigo-600 underline">View attempts</Link>
                    <Link :href="route('learn.quizzes.analytics', item.quiz.id)" class="text-xs text-indigo-600 underline">Analytics</Link>
                  </div>

                  <div v-if="course.can_edit" class="border-t border-slate-100 pt-2 space-y-2">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Questions</p>
                    <p v-if="item.quiz.is_locked" class="text-xs text-amber-600">Locked — students have submitted attempts. Existing questions cannot be changed, but new ones can still be added.</p>

                    <div v-for="q in item.quiz.questions" :key="q.id" class="flex items-start gap-2 border border-slate-100 rounded-lg p-2">
                      <div class="min-w-0 flex-1">
                        <MathContent :html="sanitizeHtml(q.prompt)" class="prose prose-sm max-w-none" />
                        <p class="text-xs text-slate-400">{{ q.question_type }} — {{ q.points }} pts</p>
                      </div>
                      <button v-if="!item.quiz.is_locked" @click="deleteQuizQuestion(q.id)" class="p-1 text-red-400 hover:text-red-600"><TrashIcon class="h-3.5 w-3.5" /></button>
                    </div>

                    <div class="border border-slate-100 rounded-lg p-3 space-y-2">
                      <p class="text-xs text-slate-500">Add another question</p>
                      <div class="flex gap-2">
                        <select v-model="newQuestionForm(item.quiz.id).question_type" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                          <option value="multiple_choice">Multiple choice</option>
                          <option value="true_false">True / False</option>
                          <option value="multiple_select">Multiple select</option>
                          <option value="short_answer">Short answer</option>
                          <option value="essay">Essay</option>
                        </select>
                        <input v-model="newQuestionForm(item.quiz.id).points" type="number" min="0" placeholder="Points" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-24" />
                      </div>
                      <textarea v-model="newQuestionForm(item.quiz.id).prompt" placeholder="Question prompt (supports $LaTeX$)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="2" />

                      <div v-if="['multiple_choice', 'true_false', 'multiple_select'].includes(newQuestionForm(item.quiz.id).question_type)" class="space-y-1">
                        <div v-for="(o, oIndex) in newQuestionForm(item.quiz.id).options" :key="oIndex" class="flex gap-2 items-center">
                          <input v-model="o.option_text" placeholder="Option text" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1" />
                          <label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" v-model="o.is_correct" /> Correct</label>
                          <button @click="removeNewQuestionOption(item.quiz.id, oIndex)" class="p-1 text-red-400 hover:text-red-600"><TrashIcon class="h-3.5 w-3.5" /></button>
                        </div>
                        <button @click="addNewQuestionOption(item.quiz.id)" class="text-xs text-indigo-600 underline">+ Add option</button>
                      </div>
                      <div v-else-if="newQuestionForm(item.quiz.id).question_type === 'short_answer'" class="space-y-1">
                        <div v-for="(a, aIndex) in newQuestionForm(item.quiz.id).accepted_answers" :key="aIndex" class="flex gap-2 items-center">
                          <input v-model="newQuestionForm(item.quiz.id).accepted_answers[aIndex]" placeholder="Accepted answer" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1" />
                          <button @click="removeNewAcceptedAnswer(item.quiz.id, aIndex)" class="p-1 text-red-400 hover:text-red-600"><TrashIcon class="h-3.5 w-3.5" /></button>
                        </div>
                        <button @click="addNewAcceptedAnswer(item.quiz.id)" class="text-xs text-indigo-600 underline">+ Add accepted answer</button>
                      </div>

                      <button @click="submitNewQuestion(item.quiz.id)" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-lg text-sm font-medium">Add question</button>
                    </div>
                  </div>
                </div>
              </div>
              <div v-if="course.can_edit" class="flex items-center gap-1 shrink-0">
                <button @click="moveItem(module, itemIndex, -1)" class="p-1 text-slate-400 hover:text-slate-700"><ArrowUpIcon class="h-3.5 w-3.5" /></button>
                <button @click="moveItem(module, itemIndex, 1)" class="p-1 text-slate-400 hover:text-slate-700"><ArrowDownIcon class="h-3.5 w-3.5" /></button>
                <button @click="toggleItemPublish(item.id)" class="p-1 text-slate-400 hover:text-slate-700">
                  <EyeIcon v-if="!item.is_published" class="h-3.5 w-3.5" />
                  <EyeSlashIcon v-else class="h-3.5 w-3.5" />
                </button>
                <button @click="deleteItem(item.id)" class="p-1 text-red-400 hover:text-red-600"><TrashIcon class="h-3.5 w-3.5" /></button>
              </div>
            </div>

            <div v-if="course.can_edit" class="border-t border-slate-100 pt-3 space-y-2">
              <div class="flex gap-2">
                <input v-model="pageForm(module.id).title" placeholder="Page title" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1" />
                <button @click="addPage(module.id)" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-lg text-sm font-medium">Add page</button>
              </div>
              <textarea v-model="pageForm(module.id).body" placeholder="Page body (optional)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="2" />
              <input v-model="pageForm(module.id).video_url" placeholder="Video URL (YouTube/Drive, optional)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />

              <div class="flex gap-2 items-center">
                <input v-model="fileTitles[module.id]" placeholder="File title" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1" />
                <label class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-lg text-sm font-medium cursor-pointer">
                  Upload file
                  <input type="file" class="hidden" @change="e => addFile(module.id, e)" />
                </label>
              </div>

              <div class="border-t border-slate-100 pt-3 space-y-2">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">New assignment</p>
                <input v-model="assignmentForm(module.id).title" placeholder="Assignment title" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
                <textarea v-model="assignmentForm(module.id).instructions" placeholder="Instructions (optional)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="2" />
                <div class="flex gap-2">
                  <select v-model="assignmentForm(module.id).submission_type" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                    <option value="text">Text entry</option>
                    <option value="file">File upload</option>
                    <option value="link">Link</option>
                  </select>
                  <input v-model="assignmentForm(module.id).due_at" type="datetime-local" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>

                <div v-if="assignmentForm(module.id).rubric_criteria.length === 0">
                  <input v-model="assignmentForm(module.id).points_possible" type="number" min="0" placeholder="Points possible" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
                </div>
                <div v-else class="space-y-1">
                  <div v-for="(criterion, i) in assignmentForm(module.id).rubric_criteria" :key="i" class="flex gap-2 items-center">
                    <input v-model="criterion.description" placeholder="Criterion" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1" />
                    <input v-model="criterion.max_points" type="number" min="0" placeholder="Points" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-24" />
                    <button @click="removeRubricCriterion(module.id, i)" class="p-1 text-red-400 hover:text-red-600"><TrashIcon class="h-4 w-4" /></button>
                  </div>
                </div>

                <div v-if="rubric_templates.length" class="flex gap-2 items-center">
                  <select @change="e => applyTemplate(module.id, e.target.value)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1">
                    <option value="" disabled selected>Start from a saved template</option>
                    <option v-for="t in rubric_templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                  </select>
                </div>

                <button @click="addRubricCriterion(module.id)" class="text-xs text-indigo-600 underline">+ Add rubric criterion</button>

                <div v-if="assignmentForm(module.id).rubric_criteria.length > 0" class="flex items-center gap-2">
                  <input type="checkbox" v-model="assignmentForm(module.id).save_as_template" :id="`save-template-${module.id}`" />
                  <label :for="`save-template-${module.id}`" class="text-xs text-slate-600">Save these criteria as a template</label>
                </div>
                <input v-if="assignmentForm(module.id).save_as_template" v-model="assignmentForm(module.id).template_name" placeholder="Template name" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />

                <div v-if="rubric_templates.length" class="border-t border-slate-100 pt-2 space-y-1">
                  <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">My templates</p>
                  <div v-for="t in rubric_templates" :key="t.id" class="flex items-center gap-2">
                    <input v-if="renameTemplateDrafts[t.id] !== undefined" v-model="renameTemplateDrafts[t.id]" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs flex-1" />
                    <span v-else class="text-xs text-slate-600 flex-1">{{ t.name }}</span>
                    <button v-if="renameTemplateDrafts[t.id] !== undefined" @click="saveTemplateRename(t)" class="text-xs text-indigo-600 underline">Save</button>
                    <button v-else @click="startRenameTemplate(t)" class="text-xs text-slate-500 underline">Rename</button>
                    <button @click="deleteTemplate(t)" class="text-xs text-red-500 underline">Delete</button>
                  </div>
                </div>

                <button @click="addAssignment(module.id)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Add assignment</button>
              </div>

              <div v-if="course.can_edit" class="border-t border-slate-100 pt-3 space-y-2">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">New quiz</p>
                <input v-model="quizForm(module.id).title" placeholder="Quiz title" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
                <textarea v-model="quizForm(module.id).instructions" placeholder="Instructions (optional)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="2" />
                <div class="grid grid-cols-2 gap-2">
                  <input v-model="quizForm(module.id).time_limit_minutes" type="number" min="1" placeholder="Time limit (minutes, optional)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                  <input v-model="quizForm(module.id).max_attempts" type="number" min="1" placeholder="Max attempts (optional)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                  <input v-model="quizForm(module.id).questions_to_draw" type="number" min="1" placeholder="Draw N random questions (optional)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                  <input v-model="quizForm(module.id).due_at" type="datetime-local" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
                <div class="flex gap-4">
                  <label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" v-model="quizForm(module.id).shuffle_questions" /> Shuffle questions</label>
                  <label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" v-model="quizForm(module.id).shuffle_options" /> Shuffle options</label>
                </div>

                <div v-for="(q, qIndex) in quizForm(module.id).questions" :key="qIndex" class="border border-slate-100 rounded-lg p-3 space-y-2">
                  <div class="flex gap-2">
                    <select v-model="q.question_type" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                      <option value="multiple_choice">Multiple choice</option>
                      <option value="true_false">True / False</option>
                      <option value="multiple_select">Multiple select</option>
                      <option value="short_answer">Short answer</option>
                      <option value="essay">Essay</option>
                    </select>
                    <input v-model="q.points" type="number" min="0" placeholder="Points" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-24" />
                    <select v-model="q.difficulty" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                      <option value="">Difficulty (optional)</option>
                      <option value="easy">Easy</option>
                      <option value="medium">Medium</option>
                      <option value="hard">Hard</option>
                    </select>
                    <button @click="removeQuizQuestion(module.id, qIndex)" class="p-1 text-red-400 hover:text-red-600"><TrashIcon class="h-4 w-4" /></button>
                  </div>
                  <textarea v-model="q.prompt" placeholder="Question prompt (supports $LaTeX$)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="2" />
                  <MathContent v-if="q.prompt" :html="sanitizeHtml(q.prompt)" class="prose prose-sm max-w-none border-l-2 border-slate-200 pl-2" />

                  <div v-if="quiz_question_bank.length" class="flex gap-2 items-center">
                    <select @change="e => applyQuizBankItem(module.id, qIndex, e.target.value)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1">
                      <option value="" disabled selected>Start from a saved question</option>
                      <option v-for="b in quiz_question_bank" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                  </div>

                  <div v-if="['multiple_choice', 'true_false', 'multiple_select'].includes(q.question_type)" class="space-y-1">
                    <div v-for="(o, oIndex) in q.options" :key="oIndex" class="flex gap-2 items-center">
                      <input v-model="o.option_text" placeholder="Option text" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1" />
                      <label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" v-model="o.is_correct" /> Correct</label>
                      <button @click="removeQuizQuestionOption(module.id, qIndex, oIndex)" class="p-1 text-red-400 hover:text-red-600"><TrashIcon class="h-3.5 w-3.5" /></button>
                    </div>
                    <button @click="addQuizQuestionOption(module.id, qIndex)" class="text-xs text-indigo-600 underline">+ Add option</button>
                  </div>

                  <div v-else-if="q.question_type === 'short_answer'" class="space-y-1">
                    <div v-for="(a, aIndex) in q.accepted_answers" :key="aIndex" class="flex gap-2 items-center">
                      <input v-model="q.accepted_answers[aIndex]" placeholder="Accepted answer" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1" />
                      <button @click="removeAcceptedAnswer(module.id, qIndex, aIndex)" class="p-1 text-red-400 hover:text-red-600"><TrashIcon class="h-3.5 w-3.5" /></button>
                    </div>
                    <button @click="addAcceptedAnswer(module.id, qIndex)" class="text-xs text-indigo-600 underline">+ Add accepted answer</button>
                  </div>

                  <div class="flex items-center gap-2">
                    <input type="checkbox" v-model="q.save_to_bank" :id="`save-qbank-${module.id}-${qIndex}`" />
                    <label :for="`save-qbank-${module.id}-${qIndex}`" class="text-xs text-slate-600">Save this question to my bank</label>
                  </div>
                  <input v-if="q.save_to_bank" v-model="q.bank_name" placeholder="Bank name" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
                </div>
                <button @click="addQuizQuestion(module.id)" class="text-xs text-indigo-600 underline">+ Add question</button>

                <div v-if="quiz_question_bank.length" class="border-t border-slate-100 pt-2 space-y-1">
                  <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">My question bank</p>
                  <div v-for="b in quiz_question_bank" :key="b.id" class="flex items-center gap-2">
                    <input v-if="renameBankItemDrafts[b.id] !== undefined" v-model="renameBankItemDrafts[b.id]" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs flex-1" />
                    <span v-else class="text-xs text-slate-600 flex-1">{{ b.name }}</span>
                    <button v-if="renameBankItemDrafts[b.id] !== undefined" @click="saveBankItemRename(b)" class="text-xs text-indigo-600 underline">Save</button>
                    <button v-else @click="startRenameBankItem(b)" class="text-xs text-slate-500 underline">Rename</button>
                    <button @click="deleteBankItem(b)" class="text-xs text-red-500 underline">Delete</button>
                  </div>
                </div>

                <button @click="addQuiz(module.id)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Add quiz</button>
              </div>
            </div>
          </div>
        </div>

        <div v-if="course.can_edit" class="flex gap-2">
          <input v-model="newModuleTitle" placeholder="New module title" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1" />
          <button @click="addModule" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-1">
            <PlusIcon class="h-4 w-4" /> Add module
          </button>
        </div>
      </section>

      <!-- Announcements -->
      <section>
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Announcements</h2>

        <div v-for="announcement in course.announcements" :key="announcement.id" class="border border-slate-200 rounded-lg p-4 mb-2">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm font-medium text-slate-800">{{ announcement.title }}</p>
              <p class="text-xs text-slate-500">{{ announcement.posted_by }} — {{ new Date(announcement.posted_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
            </div>
            <button v-if="course.can_edit" @click="deleteAnnouncement(announcement.id)" class="p-1 text-red-400 hover:text-red-600"><TrashIcon class="h-4 w-4" /></button>
          </div>
          <p class="text-sm text-slate-600 mt-2 whitespace-pre-line">{{ announcement.body }}</p>
        </div>

        <div v-if="course.can_edit" class="border border-slate-200 rounded-lg p-4 space-y-2">
          <input v-model="announcementForm.title" placeholder="Announcement title" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
          <textarea v-model="announcementForm.body" placeholder="Announcement body" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="3" />
          <button @click="postAnnouncement" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Post announcement</button>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
