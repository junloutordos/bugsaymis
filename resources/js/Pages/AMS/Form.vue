<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  PaperClipIcon,
  XMarkIcon,
  ArrowLeftIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  activity: Object,   // null for create
  employees: Array,
})

const isEditing = computed(() => !!props.activity)

// Form state
const form = ref({
  title:           props.activity?.title ?? '',
  start_date:      props.activity?.start_date ?? '',
  start_time:      props.activity?.start_time ?? '',
  end_date:        props.activity?.end_date ?? '',
  end_time:        props.activity?.end_time ?? '',
  total_hours:     props.activity?.total_hours ?? '',
  venue:           props.activity?.venue ?? '',
  resource_person: props.activity?.resource_person ?? '',
})

// File refs
const bannerFile          = ref(null)
const specialOrderFile    = ref(null)
const activityReportFile  = ref(null)
const officialDocFile     = ref(null)

const errors = ref({})
const submitting = ref(false)

function submit() {
  submitting.value = true
  errors.value = {}

  const fd = new FormData()
  Object.entries(form.value).forEach(([k, v]) => {
    if (v !== null && v !== undefined && v !== '') fd.append(k, v)
  })
  if (bannerFile.value)         fd.append('banner', bannerFile.value)
  if (specialOrderFile.value)   fd.append('special_order', specialOrderFile.value)
  if (activityReportFile.value) fd.append('activity_report', activityReportFile.value)
  if (officialDocFile.value)    fd.append('official_documentation', officialDocFile.value)

  const url = isEditing.value
    ? route('ams.activities.update', props.activity.id)
    : route('ams.activities.store')

  router.post(url, fd, {
    forceFormData: true,
    preserveScroll: true,
    onError: (e) => { errors.value = e; submitting.value = false },
    onFinish: () => { submitting.value = false },
  })
}

function pickFile(event, fieldRef) {
  const file = event.target.files[0]
  if (file) fieldRef.value = file
}
</script>

<template>
  <Head :title="isEditing ? 'Edit Activity' : 'New Activity'" />
  <AdminLayout :title="isEditing ? 'Edit Activity' : 'New Activity'">

    <!-- Back -->
    <div class="mb-4">
      <a
        :href="isEditing ? route('ams.activities.show', activity.id) : route('ams.activities.index')"
        class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700"
      >
        <ArrowLeftIcon class="w-4 h-4" />
        {{ isEditing ? 'Back to Activity' : 'Back to Activities' }}
      </a>
    </div>

    <form @submit.prevent="submit" class="space-y-6 max-w-3xl mx-auto">

      <!-- Basic Info -->
      <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Activity Details</h2>

        <!-- Title -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Title <span class="text-red-500">*</span></label>
          <input
            v-model="form.title"
            type="text"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
            :class="{ 'border-red-400': errors.title }"
          />
          <p v-if="errors.title" class="mt-1 text-xs text-red-500">{{ errors.title }}</p>
        </div>

        <!-- Date/Time row -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Start Date <span class="text-red-500">*</span></label>
            <input
              v-model="form.start_date"
              type="date"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
              :class="{ 'border-red-400': errors.start_date }"
            />
            <p v-if="errors.start_date" class="mt-1 text-xs text-red-500">{{ errors.start_date }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Start Time</label>
            <input
              v-model="form.start_time"
              type="time"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">End Date <span class="text-red-500">*</span></label>
            <input
              v-model="form.end_date"
              type="date"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
              :class="{ 'border-red-400': errors.end_date }"
            />
            <p v-if="errors.end_date" class="mt-1 text-xs text-red-500">{{ errors.end_date }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">End Time</label>
            <input
              v-model="form.end_time"
              type="time"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
            />
          </div>
        </div>

        <!-- Hours / Venue / Resource Person -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Total Hours</label>
            <input
              v-model="form.total_hours"
              type="text"
              placeholder="e.g. 8 hours"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
            />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-slate-700 mb-1">Venue</label>
            <input
              v-model="form.venue"
              type="text"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
            />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Resource Person</label>
          <input
            v-model="form.resource_person"
            type="text"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
          />
        </div>
      </div>

      <!-- File Attachments -->
      <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Attachments</h2>
        <p class="text-xs text-slate-400">JPG, PNG, or PDF — max 10 MB each. Leave blank to keep existing file.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

          <!-- Banner -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Banner / Poster</label>
            <div class="flex items-center gap-2">
              <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">
                <PaperClipIcon class="w-4 h-4" />
                {{ bannerFile ? bannerFile.name : 'Choose file' }}
                <input type="file" class="hidden" accept=".jpg,.jpeg,.png,.pdf" @change="pickFile($event, bannerFile)" @input="bannerFile = $event.target.files[0]" />
              </label>
              <a v-if="activity?.banner && !bannerFile" :href="activity.banner" target="_blank" class="text-xs text-indigo-600 hover:underline">View</a>
            </div>
            <p v-if="errors.banner" class="mt-1 text-xs text-red-500">{{ errors.banner }}</p>
          </div>

          <!-- Special Order -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Special Order</label>
            <div class="flex items-center gap-2">
              <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">
                <PaperClipIcon class="w-4 h-4" />
                {{ specialOrderFile ? specialOrderFile.name : 'Choose file' }}
                <input type="file" class="hidden" accept=".jpg,.jpeg,.png,.pdf" @input="specialOrderFile = $event.target.files[0]" />
              </label>
              <a v-if="activity?.special_order && !specialOrderFile" :href="activity.special_order" target="_blank" class="text-xs text-indigo-600 hover:underline">View</a>
            </div>
            <p v-if="errors.special_order" class="mt-1 text-xs text-red-500">{{ errors.special_order }}</p>
          </div>

          <!-- Activity Report -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Activity Report</label>
            <div class="flex items-center gap-2">
              <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">
                <PaperClipIcon class="w-4 h-4" />
                {{ activityReportFile ? activityReportFile.name : 'Choose file' }}
                <input type="file" class="hidden" accept=".jpg,.jpeg,.png,.pdf" @input="activityReportFile = $event.target.files[0]" />
              </label>
              <a v-if="activity?.activity_report && !activityReportFile" :href="activity.activity_report" target="_blank" class="text-xs text-indigo-600 hover:underline">View</a>
            </div>
            <p v-if="errors.activity_report" class="mt-1 text-xs text-red-500">{{ errors.activity_report }}</p>
          </div>

          <!-- Official Documentation -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Official Documentation</label>
            <div class="flex items-center gap-2">
              <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">
                <PaperClipIcon class="w-4 h-4" />
                {{ officialDocFile ? officialDocFile.name : 'Choose file' }}
                <input type="file" class="hidden" accept=".jpg,.jpeg,.png,.pdf" @input="officialDocFile = $event.target.files[0]" />
              </label>
              <a v-if="activity?.official_documentation && !officialDocFile" :href="activity.official_documentation" target="_blank" class="text-xs text-indigo-600 hover:underline">View</a>
            </div>
            <p v-if="errors.official_documentation" class="mt-1 text-xs text-red-500">{{ errors.official_documentation }}</p>
          </div>

        </div>
      </div>

      <!-- Submit -->
      <div class="flex items-center gap-3">
        <button
          type="submit"
          :disabled="submitting"
          class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-medium"
        >
          {{ submitting ? 'Saving…' : (isEditing ? 'Save Changes' : 'Create Activity') }}
        </button>
        <a
          :href="isEditing ? route('ams.activities.show', activity.id) : route('ams.activities.index')"
          class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50"
        >Cancel</a>
      </div>

    </form>

  </AdminLayout>
</template>
