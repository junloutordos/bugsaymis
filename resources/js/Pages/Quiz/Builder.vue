<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppModal from '@/Components/AppModal.vue'
import AppBadge from '@/Components/AppBadge.vue'
import FlashMessage from '@/Components/FlashMessage.vue'
import {
  PlusIcon,
  TrashIcon,
  ChevronUpIcon,
  ChevronDownIcon,
  EyeIcon,
  PhotoIcon,
  CheckCircleIcon,
} from '@heroicons/vue/24/outline'
import { CheckCircleIcon as CheckCircleSolid } from '@heroicons/vue/24/solid'

const props = defineProps({
  quiz: { type: Object, default: null },
  sessions: { type: Array, default: () => [] },
  source_type: { type: String, default: null },
  source_id: { type: [String, Number], default: null },
})

const page = usePage()
const flash = computed(() => page.props.flash ?? {})

// ── New quiz — title/description only, then hand off to the full builder ──
const newQuizForm = reactive({ title: '', description: '' })
const creating = ref(false)

function createQuiz() {
  if (!newQuizForm.title.trim()) return
  creating.value = true
  router.post(route('quiz.store'), {
    title: newQuizForm.title,
    description: newQuizForm.description,
    source_type: props.source_type,
    source_id: props.source_id,
  }, { onFinish: () => (creating.value = false) })
}

// ── Existing quiz — full builder ────────────────────────────────────────────
const TYPE_LABELS = {
  mcq: 'Multiple Choice',
  true_false: 'True / False',
  checkbox: 'Checkboxes (multi-select)',
  poll: 'Poll (no right answer)',
  open_ended: 'Open-ended',
}

const quizForm = reactive({
  title: props.quiz?.title ?? '',
  description: props.quiz?.description ?? '',
})
const settings = reactive({
  shuffle_answers: props.quiz?.settings?.shuffle_answers ?? false,
  shuffle_questions: props.quiz?.settings?.shuffle_questions ?? false,
  require_roster_join: props.quiz?.settings?.require_roster_join ?? false,
})

function saveQuizMeta() {
  router.post(route('quiz.update', props.quiz.id), { ...quizForm }, { preserveScroll: true })
}
function saveSettings() {
  router.post(route('quiz.update', props.quiz.id), { settings: { ...settings } }, { preserveScroll: true })
}
function togglePublish() {
  const status = props.quiz.status === 'published' ? 'draft' : 'published'
  router.post(route('quiz.update', props.quiz.id), { status }, { preserveScroll: true })
}

function pickCover(e) {
  const file = e.target.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => {
    router.post(route('quiz.update', props.quiz.id), { cover_image_base64: reader.result }, { preserveScroll: true })
  }
  reader.readAsDataURL(file)
}

// ── Questions ────────────────────────────────────────────────────────────
const selectedId = ref(props.quiz?.questions?.[0]?.id ?? null)
const isNewQuestion = ref(props.quiz?.questions?.length === 0)

const blankQuestion = () => ({
  type: 'mcq',
  question_text: '',
  time_limit_seconds: 20,
  points_base: 1000,
  double_points: false,
  options: [
    { option_text: '', is_correct: true },
    { option_text: '', is_correct: false },
  ],
  image_base64: null,
  explanation_text: '',
  explanation_image_base64: null,
  remove_explanation_image: false,
})

const editForm = reactive(blankQuestion())
const imagePreview = ref(null)
const explanationImagePreview = ref(null)
const showExplanationEditor = ref(false)

function loadQuestionIntoForm(question) {
  editForm.type = question.type
  editForm.question_text = question.question_text
  editForm.time_limit_seconds = question.time_limit_seconds
  editForm.points_base = question.points_base
  editForm.double_points = question.double_points ?? false
  editForm.options = question.options.map(o => ({ option_text: o.option_text, is_correct: o.is_correct }))
  editForm.image_base64 = null
  editForm.explanation_text = question.explanation_text ?? ''
  editForm.explanation_image_base64 = null
  editForm.remove_explanation_image = false
  imagePreview.value = question.image
  explanationImagePreview.value = question.explanation_image
  showExplanationEditor.value = !!(question.explanation_text || question.explanation_image)
}

