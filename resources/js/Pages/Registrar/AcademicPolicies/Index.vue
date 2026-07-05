<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import {
  PlusIcon,
  PencilSquareIcon,
  TrashIcon,
  CheckCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  policies: Array,
  schoolYears: Array,
})

// ── Create / edit modal ───────────────────────────────────────────────────────
const showModal     = ref(false)
const editingPolicy = ref(null)

const form = useForm({
  name:                            '',
  school_year_id:                  null,
  grade_band:                      'all',
  failed_subjects_for_retention:   3,
  failed_subjects_for_exclusion:   null,
  min_gwa_for_promotion:           null,
  honors_highest_cutoff:           98.00,
  honors_high_cutoff:              95.00,
  honors_regular_cutoff:           90.00,
  is_active:                       true,
})

function openCreate() {
  editingPolicy.value = null
  form.reset()
  showModal.value     = true
}

function openEdit(policy) {
  editingPolicy.value = policy
  Object.assign(form, {
    name:                            policy.name,
    school_year_id:                  policy.school_year_id,
    grade_band:                      policy.grade_band,
    failed_subjects_for_retention:   policy.failed_subjects_for_retention,
    failed_subjects_for_exclusion:   policy.failed_subjects_for_exclusion,
    min_gwa_for_promotion:           policy.min_gwa_for_promotion,
    honors_highest_cutoff:           policy.honors_highest_cutoff,
    honors_high_cutoff:              policy.honors_high_cutoff,
    honors_regular_cutoff:           policy.honors_regular_cutoff,
    is_active:                       policy.is_active,
  })
  showModal.value = true
}

function submitForm() {
  if (editingPolicy.value) {
    form.put(route('registrar.academic-policies.update', editingPolicy.value.id), {
      onSuccess: () => { showModal.value = false },
    })
  } else {
    form.post(route('registrar.academic-policies.store'), {
      onSuccess: () => { showModal.value = false },
    })
  }
}

const deleteForm = useForm({})

async function deletePolicy(policy) {
  if (! await confirmDelete(`Delete "${policy.name}"?`)) return
  deleteForm.delete(route('registrar.academic-policies.destroy', policy.id))
}

function gradeBandBadge(band) {
  const map = {
    jhs: 'blue',
    shs: 'purple',
    all: 'slate',
  }
  return map[band] ?? 'slate'
}
</script>

