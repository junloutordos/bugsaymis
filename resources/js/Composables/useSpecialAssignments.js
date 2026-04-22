import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"

export function useSpecialAssignments(props) {
  const assignmentsList = ref(props.assignments || [])
  const showModal = ref(false)
  const modalMode = ref("create")
  const selectedAssignment = ref(null)

  const searchQuery = ref("")
  const currentPage = ref(1)
  const perPage = 10

  const emptyForm = () => ({
    id: null,
    name: "",
    coordinator_id: "",
    description: "",
    member_ids: [],
    member_tasks: {},
  })

  const form = ref(emptyForm())

  const filteredAssignments = computed(() => {
    const results = assignmentsList.value.filter((a) =>
      a?.name?.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
    const start = (currentPage.value - 1) * perPage
    return results.slice(start, start + perPage)
  })

  const totalPages = computed(() =>
    Math.max(1, Math.ceil(assignmentsList.value.length / perPage))
  )

  const openModal = (mode, assignment = null) => {
    modalMode.value = mode
    showModal.value = true

    if ((mode === "edit" || mode === "view") && assignment) {
      selectedAssignment.value = assignment
      const memberTasks = {}
      assignment.members?.forEach(m => { memberTasks[m.id] = m.pivot?.task ?? "" })
      form.value = {
        id: assignment.id,
        name: assignment.name ?? "",
        coordinator_id: assignment.coordinator_id ?? "",
        description: assignment.description ?? "",
        member_ids: assignment.members?.map(m => m.id) ?? [],
        member_tasks: memberTasks,
      }
    } else {
      form.value = emptyForm()
      selectedAssignment.value = null
    }
  }

  const closeModal = () => {
    showModal.value = false
    selectedAssignment.value = null
  }

  const toggleMember = (userId) => {
    const idx = form.value.member_ids.indexOf(userId)
    if (idx === -1) {
      form.value.member_ids.push(userId)
    } else {
      form.value.member_ids.splice(idx, 1)
      delete form.value.member_tasks[userId]
    }
  }

  const submitAssignment = () => {
    const payload = { ...form.value }
    const onError = (errors) => {
      Swal.fire("Error", Object.values(errors).flat().join("\n") || "Something went wrong.", "error")
    }

    if (modalMode.value === "create") {
      router.post(route("special-assignments.store"), payload, {
        onSuccess: async () => {
          closeModal()
          await Swal.fire("Created", "Special Assignment created successfully.", "success")
          window.location.reload()
        },
        onError,
      })
    } else {
      router.put(route("special-assignments.update", form.value.id), payload, {
        onSuccess: async () => {
          closeModal()
          await Swal.fire("Updated", "Special Assignment updated successfully.", "success")
          window.location.reload()
        },
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
      router.delete(route("special-assignments.destroy", assignment.id), {
        onSuccess: async () => {
          assignmentsList.value = assignmentsList.value.filter(a => a.id !== assignment.id)
          await Swal.fire("Deleted", "Special Assignment deleted.", "success")
          window.location.reload()
        },
      })
    }
  }

  return {
    assignmentsList, form, showModal, modalMode, selectedAssignment,
    searchQuery, currentPage, totalPages, filteredAssignments,
    openModal, closeModal, toggleMember, submitAssignment, deleteAssignment,
  }
}
