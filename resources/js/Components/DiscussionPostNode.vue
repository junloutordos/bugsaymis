<script setup>
import { ref, computed } from 'vue'
import MathContent from '@/Components/MathContent.vue'
import DOMPurify from 'dompurify'

const props = defineProps({
  post: Object,
  currentAuthorType: String,
  currentAuthorId: [Number, String],
  canModerateAny: Boolean,
})

const emit = defineEmits(['reply', 'edit', 'delete'])

function sanitizeHtml(html) {
  return DOMPurify.sanitize(html || '')
}

const isOwnPost = computed(() =>
  props.post.author_type === props.currentAuthorType && Number(props.post.author_id) === Number(props.currentAuthorId)
)
const canEditPost = computed(() => isOwnPost.value && ! props.post.is_deleted)
const canDeletePost = computed(() => (isOwnPost.value || props.canModerateAny) && ! props.post.is_deleted)

const replying = ref(false)
const editing = ref(false)
const replyBody = ref('')
const editBody = ref(props.post.body || '')

function submitReply() {
  if (! replyBody.value.trim()) return
  emit('reply', props.post.id, replyBody.value)
  replyBody.value = ''
  replying.value = false
}
function submitEdit() {
  emit('edit', props.post.id, editBody.value)
  editing.value = false
}
</script>

<template>
  <div class="border-l-2 border-slate-100 pl-3">
    <div class="border border-slate-200 rounded-lg p-3 mb-2">
      <div class="flex items-center justify-between">
        <p class="text-xs font-medium text-slate-700">{{ post.author_name }}</p>
        <p class="text-xs text-slate-400">{{ new Date(post.created_at).toLocaleString('en-PH') }}</p>
      </div>

      <p v-if="post.is_deleted" class="text-sm text-slate-400 italic mt-1">[deleted]</p>
      <MathContent v-else-if="!editing" :html="sanitizeHtml(post.body)" class="prose prose-sm max-w-none mt-1" />
      <div v-else class="mt-1 space-y-2">
        <textarea v-model="editBody" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="2" />
        <div class="flex gap-2">
          <button @click="submitEdit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">Save</button>
          <button @click="editing = false" class="text-xs text-slate-500 underline">Cancel</button>
        </div>
      </div>

      <div v-if="!post.is_deleted" class="flex gap-3 mt-2">
        <button @click="replying = !replying" class="text-xs text-indigo-600 underline">Reply</button>
        <button v-if="canEditPost && !editing" @click="editing = true" class="text-xs text-slate-500 underline">Edit</button>
        <button v-if="canDeletePost" @click="emit('delete', post.id)" class="text-xs text-red-500 underline">Delete</button>
      </div>

      <div v-if="replying" class="mt-2 space-y-2">
        <textarea v-model="replyBody" placeholder="Write a reply" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="2" />
        <div class="flex gap-2">
          <button @click="submitReply" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">Post reply</button>
          <button @click="replying = false" class="text-xs text-slate-500 underline">Cancel</button>
        </div>
      </div>
    </div>

    <DiscussionPostNode
      v-for="reply in post.replies"
      :key="reply.id"
      :post="reply"
      :current-author-type="currentAuthorType"
      :current-author-id="currentAuthorId"
      :can-moderate-any="canModerateAny"
      @reply="(...args) => emit('reply', ...args)"
      @edit="(...args) => emit('edit', ...args)"
      @delete="(...args) => emit('delete', ...args)"
    />
  </div>
</template>
