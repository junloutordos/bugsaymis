<template>
  <Head title="Supervisory Positions" />
  <AdminLayout title="Supervisory Positions">
    <div class="space-y-5">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Supervisory Positions</h1>
          <p class="text-sm text-slate-500 mt-0.5">
            Assign supervisory roles and reflect them in faculty loading
            <span v-if="schoolYear" class="font-medium text-slate-700">({{ schoolYear.name }})</span>
          </p>
        </div>
        <button v-if="hasImportSuggestions" @click="importFromDivisions"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors shadow-sm shrink-0">
          <ArrowDownTrayIcon class="h-4 w-4" /> Import from HR Divisions
        </button>
      </div>

      <!-- School Year picker -->
      <div class="flex items-center gap-2">
        <label class="text-xs font-medium text-slate-500 uppercase tracking-wide">School Year</label>
        <select v-model="selectedSy" @change="switchYear"
          class="text-sm border border-indigo-300 bg-indigo-50 text-indigo-700 font-medium rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
            {{ sy.name }}{{ sy.is_current ? ' (current)' : '' }}
          </option>
        </select>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
        class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="Object.keys($page.props.errors ?? {}).length"
        class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-4 py-3 text-sm">
        <p v-for="(msg, key) in $page.props.errors" :key="key">{{ msg }}</p>
      </div>

      <!-- No term warning -->
      <div v-if="!term"
        class="bg-amber-50 border border-amber-200 text-amber-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <ExclamationTriangleIcon class="h-4 w-4 shrink-0" />
        No active academic term found. Set a current term in School Years to enable load assignment.
      </div>

      <!-- Positions grid -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead>
            <tr class="text-xs text-slate-400 uppercase tracking-wide bg-slate-50">
              <th class="px-4 py-2.5 text-left">Position</th>
              <th class="px-4 py-2.5 text-center">Load Units</th>
              <th class="px-4 py-2.5 text-left">Current Holder</th>
              <th class="px-4 py-2.5 text-left">Source</th>
              <th class="px-4 py-2.5"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="pos in positions" :key="pos.id" class="hover:bg-slate-50/50">
              <!-- Position name -->
              <td class="px-4 py-3">
                <div class="font-medium text-slate-800">{{ pos.name }}</div>
                <div class="text-xs text-slate-400 font-mono">{{ pos.code }}</div>
              </td>

              <!-- Load units -->
              <td class="px-4 py-3 text-center">
                <span class="inline-flex items-center rounded-full bg-indigo-50 text-indigo-700 px-2 py-0.5 text-xs font-semibold">
                  {{ pos.load_units }} units
                </span>
              </td>

              <!-- Current holder -->
              <td class="px-4 py-3">
                <span v-if="pos.assigned" class="text-slate-800 font-medium">
                  {{ pos.assigned.user_name }}
                </span>
                <span v-else-if="pos.suggestion" class="text-amber-600 italic text-xs">
                  {{ pos.suggestion.user_name }} (HR: {{ pos.suggestion.division_name }}) — not yet imported
                </span>
                <span v-else class="text-slate-400 italic text-xs">Unassigned</span>
              </td>

              <!-- Source badge -->
              <td class="px-4 py-3">
                <span v-if="pos.assigned"
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-emerald-50 text-emerald-700">
                  Faculty Loading
                </span>
                <span v-else-if="pos.suggestion"
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-amber-50 text-amber-700">
                  HR Division
                </span>
                <span v-else
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-400">
                  —
                </span>
              </td>

              <!-- Actions -->
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <button @click="openAssign(pos)"
                    class="p-1.5 text-slate-400 hover:text-indigo-600 rounded" title="Assign">
                    <PencilIcon class="h-4 w-4" />
                  </button>
                  <button v-if="pos.assigned" @click="removeAssignment(pos)"
                    class="p-1.5 text-slate-400 hover:text-red-600 rounded" title="Remove">
                    <TrashIcon class="h-4 w-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>

    <!-- Assign Modal -->
    <div v-if="modal" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 backdrop-blur-sm p-4 overflow-y-auto">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4 my-8">
        <h2 class="text-lg font-semibold text-slate-800">Assign Position</h2>
        <div>
          <p class="text-sm text-slate-600 mb-1">Position</p>
          <p class="text-sm font-medium text-slate-800">{{ selectedPos?.name }}</p>
          <p class="text-xs text-slate-400 font-mono">{{ selectedPos?.code }} · {{ selectedPos?.load_units }} units (admin load)</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Assign to *</label>
          <select v-model="form.user_id"
            class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option :value="null">Select person...</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>

        <div class="flex justify-end gap-2 pt-1">
          <button @click="modal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
          <button @click="save" :disabled="!form.user_id || form.processing"
            class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium disabled:opacity-50">
            Assign
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  ArrowDownTrayIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  PencilIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  positions:           { type: Array,  default: () => [] },
  users:               { type: Array,  default: () => [] },
  schoolYear:          { type: Object, default: null },
  term:                { type: Object, default: null },
  schoolYears:         { type: Array,  default: () => [] },
  currentSchoolYearId: { type: Number, default: null },
})

const selectedSy = ref(props.currentSchoolYearId)

function switchYear() {
  router.get(route('faculty-loading.supervisory.index'), { school_year_id: selectedSy.value }, { preserveState: false })
}

const hasImportSuggestions = computed(() => props.positions.some(p => p.suggestion && !p.assigned))

// ── Assign modal ──────────────────────────────────────────────────────────────

const modal       = ref(false)
const selectedPos = ref(null)
const form        = useForm({ user_id: null })

function openAssign(pos) {
  selectedPos.value = pos
  form.user_id = pos.assigned?.user_id ?? null
  modal.value = true
}

function save() {
  form.post(route('faculty-loading.supervisory.assign', selectedPos.value.id), {
    onSuccess: () => { modal.value = false },
  })
}

function removeAssignment(pos) {
  if (!confirm(`Remove "${pos.assigned?.user_name}" from "${pos.name}"?`)) return
  useForm({}).delete(route('faculty-loading.supervisory.remove', pos.id))
}

function importFromDivisions() {
  if (!confirm('Import CID and SSD chiefs from HR Divisions into Faculty Loading?')) return
  router.post(route('faculty-loading.supervisory.import-divisions'))
}
</script>
