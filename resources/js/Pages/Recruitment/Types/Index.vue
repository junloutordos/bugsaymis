<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'

const props = defineProps({
  types: { type: Array, required: true },
})

const page = usePage()

// ── Selected type for editing ──────────────────────────────────────────────────
const selectedType = ref(null)
const activePanel  = ref(null) // 'flags' | 'criteria' | 'onboarding'

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
  const res = await Swal.fire({
    title: `Remove "${criterion.name}"?`,
    icon: 'warning', showCancelButton: true,
    confirmButtonColor: '#ef4444', confirmButtonText: 'Remove', reverseButtons: true,
  })
  if (!res.isConfirmed) return

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
  const res = await Swal.fire({
    title: `Remove "${req.requirement_name}"?`,
    icon: 'warning', showCancelButton: true,
    confirmButtonColor: '#ef4444', confirmButtonText: 'Remove', reverseButtons: true,
  })
  if (!res.isConfirmed) return

  router.delete(route('recruitment.types.onboarding.destroy', [selectedType.value.id, req.id]), {
    onSuccess: () => Swal.fire({ icon: 'success', title: 'Removed.', timer: 1200, showConfirmButton: false }),
    onError: (e) => Swal.fire('Error', Object.values(e)[0], 'error'),
  })
}

const weightColor = computed(() => {
  const w = totalWeight.value
  if (w > 100.01) return 'text-red-600'
  if (w === 100)  return 'text-green-600'
  return 'text-yellow-600'
})
</script>

