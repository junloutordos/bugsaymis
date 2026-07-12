<template>
  <Head title="Announcements" />
  <AdminLayout title="Announcements">
    <div class="space-y-5">

      <AppPageHeader title="Announcements"
        subtitle="Campus announcements and advisories">
        <template #actions>
          <AppButton v-if="canManage" @click="openForm()">
            <PlusIcon class="h-4 w-4" /> New Announcement
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
        class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>

      <!-- Filters -->
      <AppFilterBar>
        <div class="relative">
          <MagnifyingGlassIcon class="h-4 w-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input v-model="search" type="text" placeholder="Search announcements..."
            class="pl-9 pr-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent w-64" />
        </div>
        <select v-if="canManage" v-model="statusFilter"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option value="">All Statuses</option>
          <option value="published">Published</option>
          <option value="draft">Draft</option>
        </select>
      </AppFilterBar>

      <!-- Feed -->
      <div v-if="displayed.length" class="space-y-4">
        <AppCard v-for="a in displayed" :key="a.id" :padded="false">
          <div class="p-5 space-y-3">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="text-base font-semibold text-slate-800">{{ a.title }}</h3>
                  <AppBadge v-if="a.status === 'draft'" color="amber">Draft</AppBadge>
                  <AppBadge v-if="canManage" :color="a.audience === 'all' ? 'indigo' : 'blue'">
                    {{ a.audience === 'all' ? 'All users' : `${a.target_count} recipient(s)` }}
                  </AppBadge>
                </div>
                <p class="text-xs text-slate-400 mt-0.5">
                  {{ a.published_at ? fmtDateTime(a.published_at) : 'Not yet published' }}
                  <template v-if="canManage && a.created_by"> · by {{ a.created_by }}</template>
                </p>
              </div>
              <div v-if="canManage" class="flex shrink-0 items-center gap-1">
                <AppButton v-if="a.status === 'draft'" size="sm" @click="confirmPublish(a)">
                  <MegaphoneIcon class="h-3.5 w-3.5" /> Publish
                </AppButton>
                <button type="button" @click="openForm(a)"
                  class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors">
                  <PencilSquareIcon class="h-4 w-4" />
                </button>
                <button type="button" @click="confirmDelete(a)"
                  class="p-1.5 rounded-lg text-slate-400 hover:text-danger-600 hover:bg-danger-50 transition-colors">
                  <TrashIcon class="h-4 w-4" />
                </button>
              </div>
            </div>

            <img v-if="a.poster_path" :src="storageUrl(a.poster_path)" :alt="a.title"
              class="rounded-lg border border-slate-100 max-h-96 w-auto max-w-full object-contain cursor-zoom-in"
              @click="lightbox = storageUrl(a.poster_path)" />

            <p class="text-sm text-slate-700 whitespace-pre-line"
              :class="{ 'line-clamp-4': !expandedIds.has(a.id) }">{{ a.body }}</p>
            <button v-if="a.body.length > 280" type="button"
              class="text-xs font-medium text-indigo-600 hover:text-indigo-700"
              @click="toggleExpand(a.id)">
              {{ expandedIds.has(a.id) ? 'Show less' : 'Read more' }}
            </button>
          </div>
        </AppCard>
      </div>

      <AppCard v-else>
        <EmptyState title="No announcements"
          :subtitle="canManage ? 'Create your first announcement to reach the campus.' : 'There are no announcements for you right now.'"
          :icon="MegaphoneIcon" />
      </AppCard>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex items-center justify-between">
        <p class="text-xs text-slate-500">
          {{ filtered.length }} announcement(s) — page {{ currentPage }} of {{ totalPages }}
        </p>
        <div class="flex gap-1">
          <AppButton variant="secondary" size="sm" :disabled="currentPage === 1" @click="currentPage--">Prev</AppButton>
          <AppButton variant="secondary" size="sm" :disabled="currentPage === totalPages" @click="currentPage++">Next</AppButton>
        </div>
      </div>

    </div>

    <!-- Poster lightbox -->
    <div v-if="lightbox" class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-6" @click="lightbox = null">
      <img :src="lightbox" class="max-h-full max-w-full rounded-lg" />
    </div>

    <!-- Create / Edit Modal -->
    <AppModal :show="modal" :title="(form.id ? 'Edit' : 'New') + ' Announcement'" size="2xl"
      body-class="px-6 py-4 space-y-4" @close="modal = false">

      <div v-if="Object.keys(form.errors).length"
        class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-3 py-2 text-xs space-y-0.5">
        <p v-for="(msg, key) in form.errors" :key="key">{{ msg }}</p>
      </div>

      <AppInput v-model="form.title" label="Title" required placeholder="e.g. Campus-wide General Assembly" />

      <AppTextarea v-model="form.body" label="Details" :rows="6" required
        placeholder="Write the announcement details..." />

      <!-- Poster -->
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1.5">Poster / Art Card (optional)</label>
        <div class="flex items-start gap-3">
          <label class="cursor-pointer inline-flex items-center gap-2 px-3 py-2 text-sm rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
            <PhotoIcon class="h-4 w-4" />
            {{ posterPreview ? 'Replace image' : 'Choose image' }}
            <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onPosterChange" />
          </label>
          <div v-if="posterPreview" class="relative">
            <img :src="posterPreview" class="h-24 rounded-lg border border-slate-200 object-contain" />
            <button v-if="form.poster_base64" type="button" @click="clearPoster"
              class="absolute -top-2 -right-2 bg-white border border-slate-200 rounded-full p-0.5 text-slate-400 hover:text-danger-600">
              <XMarkIcon class="h-3.5 w-3.5" />
            </button>
          </div>
        </div>
        <p class="text-xs text-slate-400 mt-1">JPEG, PNG, or WebP — max 5 MB.</p>
      </div>

      <!-- Audience -->
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1.5">Audience *</label>
        <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-sm">
          <button type="button" @click="form.audience = 'all'"
            :class="['px-4 py-2 font-medium transition-colors', form.audience === 'all' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50']">
            All Users
          </button>
          <button type="button" @click="form.audience = 'specific'"
            :class="['px-4 py-2 font-medium border-l border-slate-200 transition-colors', form.audience === 'specific' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50']">
            Specific Users
          </button>
        </div>
      </div>

      <!-- Specific targets -->
      <div v-if="form.audience === 'specific'" class="space-y-2">
        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Recipients</p>
        <div class="relative">
          <MagnifyingGlassIcon class="h-4 w-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input v-model="targetQuery" type="text" placeholder="Search employee by name..."
            class="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
          <div v-if="targetResults.length"
            class="absolute z-20 mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
            <button v-for="e in targetResults" :key="e.id" type="button" @click="addTarget(e)"
              class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 flex justify-between items-baseline gap-2">
              <span class="text-slate-700">{{ e.name }}</span>
              <span v-if="e.position" class="text-xs text-slate-400 shrink-0 truncate">{{ e.position }}</span>
            </button>
          </div>
        </div>
        <div v-if="form.targets.length" class="flex flex-wrap gap-1.5">
          <span v-for="id in form.targets" :key="id"
            class="inline-flex items-center gap-1 bg-indigo-50 border border-indigo-100 text-indigo-700 rounded-full px-2.5 py-1 text-xs font-medium">
            {{ employeeName(id) }}
            <button type="button" @click="form.targets = form.targets.filter(t => t !== id)"
              class="hover:text-danger-600"><XMarkIcon class="h-3 w-3" /></button>
          </span>
        </div>
        <p v-else class="text-xs text-slate-400">No recipients added yet — search above to add.</p>
      </div>

      <template #footer>
        <AppButton variant="ghost" @click="modal = false">Cancel</AppButton>
        <AppButton :loading="form.processing" @click="save">{{ form.id ? 'Update' : 'Save as Draft' }}</AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { storageUrl } from '@/Composables/useStorage'
