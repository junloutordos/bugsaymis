<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import Swal from 'sweetalert2'

const props = defineProps({
  types: { type: Array, required: true },
})

// ── Selected type for editing ──────────────────────────────────────────────────
const selectedType = ref(null)
const activePanel  = ref(null) // 'flags' | 'criteria' | 'onboarding'

const flagFields = [
  { key: 'has_ranking',              label: 'Has Ranking',              desc: 'Enable applicant ranking' },
  { key: 'has_exam',                 label: 'Has Written Exam',         desc: 'Include exam stage in workflow' },
  { key: 'has_interview',            label: 'Has Interview',            desc: 'Include interview stage' },
  { key: 'requires_csc_eligibility', label: 'Requires CSC Eligibility', desc: 'Mandate CSC eligibility cert' },
  { key: 'requires_prc_license',     label: 'Requires PRC License',     desc: 'Mandate PRC license' },
  { key: 'is_active',                label: 'Active',                   desc: 'Show in job item creation' },
]

const selectType = (type, panel = 'flags') => {
  selectedType.value = type
  activePanel.value  = panel
  // Deep copy flags for editing
  flagForm.value = {
    has_ranking:              type.has_ranking,
    has_exam:                 type.has_exam,
    has_interview:            type.has_interview,
    requires_csc_eligibility: type.requires_csc_eligibility,
    requires_prc_license:     type.requires_prc_license,
    is_active:                type.is_active,
    description:              type.description ?? '',
  }
}

// ── Flag editing ───────────────────────────────────────────────────────────────
const flagForm    = ref({})
const flagSaving  = ref(false)

const saveFlags = () => {
  flagSaving.value = true
  router.put(route('recruitment.types.update', selectedType.value.id), flagForm.value, {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Saved!', timer: 1200, showConfirmButton: false }),
    onError: (e) => Swal.fire('Error', Object.values(e)[0], 'error'),
    onFinish: () => { flagSaving.value = false },
  })
}

// ── Evaluation Criteria ────────────────────────────────────────────────────────
const criteria = computed(() => selectedType.value?.evaluation_criteria ?? [])
const totalWeight = computed(() => criteria.value.reduce((s, c) => s + parseFloat(c.weight_percentage), 0))

const criteriaForm    = ref({ name: '', weight_percentage: '', scoring_guide: '' })
const criteriaErrors  = ref({})
const criteriaSaving  = ref(false)

const addCriterion = () => {
  criteriaSaving.value = true
  criteriaErrors.value = {}
  router.post(route('recruitment.types.criteria.store', selectedType.value.id), criteriaForm.value, {
    onSuccess: () => {
      criteriaForm.value = { name: '', weight_percentage: '', scoring_guide: '' }
      Swal.fire({ icon: 'success', title: 'Criterion added!', timer: 1200, showConfirmButton: false })
    },
    onError: (e) => { criteriaErrors.value = e },
    onFinish: () => { criteriaSaving.value = false },
  })
}

const deleteCriterion = async (criterion) => {
  if (!(await confirmDelete(`Remove "${criterion.name}"? This cannot be undone.`))) return

  router.delete(route('recruitment.types.criteria.destroy', [selectedType.value.id, criterion.id]), {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Removed.', timer: 1200, showConfirmButton: false }),
    onError: (e) => Swal.fire('Error', Object.values(e)[0], 'error'),
  })
}

// ── Onboarding Requirements ────────────────────────────────────────────────────
const requirements    = computed(() => selectedType.value?.onboarding_requirements ?? [])

const reqForm   = ref({ requirement_name: '', description: '', is_required: true, sort_order: 0 })
const reqErrors = ref({})
const reqSaving = ref(false)

const addRequirement = () => {
  reqSaving.value = true
  reqErrors.value = {}
  router.post(route('recruitment.types.onboarding.store', selectedType.value.id), reqForm.value, {
    onSuccess: () => {
      reqForm.value = { requirement_name: '', description: '', is_required: true, sort_order: 0 }
      Swal.fire({ icon: 'success', title: 'Requirement added!', timer: 1200, showConfirmButton: false })
    },
    onError: (e) => { reqErrors.value = e },
    onFinish: () => { reqSaving.value = false },
  })
}

const deleteRequirement = async (req) => {
  if (!(await confirmDelete(`Remove "${req.requirement_name}"? This cannot be undone.`))) return

  router.delete(route('recruitment.types.onboarding.destroy', [selectedType.value.id, req.id]), {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Removed.', timer: 1200, showConfirmButton: false }),
    onError: (e) => Swal.fire('Error', Object.values(e)[0], 'error'),
  })
}

