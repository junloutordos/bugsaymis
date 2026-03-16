<script setup>
import { ref, computed } from "vue"
import { Head, router, Link } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { PencilSquareIcon, TrashIcon, PlusIcon, ArrowRightIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({
  assignments: Array,
  users: Array,
  plans: Array,
  authUser: Object,
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

  if (modalMode.value === "create") {
    submit.post(route("pm-special-assignments.store"), { ...form.value }, {
      onSuccess: () => { closeModal(); Swal.fire("Created", "Special Assignment created.", "success") },
      onError,
    })
  } else {
    submit.put(route("pm-special-assignments.update", form.value.id), { ...form.value }, {
      onSuccess: () => { closeModal(); Swal.fire("Updated", "Special Assignment updated.", "success") },
      onError,
    })
  }
}

const deleteAssignment = async (assignment) => {
  const result = await Swal.fire({
    title: "Delete Special Assignment?",
    text: "This action cannot be undone.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Delete",
  })
  if (result.isConfirmed) {
    submit.delete(route("pm-special-assignments.destroy", assignment.id), {
      onSuccess: () => Swal.fire("Deleted", "Special Assignment deleted.", "success"),
    })
  }
}
</script>

<template>
  <Head title="Special Assignments" />
  <AdminLayout title="Special Assignments">
    <div>
      <div class="flex items-center justify-between mb-4 gap-2">
        <h1 class="text-xl md:text-2xl font-bold text-gray-800">Special Assignments</h1>
        <button @click="openModal('create')"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow flex items-center gap-1">
          <PlusIcon class="w-5 h-5" /> New Special Assignment
        </button>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-4">
        <input v-model="searchQuery" type="text" placeholder="Search special assignments..."
          class="w-1/2 rounded-lg border-gray-300 shadow-sm" />

        <div class="overflow-x-auto mt-4">
          <table class="min-w-full border border-gray-200 text-sm">
            <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
              <tr>
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Name</th>
                <th class="px-4 py-3 text-left">Coordinator</th>
                <th class="px-4 py-3 text-left">Members</th>
                <th class="px-4 py-3 text-left">Tagged Plans</th>
                <th class="px-4 py-3 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="assignment in paginated" :key="assignment.id" class="hover:bg-gray-50">
                <td class="px-4 py-3">{{ assignment.id }}</td>
                <td class="px-4 py-3 font-medium">{{ assignment.name }}</td>
                <td class="px-4 py-3">{{ assignment.coordinator?.name ?? "—" }}</td>
                <td class="px-4 py-3">{{ assignment.members?.length ?? 0 }}</td>
                <td class="px-4 py-3">{{ assignment.work_distribution_plans?.length ?? 0 }}</td>
                <td class="px-4 py-3 text-center space-x-1">
                  <Link :href="route('pm-special-assignments.show', assignment.id)"
                    class="inline-flex text-green-600 hover:text-green-800" title="View Performance">
                    <ArrowRightIcon class="w-5 h-5" />
                  </Link>
                  <button @click="openModal('edit', assignment)" class="text-yellow-600 hover:text-yellow-800" title="Edit">
                    <PencilSquareIcon class="w-5 h-5" />
                  </button>
                  <button @click="deleteAssignment(assignment)" class="text-red-600 hover:text-red-800" title="Delete">
                    <TrashIcon class="w-5 h-5" />
                  </button>
                </td>
              </tr>
              <tr v-if="paginated.length === 0">
                <td colspan="6" class="px-4 py-6 text-center text-gray-500">No special assignments found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex justify-center items-center gap-2 mt-4">
          <button @click="currentPage--" :disabled="currentPage === 1" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-40">Prev</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="currentPage++" :disabled="currentPage === totalPages" class="px-3 py-1 bg-gray-200 rounded disabled:opacity-40">Next</button>
        </div>
      </div>

      <!-- MODAL -->
      <div v-show="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 relative max-h-[90vh] overflow-y-auto">
          <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800 text-xl" @click="closeModal">✕</button>
          <h2 class="text-xl font-semibold mb-4">
            {{ modalMode === 'create' ? 'New Special Assignment' : 'Edit Special Assignment' }}
          </h2>

          <form @submit.prevent="submitAssignment" class="space-y-4">
            <div>
              <label class="font-medium">Name <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" required class="w-full mt-1 rounded-lg border-gray-300" />
            </div>
            <div>
              <label class="font-medium">Coordinator</label>
              <select v-model="form.coordinator_id" class="w-full mt-1 rounded-lg border-gray-300">
                <option value="">— None —</option>
                <option v-for="u in props.users" :key="u.id" :value="u.id">
                  {{ u.name }}<span v-if="u.position"> ({{ u.position }})</span>
                </option>
              </select>
            </div>
            <div>
              <label class="font-medium">Description</label>
              <textarea v-model="form.description" rows="2" class="w-full mt-1 rounded-lg border-gray-300"></textarea>
            </div>

            <!-- Tagged WDP Plans -->
            <div>
              <label class="font-medium block mb-1">Tagged Work Distribution Plans</label>
              <div class="border border-gray-300 rounded-lg p-2 max-h-40 overflow-y-auto space-y-1 text-sm">
                <div v-for="p in props.plans" :key="p.id" class="flex items-start gap-2">
                  <input type="checkbox" :value="p.id" :checked="form.plan_ids.includes(p.id)"
                    @change="togglePlan(p.id)" class="mt-0.5 rounded border-gray-300" />
                  <span>{{ p.success_indicator }}
                    <span v-if="p.rated_by" class="text-gray-400 text-xs">({{ p.rated_by }})</span>
                  </span>
                </div>
                <p v-if="props.plans.length === 0" class="text-gray-400">No plans available.</p>
              </div>
              <p class="text-xs text-gray-400 mt-1">{{ form.plan_ids.length }} plan(s) selected</p>
            </div>

            <!-- Members -->
            <div>
              <label class="font-medium block mb-1">Members</label>
              <div class="border border-gray-300 rounded-lg p-2 max-h-52 overflow-y-auto space-y-2 text-sm">
                <div v-for="u in props.users" :key="u.id" class="flex items-start gap-2">
                  <input type="checkbox" :value="u.id" :checked="form.member_ids.includes(u.id)"
                    @change="toggleMember(u.id)" class="mt-1 rounded border-gray-300" />
                  <div class="flex-1">
                    <span>{{ u.name }}<span v-if="u.position" class="text-gray-400"> ({{ u.position }})</span></span>
                    <input v-if="form.member_ids.includes(u.id)" v-model="form.member_tasks[u.id]"
                      type="text" placeholder="Task / Role..." class="mt-1 w-full rounded border-gray-300 text-sm" />
                  </div>
                </div>
              </div>
              <p class="text-xs text-gray-400 mt-1">{{ form.member_ids.length }} member(s) selected</p>
            </div>

            <div class="flex justify-end gap-2 pt-4">
              <button type="button" @click="closeModal" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
              <button type="submit" :disabled="isSubmitting" class="px-4 py-2 bg-blue-600 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed">{{ isSubmitting ? 'Saving…' : 'Save' }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
