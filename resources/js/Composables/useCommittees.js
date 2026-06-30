import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"

export function useCommittees(props) {
  const showModal  = ref(false)
  const modalMode  = ref("create")
  const selectedCommittee = ref(null)

  const searchQuery = ref("")
  const currentPage = ref(1)
  const perPage = 10

  const memberSearch = ref("")

  const emptySubCommittee = () => ({
    id: null,
    name: "",
    head_id: "",
    member_ids: [],
    member_tasks: {},
    memberSearch: "",
  })

  const emptyForm = () => ({
    id: null,
    name: "",
    head_id: "",
    description: "",
    has_subcommittees: false,
    member_ids: [],
    member_tasks: {},
    sub_committees: [],
  })

  const form = ref(emptyForm())

  const committees = computed(() => props.committees || [])

  const filteredCommittees = computed(() => {
    const results = committees.value.filter((c) =>
      c?.name?.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
    const start = (currentPage.value - 1) * perPage
    return results.slice(start, start + perPage)
  })

  const totalPages = computed(() =>
    Math.max(1, Math.ceil(committees.value.length / perPage))
  )

  const filteredUsers = computed(() => {
    if (!memberSearch.value) return props.users || []
    const q = memberSearch.value.toLowerCase()
    return (props.users || []).filter(u => u.name.toLowerCase().includes(q))
  })

  function filteredSubUsers(sub) {
    if (!sub.memberSearch) return props.users || []
    const q = sub.memberSearch.toLowerCase()
    return (props.users || []).filter(u => u.name.toLowerCase().includes(q))
  }

  const openModal = (mode, committee = null) => {
    modalMode.value = mode
    showModal.value = true
    memberSearch.value = ""

    if ((mode === "edit" || mode === "view") && committee) {
      selectedCommittee.value = committee
      const hasSubCommittees = (committee.sub_committees?.length ?? 0) > 0

      const memberTasks = {}
      committee.members?.forEach(m => { memberTasks[m.id] = m.pivot?.task ?? "" })

      const subCommittees = (committee.sub_committees ?? []).map(sub => {
        const subTasks = {}
        sub.members?.forEach(m => { subTasks[m.id] = m.pivot?.task ?? "" })
        return {
          id: sub.id,
          name: sub.name ?? "",
          head_id: sub.head_id ?? "",
          member_ids: sub.members?.map(m => m.id) ?? [],
          member_tasks: subTasks,
          memberSearch: "",
        }
      })

      form.value = {
        id: committee.id,
        name: committee.name ?? "",
        head_id: committee.head_id ?? "",
        description: committee.description ?? "",
        has_subcommittees: hasSubCommittees,
        member_ids: committee.members?.map(m => m.id) ?? [],
        member_tasks: memberTasks,
        sub_committees: subCommittees,
      }
    } else {
      form.value = emptyForm()
      selectedCommittee.value = null
    }
  }

  const closeModal = () => {
    showModal.value = false
    selectedCommittee.value = null
    memberSearch.value = ""
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

  const addSubCommittee = () => {
    form.value.sub_committees.push(emptySubCommittee())
  }

  const removeSubCommittee = (idx) => {
    form.value.sub_committees.splice(idx, 1)
  }

  const toggleSubMember = (subIdx, userId) => {
    const sub = form.value.sub_committees[subIdx]
    const pos = sub.member_ids.indexOf(userId)
    if (pos === -1) {
      sub.member_ids.push(userId)
    } else {
      sub.member_ids.splice(pos, 1)
      delete sub.member_tasks[userId]
    }
  }

  const submitCommittee = () => {
    const payload = {
      name: form.value.name,
      head_id: form.value.head_id || null,
      description: form.value.description,
      has_subcommittees: form.value.has_subcommittees,
      ...(form.value.has_subcommittees
        ? {
            sub_committees: form.value.sub_committees.map(s => ({
              id: s.id,
              name: s.name,
              head_id: s.head_id || null,
              member_ids: s.member_ids,
              member_tasks: s.member_tasks,
            })),
          }
        : {
            member_ids: form.value.member_ids,
            member_tasks: form.value.member_tasks,
          }),
    }

    const options = {
      onSuccess: () => {
        closeModal()
        router.reload({ only: ['committees'] })
      },
    }

    if (modalMode.value === "create") {
      router.post(route("committees.store"), payload, options)
    } else {
      router.put(route("committees.update", form.value.id), payload, options)
    }
  }

  const deleteCommittee = (committee) => {
    if (!confirm(`Delete "${committee.name}"? This action cannot be undone.`)) return
    router.delete(route("committees.destroy", committee.id), {
      onSuccess: () => router.reload({ only: ['committees'] }),
    })
  }

  return {
    form, showModal, modalMode, selectedCommittee,
    searchQuery, currentPage, totalPages, filteredCommittees,
    memberSearch, filteredUsers, filteredSubUsers,
    openModal, closeModal,
    toggleMember, addSubCommittee, removeSubCommittee, toggleSubMember,
    submitCommittee, deleteCommittee,
  }
}
