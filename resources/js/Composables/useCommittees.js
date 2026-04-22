import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"

export function useCommittees(props) {
  const committeesList = ref(props.committees || [])
  const showModal = ref(false)
  const modalMode = ref("create")
  const selectedCommittee = ref(null)

  const searchQuery = ref("")
  const currentPage = ref(1)
  const perPage = 10

  const emptyForm = () => ({
    id: null,
    name: "",
    head_id: "",
    description: "",
    member_ids: [],
    member_tasks: {},
  })

  const form = ref(emptyForm())

  const filteredCommittees = computed(() => {
    const results = committeesList.value.filter((c) =>
      c?.name?.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
    const start = (currentPage.value - 1) * perPage
    return results.slice(start, start + perPage)
  })

  const totalPages = computed(() =>
    Math.max(1, Math.ceil(committeesList.value.length / perPage))
  )

  const openModal = (mode, committee = null) => {
    modalMode.value = mode
    showModal.value = true

    if ((mode === "edit" || mode === "view") && committee) {
      selectedCommittee.value = committee
      const memberTasks = {}
      committee.members?.forEach(m => { memberTasks[m.id] = m.pivot?.task ?? "" })
      form.value = {
        id: committee.id,
        name: committee.name ?? "",
        head_id: committee.head_id ?? "",
        description: committee.description ?? "",
        member_ids: committee.members?.map(m => m.id) ?? [],
        member_tasks: memberTasks,
      }
    } else {
      form.value = emptyForm()
      selectedCommittee.value = null
    }
  }

  const closeModal = () => {
    showModal.value = false
    selectedCommittee.value = null
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

  const submitCommittee = () => {
    const payload = { ...form.value }
    const onError = (errors) => {
      Swal.fire("Error", Object.values(errors).flat().join("\n") || "Something went wrong.", "error")
    }

    if (modalMode.value === "create") {
      router.post(route("committees.store"), payload, {
        onSuccess: async () => {
          closeModal()
          await Swal.fire("Created", "Committee created successfully.", "success")
          window.location.reload()
        },
        onError,
      })
    } else {
      router.put(route("committees.update", form.value.id), payload, {
        onSuccess: async () => {
          closeModal()
          await Swal.fire("Updated", "Committee updated successfully.", "success")
          window.location.reload()
        },
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
      router.delete(route("committees.destroy", committee.id), {
        onSuccess: async () => {
          committeesList.value = committeesList.value.filter(c => c.id !== committee.id)
          await Swal.fire("Deleted", "Committee deleted.", "success")
          window.location.reload()
        },
      })
    }
  }

  return {
    committeesList, form, showModal, modalMode, selectedCommittee,
    searchQuery, currentPage, totalPages, filteredCommittees,
    openModal, closeModal, toggleMember, submitCommittee, deleteCommittee,
  }
}
