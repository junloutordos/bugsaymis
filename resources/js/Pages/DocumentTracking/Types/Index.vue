<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import { userDisplayName } from '@/Utils/userDisplay.js'
import {
  PlusIcon, PencilSquareIcon, TrashIcon,
  ChevronUpIcon, ChevronDownIcon, Cog6ToothIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  documentTypes: Array,
  offices:       Array,
  users:         Array,
})

// ── Type modal ────────────────────────────────────────────────────────────
const typeModal  = ref(null) // null | 'create' | 'edit'
const editTarget = ref(null)
const typeForm   = ref({ name: '', code: '', description: '', lead_time_hours: 24, routing_type: 'sequential', applicable_to: 'internal', is_active: true })
const typeErrors = ref({})
const submitting = ref(false)

function openCreate() {
  typeForm.value  = { name: '', code: '', description: '', lead_time_hours: 24, routing_type: 'sequential', applicable_to: 'internal', is_active: true }
  typeErrors.value = {}
  typeModal.value = 'create'
}
function openEdit(type) {
  editTarget.value = type
  typeForm.value   = { name: type.name, code: type.code, description: type.description ?? '', lead_time_hours: type.lead_time_hours, routing_type: type.routing_type, applicable_to: type.applicable_to ?? 'both', is_active: type.is_active }
  typeErrors.value = {}
  typeModal.value  = 'edit'
}
function closeTypeModal() { typeModal.value = null }

function submitType() {
  submitting.value = true
  typeErrors.value = {}
  const isEdit = typeModal.value === 'edit'
  const routeKey = isEdit ? 'document-tracking.types.update' : 'document-tracking.types.store'
  const params   = isEdit ? editTarget.value.id : undefined
  const method   = isEdit ? 'put' : 'post'
  router[method](route(routeKey, params), typeForm.value, {
    onSuccess: closeTypeModal,
    onError:   e  => { typeErrors.value = e },
    onFinish:  () => { submitting.value = false },
    preserveScroll: true,
  })
}

async function deleteType(type) {
  if (!(await confirmDelete(`Delete "${type.name}"? This cannot be undone.`))) return
  router.delete(route('document-tracking.types.destroy', type.id), { preserveScroll: true })
}

// ── Step modal ────────────────────────────────────────────────────────────
const stepModal    = ref(false)
const stepTypeId   = ref(null)
const stepTarget   = ref(null)
const stepForm     = ref({ office_id: '', assigned_user_id: '', action_required: '', lead_time_hours: 24, is_required: true, step_order: 1 })
const stepErrors   = ref({})

// Users filtered by the selected office in the step form
const stepOfficeUsers = computed(() => {
  if (!stepForm.value.office_id) return props.users ?? []
  return (props.users ?? []).filter(u => u.office_id === +stepForm.value.office_id)
})

function openStep(typeId, step = null) {
  stepTypeId.value = typeId
  stepTarget.value = step
  stepForm.value   = step
    ? { office_id: step.office_id ?? '', assigned_user_id: step.assigned_user_id ?? '', action_required: step.action_required, lead_time_hours: step.lead_time_hours, is_required: step.is_required, step_order: step.step_order }
    : { office_id: '', assigned_user_id: '', action_required: '', lead_time_hours: 24, is_required: true, step_order: 99 }
  stepErrors.value = {}
  stepModal.value  = true
}

function submitStep() {
  submitting.value = true
  stepErrors.value = {}
  const isEdit = !!stepTarget.value
  const routeKey = isEdit ? 'document-tracking.types.steps.update' : 'document-tracking.types.steps.store'
  const params   = isEdit ? stepTarget.value.id : stepTypeId.value
  const method   = isEdit ? 'put' : 'post'
  router[method](route(routeKey, params), stepForm.value, {
    onSuccess: () => { stepModal.value = false },
    onError:   e  => { stepErrors.value = e },
    onFinish:  () => { submitting.value = false },
    preserveScroll: true,
  })
}

