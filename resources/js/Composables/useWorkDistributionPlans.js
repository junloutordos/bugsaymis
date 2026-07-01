import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"

export function useWorkDistributionPlans(props) {
  const plansList = ref(props.plans || [])
  const showModal = ref(false)
  const modalMode = ref("create")
  const selectedPlan = ref(null)

  const searchQuery = ref("")
  const appliedSearchQuery = ref("")
  const currentPage = ref(1)
  const perPage = 10

  const emptyForm = () => ({
    id: null,
    performance_indicator_id: "",
    success_indicator: "",
    involved: [],         // [{ type: 'all'|'office'|'committee'|'assignment'|'text', id?, label }]
    rated_by: "",
  })

  const form = ref(emptyForm())

  // Tag search state
  const tagSearch = ref("")

  const filteredPlans = computed(() => {
    const q = appliedSearchQuery.value.toLowerCase()
    const results = plansList.value.filter((p) =>
      p?.success_indicator?.toLowerCase().includes(q)
    )
    const start = (currentPage.value - 1) * perPage
    return results.slice(start, start + perPage)
  })

  const totalPages = computed(() =>
    Math.max(1, Math.ceil(plansList.value.filter((p) =>
      p?.success_indicator?.toLowerCase().includes(appliedSearchQuery.value.toLowerCase())
    ).length / perPage))
  )

  const applyFilters = () => {
    appliedSearchQuery.value = searchQuery.value
    currentPage.value = 1
  }

  const clearFilters = () => {
    searchQuery.value = ""
    appliedSearchQuery.value = ""
    currentPage.value = 1
  }

  // Rebuild tags from a saved plan for edit mode
  const buildInvolvedFromPlan = (plan) => {
    if (plan.office_involved === 'All Offices' || plan.office_involved === 'All') {
      return [{ type: 'all', label: 'All Offices' }]
    }
    const tags = []
    plan.offices?.forEach(o => tags.push({ type: 'office', id: o.id, label: o.name }))
    plan.committees?.forEach(c => tags.push({ type: 'committee', id: c.id, label: c.name }))
    plan.special_assignments?.forEach(a => tags.push({ type: 'assignment', id: a.id, label: a.name }))
    if (plan.free_text_involved) {
      plan.free_text_involved.split(', ').filter(Boolean).forEach(t =>
        tags.push({ type: 'text', label: t })
      )
    }
    return tags
  }

  const openModal = (mode, plan = null) => {
    modalMode.value = mode
    showModal.value = true
    tagSearch.value = ""

    if ((mode === "edit" || mode === "view") && plan) {
      selectedPlan.value = plan
      form.value = {
        id: plan.id,
        performance_indicator_id: plan.performance_indicator_id ?? "",
        success_indicator: plan.success_indicator ?? "",
        involved: buildInvolvedFromPlan(plan),
        rated_by: plan.rated_by ?? "",
      }
    } else {
      form.value = emptyForm()
      selectedPlan.value = null
    }
  }

  const closeModal = () => {
    showModal.value = false
    selectedPlan.value = null
    tagSearch.value = ""
  }

  // Tag management
  const addTag = (tag) => {
    if (tag.type === 'all') {
      form.value.involved = [{ type: 'all', label: 'All Offices' }]
      return
    }
    // Don't add duplicates
    const exists = form.value.involved.some(t => t.type === tag.type && (t.id ? t.id === tag.id : t.label === tag.label))
    if (exists) return
    // Remove 'all' if adding a specific tag
    form.value.involved = form.value.involved.filter(t => t.type !== 'all')
    form.value.involved.push(tag)
  }

  const removeTag = (index) => {
    form.value.involved.splice(index, 1)
  }

  const addFreeTextTag = () => {
    const text = tagSearch.value.trim()
    if (!text) return
    const exists = form.value.involved.some(t => t.type === 'text' && t.label === text)
    if (!exists) {
      form.value.involved.push({ type: 'text', label: text })
    }
    tagSearch.value = ""
  }

  // Build payload from involved tags
  const buildPayload = () => {
    const involved = form.value.involved
    const officeIds = involved.some(t => t.type === 'all')
      ? ['all']
      : involved.filter(t => t.type === 'office').map(t => t.id)
    const committeeIds = involved.filter(t => t.type === 'committee').map(t => t.id)
    const assignmentIds = involved.filter(t => t.type === 'assignment').map(t => t.id)
    const freeText = involved.filter(t => t.type === 'text').map(t => t.label).join(', ') || null

    return {
      performance_indicator_id: form.value.performance_indicator_id,
      success_indicator: form.value.success_indicator,
      rated_by: form.value.rated_by || null,
      office_ids: officeIds,
      committee_ids: committeeIds,
      assignment_ids: assignmentIds,
      free_text_involved: freeText,
    }
  }

  const submitPlan = () => {
    const url = "/work-distributions"
    const payload = buildPayload()

    const onError = (errors) => {
      Swal.fire("Error", Object.values(errors).flat().join('\n') || "Something went wrong.", "error")
    }

    if (modalMode.value === "create") {
      router.post(url, payload, {
        onSuccess: async () => {
          closeModal()
          await Swal.fire("Success", "WDP created successfully", "success")
          window.location.reload()
        },
        onError,
      })
    } else {
      router.put(`${url}/${form.value.id}`, payload, {
        onSuccess: async () => {
          closeModal()
          await Swal.fire("Updated", "WDP updated successfully", "success")
          window.location.reload()
        },
        onError,
      })
    }
  }

  const deletePlan = async (plan) => {
    const confirm = await Swal.fire({
      title: "Delete WDP?",
      text: "This action cannot be undone.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Delete",
    })

    if (confirm.isConfirmed) {
      router.delete(`/work-distribution-plans/${plan.id}`, {
        onSuccess: async () => {
          plansList.value = plansList.value.filter((p) => p.id !== plan.id)
          await Swal.fire("Deleted", "WDP deleted", "success")
          window.location.reload()
        },
      })
    }
  }

  return {
    plansList, form, showModal, modalMode, selectedPlan,
    searchQuery, currentPage, totalPages, filteredPlans,
    applyFilters, clearFilters,
    tagSearch,
    openModal, closeModal,
    addTag, removeTag, addFreeTextTag,
    submitPlan, deletePlan,
  }
}
