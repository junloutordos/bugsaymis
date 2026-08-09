<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DiscussionPostNode from '@/Components/DiscussionPostNode.vue'
import DOMPurify from 'dompurify'

const props = defineProps({ discussion: Object, posts: Array, current_user_id: Number })

function sanitizeHtml(html) {
  return DOMPurify.sanitize(html || '')
}

const newTopLevelBody = ref('')
function postTopLevel() {
  if (! newTopLevelBody.value.trim()) return
  router.post(route('learn.discussion-posts.store', props.discussion.id), { body: newTopLevelBody.value }, {
    preserveScroll: true,
    onSuccess: () => { newTopLevelBody.value = '' },
  })
}

function handleReply(parentPostId, body) {
  router.post(route('learn.discussion-posts.store', props.discussion.id), { parent_post_id: parentPostId, body }, { preserveScroll: true })
}
function handleEdit(postId, body) {
  router.put(route('learn.discussion-posts.update', postId), { body }, { preserveScroll: true })
}
function handleDelete(postId) {
  router.delete(route('learn.discussion-posts.destroy', postId), { preserveScroll: true })
}
</script>

<template>
  <Head :title="discussion.title" />
  <AdminLayout :title="discussion.title">
    <div class="max-w-3xl mx-auto py-6 px-4 space-y-4">
      <div>
        <h1 class="text-lg font-semibold text-slate-800">{{ discussion.title }}</h1>
        <div class="prose prose-sm max-w-none mt-2" v-html="sanitizeHtml(discussion.prompt)" />
      </div>

      <div class="border border-slate-200 rounded-lg p-4 space-y-2">
        <textarea v-model="newTopLevelBody" placeholder="Post a reply to the discussion" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="3" />
        <button @click="postTopLevel" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Post</button>
      </div>

      <DiscussionPostNode
        v-for="post in posts"
        :key="post.id"
        :post="post"
        current-author-type="faculty"
        :current-author-id="current_user_id"
        :can-moderate-any="discussion.can_edit"
        @reply="handleReply"
        @edit="handleEdit"
        @delete="handleDelete"
      />
      <p v-if="posts.length === 0" class="text-sm text-slate-400">No posts yet.</p>
    </div>
  </AdminLayout>
</template>
