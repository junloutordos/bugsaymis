<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import {
  PlusIcon, PencilSquareIcon, TrashIcon, ChevronLeftIcon,
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
const typeForm   = ref({ name: '', code: '', description: '', lead_time_hours: 24, routing_type: 'sequential', is_active: true })
const typeErrors = ref({})
const submitting = ref(false)

function openCreate() {
  typeForm.value  = { name: '', code: '', description: '', lead_time_hours: 24, routing_type: 'sequential', is_active: true }
  typeErrors.value = {}
  typeModal.value = 'create'
}
function openEdit(type) {
  editTarget.value = type
  typeForm.value   = { name: type.name, code: type.code, description: type.description ?? '', lead_time_hours: type.lead_time_hours, routing_type: type.routing_type, is_active: type.is_active }
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

function deleteType(type) {
  if (!confirm(`Delete "${type.name}"? This cannot be undone.`)) return
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

function deleteStep(step) {
  if (!confirm('Remove this routing step?')) return
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
const routingTypeLabel = { sequential: 'Sequential', parallel: 'Parallel', manual: 'Manual' }
const routingTypeCls   = { sequential: 'bg-indigo-100 text-indigo-700', parallel: 'bg-amber-100 text-amber-700', manual: 'bg-slate-100 text-slate-600' }

// watch office change → reset user selection
function onStepOfficeChange() { stepForm.value.assigned_user_id = '' }
</script>

<template>
  <Head title="Document Types" />
  <AdminLayout title="Document Types">

    <div class="flex items-center justify-between mb-5">
      <div class="flex items-center gap-3">
        <button @click="router.visit(route('document-tracking.index'))"
          class="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800">
          <ChevronLeftIcon class="h-4 w-4" /> Document Tracking
        </button>
        <span class="text-slate-300">/</span>
        <h2 class="text-base font-bold text-slate-800">Document Types & Routing Templates</h2>
      </div>
      <button @click="openCreate"
        class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg">
        <PlusIcon class="h-4 w-4" /> New Type
      </button>
    </div>

    <!-- Type cards -->
    <div v-if="documentTypes.length === 0" class="py-16 text-center text-slate-400 text-sm">
      No document types configured. Create your first one.
    </div>

    <div class="space-y-4">
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
                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold"
                  :class="routingTypeCls[type.routing_type] ?? routingTypeCls.manual">
                  {{ routingTypeLabel[type.routing_type] ?? type.routing_type }}
                </span>
                <span v-if="!type.is_active" class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 text-red-600">
                  Inactive
                </span>
              </div>
              <p class="text-xs text-slate-500 mt-0.5">
                Lead time: {{ type.lead_time_hours }}h
                <span v-if="type.description"> · {{ type.description }}</span>
              </p>
            </div>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <button @click="openStep(type.id)"
              class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50">
              <PlusIcon class="h-3.5 w-3.5" /> Add Step
            </button>
            <button @click="openEdit(type)"
              class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg">
              <PencilSquareIcon class="h-4 w-4" />
            </button>
            <button @click="deleteType(type)"
              class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg">
              <TrashIcon class="h-4 w-4" />
            </button>
          </div>
        </div>

        <!-- Routing steps -->
        <div v-if="type.routing_steps?.length" class="border-t border-slate-100">
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
                    → {{ step.assigned_user.name }}
                  </span>
                  <span v-if="!step.is_required" class="text-xs text-slate-400">(optional)</span>
                </div>
                <p class="text-xs text-slate-400 mt-0.5">Lead time: {{ step.lead_time_hours }}h</p>
              </div>

              <!-- Edit / Delete -->
              <div class="flex items-center gap-1 shrink-0">
                <button @click="openStep(type.id, step)" class="p-1 text-slate-400 hover:text-indigo-600 rounded">
                  <PencilSquareIcon class="h-3.5 w-3.5" />
                </button>
                <button @click="deleteStep(step)" class="p-1 text-slate-400 hover:text-red-600 rounded">
                  <TrashIcon class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="border-t border-slate-100 px-5 py-3 text-xs text-slate-400 italic">
          No routing steps — documents of this type will use manual routing.
        </div>
      </div>
    </div>

    <!-- ── Type modal ─────────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="typeModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
        <div class="fixed inset-0 bg-black/40" @click="closeTypeModal" />
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800">{{ typeModal === 'create' ? 'New Document Type' : 'Edit Document Type' }}</h3>
            <button @click="closeTypeModal" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
          </div>
          <div class="px-6 py-5 space-y-4">
            <div class="grid grid-cols-2 gap-3">
              <AppInput v-model="typeForm.name" label="Name" :required="true" :error="typeErrors.name" placeholder="e.g. Memorandum" />
              <AppInput v-model="typeForm.code" label="Code" :required="true" :error="typeErrors.code" placeholder="e.g. MEMO" />
            </div>
            <AppInput v-model="typeForm.description" label="Description" />
            <div class="grid grid-cols-2 gap-3">
              <AppInput v-model="typeForm.lead_time_hours" label="Default Lead Time (hours)" type="number" />
              <AppSelect v-model="typeForm.routing_type" label="Routing Mode" :show-blank="false">
                <option value="sequential">Sequential — one at a time</option>
                <option value="parallel">Parallel — all at once</option>
                <option value="manual">Manual — sender picks each time</option>
              </AppSelect>
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="typeForm.is_active" class="rounded border-slate-300 text-indigo-600" />
              <span class="text-sm text-slate-700">Active (available when logging documents)</span>
            </label>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click="closeTypeModal" class="px-4 py-2 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button @click="submitType" :disabled="submitting"
              class="px-5 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50">
              {{ submitting ? 'Saving…' : (typeModal === 'create' ? 'Create' : 'Save Changes') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Step modal ──────────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="stepModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
        <div class="fixed inset-0 bg-black/40" @click="stepModal = false" />
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800">{{ stepTarget ? 'Edit Routing Step' : 'Add Routing Step' }}</h3>
            <button @click="stepModal = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
          </div>
          <div class="px-6 py-5 space-y-4">
            <!-- Office -->
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Office <span class="text-red-500">*</span></label>
              <select v-model="stepForm.office_id" @change="onStepOfficeChange" required
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Select office…</option>
                <option v-for="o in offices" :key="o.id" :value="o.id">{{ o.name }}</option>
              </select>
              <p v-if="stepErrors.office_id" class="text-xs text-red-500 mt-1">{{ stepErrors.office_id }}</p>
            </div>

            <!-- User from that office -->
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">
                Assigned To
                <span class="text-slate-400 font-normal">(from selected office)</span>
              </label>
              <select v-model="stepForm.assigned_user_id"
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Select person…</option>
                <option v-for="u in stepOfficeUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
              <p v-if="!stepForm.office_id" class="text-xs text-slate-400 mt-1">Select an office first to filter personnel.</p>
              <p v-else-if="stepOfficeUsers.length === 0" class="text-xs text-amber-600 mt-1">No active users found in this office.</p>
              <p v-if="stepErrors.assigned_user_id" class="text-xs text-red-500 mt-1">{{ stepErrors.assigned_user_id }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Action Required <span class="text-red-500">*</span></label>
              <input v-model="stepForm.action_required" type="text" required
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                placeholder="e.g. Review and add routing instructions" />
              <p v-if="stepErrors.action_required" class="text-xs text-red-500 mt-1">{{ stepErrors.action_required }}</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Lead Time (hours)</label>
                <input v-model="stepForm.lead_time_hours" type="number" min="1" max="720"
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Step Order</label>
                <input v-model="stepForm.step_order" type="number" min="1"
                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="stepForm.is_required" class="rounded border-slate-300 text-indigo-600" />
              <span class="text-sm text-slate-700">Required step</span>
            </label>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click="stepModal = false" class="px-4 py-2 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button @click="submitStep" :disabled="submitting"
              class="px-5 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50">
              {{ submitting ? 'Saving…' : (stepTarget ? 'Save Changes' : 'Add Step') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>