watch(selectedId, (id) => {
  if (id === null) return
  const q = props.quiz.questions.find(x => x.id === id)
  if (q) {
    isNewQuestion.value = false
    loadQuestionIntoForm(q)
  }
}, { immediate: true })

function addQuestion() {
  isNewQuestion.value = true
  selectedId.value = null
  Object.assign(editForm, blankQuestion())
  imagePreview.value = null
  explanationImagePreview.value = null
  showExplanationEditor.value = false
}

function onTypeChange() {
  if (editForm.type === 'true_false') {
    editForm.options = [
      { option_text: 'True', is_correct: true },
      { option_text: 'False', is_correct: false },
    ]
  } else if (editForm.type === 'poll') {
    editForm.options = editForm.options.map(o => ({ ...o, is_correct: false }))
  } else if (editForm.type === 'open_ended') {
    editForm.options = []
  } else if (editForm.options.length === 0) {
    editForm.options = [
      { option_text: '', is_correct: true },
      { option_text: '', is_correct: false },
    ]
  }
}

function addOption() {
  if (editForm.options.length >= 6) return
  editForm.options.push({ option_text: '', is_correct: false })
}
function removeOption(i) {
  editForm.options.splice(i, 1)
}
function setCorrect(i) {
  // mcq / true_false — single correct answer, radio-style
  editForm.options.forEach((o, idx) => (o.is_correct = idx === i))
}
function toggleCorrect(i) {
  // checkbox — multi-select
  editForm.options[i].is_correct = !editForm.options[i].is_correct
}

function pickQuestionImage(e) {
  const file = e.target.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => {
    editForm.image_base64 = reader.result
    imagePreview.value = reader.result
  }
  reader.readAsDataURL(file)
}

function pickExplanationImage(e) {
  const file = e.target.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => {
    editForm.explanation_image_base64 = reader.result
    editForm.remove_explanation_image = false
    explanationImagePreview.value = reader.result
  }
  reader.readAsDataURL(file)
}

function removeExplanationImage() {
  editForm.explanation_image_base64 = null
  editForm.remove_explanation_image = true
  explanationImagePreview.value = null
}

const saving = ref(false)
function saveQuestion() {
  saving.value = true
  const payload = { ...editForm }
  const url = isNewQuestion.value
    ? route('quiz.questions.store', props.quiz.id)
    : route('quiz.questions.update', [props.quiz.id, selectedId.value])

  router.post(url, payload, {
    preserveScroll: true,
    onSuccess: () => {
      isNewQuestion.value = false
    },
    onFinish: () => (saving.value = false),
  })
}

function deleteQuestion(question) {
  if (!confirm('Delete this question?')) return
  router.delete(route('quiz.questions.destroy', [props.quiz.id, question.id]), {
    preserveScroll: true,
    onSuccess: () => {
      selectedId.value = props.quiz.questions[0]?.id ?? null
      isNewQuestion.value = props.quiz.questions.length === 0
    },
  })
}

function moveQuestion(index, dir) {
  const list = [...props.quiz.questions]
  const target = index + dir
  if (target < 0 || target >= list.length) return
  ;[list[index], list[target]] = [list[target], list[index]]
  router.post(route('quiz.questions.reorder', props.quiz.id), {
    question_ids: list.map(q => q.id),
  }, { preserveScroll: true })
}

// ── Preview ──────────────────────────────────────────────────────────────
const showPreview = ref(false)
</script>