import Swal from 'sweetalert2'
import {
  CheckCircleIcon, MagnifyingGlassIcon, MegaphoneIcon, PencilSquareIcon,
  PhotoIcon, PlusIcon, TrashIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  announcements: { type: Array, default: () => [] },
  employees:     { type: Array, default: () => [] },
  canManage:     { type: Boolean, default: false },
})

// ── Filters & pagination ─────────────────────────────────────────────────────

const search       = ref('')
const statusFilter = ref('')

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return props.announcements.filter(a => {
    if (statusFilter.value && a.status !== statusFilter.value) return false
    if (!q) return true
    return `${a.title} ${a.body}`.toLowerCase().includes(q)
  })
})

const PER_PAGE = 15
const currentPage = ref(1)
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return filtered.value.slice(start, start + PER_PAGE)
})
watch([search, statusFilter], () => { currentPage.value = 1 })

// ── Formatting / body expand ────────────────────────────────────────────────

function fmtDateTime(d) {
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
}

const expandedIds = ref(new Set())
function toggleExpand(id) {
  const next = new Set(expandedIds.value)
  next.has(id) ? next.delete(id) : next.add(id)
  expandedIds.value = next
}

const lightbox = ref(null)

function employeeName(id) {
  return props.employees.find(e => e.id === id)?.name ?? `User ${id}`
}

