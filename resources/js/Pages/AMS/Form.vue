<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import {
  PaperClipIcon,
  XMarkIcon,
  PlusIcon,
  AcademicCapIcon,
  BuildingOffice2Icon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  activity: Object,   // null for create
  employees: Array,
})

const isEditing = computed(() => !!props.activity)

const breadcrumbItems = computed(() => {
  const items = [{ label: 'Activities', href: route('ams.activities.index') }]
  if (isEditing.value) {
    items.push({ label: props.activity.title, href: route('ams.activities.show', props.activity.id) })
    items.push({ label: 'Edit Activity' })
  } else {
    items.push({ label: 'New Activity' })
  }
  return items
})

// Form state
const form = ref({
  title:           props.activity?.title ?? '',
  activity_type:   props.activity?.activity_type ?? 'in_house',
  start_date:      props.activity?.start_date ?? '',
  start_time:      props.activity?.start_time ?? '',
  end_date:        props.activity?.end_date ?? '',
  end_time:        props.activity?.end_time ?? '',
  total_hours:     props.activity?.total_hours ?? '',
  venue:           props.activity?.venue ?? '',
  resource_person: props.activity?.resource_person ?? '',
  what_to_bring:   props.activity?.what_to_bring ?? '',
})

const isTws = computed(() => form.value.activity_type === 'training_workshop_seminar')

// ── Speakers (Training/Workshop/Seminar only) ───────────────────────────────

const speakers = ref(
  props.activity?.speakers?.length
    ? props.activity.speakers.map(s => ({ id: s.id, name: s.name, designation: s.designation ?? '', topic: s.topic ?? '' }))
    : [{ name: '', designation: '', topic: '' }]
)

function addSpeaker() {
  speakers.value.push({ name: '', designation: '', topic: '' })
}
function removeSpeaker(i) {
  if (speakers.value.length > 1) speakers.value.splice(i, 1)
}

// ── Meals & Snacks (per-day grid across the activity's date range) ─────────

const MAX_MEAL_DAYS = 60

const mealDays = computed(() => {
  if (!form.value.start_date || !form.value.end_date) return []
  const start = new Date(form.value.start_date + 'T00:00:00')
  const end   = new Date(form.value.end_date + 'T00:00:00')
  if (isNaN(start) || isNaN(end) || end < start) return []

  const days = []
  const cursor = new Date(start)
  while (cursor <= end && days.length < MAX_MEAL_DAYS) {
    days.push(cursor.toISOString().slice(0, 10))
    cursor.setDate(cursor.getDate() + 1)
  }
  return days
})

const provideMeals = ref(
  Boolean(props.activity?.meal_plans?.length)
)

const mealPlanState = ref({})
for (const mp of props.activity?.meal_plans ?? []) {
  mealPlanState.value[mp.date] = {
    am_snacks: mp.am_snacks,
    lunch: mp.lunch,
    pm_snacks: mp.pm_snacks,
    dinner: mp.dinner,
  }
}

function getMealDay(date) {
  if (!mealPlanState.value[date]) {
    mealPlanState.value[date] = { am_snacks: false, lunch: false, pm_snacks: false, dinner: false }
  }
  return mealPlanState.value[date]
}

function formatDayLabel(dateStr) {
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-PH', {
    weekday: 'short', month: 'short', day: 'numeric',
  })
}

// ── File uploads (base64 JSON — Cloudflare WAF blocks multipart/form-data) ─

function makeFileField(existingUrl) {
  return { fileName: null, base64: null, existingUrl: existingUrl ?? null }
}

const bannerFile          = ref(makeFileField(props.activity?.banner))
const specialOrderFile    = ref(makeFileField(props.activity?.special_order))
const activityReportFile  = ref(makeFileField(props.activity?.activity_report))
const officialDocFile     = ref(makeFileField(props.activity?.official_documentation))

function pickFile(event, fieldRef) {
  const file = event.target.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => {
    fieldRef.value.fileName = file.name
    fieldRef.value.base64   = reader.result
  }
  reader.readAsDataURL(file)
}

const errors = ref({})
const submitting = ref(false)

function submit() {
  submitting.value = true
  errors.value = {}

  const mealPlans = provideMeals.value
    ? mealDays.value.map(d => ({ date: d, ...getMealDay(d) }))
    : []

  const payload = {
    ...form.value,
    meal_plans: mealPlans,
    speakers: isTws.value ? speakers.value : [],
    banner: bannerFile.value.base64,
    special_order: specialOrderFile.value.base64,
    activity_report: activityReportFile.value.base64,
    official_documentation: officialDocFile.value.base64,
  }

  const url = isEditing.value
    ? route('ams.activities.update', props.activity.id)
    : route('ams.activities.store')

  router.post(url, payload, {
    preserveScroll: true,
    onError: (e) => { errors.value = e; submitting.value = false },
    onFinish: () => { submitting.value = false },
  })
}
</script>

