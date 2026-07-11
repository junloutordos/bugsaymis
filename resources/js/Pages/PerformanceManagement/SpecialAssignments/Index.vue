<script setup>
import { ref, computed } from "vue"
import { Head, Link } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppPageHeader from "@/Components/AppPageHeader.vue"
import AppButton from "@/Components/AppButton.vue"
import AppFilterBar from "@/Components/AppFilterBar.vue"
import AppInput from "@/Components/AppInput.vue"
import AppSelect from "@/Components/AppSelect.vue"
import AppTextarea from "@/Components/AppTextarea.vue"
import AppTable from "@/Components/AppTable.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import AppModal from "@/Components/AppModal.vue"
import EmptyState from "@/Components/EmptyState.vue"
import PaginationControl from "@/Components/PaginationControl.vue"
import { PencilSquareIcon, TrashIcon, PlusIcon, ArrowRightIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"
import FiscalYearFilter from "@/Components/FiscalYearFilter.vue"
import { useSubmit } from "@/Composables/useSubmit"
import { confirmAction } from "@/Composables/useConfirm.js"

const props = defineProps({
  assignments: Array,
  users: Array,
  plans: Array,
  authUser: Object,
  fiscalYears: { type: Array, default: () => [] },
  selectedFiscalYear: { type: [String, Number], default: "" },
  currentFiscalYear: { type: Number, default: null },
})

const { isSubmitting, submit } = useSubmit()

// --- List ---
const searchQuery = ref("")
const currentPage = ref(1)
const perPage = 10

const filtered = computed(() => {
  const q = searchQuery.value.toLowerCase()
  return props.assignments.filter(a => a.name?.toLowerCase().includes(q))
})
const paginated = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filtered.value.slice(start, start + perPage)
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)))

// --- Modal ---
const showModal = ref(false)
const modalMode = ref("create")

const emptyForm = () => ({
  id: null,
  name: "",
  coordinator_id: "",
  description: "",
  fiscal_year: props.currentFiscalYear ?? null,
  member_ids: [],
  member_tasks: {},
  plan_ids: [],
})

const form = ref(emptyForm())

const openModal = (mode, assignment = null) => {
  modalMode.value = mode
  showModal.value = true
  if ((mode === "edit") && assignment) {
    const memberTasks = {}
    assignment.members?.forEach(m => { memberTasks[m.id] = m.pivot?.task ?? "" })
    form.value = {
      id: assignment.id,
      name: assignment.name ?? "",
      coordinator_id: assignment.coordinator_id ?? "",
      description: assignment.description ?? "",
      fiscal_year: assignment.fiscal_year ?? null,
      member_ids: assignment.members?.map(m => m.id) ?? [],
      member_tasks: memberTasks,
      plan_ids: assignment.work_distribution_plans?.map(p => p.id) ?? [],
    }
  } else {
    form.value = emptyForm()
  }
}

const closeModal = () => { showModal.value = false }

const toggleMember = (userId) => {
  const idx = form.value.member_ids.indexOf(userId)
  if (idx === -1) {
    form.value.member_ids.push(userId)
  } else {
    form.value.member_ids.splice(idx, 1)
    delete form.value.member_tasks[userId]
  }
}

const togglePlan = (planId) => {
  const idx = form.value.plan_ids.indexOf(planId)
  if (idx === -1) form.value.plan_ids.push(planId)
  else form.value.plan_ids.splice(idx, 1)
}

const submitAssignment = () => {
  const onError = (errors) => {
    Swal.fire("Error", Object.values(errors).flat().join("\n") || "Something went wrong.", "error")
  }

  const payload = { ...form.value, fiscal_year: form.value.fiscal_year ? Number(form.value.fiscal_year) : null }

  if (modalMode.value === "create") {
    submit.post(route("pm-special-assignments.store"), payload, {
      onSuccess: () => { closeModal(); Swal.fire("Created", "Special Assignment created.", "success") },
      onError,
    })
  } else {
    submit.put(route("pm-special-assignments.update", form.value.id), payload, {
      onSuccess: () => { closeModal(); Swal.fire("Updated", "Special Assignment updated.", "success") },
      onError,
    })
  }
}

const deleteAssignment = async (assignment) => {
  const ok = await confirmAction({
    title: "Delete Special Assignment?",
    text: "This action cannot be undone.",
    confirmText: "Delete",
    icon: "warning",
  })
  if (ok) {
    submit.delete(route("pm-special-assignments.destroy", assignment.id), {
      onSuccess: () => Swal.fire("Deleted", "Special Assignment deleted.", "success"),
    })
  }
}
</script>