async function deleteStep(step) {
  if (!(await confirmDelete('Remove this routing step?'))) return
  router.delete(route('document-tracking.types.steps.destroy', step.id), { preserveScroll: true })
}

function moveStep(type, step, dir) {
  const steps     = [...type.routing_steps].sort((a, b) => a.step_order - b.step_order)
  const idx       = steps.findIndex(s => s.id === step.id)
  const swapIdx   = dir === 'up' ? idx - 1 : idx + 1
  if (swapIdx < 0 || swapIdx >= steps.length) return

  const reordered = steps.map((s, i) => ({
    id: s.id,
    step_order: i === idx ? steps[swapIdx].step_order : i === swapIdx ? steps[idx].step_order : s.step_order,
  }))

  axios.post(route('document-tracking.types.steps.reorder', type.id), { steps: reordered })
    .then(() => router.reload({ preserveScroll: true }))
}

// ── Helpers ────────────────────────────────────────────────────────────────
// Values below are AppBadge color keys (slate|indigo|blue|green|amber|orange|red|purple).
const routingTypeLabel = { sequential: 'Sequential', parallel: 'Parallel', manual: 'Manual' }
const routingTypeCls   = { sequential: 'indigo', parallel: 'amber', manual: 'slate' }

const applicableLabel = { internal: 'Internal', external: 'External', both: 'Internal & External' }
const applicableCls   = { internal: 'blue', external: 'green', both: 'purple' }

// watch office change → reset user selection
function onStepOfficeChange() { stepForm.value.assigned_user_id = '' }
</script>

