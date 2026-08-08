<script setup>
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import DOMPurify from 'dompurify'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import RichTextEditor from '@/Components/RichTextEditor.vue'
import {
  PlusIcon, TrashIcon, EyeIcon, EyeSlashIcon,
  ArrowUpIcon, ArrowDownIcon, DocumentIcon, PaperClipIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({ course: Object })

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
        <button
          v-if="course.can_edit"
          @click="toggleCourseStatus"
          class="rounded-lg px-4 py-2 text-sm font-medium"
          :class="course.status === 'published' ? 'bg-slate-100 text-slate-700' : 'bg-indigo-600 hover:bg-indigo-700 text-white'"
        >
          {{ course.status === 'published' ? 'Unpublish' : 'Publish course' }}
        </button>
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
              <PaperClipIcon v-else class="h-5 w-5 text-slate-400 shrink-0" />
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-slate-700">{{ item.title }}</p>
                <div v-if="item.type === 'page' && item.body" class="prose prose-sm max-w-none mt-1" v-html="sanitizeHtml(item.body)" />
                <a v-if="item.type === 'page' && safeVideoUrl(item.video_url)" :href="safeVideoUrl(item.video_url)" target="_blank" rel="noopener noreferrer" class="text-xs text-indigo-600 underline">Watch video</a>
                <a v-if="item.type === 'file'" :href="item.file_url" target="_blank" rel="noopener noreferrer" class="text-xs text-indigo-600 underline">Download file</a>
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
