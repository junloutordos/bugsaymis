<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import DOMPurify from 'dompurify'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import RichTextEditor from '@/Components/RichTextEditor.vue'
import MathContent from '@/Components/MathContent.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppInput from '@/Components/AppInput.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTabs from '@/Components/AppTabs.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import CourseCover from '@/Components/CourseCover.vue'
import SetupProgressBar from '@/Components/SetupProgressBar.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { COURSE_COVER_PRESETS } from '@/Constants/courseCoverPresets'
import {
  PlusIcon, TrashIcon, EyeIcon, EyeSlashIcon,
  ArrowUpIcon, ArrowDownIcon, DocumentIcon, PaperClipIcon, AcademicCapIcon, ChatBubbleLeftRightIcon,
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

// ── Tabs ─────────────────────────────────────────────────────────────────
const tabs = [
  { key: 'overview', label: 'Overview' },
  { key: 'modules', label: 'Modules' },
  { key: 'announcements', label: 'Announcements' },
]
const activeTab = ref('overview')
const subjectInitials = computed(() => (props.course.subject_name || '').trim().split(/\s+/).map(w => w[0]).join('').slice(0, 3).toUpperCase())

// ── Cover photo ──────────────────────────────────────────────────────────
function selectCoverPreset(presetKey) {
  router.put(route('learn.cover.update', props.course.id), { preset: presetKey }, { preserveScroll: true })
}
async function uploadCoverPhoto(event) {
  const file = event.target.files[0]
  if (! file) return
  const base64 = await readFileAsBase64(file)
  router.put(route('learn.cover.update', props.course.id), { photo_base64: base64 }, { preserveScroll: true })
  event.target.value = ''
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

const discussionForms = ref({})
function discussionForm(moduleId) {
  if (! discussionForms.value[moduleId]) {
    discussionForms.value[moduleId] = useForm({ title: '', prompt: '', points_possible: '' })
  }
  return discussionForms.value[moduleId]
}
function addDiscussion(moduleId) {
  discussionForm(moduleId).post(route('learn.items.store-discussion', moduleId), {
    preserveScroll: true,
    onSuccess: () => { discussionForms.value[moduleId] = null },
  })
}
</script>

<template>
  <Head :title="`Learn — ${course.subject_name}`" />
  <AdminLayout :title="course.subject_name">
    <div class="max-w-5xl mx-auto py-6 px-4 space-y-5">
      <AppPageHeader
        hero
        :title="course.subject_name"
        :subtitle="`Grade ${course.grade_level} — ${course.section_name}`"
      >
        <template #cover>
          <CourseCover :photo-url="course.cover_photo_url" :preset="course.cover_preset" :initials="subjectInitials" class="h-full w-full" />
        </template>
        <template #actions>
          <AppBadge :color="course.status === 'published' ? 'green' : 'slate'">
            {{ course.status === 'published' ? 'Published' : 'Draft' }}
          </AppBadge>
          <Link v-if="course.can_edit" :href="route('learn.course-trend', course.id)" class="text-xs font-medium text-indigo-600 hover:underline">
            Quiz trend
          </Link>
          <AppButton
            v-if="course.can_edit"
            :variant="course.status === 'published' ? 'secondary' : 'primary'"
            @click="toggleCourseStatus"
          >
            {{ course.status === 'published' ? 'Unpublish' : 'Publish course' }}
          </AppButton>
        </template>
      </AppPageHeader>

      <div v-if="course.is_read_only" class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800">
        This course is from a past school year and is read-only.
      </div>

      <AppTabs v-model="activeTab" :tabs="tabs">
        <template v-if="activeTab === 'overview'">
          <div class="space-y-5">
            <AppCard title="Course setup">
              <SetupProgressBar :steps="course.setup_progress.steps" :percent="course.setup_progress.percent" />
            </AppCard>

            <AppCard v-if="course.can_edit" title="Course cover" subtitle="Shown on your class card and at the top of this page.">
              <div class="flex flex-wrap gap-3">
                <button
                  v-for="preset in COURSE_COVER_PRESETS"
                  :key="preset.key"
                  type="button"
                  :class="['h-16 w-24 rounded-lg ring-2 transition', preset.class, course.cover_preset === preset.key && !course.cover_photo_url ? 'ring-indigo-600' : 'ring-transparent hover:ring-slate-300']"
                  :aria-label="preset.label"
                  @click="selectCoverPreset(preset.key)"
                />
                <label class="flex h-16 w-24 cursor-pointer items-center justify-center rounded-lg border-2 border-dashed border-slate-300 text-center text-xs font-medium text-slate-500 hover:border-indigo-400 hover:text-indigo-600">
                  Upload photo
                  <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="uploadCoverPhoto" />
                </label>
              </div>
            </AppCard>

            <AppCard title="Syllabus">
              <RichTextEditor v-if="course.can_edit" v-model="syllabus" />
              <div v-else class="prose prose-sm max-w-none" v-html="sanitizeHtml(course.syllabus_body) || '<p class=\'text-slate-400\'>No syllabus yet.</p>'" />
              <AppButton v-if="course.can_edit" class="mt-3" @click="saveSyllabus">Save syllabus</AppButton>
            </AppCard>
          </div>
        </template>

        <template v-else-if="activeTab === 'modules'">
          <div class="space-y-5">
            <AppCard v-for="(module, index) in course.modules" :key="module.id" :padded="false">
              <template #header>
                <div class="flex flex-1 items-center justify-between gap-3">
                  <div class="flex min-w-0 items-center gap-2">
                    <span class="truncate text-sm font-semibold text-slate-800">{{ module.title }}</span>
                    <AppBadge :color="module.is_published ? 'green' : 'slate'">{{ module.is_published ? 'Published' : 'Draft' }}</AppBadge>
                  </div>
                  <div v-if="course.can_edit" class="flex shrink-0 items-center gap-1">
                    <AppIconButton label="Move up" @click="moveModule(index, -1)"><ArrowUpIcon class="h-4 w-4" /></AppIconButton>
                    <AppIconButton label="Move down" @click="moveModule(index, 1)"><ArrowDownIcon class="h-4 w-4" /></AppIconButton>
                    <AppIconButton :label="module.is_published ? 'Unpublish module' : 'Publish module'" @click="toggleModulePublish(module.id)">
                      <EyeIcon v-if="!module.is_published" class="h-4 w-4" />
                      <EyeSlashIcon v-else class="h-4 w-4" />
                    </AppIconButton>
                    <AppIconButton label="Delete module" variant="danger" @click="deleteModule(module.id)"><TrashIcon class="h-4 w-4" /></AppIconButton>
                  </div>
                </div>
              </template>

              <div class="space-y-3 p-5">
                <div v-for="(item, itemIndex) in module.items" :key="item.id" class="flex items-start gap-2 rounded-lg border border-slate-100 p-3">
                  <DocumentIcon v-if="item.type === 'page'" class="h-5 w-5 shrink-0 text-slate-400" />
                  <AcademicCapIcon v-else-if="item.type === 'quiz'" class="h-5 w-5 shrink-0 text-slate-400" />
                  <ChatBubbleLeftRightIcon v-else-if="item.type === 'discussion'" class="h-5 w-5 shrink-0 text-slate-400" />
                  <PaperClipIcon v-else class="h-5 w-5 shrink-0 text-slate-400" />
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                      <p class="text-sm font-medium text-slate-700">{{ item.title }}</p>
                      <AppBadge :color="item.is_published ? 'green' : 'slate'">{{ item.is_published ? 'Published' : 'Draft' }}</AppBadge>
                    </div>
                    <div v-if="item.type === 'page' && item.body" class="prose prose-sm mt-1 max-w-none" v-html="sanitizeHtml(item.body)" />
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

                      <div v-if="course.can_edit" class="space-y-2 border-t border-slate-100 pt-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Questions</p>
                        <p v-if="item.quiz.is_locked" class="text-xs text-warning-600">Locked — students have submitted attempts. Existing questions cannot be changed, but new ones can still be added.</p>

                        <div v-for="q in item.quiz.questions" :key="q.id" class="flex items-start gap-2 rounded-lg border border-slate-100 p-2">
                          <div class="min-w-0 flex-1">
                            <MathContent :html="sanitizeHtml(q.prompt)" class="prose prose-sm max-w-none" />
                            <p class="text-xs text-slate-400">{{ q.question_type }} — {{ q.points }} pts</p>
                          </div>
                          <AppIconButton v-if="!item.quiz.is_locked" label="Delete question" variant="danger" size="sm" @click="deleteQuizQuestion(q.id)"><TrashIcon class="h-3.5 w-3.5" /></AppIconButton>
                        </div>

                        <AppCard class="space-y-2">
                          <p class="text-xs text-slate-500">Add another question</p>
                          <div class="flex gap-2">
                            <AppSelect v-model="newQuestionForm(item.quiz.id).question_type" :show-blank="false" class="max-w-[200px]">
                              <option value="multiple_choice">Multiple choice</option>
                              <option value="true_false">True / False</option>
                              <option value="multiple_select">Multiple select</option>
                              <option value="short_answer">Short answer</option>
                              <option value="essay">Essay</option>
                            </AppSelect>
                            <AppInput v-model="newQuestionForm(item.quiz.id).points" type="number" min="0" placeholder="Points" class="w-24" />
                          </div>
                          <AppTextarea v-model="newQuestionForm(item.quiz.id).prompt" placeholder="Question prompt (supports $LaTeX$)" :rows="2" />

                          <div v-if="['multiple_choice', 'true_false', 'multiple_select'].includes(newQuestionForm(item.quiz.id).question_type)" class="space-y-1">
                            <div v-for="(o, oIndex) in newQuestionForm(item.quiz.id).options" :key="oIndex" class="flex items-center gap-2">
                              <AppInput v-model="o.option_text" placeholder="Option text" class="flex-1" />
                              <label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" v-model="o.is_correct" /> Correct</label>
                              <AppIconButton label="Remove option" variant="danger" size="sm" @click="removeNewQuestionOption(item.quiz.id, oIndex)"><TrashIcon class="h-3.5 w-3.5" /></AppIconButton>
                            </div>
                            <button type="button" class="text-xs text-indigo-600 underline" @click="addNewQuestionOption(item.quiz.id)">+ Add option</button>
                          </div>
                          <div v-else-if="newQuestionForm(item.quiz.id).question_type === 'short_answer'" class="space-y-1">
                            <div v-for="(a, aIndex) in newQuestionForm(item.quiz.id).accepted_answers" :key="aIndex" class="flex items-center gap-2">
                              <AppInput v-model="newQuestionForm(item.quiz.id).accepted_answers[aIndex]" placeholder="Accepted answer" class="flex-1" />
                              <AppIconButton label="Remove accepted answer" variant="danger" size="sm" @click="removeNewAcceptedAnswer(item.quiz.id, aIndex)"><TrashIcon class="h-3.5 w-3.5" /></AppIconButton>
                            </div>
                            <button type="button" class="text-xs text-indigo-600 underline" @click="addNewAcceptedAnswer(item.quiz.id)">+ Add accepted answer</button>
                          </div>

                          <AppButton variant="secondary" size="sm" @click="submitNewQuestion(item.quiz.id)">Add question</AppButton>
                        </AppCard>
                      </div>
                    </div>
                    <div v-if="item.type === 'discussion'" class="mt-1 space-y-1">
                      <div class="prose prose-sm max-w-none" v-html="sanitizeHtml(item.discussion.prompt)" />
                      <p class="text-xs text-slate-500">
                        {{ item.discussion.post_count }} post{{ item.discussion.post_count === 1 ? '' : 's' }}
                        <span v-if="item.discussion.max_score !== null"> — {{ item.discussion.max_score }} pts</span>
                      </p>
                      <div class="flex gap-2">
                        <Link :href="route('learn.discussions.show', item.discussion.id)" class="text-xs text-indigo-600 underline">View discussion</Link>
                        <Link v-if="item.discussion.max_score !== null" :href="route('learn.discussions.grades', item.discussion.id)" class="text-xs text-indigo-600 underline">Grades</Link>
                      </div>
                    </div>
                  </div>
                  <div v-if="course.can_edit" class="flex shrink-0 items-center gap-1">
                    <AppIconButton label="Move up" size="sm" @click="moveItem(module, itemIndex, -1)"><ArrowUpIcon class="h-3.5 w-3.5" /></AppIconButton>
                    <AppIconButton label="Move down" size="sm" @click="moveItem(module, itemIndex, 1)"><ArrowDownIcon class="h-3.5 w-3.5" /></AppIconButton>
                    <AppIconButton :label="item.is_published ? 'Unpublish item' : 'Publish item'" size="sm" @click="toggleItemPublish(item.id)">
                      <EyeIcon v-if="!item.is_published" class="h-3.5 w-3.5" />
                      <EyeSlashIcon v-else class="h-3.5 w-3.5" />
                    </AppIconButton>
                    <AppIconButton label="Delete item" variant="danger" size="sm" @click="deleteItem(item.id)"><TrashIcon class="h-3.5 w-3.5" /></AppIconButton>
                  </div>
                </div>

                <div v-if="course.can_edit" class="space-y-3 border-t border-slate-100 pt-3">
                  <div class="flex gap-2">
                    <AppInput v-model="pageForm(module.id).title" placeholder="Page title" class="flex-1" />
                    <AppButton variant="secondary" @click="addPage(module.id)">Add page</AppButton>
                  </div>
                  <AppTextarea v-model="pageForm(module.id).body" placeholder="Page body (optional)" :rows="2" />
                  <AppInput v-model="pageForm(module.id).video_url" placeholder="Video URL (YouTube/Drive, optional)" />

                  <div class="flex items-center gap-2">
                    <AppInput v-model="fileTitles[module.id]" placeholder="File title" class="flex-1" />
                    <label class="cursor-pointer rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
                      Upload file
                      <input type="file" class="hidden" @change="e => addFile(module.id, e)" />
                    </label>
                  </div>

                  <div class="space-y-2 border-t border-slate-100 pt-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">New assignment</p>
                    <AppInput v-model="assignmentForm(module.id).title" placeholder="Assignment title" />
                    <AppTextarea v-model="assignmentForm(module.id).instructions" placeholder="Instructions (optional)" :rows="2" />
                    <div class="flex gap-2">
                      <AppSelect v-model="assignmentForm(module.id).submission_type" :show-blank="false" class="max-w-[200px]">
                        <option value="text">Text entry</option>
                        <option value="file">File upload</option>
                        <option value="link">Link</option>
                      </AppSelect>
                      <AppInput v-model="assignmentForm(module.id).due_at" type="datetime-local" />
                    </div>

                    <div v-if="assignmentForm(module.id).rubric_criteria.length === 0">
                      <AppInput v-model="assignmentForm(module.id).points_possible" type="number" min="0" placeholder="Points possible" />
                    </div>
                    <div v-else class="space-y-1">
                      <div v-for="(criterion, i) in assignmentForm(module.id).rubric_criteria" :key="i" class="flex items-center gap-2">
                        <AppInput v-model="criterion.description" placeholder="Criterion" class="flex-1" />
                        <AppInput v-model="criterion.max_points" type="number" min="0" placeholder="Points" class="w-24" />
                        <AppIconButton label="Remove criterion" variant="danger" @click="removeRubricCriterion(module.id, i)"><TrashIcon class="h-4 w-4" /></AppIconButton>
                      </div>
                    </div>

                    <AppSelect v-if="rubric_templates.length" :show-blank="false" placeholder="Start from a saved template" @update:model-value="value => applyTemplate(module.id, value)">
                      <option value="" disabled selected>Start from a saved template</option>
                      <option v-for="t in rubric_templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </AppSelect>

                    <button type="button" class="text-xs text-indigo-600 underline" @click="addRubricCriterion(module.id)">+ Add rubric criterion</button>

                    <div v-if="assignmentForm(module.id).rubric_criteria.length > 0" class="flex items-center gap-2">
                      <input type="checkbox" v-model="assignmentForm(module.id).save_as_template" :id="`save-template-${module.id}`" />
                      <label :for="`save-template-${module.id}`" class="text-xs text-slate-600">Save these criteria as a template</label>
                    </div>
                    <AppInput v-if="assignmentForm(module.id).save_as_template" v-model="assignmentForm(module.id).template_name" placeholder="Template name" />

                    <div v-if="rubric_templates.length" class="space-y-1 border-t border-slate-100 pt-2">
                      <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">My templates</p>
                      <div v-for="t in rubric_templates" :key="t.id" class="flex items-center gap-2">
                        <AppInput v-if="renameTemplateDrafts[t.id] !== undefined" v-model="renameTemplateDrafts[t.id]" class="flex-1" />
                        <span v-else class="flex-1 text-xs text-slate-600">{{ t.name }}</span>
                        <button v-if="renameTemplateDrafts[t.id] !== undefined" type="button" class="text-xs text-indigo-600 underline" @click="saveTemplateRename(t)">Save</button>
                        <button v-else type="button" class="text-xs text-slate-500 underline" @click="startRenameTemplate(t)">Rename</button>
                        <button type="button" class="text-xs text-red-500 underline" @click="deleteTemplate(t)">Delete</button>
                      </div>
                    </div>

                    <AppButton @click="addAssignment(module.id)">Add assignment</AppButton>
                  </div>

                  <div class="space-y-2 border-t border-slate-100 pt-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">New quiz</p>
                    <AppInput v-model="quizForm(module.id).title" placeholder="Quiz title" />
                    <AppTextarea v-model="quizForm(module.id).instructions" placeholder="Instructions (optional)" :rows="2" />
                    <div class="grid grid-cols-2 gap-2">
                      <AppInput v-model="quizForm(module.id).time_limit_minutes" type="number" min="1" placeholder="Time limit (minutes, optional)" />
                      <AppInput v-model="quizForm(module.id).max_attempts" type="number" min="1" placeholder="Max attempts (optional)" />
                      <AppInput v-model="quizForm(module.id).questions_to_draw" type="number" min="1" placeholder="Draw N random questions (optional)" />
                      <AppInput v-model="quizForm(module.id).due_at" type="datetime-local" />
                    </div>
                    <div class="flex gap-4">
                      <label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" v-model="quizForm(module.id).shuffle_questions" /> Shuffle questions</label>
                      <label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" v-model="quizForm(module.id).shuffle_options" /> Shuffle options</label>
                    </div>

                    <AppCard v-for="(q, qIndex) in quizForm(module.id).questions" :key="qIndex" class="space-y-2">
                      <div class="flex gap-2">
                        <AppSelect v-model="q.question_type" :show-blank="false" class="max-w-[180px]">
                          <option value="multiple_choice">Multiple choice</option>
                          <option value="true_false">True / False</option>
                          <option value="multiple_select">Multiple select</option>
                          <option value="short_answer">Short answer</option>
                          <option value="essay">Essay</option>
                        </AppSelect>
                        <AppInput v-model="q.points" type="number" min="0" placeholder="Points" class="w-24" />
                        <AppSelect v-model="q.difficulty" placeholder="Difficulty (optional)" class="max-w-[160px]">
                          <option value="easy">Easy</option>
                          <option value="medium">Medium</option>
                          <option value="hard">Hard</option>
                        </AppSelect>
                        <AppIconButton label="Remove question" variant="danger" @click="removeQuizQuestion(module.id, qIndex)"><TrashIcon class="h-4 w-4" /></AppIconButton>
                      </div>
                      <AppTextarea v-model="q.prompt" placeholder="Question prompt (supports $LaTeX$)" :rows="2" />
                      <MathContent v-if="q.prompt" :html="sanitizeHtml(q.prompt)" class="prose prose-sm max-w-none border-l-2 border-slate-200 pl-2" />

                      <AppSelect v-if="quiz_question_bank.length" :show-blank="false" placeholder="Start from a saved question" @update:model-value="value => applyQuizBankItem(module.id, qIndex, value)">
                        <option value="" disabled selected>Start from a saved question</option>
                        <option v-for="b in quiz_question_bank" :key="b.id" :value="b.id">{{ b.name }}</option>
                      </AppSelect>

                      <div v-if="['multiple_choice', 'true_false', 'multiple_select'].includes(q.question_type)" class="space-y-1">
                        <div v-for="(o, oIndex) in q.options" :key="oIndex" class="flex items-center gap-2">
                          <AppInput v-model="o.option_text" placeholder="Option text" class="flex-1" />
                          <label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" v-model="o.is_correct" /> Correct</label>
                          <AppIconButton label="Remove option" variant="danger" size="sm" @click="removeQuizQuestionOption(module.id, qIndex, oIndex)"><TrashIcon class="h-3.5 w-3.5" /></AppIconButton>
                        </div>
                        <button type="button" class="text-xs text-indigo-600 underline" @click="addQuizQuestionOption(module.id, qIndex)">+ Add option</button>
                      </div>

                      <div v-else-if="q.question_type === 'short_answer'" class="space-y-1">
                        <div v-for="(a, aIndex) in q.accepted_answers" :key="aIndex" class="flex items-center gap-2">
                          <AppInput v-model="q.accepted_answers[aIndex]" placeholder="Accepted answer" class="flex-1" />
                          <AppIconButton label="Remove accepted answer" variant="danger" size="sm" @click="removeAcceptedAnswer(module.id, qIndex, aIndex)"><TrashIcon class="h-3.5 w-3.5" /></AppIconButton>
                        </div>
                        <button type="button" class="text-xs text-indigo-600 underline" @click="addAcceptedAnswer(module.id, qIndex)">+ Add accepted answer</button>
                      </div>

                      <div class="flex items-center gap-2">
                        <input type="checkbox" v-model="q.save_to_bank" :id="`save-qbank-${module.id}-${qIndex}`" />
                        <label :for="`save-qbank-${module.id}-${qIndex}`" class="text-xs text-slate-600">Save this question to my bank</label>
                      </div>
                      <AppInput v-if="q.save_to_bank" v-model="q.bank_name" placeholder="Bank name" />
                    </AppCard>
                    <button type="button" class="text-xs text-indigo-600 underline" @click="addQuizQuestion(module.id)">+ Add question</button>

                    <div v-if="quiz_question_bank.length" class="space-y-1 border-t border-slate-100 pt-2">
                      <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">My question bank</p>
                      <div v-for="b in quiz_question_bank" :key="b.id" class="flex items-center gap-2">
                        <AppInput v-if="renameBankItemDrafts[b.id] !== undefined" v-model="renameBankItemDrafts[b.id]" class="flex-1" />
                        <span v-else class="flex-1 text-xs text-slate-600">{{ b.name }}</span>
                        <button v-if="renameBankItemDrafts[b.id] !== undefined" type="button" class="text-xs text-indigo-600 underline" @click="saveBankItemRename(b)">Save</button>
                        <button v-else type="button" class="text-xs text-slate-500 underline" @click="startRenameBankItem(b)">Rename</button>
                        <button type="button" class="text-xs text-red-500 underline" @click="deleteBankItem(b)">Delete</button>
                      </div>
                    </div>

                    <AppButton @click="addQuiz(module.id)">Add quiz</AppButton>
                  </div>

                  <div class="space-y-2 border-t border-slate-100 pt-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">New discussion</p>
                    <AppInput v-model="discussionForm(module.id).title" placeholder="Discussion title" />
                    <AppTextarea v-model="discussionForm(module.id).prompt" placeholder="Discussion prompt" :rows="2" />
                    <AppInput v-model="discussionForm(module.id).points_possible" type="number" min="0" placeholder="Points possible (optional — leave blank for ungraded)" />
                    <AppButton @click="addDiscussion(module.id)">Add discussion</AppButton>
                  </div>
                </div>
              </div>
            </AppCard>

            <div v-if="course.can_edit" class="flex gap-2">
              <AppInput v-model="newModuleTitle" placeholder="New module title" class="flex-1" />
              <AppButton @click="addModule"><PlusIcon class="h-4 w-4" /> Add module</AppButton>
            </div>

            <EmptyState v-if="course.modules.length === 0" title="No modules yet" subtitle="Add your first module to start building this course." />
          </div>
        </template>

        <template v-else>
          <div class="space-y-5">
            <AppCard v-for="announcement in course.announcements" :key="announcement.id">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-sm font-medium text-slate-800">{{ announcement.title }}</p>
                  <p class="text-xs text-slate-500">{{ announcement.posted_by }} — {{ new Date(announcement.posted_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
                </div>
                <AppIconButton v-if="course.can_edit" label="Delete announcement" variant="danger" @click="deleteAnnouncement(announcement.id)"><TrashIcon class="h-4 w-4" /></AppIconButton>
              </div>
              <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ announcement.body }}</p>
            </AppCard>

            <EmptyState v-if="course.announcements.length === 0" title="No announcements yet" />

            <AppCard v-if="course.can_edit" title="Post announcement">
              <div class="space-y-2">
                <AppInput v-model="announcementForm.title" placeholder="Announcement title" />
                <AppTextarea v-model="announcementForm.body" placeholder="Announcement body" :rows="3" />
                <AppButton @click="postAnnouncement">Post announcement</AppButton>
              </div>
            </AppCard>
          </div>
        </template>
      </AppTabs>
    </div>
  </AdminLayout>
</template>