<template>
  <Head :title="isEditing ? 'Edit Activity' : 'New Activity'" />
  <AdminLayout :title="isEditing ? 'Edit Activity' : 'New Activity'">
    <div class="max-w-3xl mx-auto space-y-6">

      <AppPageHeader :title="isEditing ? 'Edit Activity' : 'New Activity'" :breadcrumb="breadcrumbItems" />

      <form @submit.prevent="submit" class="space-y-6">

        <!-- Activity Type -->
        <AppCard title="Activity Type">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <button
              type="button"
              @click="form.activity_type = 'training_workshop_seminar'"
              class="flex items-start gap-3 rounded-xl border-2 p-4 text-left transition-colors"
              :class="isTws ? 'border-indigo-600 bg-indigo-50' : 'border-slate-200 hover:border-indigo-300'"
            >
              <AcademicCapIcon class="w-6 h-6 mt-0.5 shrink-0" :class="isTws ? 'text-indigo-600' : 'text-slate-400'" />
              <div>
                <p class="text-sm font-semibold text-slate-800">Training / Workshop / Seminar</p>
                <p class="text-xs text-slate-500 mt-0.5">Has one or more speakers; participants also evaluate each speaker.</p>
              </div>
            </button>
            <button
              type="button"
              @click="form.activity_type = 'in_house'"
              class="flex items-start gap-3 rounded-xl border-2 p-4 text-left transition-colors"
              :class="!isTws ? 'border-indigo-600 bg-indigo-50' : 'border-slate-200 hover:border-indigo-300'"
            >
              <BuildingOffice2Icon class="w-6 h-6 mt-0.5 shrink-0" :class="!isTws ? 'text-indigo-600' : 'text-slate-400'" />
              <div>
                <p class="text-sm font-semibold text-slate-800">In-house Activity</p>
                <p class="text-xs text-slate-500 mt-0.5">Campus-initiated activities without a dedicated resource speaker.</p>
              </div>
            </button>
          </div>
          <p v-if="errors.activity_type" class="mt-2 text-xs text-red-500">{{ errors.activity_type }}</p>
        </AppCard>

        <!-- Basic Info -->
        <AppCard title="Activity Details">
          <div class="space-y-4">
            <AppInput v-model="form.title" label="Title" required :error="errors.title" />

            <!-- Date/Time row -->
            <div class="grid grid-cols-2 gap-4">
              <AppInput v-model="form.start_date" type="date" label="Start Date" required :error="errors.start_date" />
              <AppInput v-model="form.start_time" type="time" label="Start Time" />
              <AppInput v-model="form.end_date" type="date" label="End Date" required :error="errors.end_date" />
              <AppInput v-model="form.end_time" type="time" label="End Time" />
            </div>

            <!-- Hours / Venue -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <AppInput v-model="form.total_hours" label="Total Hours" placeholder="e.g. 8 hours" />
              <div class="sm:col-span-2">
                <AppInput v-model="form.venue" label="Venue" />
              </div>
            </div>

            <!-- Resource Person (In-house only — TWS uses the Speakers list below) -->
            <AppInput v-if="!isTws" v-model="form.resource_person" label="Facilitator / Resource Person" />

            <!-- What to Bring -->
            <AppTextarea
              v-model="form.what_to_bring"
              :rows="3"
              label="What to Bring"
              placeholder="e.g. Laptop, valid ID, comfortable clothing…"
            />
          </div>
        </AppCard>

        <!-- Speakers (Training/Workshop/Seminar only) -->
        <AppCard v-if="isTws" title="Speakers">
          <template #header>
            <AppButton type="button" size="sm" @click="addSpeaker">
              <PlusIcon class="w-4 h-4" /> Add Speaker
            </AppButton>
          </template>

          <p class="text-xs text-slate-400 mb-4">Each speaker is evaluated individually by participants alongside the overall training evaluation.</p>

          <div class="space-y-4">
            <div v-for="(sp, i) in speakers" :key="i" class="rounded-lg border border-slate-200 p-4 relative">
              <AppIconButton
                v-if="speakers.length > 1"
                label="Remove speaker"
                variant="danger"
                size="sm"
                class="absolute top-3 right-3"
                @click="removeSpeaker(i)"
              >
                <XMarkIcon class="w-4 h-4" />
              </AppIconButton>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <AppInput v-model="sp.name" label="Speaker Name" required :error="errors[`speakers.${i}.name`]" />
                <AppInput v-model="sp.designation" label="Designation / Office" />
              </div>
              <div class="mt-3">
                <AppInput v-model="sp.topic" label="Topic" />
              </div>
            </div>
          </div>
          <p v-if="errors.speakers" class="mt-3 text-xs text-red-500">{{ errors.speakers }}</p>
        </AppCard>

        <!-- Meals & Snacks -->
        <AppCard>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="provideMeals" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
            <span class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Free Meals & Snacks Provided</span>
          </label>

          <div v-if="provideMeals && mealDays.length" class="overflow-x-auto mt-4">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                  <th class="py-2 pr-4">Day</th>
                  <th class="py-2 px-2 text-center">AM Snacks</th>
                  <th class="py-2 px-2 text-center">Lunch</th>
                  <th class="py-2 px-2 text-center">PM Snacks</th>
                  <th class="py-2 px-2 text-center">Dinner</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="day in mealDays" :key="day">
                  <td class="py-2 pr-4 text-slate-700">{{ formatDayLabel(day) }}</td>
                  <td class="py-2 px-2 text-center">
                    <input type="checkbox" v-model="getMealDay(day).am_snacks" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                  </td>
                  <td class="py-2 px-2 text-center">
                    <input type="checkbox" v-model="getMealDay(day).lunch" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                  </td>
                  <td class="py-2 px-2 text-center">
                    <input type="checkbox" v-model="getMealDay(day).pm_snacks" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                  </td>
                  <td class="py-2 px-2 text-center">
                    <input type="checkbox" v-model="getMealDay(day).dinner" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else-if="provideMeals" class="text-xs text-slate-400 mt-3">Set the Start Date and End Date above to build the meal schedule.</p>
        </AppCard>

        <!-- File Attachments -->
        <AppCard title="Attachments" subtitle="JPG, PNG, or PDF. Leave blank to keep existing file.">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <!-- Banner -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Banner / Poster</label>
              <div class="flex items-center gap-2">
                <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">
                  <PaperClipIcon class="w-4 h-4" />
                  {{ bannerFile.fileName ? bannerFile.fileName : 'Choose file' }}
                  <input type="file" class="hidden" accept=".jpg,.jpeg,.png,.pdf" @change="pickFile($event, bannerFile)" />
                </label>
                <a v-if="bannerFile.existingUrl && !bannerFile.fileName" :href="bannerFile.existingUrl" target="_blank" class="text-xs text-indigo-600 hover:underline">View</a>
              </div>
              <p v-if="errors.banner" class="mt-1 text-xs text-red-500">{{ errors.banner }}</p>
            </div>

            <!-- Special Order -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Special Order</label>
              <div class="flex items-center gap-2">
                <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">
                  <PaperClipIcon class="w-4 h-4" />
                  {{ specialOrderFile.fileName ? specialOrderFile.fileName : 'Choose file' }}
                  <input type="file" class="hidden" accept=".jpg,.jpeg,.png,.pdf" @change="pickFile($event, specialOrderFile)" />
                </label>
                <a v-if="specialOrderFile.existingUrl && !specialOrderFile.fileName" :href="specialOrderFile.existingUrl" target="_blank" class="text-xs text-indigo-600 hover:underline">View</a>
              </div>
              <p v-if="errors.special_order" class="mt-1 text-xs text-red-500">{{ errors.special_order }}</p>
            </div>

            <!-- Activity Report -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Activity Report</label>
              <div class="flex items-center gap-2">
                <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">
                  <PaperClipIcon class="w-4 h-4" />
                  {{ activityReportFile.fileName ? activityReportFile.fileName : 'Choose file' }}
                  <input type="file" class="hidden" accept=".jpg,.jpeg,.png,.pdf" @change="pickFile($event, activityReportFile)" />
                </label>
                <a v-if="activityReportFile.existingUrl && !activityReportFile.fileName" :href="activityReportFile.existingUrl" target="_blank" class="text-xs text-indigo-600 hover:underline">View</a>
              </div>
              <p v-if="errors.activity_report" class="mt-1 text-xs text-red-500">{{ errors.activity_report }}</p>
            </div>

            <!-- Official Documentation -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Official Documentation</label>
              <div class="flex items-center gap-2">
                <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">
                  <PaperClipIcon class="w-4 h-4" />
                  {{ officialDocFile.fileName ? officialDocFile.fileName : 'Choose file' }}
                  <input type="file" class="hidden" accept=".jpg,.jpeg,.png,.pdf" @change="pickFile($event, officialDocFile)" />
                </label>
                <a v-if="officialDocFile.existingUrl && !officialDocFile.fileName" :href="officialDocFile.existingUrl" target="_blank" class="text-xs text-indigo-600 hover:underline">View</a>
              </div>
              <p v-if="errors.official_documentation" class="mt-1 text-xs text-red-500">{{ errors.official_documentation }}</p>
            </div>

          </div>
        </AppCard>

        <!-- Submit -->
        <div class="flex items-center gap-3">
          <AppButton type="submit" :loading="submitting" :disabled="submitting">
            {{ submitting ? 'Saving…' : (isEditing ? 'Save Changes' : 'Create Activity') }}
          </AppButton>
          <AppButton
            as="link"
            variant="secondary"
            :href="isEditing ? route('ams.activities.show', activity.id) : route('ams.activities.index')"
          >Cancel</AppButton>
        </div>

      </form>

    </div>
  </AdminLayout>
</template>