<template>
  <Head title="Special Assignments" />
  <AdminLayout title="Special Assignments">
    <div class="space-y-5">

      <AppPageHeader title="Special Assignments" subtitle="Manage special assignments and their members.">
        <template #actions>
          <AppButton @click="openModal('create')">
            <PlusIcon class="w-4 h-4" /> New Special Assignment
          </AppButton>
        </template>
      </AppPageHeader>

      <AppFilterBar>
        <AppInput v-model="searchQuery" placeholder="Search special assignments..." class="w-full sm:w-72" />
        <FiscalYearFilter :fiscal-years="fiscalYears" :selected="selectedFiscalYear" route-name="pm-special-assignments.index" />
      </AppFilterBar>

      <AppTable :is-empty="paginated.length === 0" :skeleton-cols="6">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Coordinator</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Members</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Tagged Plans</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="assignment in paginated" :key="assignment.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-sm text-slate-700">{{ assignment.id }}</td>
          <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ assignment.name }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ assignment.coordinator?.name ?? "—" }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ assignment.members?.length ?? 0 }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ assignment.work_distribution_plans?.length ?? 0 }}</td>
          <td class="px-4 py-3 text-center">
            <div class="flex items-center justify-center gap-1">
              <Link :href="route('pm-special-assignments.show', assignment.id)" title="View Performance"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors">
                <ArrowRightIcon class="w-4 h-4" />
              </Link>
              <AppIconButton label="Edit" @click="openModal('edit', assignment)"><PencilSquareIcon class="w-4 h-4" /></AppIconButton>
              <AppIconButton label="Delete" variant="danger" @click="deleteAssignment(assignment)"><TrashIcon class="w-4 h-4" /></AppIconButton>
            </div>
          </td>
        </tr>

        <template #empty>
          <EmptyState title="No special assignments found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            @prev="currentPage--"
            @next="currentPage++"
            @page="currentPage = $event"
          />
        </template>
      </AppTable>

      <!-- MODAL -->
      <AppModal :show="showModal" :title="modalMode === 'create' ? 'New Special Assignment' : 'Edit Special Assignment'" size="2xl" @close="closeModal">
        <form @submit.prevent="submitAssignment" class="space-y-4">
          <AppInput v-model="form.name" label="Name" required />

          <AppSelect v-model="form.coordinator_id" label="Coordinator" placeholder="— None —">
            <option v-for="u in props.users" :key="u.id" :value="u.id">
              {{ u.name }}<span v-if="u.position"> ({{ u.position }})</span>
            </option>
          </AppSelect>

          <AppTextarea v-model="form.description" label="Description" :rows="2" />

          <AppSelect v-model="form.fiscal_year" label="Fiscal Year">
            <option :value="null">All years (unscoped)</option>
            <option v-for="y in fiscalYears" :key="y" :value="y">FY {{ y }}</option>
          </AppSelect>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Tagged Work Distribution Plans</label>
            <div class="border border-slate-200 rounded-lg p-2 max-h-40 overflow-y-auto space-y-1 text-sm">
              <div v-for="p in props.plans" :key="p.id" class="flex items-start gap-2">
                <input type="checkbox" :value="p.id" :checked="form.plan_ids.includes(p.id)"
                  @change="togglePlan(p.id)" class="mt-0.5 rounded border-slate-300" />
                <span class="text-slate-700">{{ p.success_indicator }}
                  <span v-if="p.rated_by" class="text-slate-400 text-xs">({{ p.rated_by }})</span>
                </span>
              </div>
              <p v-if="props.plans.length === 0" class="text-slate-400">No plans available.</p>
            </div>
            <p class="text-xs text-slate-400 mt-1">{{ form.plan_ids.length }} plan(s) selected</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Members</label>
            <div class="border border-slate-200 rounded-lg p-2 max-h-52 overflow-y-auto space-y-2 text-sm">
              <div v-for="u in props.users" :key="u.id" class="flex items-start gap-2">
                <input type="checkbox" :value="u.id" :checked="form.member_ids.includes(u.id)"
                  @change="toggleMember(u.id)" class="mt-1 rounded border-slate-300" />
                <div class="flex-1">
                  <span class="text-slate-700">{{ u.name }}<span v-if="u.position" class="text-slate-400"> ({{ u.position }})</span></span>
                  <input v-if="form.member_ids.includes(u.id)" v-model="form.member_tasks[u.id]"
                    type="text" placeholder="Task / Role..."
                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                </div>
              </div>
            </div>
            <p class="text-xs text-slate-400 mt-1">{{ form.member_ids.length }} member(s) selected</p>
          </div>

          <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <AppButton type="button" variant="secondary" @click="closeModal">Cancel</AppButton>
            <AppButton type="submit" :loading="isSubmitting" :disabled="isSubmitting">{{ isSubmitting ? 'Saving…' : 'Save' }}</AppButton>
          </div>
        </form>
      </AppModal>
    </div>
  </AdminLayout>
</template>