const weightColor = computed(() => {
  const w = totalWeight.value
  if (w > 100.01) return 'text-danger-600'
  if (w === 100)  return 'text-success-600'
  return 'text-warning-600'
})

function statusColor(isActive) {
  return isActive ? 'green' : 'slate'
}
</script>

<template>
  <Head title="Recruitment Type Configuration" />
  <AdminLayout title="Recruitment Types">
    <div class="space-y-5">

      <AppPageHeader title="Recruitment Type Configuration" subtitle="Configure recruitment types, criteria, and onboarding requirements" />

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.success }}
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <!-- ── Left: Type List ────────────────────────────────────────────── -->
        <div class="lg:col-span-1 space-y-2">
          <div v-for="type in types" :key="type.id"
               @click="selectType(type)"
               class="bg-white rounded-xl border shadow-sm p-4 cursor-pointer transition-colors"
               :class="selectedType?.id === type.id ? 'border-indigo-400 bg-indigo-50/40' : 'border-slate-100 hover:border-slate-200'">
            <div class="flex items-start justify-between">
              <div>
                <h3 class="font-semibold text-slate-800 text-sm">{{ type.name }}</h3>
                <p v-if="type.description" class="text-xs text-slate-400 mt-0.5 line-clamp-2">{{ type.description }}</p>
              </div>
              <AppBadge :color="statusColor(type.is_active)">{{ type.is_active ? 'Active' : 'Inactive' }}</AppBadge>
            </div>
            <!-- Capability badges -->
            <div class="mt-2 flex flex-wrap gap-1">
              <AppBadge v-if="type.has_ranking" color="blue">Ranking</AppBadge>
              <AppBadge v-if="type.has_exam" color="amber">Exam</AppBadge>
              <AppBadge v-if="type.has_interview" color="purple">Interview</AppBadge>
              <AppBadge v-if="type.requires_csc_eligibility" color="orange">CSC Elig.</AppBadge>
              <AppBadge v-if="type.requires_prc_license" color="indigo">PRC</AppBadge>
            </div>
            <!-- Quick nav -->
            <div v-if="selectedType?.id === type.id" class="mt-3 flex gap-1 flex-wrap">
              <button @click.stop="selectType(type, 'flags')"
                      class="px-2 py-1 text-xs rounded-lg font-medium transition-colors"
                      :class="activePanel === 'flags' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                Settings
              </button>
              <button @click.stop="selectType(type, 'criteria')"
                      class="px-2 py-1 text-xs rounded-lg font-medium transition-colors"
                      :class="activePanel === 'criteria' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                Criteria ({{ type.evaluation_criteria?.length ?? 0 }})
              </button>
              <button @click.stop="selectType(type, 'onboarding')"
                      class="px-2 py-1 text-xs rounded-lg font-medium transition-colors"
                      :class="activePanel === 'onboarding' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                Onboarding ({{ type.onboarding_requirements?.length ?? 0 }})
              </button>
            </div>
          </div>
        </div>

        <!-- ── Right: Detail Panel ────────────────────────────────────────── -->
        <div class="lg:col-span-2">
          <div v-if="!selectedType" class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 p-12 text-center text-slate-400 text-sm">
            Select a recruitment type on the left to configure it.
          </div>

          <!-- Settings Panel -->
          <div v-else-if="activePanel === 'flags'" class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
            <div class="px-5 py-4 border-b border-slate-100">
              <h2 class="text-sm font-semibold text-slate-800">{{ selectedType.name }} — Settings</h2>
            </div>
            <div class="p-5 space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
                <textarea v-model="flagForm.description" rows="2"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"></textarea>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label v-for="flag in flagFields" :key="flag.key"
                      class="flex items-start gap-3 p-3 rounded-lg border border-slate-100 cursor-pointer hover:bg-indigo-50/40 transition-colors">
                  <input type="checkbox" v-model="flagForm[flag.key]"
                         class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                  <div>
                    <div class="text-sm font-medium text-slate-800">{{ flag.label }}</div>
                    <div class="text-xs text-slate-400">{{ flag.desc }}</div>
                  </div>
                </label>
              </div>

              <div class="flex justify-end pt-2 border-t border-slate-100">
                <AppButton :loading="flagSaving" @click="saveFlags">Save Settings</AppButton>
              </div>
            </div>
          </div>

          <!-- Criteria Panel -->
          <div v-else-if="activePanel === 'criteria'" class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
              <h2 class="text-sm font-semibold text-slate-800">{{ selectedType.name }} — Evaluation Criteria</h2>
              <span class="text-sm font-semibold" :class="weightColor">
                Total: {{ totalWeight.toFixed(2) }}%
                <span v-if="totalWeight > 100.01" class="text-xs"> (exceeds 100!)</span>
                <span v-else-if="totalWeight < 99.99 && criteria.length" class="text-xs text-warning-500"> (not 100%)</span>
              </span>
            </div>
            <div class="p-5 space-y-4">
              <!-- Existing criteria -->
              <div v-if="criteria.length" class="space-y-2">
                <div v-for="c in criteria" :key="c.id"
                     class="flex items-start justify-between p-3 rounded-lg border border-slate-100">
                  <div>
                    <div class="text-sm font-medium text-slate-800">{{ c.name }}</div>
                    <div class="text-xs text-slate-400 mt-0.5">Weight: {{ c.weight_percentage }}% · {{ c.scoring_guide ?? 'No guide' }}</div>
                  </div>
                  <AppButton size="sm" variant="danger" @click="deleteCriterion(c)">Remove</AppButton>
                </div>
              </div>
              <EmptyState v-else title="No criteria yet" subtitle="Add one below." />

              <!-- Add criterion form -->
              <div class="border-t border-slate-100 pt-4">
                <h3 class="text-xs font-semibold text-slate-600 mb-3 uppercase tracking-wide">Add Criterion</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Name *</label>
                    <input v-model="criteriaForm.name" type="text" placeholder="e.g. Education"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                    <p v-if="criteriaErrors.name" class="text-danger-500 text-xs mt-1">{{ criteriaErrors.name }}</p>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Weight % *</label>
                    <input v-model="criteriaForm.weight_percentage" type="number" min="0.01" max="100" step="0.01"
                      :placeholder="`Remaining: ${(100 - totalWeight).toFixed(2)}%`"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                    <p v-if="criteriaErrors.weight_percentage" class="text-danger-500 text-xs mt-1">{{ criteriaErrors.weight_percentage }}</p>
                  </div>
                  <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Scoring Guide</label>
                    <input v-model="criteriaForm.scoring_guide" type="text" placeholder="Brief rubric or description"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                  </div>
                </div>
                <div class="flex justify-end mt-3">
                  <AppButton :loading="criteriaSaving" @click="addCriterion">+ Add Criterion</AppButton>
                </div>
              </div>
            </div>
          </div>

          <!-- Onboarding Panel -->
          <div v-else-if="activePanel === 'onboarding'" class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
            <div class="px-5 py-4 border-b border-slate-100">
              <h2 class="text-sm font-semibold text-slate-800">{{ selectedType.name }} — Onboarding Requirements</h2>
            </div>
            <div class="p-5 space-y-4">
              <!-- Existing -->
              <div v-if="requirements.length" class="space-y-2">
                <div v-for="req in requirements" :key="req.id"
                     class="flex items-start justify-between p-3 rounded-lg border border-slate-100">
                  <div>
                    <div class="flex items-center gap-2">
                      <span class="text-sm font-medium text-slate-800">{{ req.requirement_name }}</span>
                      <AppBadge :color="req.is_required ? 'red' : 'slate'">{{ req.is_required ? 'Required' : 'Optional' }}</AppBadge>
                    </div>
                    <div v-if="req.description" class="text-xs text-slate-400 mt-0.5">{{ req.description }}</div>
                  </div>
                  <AppButton size="sm" variant="danger" @click="deleteRequirement(req)">Remove</AppButton>
                </div>
              </div>
              <EmptyState v-else title="No requirements yet" />

              <!-- Add form -->
              <div class="border-t border-slate-100 pt-4">
                <h3 class="text-xs font-semibold text-slate-600 mb-3 uppercase tracking-wide">Add Requirement</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Requirement Name *</label>
                    <input v-model="reqForm.requirement_name" type="text" placeholder="e.g. NBI Clearance"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                    <p v-if="reqErrors.requirement_name" class="text-danger-500 text-xs mt-1">{{ reqErrors.requirement_name }}</p>
                  </div>
                  <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
                    <input v-model="reqForm.description" type="text" placeholder="Optional additional notes"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Sort Order</label>
                    <input v-model="reqForm.sort_order" type="number" min="0"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                  </div>
                  <div class="flex items-center gap-2 mt-4">
                    <input type="checkbox" v-model="reqForm.is_required" id="is_required"
                      class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                    <label for="is_required" class="text-sm text-slate-700">Required document</label>
                  </div>
                </div>
                <div class="flex justify-end mt-3">
                  <AppButton :loading="reqSaving" @click="addRequirement">+ Add Requirement</AppButton>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