<template>
  <Head :title="quiz ? quiz.title : 'Create Quiz'" />
  <AdminLayout title="Quiz Builder">
    <FlashMessage :success="flash.success" :error="flash.error" />

    <!-- ── Step 1: no quiz yet — collect a title first ── -->
    <template v-if="!quiz">
      <AppPageHeader title="Create Quiz" subtitle="Give it a name — you'll add questions next" />
      <AppCard class="max-w-lg">
        <div class="space-y-4">
          <AppInput v-model="newQuizForm.title" label="Quiz title" required placeholder="e.g. Cell Biology Review" />
          <AppTextarea v-model="newQuizForm.description" label="Description" :rows="3" placeholder="Optional" />
          <AppButton variant="primary" :loading="creating" @click="createQuiz">
            <PlusIcon class="h-4 w-4" /> Create &amp; Add Questions
          </AppButton>
        </div>
      </AppCard>
    </template>

    <!-- ── Step 2: full builder ── -->
    <template v-else>
      <AppPageHeader :title="quiz.title" subtitle="Build your quiz — changes save as you go">
        <template #actions>
          <AppBadge :color="quiz.status === 'published' ? 'green' : 'slate'">{{ quiz.status }}</AppBadge>
          <AppButton variant="secondary" @click="showPreview = true"><EyeIcon class="h-4 w-4" /> Preview</AppButton>
          <AppButton :variant="quiz.status === 'published' ? 'secondary' : 'success'" @click="togglePublish">
            {{ quiz.status === 'published' ? 'Unpublish' : 'Publish' }}
          </AppButton>
        </template>
      </AppPageHeader>

      <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-5">
        <!-- Left rail -->
        <div class="space-y-4">
          <AppCard title="Quiz details" :padded="true">
            <div class="space-y-3">
              <label class="block">
                <span class="block text-xs font-medium text-slate-600 mb-1">Cover image</span>
                <div
                  class="h-20 rounded-lg mb-2 flex items-center justify-center cursor-pointer overflow-hidden"
                  :style="quiz.cover_image
                    ? `background-image:url(${quiz.cover_image});background-size:cover;background-position:center`
                    : 'background:#EFF6FF'"
                  @click="$refs.coverInput.click()"
                >
                  <PhotoIcon v-if="!quiz.cover_image" class="h-6 w-6 text-indigo-300" />
                </div>
                <input ref="coverInput" type="file" accept="image/*" class="hidden" @change="pickCover" />
              </label>
              <AppInput v-model="quizForm.title" label="Title" @blur="saveQuizMeta" />
              <AppTextarea v-model="quizForm.description" label="Description" :rows="2" @blur="saveQuizMeta" />
            </div>
          </AppCard>

          <AppCard title="Settings" :padded="true">
            <div class="space-y-2 text-sm text-slate-600">
              <label class="flex items-center gap-2">
                <input type="checkbox" v-model="settings.shuffle_answers" @change="saveSettings" />
                Shuffle answer order
              </label>
              <label class="flex items-center gap-2">
                <input type="checkbox" v-model="settings.require_roster_join" @change="saveSettings" />
                Require class roster join
              </label>
            </div>
          </AppCard>

          <AppCard title="Questions" :padded="false">
            <ul class="divide-y divide-slate-100">
              <li
                v-for="(q, i) in quiz.questions"
                :key="q.id"
                :class="['flex items-center gap-2 px-3 py-2 cursor-pointer text-sm', selectedId === q.id && !isNewQuestion ? 'bg-indigo-50' : 'hover:bg-slate-50']"
                @click="selectedId = q.id; isNewQuestion = false"
              >
                <span class="text-xs text-slate-400 w-4">{{ i + 1 }}</span>
                <span class="flex-1 truncate">{{ q.question_text || '(untitled)' }}</span>
                <span v-if="q.explanation_text || q.explanation_image" class="text-[10px]" title="Has explanation">💡</span>
                <span v-if="q.double_points" class="text-[10px] font-bold text-amber-500" title="Double points">2×</span>
                <button type="button" class="text-slate-300 hover:text-slate-600" @click.stop="moveQuestion(i, -1)"><ChevronUpIcon class="h-3.5 w-3.5" /></button>
                <button type="button" class="text-slate-300 hover:text-slate-600" @click.stop="moveQuestion(i, 1)"><ChevronDownIcon class="h-3.5 w-3.5" /></button>
              </li>
            </ul>
            <div class="p-3">
              <AppButton size="sm" variant="secondary" block @click="addQuestion">
                <PlusIcon class="h-4 w-4" /> Add Question
              </AppButton>
            </div>
          </AppCard>

          <AppCard v-if="sessions.length" title="Recent sessions" :padded="false">
            <ul class="divide-y divide-slate-100">
              <li v-for="s in sessions" :key="s.id" class="flex items-center gap-2 px-3 py-2 text-sm">
                <div class="flex-1 min-w-0">
                  <p class="text-slate-700 truncate">
                    PIN {{ s.game_pin }}
                    <AppBadge :color="s.status === 'ended' ? 'slate' : 'green'" class="ml-1">{{ s.status === 'ended' ? 'ended' : 'live' }}</AppBadge>
                  </p>
                  <p class="text-xs text-slate-400">
                    {{ new Date(s.created_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) }}
                    · {{ s.player_count }} player{{ s.player_count === 1 ? '' : 's' }}
                  </p>
                </div>
                <a
                  :href="s.status === 'ended' ? s.report_url : s.host_url"
                  class="text-xs text-indigo-600 hover:underline shrink-0"
                >{{ s.status === 'ended' ? 'Report' : 'Resume' }}</a>
              </li>
            </ul>
          </AppCard>
        </div>

        <!-- Right pane — question editor -->
        <AppCard v-if="isNewQuestion || selectedId" :title="isNewQuestion ? 'New Question' : 'Edit Question'">
          <div class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <AppSelect v-model="editForm.type" label="Question type" :show-blank="false" class="sm:col-span-1" @change="onTypeChange">
                <option v-for="(label, key) in TYPE_LABELS" :key="key" :value="key">{{ label }}</option>
              </AppSelect>
              <AppInput v-model.number="editForm.time_limit_seconds" type="number" label="Time limit (sec)" />
              <AppInput v-model.number="editForm.points_base" type="number" label="Points" :disabled="editForm.type === 'poll' || editForm.type === 'open_ended'" />
            </div>

            <label
              v-if="editForm.type !== 'poll' && editForm.type !== 'open_ended'"
              class="flex items-center gap-2 text-sm text-slate-600"
            >
              <input type="checkbox" v-model="editForm.double_points" />
              <span><span class="font-semibold text-amber-600">2× Double points</span> — this question is worth twice the score</span>
            </label>

            <AppTextarea v-model="editForm.question_text" label="Question" :rows="2" required />

            <label class="block">
              <span class="block text-xs font-medium text-slate-600 mb-1">Image (optional)</span>
              <div class="flex items-center gap-3">
                <div v-if="imagePreview" class="h-16 w-24 rounded-lg overflow-hidden bg-slate-100">
                  <img :src="imagePreview" class="h-full w-full object-cover" />
                </div>
                <AppButton size="sm" variant="secondary" type="button" @click="$refs.qImageInput.click()">
                  <PhotoIcon class="h-4 w-4" /> Upload
                </AppButton>
                <input ref="qImageInput" type="file" accept="image/*" class="hidden" @change="pickQuestionImage" />
              </div>
            </label>

            <div v-if="editForm.type !== 'open_ended'">
              <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs font-medium text-slate-600">Options</span>
                <button
                  v-if="editForm.type !== 'true_false' && editForm.options.length < 6"
                  type="button" class="text-xs text-indigo-600 hover:underline" @click="addOption"
                >+ Add option</button>
              </div>
              <div class="space-y-2">
                <div v-for="(opt, i) in editForm.options" :key="i" class="flex items-center gap-2">
                  <button
                    v-if="editForm.type === 'mcq' || editForm.type === 'true_false'"
                    type="button" @click="setCorrect(i)"
                    :class="opt.is_correct ? 'text-emerald-500' : 'text-slate-300 hover:text-slate-400'"
                  >
                    <component :is="opt.is_correct ? CheckCircleSolid : CheckCircleIcon" class="h-6 w-6" />
                  </button>
                  <input
                    v-else-if="editForm.type === 'checkbox'"
                    type="checkbox" :checked="opt.is_correct" @change="toggleCorrect(i)"
                  />
                  <AppInput v-model="opt.option_text" :placeholder="`Option ${i + 1}`" class="flex-1" :disabled="editForm.type === 'true_false'" />
                  <button
                    v-if="editForm.type !== 'true_false' && editForm.options.length > 2"
                    type="button" class="text-slate-300 hover:text-red-500" @click="removeOption(i)"
                  ><TrashIcon class="h-4 w-4" /></button>
                </div>
              </div>
            </div>

            <!-- Explanation / fun fact — shown by the host after the reveal -->
            <div class="border-t border-slate-100 pt-3">
              <button
                type="button"
                class="flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-700"
                @click="showExplanationEditor = !showExplanationEditor"
              >
                <component :is="showExplanationEditor ? ChevronUpIcon : ChevronDownIcon" class="h-4 w-4" />
                💡 Explanation / fun fact (optional)
              </button>
              <p class="text-xs text-slate-400 mt-0.5 ml-5">Shown after the correct answer is revealed, when you choose to display it while hosting.</p>

              <div v-if="showExplanationEditor" class="mt-3 space-y-3">
                <AppTextarea
                  v-model="editForm.explanation_text"
                  label="Explanation"
                  :rows="3"
                  maxlength="2000"
                  placeholder="e.g. The mitochondria converts nutrients into ATP — that's why it's called the powerhouse of the cell."
                />
                <label class="block">
                  <span class="block text-xs font-medium text-slate-600 mb-1">Photo (optional)</span>
                  <div class="flex items-center gap-3">
                    <div v-if="explanationImagePreview" class="h-16 w-24 rounded-lg overflow-hidden bg-slate-100">
                      <img :src="explanationImagePreview" class="h-full w-full object-cover" />
                    </div>
                    <AppButton size="sm" variant="secondary" type="button" @click="$refs.expImageInput.click()">
                      <PhotoIcon class="h-4 w-4" /> Upload
                    </AppButton>
                    <button
                      v-if="explanationImagePreview"
                      type="button" class="text-xs text-red-500 hover:underline" @click="removeExplanationImage"
                    >Remove</button>
                    <input ref="expImageInput" type="file" accept="image/*" class="hidden" @change="pickExplanationImage" />
                  </div>
                </label>
              </div>
            </div>

            <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
              <AppButton variant="primary" :loading="saving" @click="saveQuestion">Save Question</AppButton>
              <AppButton
                v-if="!isNewQuestion"
                variant="secondary" class="!text-red-500"
                @click="deleteQuestion({ id: selectedId })"
              ><TrashIcon class="h-4 w-4" /> Delete</AppButton>
            </div>
          </div>
        </AppCard>
      </div>
    </template>

    <!-- Preview modal -->
    <AppModal :show="showPreview" title="Question preview" size="lg" @close="showPreview = false">
      <div v-if="quiz && (selectedId || isNewQuestion)">
        <p class="text-sm text-slate-500 mb-3">{{ editForm.time_limit_seconds }}s · {{ editForm.points_base }} pts</p>
        <h3 class="text-lg font-semibold text-slate-800 mb-4">{{ editForm.question_text || '(untitled question)' }}</h3>
        <div v-if="editForm.options.length" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <div
            v-for="(opt, i) in editForm.options" :key="i"
            :class="['rounded-lg px-4 py-3 text-sm font-medium text-white flex items-center gap-2', ['bg-red-500','bg-blue-500','bg-amber-500','bg-emerald-500','bg-purple-500','bg-pink-500'][i % 6]]"
          >
            <CheckCircleSolid v-if="opt.is_correct" class="h-4 w-4" />
            {{ opt.option_text || `Option ${i + 1}` }}
          </div>
        </div>
        <p v-else class="text-sm text-slate-400 italic">Players type a free-text answer.</p>
      </div>
    </AppModal>
  </AdminLayout>
</template>