<template>
  <Head title="Document Types" />
  <AdminLayout title="Document Types">
    <div class="space-y-5">

      <AppPageHeader
        title="Document Types & Routing Templates"
        :breadcrumb="[{ label: 'Document Tracking', href: route('document-tracking.index') }, { label: 'Document Types' }]"
      >
        <template #actions>
          <AppButton @click="openCreate">
            <PlusIcon class="h-4 w-4" />
            New Type
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.success }}
      </div>

      <!-- Type cards -->
      <EmptyState v-if="documentTypes.length === 0" title="No document types configured" subtitle="Create your first one." />

      <div v-else class="space-y-4">
        <div v-for="type in documentTypes" :key="type.id"
          class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">

          <!-- Type header -->
          <div class="px-5 py-4 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3 min-w-0">
              <div class="shrink-0 w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center">
                <Cog6ToothIcon class="h-5 w-5 text-indigo-500" />
              </div>
              <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-bold text-slate-800">{{ type.name }}</span>
                  <span class="font-mono text-xs text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded">{{ type.code }}</span>
                  <AppBadge :color="applicableCls[type.applicable_to] ?? applicableCls.both">
                    {{ applicableLabel[type.applicable_to] ?? type.applicable_to }}
                  </AppBadge>
                  <AppBadge v-if="type.applicable_to !== 'external'" :color="routingTypeCls[type.routing_type] ?? routingTypeCls.manual">
                    {{ routingTypeLabel[type.routing_type] ?? type.routing_type }}
                  </AppBadge>
                  <AppBadge v-if="!type.is_active" color="red">Inactive</AppBadge>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                  Lead time: {{ type.lead_time_hours }}h
                  <span v-if="type.description"> · {{ type.description }}</span>
                </p>
              </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <AppButton v-if="type.applicable_to !== 'external'" size="sm" variant="secondary" @click="openStep(type.id)">
                <PlusIcon class="h-3.5 w-3.5" /> Add Step
              </AppButton>
              <AppIconButton label="Edit type" variant="ghost" @click="openEdit(type)">
                <PencilSquareIcon class="h-4 w-4" />
              </AppIconButton>
              <AppIconButton label="Delete type" variant="danger" @click="deleteType(type)">
                <TrashIcon class="h-4 w-4" />
              </AppIconButton>
            </div>
          </div>

          <!-- Routing steps (not applicable for external-only types) -->
          <div v-if="type.applicable_to !== 'external' && type.routing_steps?.length" class="border-t border-slate-100">
            <div class="px-4 py-2 bg-slate-50 flex items-center gap-2">
              <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Routing Steps</span>
              <span class="text-xs text-slate-400">({{ type.routing_type === 'parallel' ? 'all notified at once' : 'one at a time' }})</span>
            </div>
            <div class="divide-y divide-slate-100">
              <div v-for="(step, idx) in [...type.routing_steps].sort((a,b) => a.step_order - b.step_order)"
                :key="step.id" class="px-5 py-3 flex items-center gap-3">

                <!-- Order buttons -->
                <div class="flex flex-col gap-0.5 shrink-0">
                  <button @click="moveStep(type, step, 'up')" :disabled="idx === 0"
                    class="p-0.5 text-slate-300 hover:text-slate-500 disabled:opacity-20">
                    <ChevronUpIcon class="h-3.5 w-3.5" />
                  </button>
                  <button @click="moveStep(type, step, 'down')" :disabled="idx === type.routing_steps.length - 1"
                    class="p-0.5 text-slate-300 hover:text-slate-500 disabled:opacity-20">
                    <ChevronDownIcon class="h-3.5 w-3.5" />
                  </button>
                </div>

                <!-- Step number -->
                <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold shrink-0">
                  {{ idx + 1 }}
                </div>

                <!-- Step info -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-sm font-medium text-slate-800">{{ step.action_required }}</span>
                    <span v-if="step.office_name" class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-medium">
                      {{ step.office_name }}
                    </span>
                    <span v-if="step.assigned_user" class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full font-medium">
                      → {{ userDisplayName(step.assigned_user, users) }}
                    </span>
                    <span v-if="!step.is_required" class="text-xs text-slate-400">(optional)</span>
                  </div>
                  <p class="text-xs text-slate-400 mt-0.5">Lead time: {{ step.lead_time_hours }}h</p>
                </div>

                <!-- Edit / Delete -->
                <div class="flex items-center gap-1 shrink-0">
                  <AppIconButton label="Edit step" variant="ghost" size="sm" @click="openStep(type.id, step)">
                    <PencilSquareIcon class="h-3.5 w-3.5" />
                  </AppIconButton>
                  <AppIconButton label="Delete step" variant="danger" size="sm" @click="deleteStep(step)">
                    <TrashIcon class="h-3.5 w-3.5" />
                  </AppIconButton>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="border-t border-slate-100 px-5 py-3 text-xs text-slate-400 italic">
            No routing steps — documents of this type will use manual routing.
          </div>
        </div>
      </div>

    </div>

    <!-- ── Type modal ─────────────────────────────────────────────────────── -->
    <AppModal
      :show="!!typeModal"
      :title="typeModal === 'create' ? 'New Document Type' : 'Edit Document Type'"
      size="lg"
      @close="closeTypeModal"
    >
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <AppInput v-model="typeForm.name" label="Name" :required="true" :error="typeErrors.name" placeholder="e.g. Memorandum" />
          <AppInput v-model="typeForm.code" label="Code" :required="true" :error="typeErrors.code" placeholder="e.g. MEMO" />
        </div>
        <AppInput v-model="typeForm.description" label="Description" />
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Applicable To <span class="text-red-500">*</span></label>
          <div class="flex gap-3">
            <label class="flex items-center gap-2 cursor-pointer px-3 py-2 rounded-lg border transition-colors flex-1 justify-center"
              :class="typeForm.applicable_to === 'internal' ? 'border-blue-400 bg-blue-50' : 'border-slate-200 hover:bg-slate-50'">
              <input type="radio" value="internal" v-model="typeForm.applicable_to" class="shrink-0" />
              <span class="text-sm font-medium text-slate-700">Internal</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer px-3 py-2 rounded-lg border transition-colors flex-1 justify-center"
              :class="typeForm.applicable_to === 'external' ? 'border-green-400 bg-green-50' : 'border-slate-200 hover:bg-slate-50'">
              <input type="radio" value="external" v-model="typeForm.applicable_to" class="shrink-0" />
              <span class="text-sm font-medium text-slate-700">External</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer px-3 py-2 rounded-lg border transition-colors flex-1 justify-center"
              :class="typeForm.applicable_to === 'both' ? 'border-purple-400 bg-purple-50' : 'border-slate-200 hover:bg-slate-50'">
              <input type="radio" value="both" v-model="typeForm.applicable_to" class="shrink-0" />
              <span class="text-sm font-medium text-slate-700">Both</span>
            </label>
          </div>
          <p v-if="typeErrors.applicable_to" class="text-xs text-red-500 mt-1">{{ typeErrors.applicable_to }}</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <AppInput v-model="typeForm.lead_time_hours" label="Default Lead Time (hours)" type="number" />
          <AppSelect v-if="typeForm.applicable_to !== 'external'" v-model="typeForm.routing_type" label="Routing Mode" :show-blank="false">
            <option value="sequential">Sequential — one at a time</option>
            <option value="parallel">Parallel — all at once</option>
            <option value="manual">Manual — sender picks each time</option>
          </AppSelect>
          <div v-else class="flex items-end pb-2">
            <p class="text-xs text-slate-400 italic">Routing mode not applicable — external docs use the fixed Records → OCD → Employee flow.</p>
          </div>
        </div>
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" v-model="typeForm.is_active" class="rounded border-slate-300 text-indigo-600" />
          <span class="text-sm text-slate-700">Active (available when logging documents)</span>
        </label>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="closeTypeModal">Cancel</AppButton>
        <AppButton :loading="submitting" @click="submitType">
          {{ typeModal === 'create' ? 'Create' : 'Save Changes' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- ── Step modal ──────────────────────────────────────────────────────── -->
    <AppModal
      :show="stepModal"
      :title="stepTarget ? 'Edit Routing Step' : 'Add Routing Step'"
      size="md"
      @close="stepModal = false"
    >
      <div class="space-y-4">
        <AppSelect
          v-model="stepForm.office_id"
          @change="onStepOfficeChange"
          label="Office"
          :required="true"
          :error="stepErrors.office_id"
          placeholder="Select office…"
        >
          <option v-for="o in offices" :key="o.id" :value="o.id">{{ o.name }}</option>
        </AppSelect>

        <div>
          <AppSelect
            v-model="stepForm.assigned_user_id"
            label="Assigned To (from selected office)"
            :error="stepErrors.assigned_user_id"
            placeholder="Select person…"
          >
            <option v-for="u in stepOfficeUsers" :key="u.id" :value="u.id">{{ userDisplayName(u, users) }}</option>
          </AppSelect>
          <p v-if="!stepForm.office_id" class="text-xs text-slate-400 mt-1">Select an office first to filter personnel.</p>
          <p v-else-if="stepOfficeUsers.length === 0" class="text-xs text-amber-600 mt-1">No active users found in this office.</p>
        </div>

        <AppInput v-model="stepForm.action_required" label="Action Required" :required="true" :error="stepErrors.action_required"
          placeholder="e.g. Review and add routing instructions" />

        <div class="grid grid-cols-2 gap-3">
          <AppInput v-model="stepForm.lead_time_hours" label="Lead Time (hours)" type="number" min="1" max="720" />
          <AppInput v-model="stepForm.step_order" label="Step Order" type="number" min="1" />
        </div>

        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" v-model="stepForm.is_required" class="rounded border-slate-300 text-indigo-600" />
          <span class="text-sm text-slate-700">Required step</span>
        </label>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="stepModal = false">Cancel</AppButton>
        <AppButton :loading="submitting" @click="submitStep">
          {{ stepTarget ? 'Save Changes' : 'Add Step' }}
        </AppButton>
      </template>
    </AppModal>

  </AdminLayout>
</template>