// ── Form & modal ─────────────────────────────────────────────────────────────

const modal = ref(false)

const form = useForm({
  id: null,
  title: '',
  body: '',
  audience: 'all',
  targets: [],
  poster_base64: null,
  poster_mime: null,
})

/** Existing poster (edit mode) or freshly picked base64. */
const existingPosterPath = ref(null)
const posterPreview = computed(() => form.poster_base64
  ?? (existingPosterPath.value ? storageUrl(existingPosterPath.value) : null))

function openForm(a = null) {
  form.reset()
  form.clearErrors()
  existingPosterPath.value = null
  if (a && a.id) {
    Object.assign(form, {
      id:       a.id,
      title:    a.title,
      body:     a.body,
      audience: a.audience,
      targets:  [...(a.target_ids ?? [])],
    })
    existingPosterPath.value = a.poster_path
  }
  targetQuery.value = ''
  modal.value = true
}

function onPosterChange(e) {
  const file = e.target.files?.[0]
  e.target.value = ''
  if (!file) return
  if (file.size > 5 * 1024 * 1024) {
    Swal.fire({ icon: 'error', title: 'Image too large', text: 'Please choose an image under 5 MB.' })
    return
  }
  const reader = new FileReader()
  reader.onload = () => {
    form.poster_base64 = reader.result
    form.poster_mime = file.type
  }
  reader.readAsDataURL(file)
}

function clearPoster() {
  form.poster_base64 = null
  form.poster_mime = null
}

function save() {
  const opts = {
    preserveScroll: true,
    onSuccess: () => { modal.value = false },
  }
  if (form.id) {
    form.put(route('announcements.update', form.id), opts)
  } else {
    form.post(route('announcements.store'), opts)
  }
}

function confirmPublish(a) {
  const audienceLabel = a.audience === 'all' ? 'all active users' : `${a.target_count} selected recipient(s)`
  Swal.fire({
    icon: 'question',
    title: 'Publish this announcement?',
    text: `"${a.title}" will notify ${audienceLabel}.`,
    showCancelButton: true,
    confirmButtonText: 'Publish',
  }).then((res) => {
    if (!res.isConfirmed) return
    router.post(route('announcements.publish', a.id), {}, { preserveScroll: true })
  })
}

function confirmDelete(a) {
  Swal.fire({
    icon: 'warning',
    title: 'Delete this announcement?',
    text: `"${a.title}" will be removed.`,
    showCancelButton: true,
    confirmButtonText: 'Delete',
    confirmButtonColor: '#dc2626',
  }).then((res) => {
    if (!res.isConfirmed) return
    router.delete(route('announcements.destroy', a.id), { preserveScroll: true })
  })
}

// ── Recipient picker ─────────────────────────────────────────────────────────

const targetQuery = ref('')
const targetResults = computed(() => {
  const q = targetQuery.value.trim().toLowerCase()
  if (q.length < 2) return []
  return props.employees
    .filter(e => !form.targets.includes(e.id) && e.name.toLowerCase().includes(q))
    .slice(0, 12)
})

function addTarget(e) {
  form.targets.push(e.id)
  targetQuery.value = ''
}
</script>
