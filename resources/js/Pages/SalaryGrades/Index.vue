<template>
  <AdminLayout title="Salary Grade Table">
    <div class="max-w-full space-y-6">

      <AppPageHeader title="Salary Grade Table" subtitle="SSL V — Philippine Government Salary Standardization Law">
        <template #actions>
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
          <AppButton v-if="!isCurrentTranche" variant="success" @click="setActive">
            Set as Active
          </AppButton>
          <AppBadge v-else color="green">Active Schedule</AppBadge>

          <!-- New tranche -->
          <AppButton @click="openNewTranche">
            <PlusIcon class="h-4 w-4" />
            New Tranche
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash messages -->
      <div v-if="$page.props.flash?.success" class="px-4 py-3 rounded-lg bg-success-50 border border-success-100 text-success-700 text-sm">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.errors?.error" class="px-4 py-3 rounded-lg bg-danger-50 border border-danger-100 text-danger-600 text-sm">
        {{ $page.props.errors.error }}
      </div>

      <!-- Table -->
      <AppTable :skeleton-cols="steps.length + 1">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap border-r border-slate-100 w-16 sticky left-0 bg-slate-50">
              SG
            </th>
            <th
              v-for="step in steps"
              :key="step"
              class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap border-r border-slate-100 min-w-[130px]"
            >
              Step {{ step }}
            </th>
          </tr>
        </template>

        <tr
          v-for="grade in grades"
          :key="grade"
          class="hover:bg-indigo-50/40 transition-colors"
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
                <AppIconButton label="Save rate" size="sm" variant="success" @click="saveEdit">
                  <CheckIcon class="h-4 w-4" />
                </AppIconButton>
                <AppIconButton label="Cancel edit" size="sm" variant="danger" @click="cancelEdit">
                  <XMarkIcon class="h-4 w-4" />
                </AppIconButton>
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
      </AppTable>

      <p class="text-xs text-slate-400 text-right">
        Effective date: {{ effectiveDate }} &nbsp;·&nbsp;
        Rates in Philippine Peso (₱) &nbsp;·&nbsp;
        Click any cell to edit
      </p>
    </div>

    <!-- New Tranche Modal -->
    <AppModal :show="showNewTranche" title="Create New Tranche" subtitle="Copy an existing tranche with an optional salary increment." @close="showNewTranche = false">
      <form id="new-tranche-form" @submit.prevent="submitNewTranche" class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Tranche Name <span class="text-danger-600">*</span></label>
          <input v-model="newTranche.tranche" type="text" placeholder="e.g. SSL V T5 2025"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
            required />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Effective Date <span class="text-danger-600">*</span></label>
          <input v-model="newTranche.effective_date" type="date"
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
            required />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Base Tranche <span class="text-danger-600">*</span></label>
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
      </form>
      <template #footer>
        <AppButton variant="secondary" @click="showNewTranche = false">Cancel</AppButton>
        <AppButton type="submit" form="new-tranche-form" :loading="newTrancheSaving">
          {{ newTrancheSaving ? 'Creating...' : 'Create Tranche' }}
        </AppButton>
      </template>
    </AppModal>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppModal from '@/Components/AppModal.vue'
import { PlusIcon, CheckIcon, XMarkIcon } from '@heroicons/vue/24/outline'

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
