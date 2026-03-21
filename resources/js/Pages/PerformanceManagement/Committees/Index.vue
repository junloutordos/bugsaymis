<script setup>
import { ref, computed } from "vue"
import { Head, router, Link } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import { EyeIcon, PencilSquareIcon, TrashIcon, PlusIcon, ArrowRightIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({
  committees: Array,
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
  return props.committees.filter(c => c.name?.toLowerCase().includes(q))
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
  head_id: "",
  description: "",
  member_ids: [],
  member_tasks: {},
  plan_ids: [],
})

const form = ref(emptyForm())

const openModal = (mode, committee = null) => {
  modalMode.value = mode
  showModal.value = true
  if ((mode === "edit" || mode === "view") && committee) {
    const memberTasks = {}
    committee.members?.forEach(m => { memberTasks[m.id] = m.pivot?.task ?? "" })
    form.value = {
      id: committee.id,
      name: committee.name ?? "",
      head_id: committee.head_id ?? "",
      description: committee.description ?? "",
      member_ids: committee.members?.map(m => m.id) ?? [],
      member_tasks: memberTasks,
      plan_ids: committee.work_distribution_plans?.map(p => p.id) ?? [],
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

const submitCommittee = () => {
  const onError = (errors) => {
    Swal.fire("Error", Object.values(errors).flat().join("\n") || "Something went wrong.", "error")
  }

  if (modalMode.value === "create") {
    submit.post(route("pm-committees.store"), { ...form.value }, {
      onSuccess: () => { closeModal(); Swal.fire("Created", "Committee created.", "success") },
      onError,
    })
  } else {
    submit.put(route("pm-committees.update", form.value.id), { ...form.value }, {
      onSuccess: () => { closeModal(); Swal.fire("Updated", "Committee updated.", "success") },
      onError,
    })
  }
}

const deleteCommittee = async (committee) => {
  const result = await Swal.fire({
    title: "Delete Committee?",
    text: "This action cannot be undone.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Delete",
  })
  if (result.isConfirmed) {
    submit.delete(route("pm-committees.destroy", committee.id), {
      onSuccess: () => Swal.fire("Deleted", "Committee deleted.", "success"),
    })
  }
}

const canManage = computed(() => {
  // Shown from backend; check via role comparison isn't available here,
  // but store always passes all users — restrict button visibility for now
  // (backend enforces auth)
  return true
})
</script>

<template>
  <Head title="Committees" />
  <AdminLayout title="Committees">
    <div>
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Committees</h1>
          <p class="text-sm text-slate-500">Manage performance committees and members.</p>
        </div>
        <button @click="openModal('create')"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
          <PlusIcon class="w-4 h-4" /> New Committee
        </button>
      </div>

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <input v-model="searchQuery" type="text" placeholder="Search committees..."
            class="w-full sm:w-72 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Head</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Members</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Tagged Plans</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="committee in paginated" :key="committee.id" class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-sm text-slate-700">{{ committee.id }}</td>
                <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ committee.name }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ committee.head?.name ?? "—" }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ committee.members?.length ?? 0 }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ committee.work_distribution_plans?.length ?? 0 }}</td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <Link :href="route('pm-committees.show', committee.id)"
                      class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="View Performance">
                      <ArrowRightIcon class="w-4 h-4" />
                    </Link>
                    <button @click="openModal('edit', committee)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" title="Edit">
                      <PencilSquareIcon class="w-4 h-4" />
                    </button>
                    <button @click="deleteCommittee(committee)" class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors" title="Delete">
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="paginated.length === 0">
                <td colspan="6" class="py-16 text-center text-slate-400 text-sm">No committees found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <div class="flex gap-2">
            <button @click="currentPage--" :disabled="currentPage === 1"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">Prev</button>
            <button @click="currentPage++" :disabled="currentPage === totalPages"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40">Next</button>
          </div>
        </div>
      </div>

      <!-- MODAL -->
      <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-slate-900/50 z-50 p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white rounded-t-2xl">
            <h2 class="text-base font-semibold text-slate-800">
              {{ modalMode === 'create' ? 'New Committee' : 'Edit Committee' }}
            </h2>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition-colors" @click="closeModal">✕</button>
          </div>

          <form @submit.prevent="submitCommittee">
            <div class="px-6 py-5 space-y-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Name <span class="text-red-500">*</span></label>
                <input v-model="form.name" type="text" required
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Committee Head</label>
                <select v-model="form.head_id"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                  <option value="">— None —</option>
                  <option v-for="u in props.users" :key="u.id" :value="u.id">
                    {{ u.name }}<span v-if="u.position"> ({{ u.position }})</span>
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
                <textarea v-model="form.description" rows="2"
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"></textarea>
              </div>

              <!-- Tagged WDP Plans -->
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

              <!-- Members -->
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
            </div>

            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
              <button type="button" @click="closeModal"
                class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
              <button type="submit" :disabled="isSubmitting"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">{{ isSubmitting ? 'Saving…' : 'Save' }}</button>
            </div>
          </form>
        </div>
      </div>
      </Teleport>
    </div>
  </AdminLayout>
</template>
