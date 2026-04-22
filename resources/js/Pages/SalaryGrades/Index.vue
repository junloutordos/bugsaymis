<template>
  <AdminLayout title="Salary Grade Table">
    <div class="max-w-full space-y-6">

      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Salary Grade Table</h1>
          <p class="text-sm text-slate-500 mt-0.5">SSL V — Philippine Government Salary Standardization Law</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <!-- Tranche selector -->
          <select
            v-model="selectedTranche"
            @change="changeTranche"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 min-w-[220px]"
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
            class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
          >
            Set as Active
          </button>
          <span v-else class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700">
            Active Schedule
          </span>

          <!-- New tranche -->
          <button
            @click="openNewTranche"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
          >
            + New Tranche
          </button>
        </div>
      </div>

      <!-- Flash messages -->
      <div v-if="$page.props.flash?.success" class="px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-700 text-sm">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.errors?.error" class="px-4 py-3 rounded-lg bg-red-50 border border-red-100 text-red-700 text-sm">
        {{ $page.props.errors.error }}
      </div>

      <!-- Table Card -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap border-r border-slate-100 w-16 sticky left-0 bg-slate-50">
                SG
              </th>
              <th
                v-for="step in steps"
                :key="step"
                class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap border-r border-slate-100 min-w-[130px]"
              >
                Step {{ step }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="grade in grades"
              :key="grade"
              class="hover:bg-slate-50/60 transition-colors"
            >
              <td class="px-4 py-2 font-bold text-center text-slate-700 border-r border-slate-100 bg-slate-50/80 sticky left-0">
                {{ grade }}
              </td>
              <td
                v-for="step in steps"
                :key="step"
                class="px-2 py-1.5 text-right border-r border-slate-100"
              >
                <template v-if="editing?.grade === grade && editing?.step === step">
                  <div class="flex items-center gap-1">
                    <input
                      v-model="editValue"
                      type="number"
                      step="0.01"
                      min="0"
                      class="w-28 rounded-lg border border-indigo-400 px-2 py-1 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                      @keydown.enter="saveEdit"
                      @keydown.esc="cancelEdit"
                      ref="editInput"
                    />
                    <button @click="saveEdit" class="text-emerald-600 hover:text-emerald-800 font-bold text-xs p-1">✓</button>
                    <button @click="cancelEdit" class="text-red-400 hover:text-red-600 text-xs p-1">✕</button>
                  </div>
                </template>
                <template v-else>
                  <button
                    @click="startEdit(grade, step)"
                    class="w-full text-right px-2 py-0.5 rounded-lg hover:bg-indigo-50 font-mono text-slate-700 text-xs transition-colors"
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

      <p class="text-xs text-slate-400 text-right">
        Effective date: {{ effectiveDate }} &nbsp;·&nbsp;
        Rates in Philippine Peso (₱) &nbsp;·&nbsp;
        Click any cell to edit
      </p>
    </div>

    <!-- New Tranche Modal -->
    <Teleport to="body">
      <div v-if="showNewTranche" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h2 class="text-base font-semibold text-slate-800">Create New Tranche</h2>
              <p class="text-xs text-slate-500 mt-0.5">Copy an existing tranche with an optional salary increment.</p>
            </div>
            <button @click="showNewTranche = false"
              class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <form @submit.prevent="submitNewTranche" class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Tranche Name <span class="text-red-500">*</span></label>
              <input v-model="newTranche.tranche" type="text" placeholder="e.g. SSL V T5 2025"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
                required />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Effective Date <span class="text-red-500">*</span></label>
              <input v-model="newTranche.effective_date" type="date"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
                required />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Base Tranche <span class="text-red-500">*</span></label>
              <select v-model="newTranche.base_tranche"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
                required>
                <option value="">— Select base —</option>
                <option v-for="t in tranches" :key="t.tranche" :value="t.tranche">{{ t.tranche }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Increment % <span class="text-slate-400">(optional)</span></label>
              <input v-model="newTranche.increment_pct" type="number" step="0.01" min="0" max="100" placeholder="0 = copy as-is"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p class="text-xs text-slate-400 mt-1">All rates in the new tranche will be multiplied by (1 + increment%).</p>
            </div>
            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
              <button type="button" @click="showNewTranche = false"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                Cancel
              </button>
              <button type="submit" :disabled="newTrancheSaving"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
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
import { router } from '@inertiajs/vue3'
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
