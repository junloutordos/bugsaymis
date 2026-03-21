<template>
  <AdminLayout title="Salary Grade Table">
    <div class="max-w-full px-4 py-6 space-y-6">

      <!-- Header -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Salary Grade Table</h1>
          <p class="text-sm text-gray-500 mt-0.5">SSL V — Philippine Government Salary Standardization Law</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <!-- Tranche selector -->
          <select
            v-model="selectedTranche"
            @change="changeTranche"
            class="border border-gray-300 rounded-md text-sm px-3 py-2 focus:ring-blue-500 focus:border-blue-500 min-w-[220px]"
          >
            <option v-for="t in tranches" :key="t.tranche" :value="t.tranche">
              {{ t.tranche }}
              <template v-if="t.is_current"> ✓ (Active)</template>
            </option>
          </select>

          <!-- Set active -->
          <button
            v-if="!isCurrentTranche"
            @click="setActive"
            class="text-sm bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-md font-medium"
          >
            Set as Active
          </button>
          <span v-else class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full font-medium">
            Active Schedule
          </span>

          <!-- New tranche -->
          <button
            @click="openNewTranche"
            class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-md font-medium"
          >
            + New Tranche
          </button>
        </div>
      </div>

      <!-- Flash messages -->
      <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 rounded-md px-4 py-3 text-sm">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.errors?.error" class="bg-red-50 border border-red-200 text-red-800 rounded-md px-4 py-3 text-sm">
        {{ $page.props.errors.error }}
      </div>

      <!-- Table -->
      <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 sticky top-0">
            <tr>
              <th class="px-3 py-3 text-left font-semibold text-gray-700 border-b border-r border-gray-200 bg-gray-100 w-16">
                SG
              </th>
              <th
                v-for="step in steps"
                :key="step"
                class="px-3 py-3 text-center font-semibold text-gray-700 border-b border-r border-gray-200 min-w-[130px]"
              >
                Step {{ step }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="grade in grades"
              :key="grade"
              :class="grade % 2 === 0 ? 'bg-white' : 'bg-gray-50'"
              class="hover:bg-blue-50 transition-colors"
            >
              <td class="px-3 py-2 font-bold text-center text-gray-800 border-r border-gray-200 bg-blue-50">
                {{ grade }}
              </td>
              <td
                v-for="step in steps"
                :key="step"
                class="px-2 py-1.5 text-right border-r border-gray-100"
              >
                <template v-if="editing?.grade === grade && editing?.step === step">
                  <div class="flex items-center gap-1">
                    <input
                      v-model="editValue"
                      type="number"
                      step="0.01"
                      min="0"
                      class="w-28 border border-blue-400 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
                      @keydown.enter="saveEdit"
                      @keydown.esc="cancelEdit"
                      ref="editInput"
                    />
                    <button @click="saveEdit" class="text-green-600 hover:text-green-800 font-bold text-xs">✓</button>
                    <button @click="cancelEdit" class="text-red-500 hover:text-red-700 text-xs">✕</button>
                  </div>
                </template>
                <template v-else>
                  <button
                    @click="startEdit(grade, step)"
                    class="w-full text-right px-2 py-0.5 rounded hover:bg-blue-100 font-mono text-gray-700 text-xs transition-colors"
                    :title="`SG ${grade} Step ${step} — click to edit`"
                  >
                    {{ formatRate(getRate(grade, step)) }}
                  </button>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p class="text-xs text-gray-400 text-right">
        Effective date: {{ effectiveDate }} &nbsp;·&nbsp;
        Rates in Philippine Peso (₱) &nbsp;·&nbsp;
        Click any cell to edit
      </p>
    </div>

    <!-- New Tranche Modal -->
    <Teleport to="body">
      <div v-if="showNewTranche" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
          <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">Create New Tranche</h2>
            <p class="text-sm text-gray-500 mt-1">Copy an existing tranche with an optional salary increment.</p>
          </div>
          <form @submit.prevent="submitNewTranche" class="p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Tranche Name <span class="text-red-500">*</span></label>
              <input
                v-model="newTranche.tranche"
                type="text"
                placeholder="e.g. SSL V T5 2025"
                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                required
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Effective Date <span class="text-red-500">*</span></label>
              <input
                v-model="newTranche.effective_date"
                type="date"
                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                required
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Base Tranche <span class="text-red-500">*</span></label>
              <select
                v-model="newTranche.base_tranche"
                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                required
              >
                <option value="">— Select base —</option>
                <option v-for="t in tranches" :key="t.tranche" :value="t.tranche">{{ t.tranche }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Increment % <span class="text-gray-400">(optional)</span></label>
              <input
                v-model="newTranche.increment_pct"
                type="number"
                step="0.01"
                min="0"
                max="100"
                placeholder="0 = copy as-is"
                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
              />
              <p class="text-xs text-gray-400 mt-1">All rates in the new tranche will be multiplied by (1 + increment%).</p>
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="showNewTranche = false" class="px-4 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50">
                Cancel
              </button>
              <button type="submit" :disabled="newTrancheSaving" class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-md font-medium disabled:opacity-50">
                {{ newTrancheSaving ? 'Creating...' : 'Create Tranche' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  rows: Array,
  tranches: Array,
  selectedTranche: String,
  grades: Array,
  steps: Array,
})

// Build a quick lookup: grade => step => { id, monthly_rate }
const rateMap = computed(() => {
  const m = {}
  for (const r of props.rows) {
    if (!m[r.grade]) m[r.grade] = {}
    m[r.grade][r.step] = { id: r.id, rate: r.monthly_rate }
  }
  return m
})

const getRate = (grade, step) => rateMap.value[grade]?.[step]?.rate ?? null
const getId  = (grade, step) => rateMap.value[grade]?.[step]?.id  ?? null

const formatRate = (val) =>
  val == null ? '—' : Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const isCurrentTranche = computed(() =>
  props.tranches.find(t => t.tranche === props.selectedTranche)?.is_current ?? false
)

const effectiveDate = computed(() =>
  props.tranches.find(t => t.tranche === props.selectedTranche)?.effective_date ?? ''
)

// ── Tranche navigation ──────────────────────────────────────────────────────

const selectedTranche = ref(props.selectedTranche)

function changeTranche() {
  router.get(route('salary-grades.index'), { tranche: selectedTranche.value }, { preserveState: true })
}

function setActive() {
  router.post(route('salary-grades.tranche.activate'), { tranche: selectedTranche.value })
}

// ── Inline editing ──────────────────────────────────────────────────────────

const editing   = ref(null)
const editValue = ref('')
const editInput = ref(null)

function startEdit(grade, step) {
  editing.value   = { grade, step }
  editValue.value = getRate(grade, step) ?? ''
  nextTick(() => editInput.value?.[0]?.focus())
}

function cancelEdit() {
  editing.value = null
}

function saveEdit() {
  const id = getId(editing.value.grade, editing.value.step)
  if (!id) { cancelEdit(); return }
  router.put(
    route('salary-grades.update', id),
    { monthly_rate: editValue.value },
    { preserveScroll: true, onSuccess: () => { editing.value = null } }
  )
}

// ── New tranche modal ───────────────────────────────────────────────────────

const showNewTranche  = ref(false)
const newTrancheSaving = ref(false)
const newTranche = ref({
  tranche: '',
  effective_date: '',
  base_tranche: '',
  increment_pct: '',
})

function openNewTranche() {
  newTranche.value = { tranche: '', effective_date: '', base_tranche: props.selectedTranche, increment_pct: '' }
  showNewTranche.value = true
}

function submitNewTranche() {
  newTrancheSaving.value = true
  router.post(route('salary-grades.tranche.store'), newTranche.value, {
    onSuccess: () => { showNewTranche.value = false; newTrancheSaving.value = false },
    onError:   () => { newTrancheSaving.value = false },
  })
}
</script>