<template>
  <Head title="Recruitment Type Configuration" />
  <AdminLayout title="Recruitment Types">
    <div>
      <!-- Flash -->
      <div v-if="page.props.flash?.success" class="mb-4 px-4 py-3 rounded bg-green-50 border border-green-100 text-green-700 text-sm">
        {{ page.props.flash.success }}
      </div>

      <h1 class="text-xl md:text-2xl font-bold text-gray-800 mb-4">Recruitment Type Configuration</h1>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <!-- ── Left: Type List ────────────────────────────────────────────── -->
        <div class="lg:col-span-1 space-y-2">
          <div v-for="type in types" :key="type.id"
               @click="selectType(type)"
               class="bg-white rounded-xl shadow-sm p-4 cursor-pointer transition border-2"
               :class="selectedType?.id === type.id ? 'border-blue-500 bg-blue-50' : 'border-transparent hover:border-gray-200'">
            <div class="flex items-start justify-between">
              <div>
                <h3 class="font-semibold text-gray-800 text-sm">{{ type.name }}</h3>
                <p v-if="type.description" class="text-xs text-gray-400 mt-0.5 line-clamp-2">{{ type.description }}</p>
              </div>
              <span class="flex-shrink-0 ml-2 px-2 py-0.5 rounded-full text-xs"
                    :class="type.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400'">
                {{ type.is_active ? 'Active' : 'Inactive' }}
              </span>
            </div>
            <!-- Capability badges -->
            <div class="mt-2 flex flex-wrap gap-1">
              <span v-if="type.has_ranking"              class="px-1.5 py-0.5 bg-blue-50 text-blue-600 text-xs rounded">Ranking</span>
              <span v-if="type.has_exam"                 class="px-1.5 py-0.5 bg-orange-50 text-orange-600 text-xs rounded">Exam</span>
              <span v-if="type.has_interview"            class="px-1.5 py-0.5 bg-purple-50 text-purple-600 text-xs rounded">Interview</span>
              <span v-if="type.requires_csc_eligibility" class="px-1.5 py-0.5 bg-yellow-50 text-yellow-600 text-xs rounded">CSC Elig.</span>
              <span v-if="type.requires_prc_license"     class="px-1.5 py-0.5 bg-teal-50 text-teal-600 text-xs rounded">PRC</span>
            </div>
            <!-- Quick nav -->
            <div v-if="selectedType?.id === type.id" class="mt-3 flex gap-1 flex-wrap">
              <button @click.stop="selectType(type, 'flags')"
                      class="px-2 py-1 text-xs rounded"
                      :class="activePanel === 'flags' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                Settings
              </button>
              <button @click.stop="selectType(type, 'criteria')"
                      class="px-2 py-1 text-xs rounded"
                      :class="activePanel === 'criteria' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                Criteria ({{ type.evaluation_criteria?.length ?? 0 }})
              </button>
              <button @click.stop="selectType(type, 'onboarding')"
                      class="px-2 py-1 text-xs rounded"
                      :class="activePanel === 'onboarding' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                Onboarding ({{ type.onboarding_requirements?.length ?? 0 }})
              </button>
            </div>
          </div>
        </div>

        <!-- ── Right: Detail Panel ────────────────────────────────────────── -->
        <div class="lg:col-span-2">
          <div v-if="!selectedType" class="bg-white rounded-xl shadow p-10 text-center text-gray-400">
            Select a recruitment type on the left to configure it.
          </div>

          <!-- Settings Panel -->
          <div v-else-if="activePanel === 'flags'" class="bg-white rounded-xl shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">{{ selectedType.name }} — Settings</h2>

            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea v-model="flagForm.description" rows="2"
                          class="w-full rounded-lg border-gray-300 shadow-sm text-sm"></textarea>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label v-for="flag in [
                  { key: 'has_ranking',              label: 'Has Ranking',              desc: 'Enable applicant ranking' },
                  { key: 'has_exam',                 label: 'Has Written Exam',         desc: 'Include exam stage in workflow' },
                  { key: 'has_interview',            label: 'Has Interview',            desc: 'Include interview stage' },
                  { key: 'requires_csc_eligibility', label: 'Requires CSC Eligibility', desc: 'Mandate CSC eligibility cert' },
                  { key: 'requires_prc_license',     label: 'Requires PRC License',     desc: 'Mandate PRC license' },
                  { key: 'is_active',                label: 'Active',                   desc: 'Show in job item creation' },
                ]" :key="flag.key"
                      class="flex items-start gap-3 p-3 rounded-lg border border-gray-100 cursor-pointer hover:bg-gray-50">
                  <input type="checkbox" v-model="flagForm[flag.key]"
                         class="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600" />
                  <div>
                    <div class="text-sm font-medium text-gray-800">{{ flag.label }}</div>
                    <div class="text-xs text-gray-400">{{ flag.desc }}</div>
                  </div>
                </label>
              </div>

              <div class="flex justify-end pt-2">
                <button @click="saveFlags" :disabled="flagSaving"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-lg text-sm font-medium">
                  {{ flagSaving ? 'Saving…' : 'Save Settings' }}
                </button>
              </div>
            </div>
          </div>

          <!-- Criteria Panel -->
          <div v-else-if="activePanel === 'criteria'" class="bg-white rounded-xl shadow p-6 space-y-4">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-bold text-gray-800">{{ selectedType.name }} — Evaluation Criteria</h2>
              <span class="text-sm font-bold" :class="weightColor">
                Total: {{ totalWeight.toFixed(2) }}%
                <span v-if="totalWeight > 100.01" class="text-xs"> (exceeds 100!)</span>
                <span v-else-if="totalWeight < 99.99 && criteria.length" class="text-xs text-yellow-500"> (not 100%)</span>
              </span>
            </div>

            <!-- Existing criteria -->
            <div v-if="criteria.length" class="space-y-2">
              <div v-for="c in criteria" :key="c.id"
                   class="flex items-start justify-between p-3 rounded-lg border border-gray-100">
                <div>
                  <div class="text-sm font-medium text-gray-800">{{ c.name }}</div>
                  <div class="text-xs text-gray-400">Weight: {{ c.weight_percentage }}% · {{ c.scoring_guide ?? 'No guide' }}</div>
                </div>
                <button @click="deleteCriterion(c)"
                        class="text-xs text-red-400 hover:text-red-600 px-2 py-1 rounded hover:bg-red-50">
                  Remove
                </button>
              </div>
            </div>
            <div v-else class="text-gray-400 text-sm text-center py-4">No criteria yet. Add one below.</div>

            <!-- Add criterion form -->
            <div class="border-t pt-4">
              <h3 class="text-sm font-semibold text-gray-700 mb-3">Add Criterion</h3>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs text-gray-500 mb-1">Name *</label>
                  <input v-model="criteriaForm.name" type="text" placeholder="e.g. Education"
                         class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
                  <p v-if="criteriaErrors.name" class="text-red-500 text-xs mt-1">{{ criteriaErrors.name }}</p>
                </div>
                <div>
                  <label class="block text-xs text-gray-500 mb-1">Weight % *</label>
                  <input v-model="criteriaForm.weight_percentage" type="number" min="0.01" max="100" step="0.01"
                         :placeholder="`Remaining: ${(100 - totalWeight).toFixed(2)}%`"
                         class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
                  <p v-if="criteriaErrors.weight_percentage" class="text-red-500 text-xs mt-1">{{ criteriaErrors.weight_percentage }}</p>
                </div>
                <div class="sm:col-span-2">
                  <label class="block text-xs text-gray-500 mb-1">Scoring Guide</label>
                  <input v-model="criteriaForm.scoring_guide" type="text"
                         placeholder="Brief rubric or description"
                         class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
                </div>
              </div>
              <div class="flex justify-end mt-3">
                <button @click="addCriterion" :disabled="criteriaSaving"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white rounded-lg text-sm font-medium">
                  {{ criteriaSaving ? 'Adding…' : '+ Add Criterion' }}
                </button>
              </div>
            </div>
          </div>

          <!-- Onboarding Panel -->
          <div v-else-if="activePanel === 'onboarding'" class="bg-white rounded-xl shadow p-6 space-y-4">
            <h2 class="text-lg font-bold text-gray-800">{{ selectedType.name }} — Onboarding Requirements</h2>

            <!-- Existing -->
            <div v-if="requirements.length" class="space-y-2">
              <div v-for="req in requirements" :key="req.id"
                   class="flex items-start justify-between p-3 rounded-lg border border-gray-100">
                <div>
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-gray-800">{{ req.requirement_name }}</span>
                    <span v-if="req.is_required" class="px-1.5 py-0.5 bg-red-50 text-red-600 text-xs rounded">Required</span>
                    <span v-else class="px-1.5 py-0.5 bg-gray-50 text-gray-400 text-xs rounded">Optional</span>
                  </div>
                  <div v-if="req.description" class="text-xs text-gray-400 mt-0.5">{{ req.description }}</div>
                </div>
                <button @click="deleteRequirement(req)"
                        class="text-xs text-red-400 hover:text-red-600 px-2 py-1 rounded hover:bg-red-50">
                  Remove
                </button>
              </div>
            </div>
            <div v-else class="text-gray-400 text-sm text-center py-4">No requirements yet.</div>

            <!-- Add form -->
            <div class="border-t pt-4">
              <h3 class="text-sm font-semibold text-gray-700 mb-3">Add Requirement</h3>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="sm:col-span-2">
                  <label class="block text-xs text-gray-500 mb-1">Requirement Name *</label>
                  <input v-model="reqForm.requirement_name" type="text"
                         placeholder="e.g. NBI Clearance"
                         class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
                  <p v-if="reqErrors.requirement_name" class="text-red-500 text-xs mt-1">{{ reqErrors.requirement_name }}</p>
                </div>
                <div class="sm:col-span-2">
                  <label class="block text-xs text-gray-500 mb-1">Description</label>
                  <input v-model="reqForm.description" type="text"
                         placeholder="Optional additional notes"
                         class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
                </div>
                <div>
                  <label class="block text-xs text-gray-500 mb-1">Sort Order</label>
                  <input v-model="reqForm.sort_order" type="number" min="0"
                         class="w-full rounded-lg border-gray-300 shadow-sm text-sm" />
                </div>
                <div class="flex items-center gap-2 mt-4">
                  <input type="checkbox" v-model="reqForm.is_required" id="is_required"
                         class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                  <label for="is_required" class="text-sm text-gray-700">Required document</label>
                </div>
              </div>
              <div class="flex justify-end mt-3">
                <button @click="addRequirement" :disabled="reqSaving"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white rounded-lg text-sm font-medium">
                  {{ reqSaving ? 'Adding…' : '+ Add Requirement' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