<template>
  <Head title="Academic Policies" />
  <AdminLayout title="Academic Policies">
    <div class="space-y-5">

      <AppPageHeader title="Academic Policies" subtitle="Configure retention, exclusion, and honors rules per school year.">
        <template #actions>
          <AppButton @click="openCreate">
            <PlusIcon class="h-4 w-4" />
            New Policy
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.error }}
      </div>

      <!-- Back link -->
      <div>
        <AppButton as="link" :href="route('registrar.enrollment.index')" variant="ghost" size="sm">
          ← Back to Enrollment
        </AppButton>
      </div>

      <!-- Policy cards -->
      <EmptyState
        v-if="policies.length === 0"
        title="No academic policies defined yet"
        subtitle="Create a global default policy to get started."
      />

      <div v-else class="space-y-4">
        <AppCard
          v-for="p in policies"
          :key="p.id"
          :class="!p.is_active ? 'opacity-60' : ''"
        >
          <template #header>
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="font-semibold text-slate-800">{{ p.name }}</h3>
              <AppBadge :color="gradeBandBadge(p.grade_band)">{{ p.grade_band_label }}</AppBadge>
              <AppBadge color="indigo">{{ p.school_year_name }}</AppBadge>
              <AppBadge v-if="!p.is_active" color="slate">Inactive</AppBadge>
            </div>
            <div class="flex items-center gap-1 shrink-0">
              <AppIconButton label="Edit policy" @click="openEdit(p)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Delete policy" variant="danger" @click="deletePolicy(p)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </template>

          <!-- Rules grid -->
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-slate-50 rounded-lg p-3">
              <p class="text-xs text-slate-500 mb-0.5">Retention Trigger</p>
              <p class="font-semibold text-slate-800">≥ {{ p.failed_subjects_for_retention }} failed</p>
            </div>
            <div class="bg-slate-50 rounded-lg p-3">
              <p class="text-xs text-slate-500 mb-0.5">Exclusion Trigger</p>
              <p class="font-semibold text-slate-800">
                {{ p.failed_subjects_for_exclusion != null ? `≥ ${p.failed_subjects_for_exclusion} failed` : 'None' }}
              </p>
            </div>
            <div class="bg-slate-50 rounded-lg p-3">
              <p class="text-xs text-slate-500 mb-0.5">Min GWA to Promote</p>
              <p class="font-semibold text-slate-800">
                {{ p.min_gwa_for_promotion != null ? p.min_gwa_for_promotion : 'None' }}
              </p>
            </div>
            <div class="bg-amber-50 rounded-lg p-3 col-span-2">
              <p class="text-xs text-slate-500 mb-1">Honors Cutoffs (GWA)</p>
              <div class="space-y-0.5 text-xs text-slate-700">
                <p><span class="text-amber-700 font-medium">Highest Honors</span> ≥ {{ p.honors_highest_cutoff }}</p>
                <p><span class="text-amber-600 font-medium">High Honors</span> ≥ {{ p.honors_high_cutoff }}</p>
                <p><span class="text-amber-500 font-medium">Honors</span> ≥ {{ p.honors_regular_cutoff }}</p>
              </div>
            </div>
          </div>
        </AppCard>
      </div>

    </div>

    <!-- ── Create / edit modal ────────────────────────────────────────────────── -->
    <AppModal
      :show="showModal"
      :title="editingPolicy ? 'Edit Academic Policy' : 'New Academic Policy'"
      size="xl"
      @close="showModal = false"
    >
      <div class="space-y-5">

        <!-- Name -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Policy Name</label>
          <input
            v-model="form.name"
            type="text"
            placeholder="e.g. PSHS-CRC JHS Retention Policy 2025-2026"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
          <p v-if="form.errors.name" class="text-xs text-red-600 mt-1">{{ form.errors.name }}</p>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <!-- School year -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">School Year <span class="text-slate-400">(blank = global)</span></label>
            <select
              v-model="form.school_year_id"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option :value="null">Global Default</option>
              <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">{{ sy.name }}</option>
            </select>
          </div>

          <!-- Grade band -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Applies To</label>
            <select
              v-model="form.grade_band"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option value="all">All Grade Levels</option>
              <option value="jhs">Junior High School (Gr. 7–10)</option>
              <option value="shs">Senior High School (Gr. 11–12)</option>
            </select>
          </div>
        </div>

        <!-- Retention / exclusion thresholds -->
        <div class="bg-slate-50 rounded-lg p-4 space-y-3">
          <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Retention & Exclusion Rules</p>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Failed Subjects → Retention</label>
              <input
                v-model.number="form.failed_subjects_for_retention"
                type="number"
                min="1" max="20"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
              <p v-if="form.errors.failed_subjects_for_retention" class="text-xs text-red-600 mt-1">{{ form.errors.failed_subjects_for_retention }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Failed Subjects → Exclusion <span class="text-slate-400">(optional)</span></label>
              <input
                v-model.number="form.failed_subjects_for_exclusion"
                type="number"
                min="1" max="20"
                placeholder="Leave blank to disable"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Minimum GWA for Promotion <span class="text-slate-400">(optional)</span></label>
            <input
              v-model.number="form.min_gwa_for_promotion"
              type="number"
              min="60" max="100" step="0.01"
              placeholder="e.g. 75.00 — leave blank to disable"
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
        </div>

        <!-- Honors cutoffs -->
        <div class="bg-amber-50 rounded-lg p-4 space-y-3">
          <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Honors Cutoffs (GWA)</p>

          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Highest Honors</label>
              <input
                v-model.number="form.honors_highest_cutoff"
                type="number" min="90" max="100" step="0.01"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">High Honors</label>
              <input
                v-model.number="form.honors_high_cutoff"
                type="number" min="90" max="100" step="0.01"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">With Honors</label>
              <input
                v-model.number="form.honors_regular_cutoff"
                type="number" min="80" max="100" step="0.01"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </div>
        </div>

        <!-- Active toggle -->
        <div class="flex items-center gap-2">
          <input
            id="is_active"
            v-model="form.is_active"
            type="checkbox"
            class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500"
          />
          <label for="is_active" class="text-sm text-slate-700">Active (applies to promotion processing)</label>
        </div>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="showModal = false">Cancel</AppButton>
        <AppButton :loading="form.processing" @click="submitForm">
          {{ form.processing ? 'Saving…' : 'Save Policy' }}
        </AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>
