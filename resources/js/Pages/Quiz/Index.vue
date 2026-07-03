<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppInput from '@/Components/AppInput.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import {
  PlusIcon,
  QuestionMarkCircleIcon,
  PlayCircleIcon,
  PencilSquareIcon,
  DocumentDuplicateIcon,
  TrashIcon,
  PuzzlePieceIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  quizzes: Array,
})

const search = ref('')

const filtered = computed(() => {
  const q = search.value.toLowerCase()
  if (!q) return props.quizzes
  return props.quizzes.filter(qz => qz.title.toLowerCase().includes(q))
})

const statusColor = {
  draft: 'slate',
  published: 'green',
  archived: 'amber',
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

function hostQuiz(quiz) {
  if (quiz.question_count === 0) {
    alert('Add at least one question before hosting a session.')
    return
  }
  router.post(route('quiz.sessions.store', quiz.id))
}

function duplicateQuiz(quiz) {
  router.post(route('quiz.duplicate', quiz.id))
}

function deleteQuiz(quiz) {
  if (confirm(`Delete "${quiz.title}"? This cannot be undone.`)) {
    router.delete(route('quiz.destroy', quiz.id), { preserveScroll: true })
  }
}
</script>

<template>
  <Head title="Quizzes" />
  <AdminLayout title="Quizzes">
    <AppPageHeader title="Quizzes" subtitle="Build and host live interactive quizzes for activities and classes">
      <template #actions>
        <AppButton variant="primary" @click="router.visit(route('quiz.create'))">
          <PlusIcon class="h-4 w-4" /> Create Quiz
        </AppButton>
      </template>
    </AppPageHeader>

    <div class="mb-5 max-w-sm">
      <AppInput v-model="search" placeholder="Search quizzes..." />
    </div>

    <EmptyState
      v-if="filtered.length === 0"
      :icon="PuzzlePieceIcon"
      title="No quizzes yet"
      subtitle="Create your first quiz to run a live Kahoot-style session."
    >
      <AppButton class="mt-4" variant="primary" @click="router.visit(route('quiz.create'))">
        <PlusIcon class="h-4 w-4" /> Create Quiz
      </AppButton>
    </EmptyState>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="quiz in filtered"
        :key="quiz.id"
        class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden flex flex-col"
      >
        <div
          class="h-28 flex items-center justify-center"
          :style="quiz.cover_image
            ? `background-image:url(${quiz.cover_image});background-size:cover;background-position:center`
            : 'background:linear-gradient(135deg,#0A2A5E,#019FE6)'"
        >
          <QuestionMarkCircleIcon v-if="!quiz.cover_image" class="h-10 w-10 text-white/70" />
        </div>

        <div class="p-4 flex-1 flex flex-col">
          <div class="flex items-start justify-between gap-2">
            <h3 class="text-sm font-semibold text-slate-800 leading-snug line-clamp-2">{{ quiz.title }}</h3>
            <AppBadge :color="statusColor[quiz.status] ?? 'slate'">{{ quiz.status }}</AppBadge>
          </div>
          <p class="mt-1 text-xs text-slate-500">
            {{ quiz.question_count }} question{{ quiz.question_count === 1 ? '' : 's' }}
            <span v-if="quiz.owner"> · {{ quiz.owner }}</span>
          </p>
          <p class="text-xs text-slate-400 mt-0.5">Updated {{ formatDate(quiz.updated_at) }}</p>

          <div class="mt-4 flex items-center gap-1.5 pt-3 border-t border-slate-100">
            <AppButton size="sm" variant="primary" @click="hostQuiz(quiz)">
              <PlayCircleIcon class="h-4 w-4" /> Host
            </AppButton>
            <AppButton size="sm" variant="secondary" @click="router.visit(route('quiz.edit', quiz.id))">
              <PencilSquareIcon class="h-4 w-4" />
            </AppButton>
            <AppButton size="sm" variant="secondary" @click="duplicateQuiz(quiz)">
              <DocumentDuplicateIcon class="h-4 w-4" />
            </AppButton>
            <AppButton size="sm" variant="secondary" class="ml-auto !text-red-500" @click="deleteQuiz(quiz)">
              <TrashIcon class="h-4 w-4" />
            </AppButton>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
