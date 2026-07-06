<template>
  <Head title="Supervisory Positions" />
  <AdminLayout title="Supervisory Positions">
    <div class="space-y-5">

      <AppPageHeader title="Supervisory Positions" :subtitle="pageSubtitle">
        <template #actions>
          <AppButton v-if="hasImportSuggestions" @click="importFromDivisions">
            <ArrowDownTrayIcon class="h-4 w-4" /> Import from HR Divisions
          </AppButton>
        </template>
      </AppPageHeader>

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
        class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="Object.keys($page.props.errors ?? {}).length"
        class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm">
        <p v-for="(msg, key) in $page.props.errors" :key="key">{{ msg }}</p>
      </div>

      <!-- No term warning -->
      <div v-if="!term"
        class="bg-warning-50 border border-warning-100 text-warning-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <ExclamationTriangleIcon class="h-4 w-4 shrink-0" />
        No active academic term found. Set a current term in School Years to enable load assignment.
      </div>

      <!-- Positions grid -->
      <AppTable :is-empty="!positions.length" :skeleton-cols="5">
        <template #head>
          <tr>
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Position</th>
            <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Load Units</th>
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Current Holder</th>
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Source</th>
            <th class="px-4 py-2.5"></th>
          </tr>
        </template>

        <tr v-for="pos in positions" :key="pos.id" class="hover:bg-slate-50/60">
          <!-- Position name -->
          <td class="px-4 py-3">
            <div class="font-medium text-slate-800">{{ pos.name }}</div>
            <div class="text-xs text-slate-400 font-mono">{{ pos.code }}</div>
          </td>

          <!-- Load units -->
          <td class="px-4 py-3 text-center">
            <AppBadge color="indigo">{{ pos.load_units }} units</AppBadge>
          </td>

          <!-- Current holder -->
          <td class="px-4 py-3">
            <span v-if="pos.assigned" class="text-slate-800 font-medium">
              {{ pos.assigned.user_name }}
            </span>
            <span v-else-if="pos.suggestion" class="text-warning-600 italic text-xs">
              {{ pos.suggestion.user_name }} (HR: {{ pos.suggestion.division_name }}) — not yet imported
            </span>
            <span v-else class="text-slate-400 italic text-xs">Unassigned</span>
          </td>

          <!-- Source badge -->
          <td class="px-4 py-3">
            <AppBadge :color="sourceInfo(pos).color">{{ sourceInfo(pos).label }}</AppBadge>
          </td>

          <!-- Actions -->
          <td class="px-4 py-3 text-right">
            <div class="flex items-center justify-end gap-1">
              <AppIconButton label="Assign" @click="openAssign(pos)"><PencilIcon class="h-4 w-4" /></AppIconButton>
              <AppIconButton v-if="pos.assigned" label="Remove" variant="danger" @click="removeAssignment(pos)"><TrashIcon class="h-4 w-4" /></AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="pos in positions" :key="pos.id" class="p-4 space-y-2">
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-800">{{ pos.name }}</p>
                <p class="text-xs text-slate-400 font-mono">{{ pos.code }}</p>
              </div>
              <AppBadge color="indigo">{{ pos.load_units }} units</AppBadge>
            </div>
            <p class="text-xs">
              <span v-if="pos.assigned" class="text-slate-800 font-medium">{{ pos.assigned.user_name }}</span>
              <span v-else-if="pos.suggestion" class="text-warning-600 italic">
                {{ pos.suggestion.user_name }} (HR: {{ pos.suggestion.division_name }}) — not yet imported
              </span>
              <span v-else class="text-slate-400 italic">Unassigned</span>
            </p>
            <div class="flex items-center justify-between pt-1">
              <AppBadge :color="sourceInfo(pos).color">{{ sourceInfo(pos).label }}</AppBadge>
              <div class="flex items-center gap-1">
                <AppIconButton label="Assign" @click="openAssign(pos)"><PencilIcon class="h-4 w-4" /></AppIconButton>
                <AppIconButton v-if="pos.assigned" label="Remove" variant="danger" @click="removeAssignment(pos)"><TrashIcon class="h-4 w-4" /></AppIconButton>
              </div>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No supervisory positions found" />
        </template>
      </AppTable>

    </div>

    <!-- Assign Modal -->
    <AppModal :show="modal" title="Assign Position" size="sm" @close="modal = false">
      <div class="space-y-4">
        <div>
          <p class="text-sm text-slate-600 mb-1">Position</p>
          <p class="text-sm font-medium text-slate-800">{{ selectedPos?.name }}</p>
          <p class="text-xs text-slate-400 font-mono">{{ selectedPos?.code }} · {{ selectedPos?.load_units }} units (admin load)</p>
        </div>

        <AppSelect v-model="form.user_id" label="Assign to" required placeholder="Select person...">
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </AppSelect>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="modal = false">Cancel</AppButton>
        <AppButton :disabled="!form.user_id" :loading="form.processing" @click="save">Assign</AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import AppSelect from '@/Components/AppSelect.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmAction } from '@/Composables/useConfirm.js'
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

const pageSubtitle = computed(() => {
  const base = 'Assign supervisory roles and reflect them in faculty loading'
  return props.schoolYear ? `${base} (${props.schoolYear.name})` : base
})

function sourceInfo(pos) {
  if (pos.assigned) return { color: 'green', label: 'Faculty Loading' }
  if (pos.suggestion) return { color: 'amber', label: 'HR Division' }
  return { color: 'slate', label: '—' }
}

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

async function removeAssignment(pos) {
  const confirmed = await confirmAction({
    title: 'Remove assignment?',
    text: `Remove "${pos.assigned?.user_name}" from "${pos.name}"?`,
    confirmText: 'Remove',
  })
  if (!confirmed) return
  useForm({}).delete(route('faculty-loading.supervisory.remove', pos.id))
}

async function importFromDivisions() {
  const confirmed = await confirmAction({
    title: 'Import from HR Divisions?',
    text: 'Import CID and SSD chiefs from HR Divisions into Faculty Loading?',
    confirmText: 'Import',
  })
  if (!confirmed) return
  router.post(route('faculty-loading.supervisory.import-divisions'))
}
</script>
