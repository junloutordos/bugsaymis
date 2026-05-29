// useJobRequests.js
import { ref, computed, watch } from "vue"
import { useForm, router } from "@inertiajs/vue3"
import Swal from "sweetalert2"

export function useJobRequests(initialRequests = []) {
  // Requests data
  const requestsList = ref([...initialRequests])

  // Modal state
  const showModal = ref(false)
  const modalMode = ref("create") // create | view | update | mis-assessment
  const selectedRequest = ref(null)

  // Search & pagination
  const searchQuery = ref("")
  const currentPage = ref(1)
  const perPage = 10

  // Sorting
  const sortKey = ref("id")
  const sortAsc = ref(false) // default: latest first

  const sortedRequests = computed(() => {
    return [...requestsList.value].sort((a, b) => {
      const valA = a[sortKey.value] ?? ""
      const valB = b[sortKey.value] ?? ""
      if (valA < valB) return sortAsc.value ? -1 : 1
      if (valA > valB) return sortAsc.value ? 1 : -1
      return 0
    })
  })

  const filteredRequestsAll = computed(() => {
    const q = searchQuery.value.toLowerCase()
    return sortedRequests.value.filter((req) =>
      (req.title || '').toLowerCase().includes(q) ||
      (req.category || '').toLowerCase().includes(q) ||
      (req.user?.name ?? '').toLowerCase().includes(q) ||
      (req.description || '').toLowerCase().includes(q) ||
      (req.status || '').toLowerCase().includes(q) ||
      (req.itjr_no || req.id || '').toString().toLowerCase().includes(q) ||
      (req.assignedto || '').toLowerCase().includes(q) ||
      (req.assigned_user?.name || '').toLowerCase().includes(q) ||
      (req.action_taken || '').toLowerCase().includes(q)
    )
  })

  const filteredRequests = computed(() => {
    const start = (currentPage.value - 1) * perPage
    return filteredRequestsAll.value.slice(start, start + perPage)
  })

  const totalPages = computed(() =>
    Math.ceil(filteredRequestsAll.value.length / perPage)
  )

  watch(searchQuery, () => { currentPage.value = 1 })

  // Form (shared for all stages)
  const form = useForm({
    id: null,
    title: "",
    category: "",
    event_date: "",
    posting_type: null,   // only for Posting to Website / Posting to Social Media
    description: "",
    priority: "normal",
    mis_assessment: "",
    recommendation: "",
    expected_completion_date: "",
    action_taken: "",
    completed_at: "",
    ict_equipment_id:"",
    pin: null,
  })

  // Open modal
  const openModal = (mode = "create", req = null) => {
    modalMode.value = mode
    selectedRequest.value = req

    if (req) {
      Object.assign(form, {
        id: req.id,
        title: req.title || "",
        category: req.category || "",
        event_date: req.event_date || "",
        description: req.description || "",
        posting_type: req.posting_type || null,
        mis_assessment: req.mis_assessment || "",
        recommendation: req.recommendation || "",
        expected_completion_date: req.expected_completion_date || "",
        action_taken: req.action_taken || "",
        completed_at: req.completed_at || "",
        ict_equipment_id: req.ict_equipment_id ||"",
      })
    } else {
      form.reset()
    }

    showModal.value = true
  }

  const closeModal = () => {
    showModal.value = false
    form.reset()
  }

  const showLoadingSwal = (title = 'Saving...') => {
    Swal.fire({
      title,
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: () => { Swal.showLoading() },
    })
  }

  // Submit form
  const submitRequest = async () => {
    if (modalMode.value === "create") {
      // Layer 2: pre-submit JS guard for 3-day rule (catches manually typed dates)
      if (form.category === 'Technical Assistance on Events') {
        if (!form.event_date) {
          await Swal.fire('Missing Event Date', 'Please provide the date of the event.', 'warning')
          return
        }
        const today    = new Date(); today.setHours(0, 0, 0, 0)
        const minDate  = new Date(today); minDate.setDate(today.getDate() + 3)
        const [y, m, d] = form.event_date.split('-').map(Number)
        const picked   = new Date(y, m - 1, d)
        if (picked < minDate) {
          await Swal.fire(
            'Filing Not Allowed',
            'Filing a Technical Assistance request less than 3 days before the event is not allowed. Please coordinate directly with MIS.',
            'error'
          )
          return
        }
      }

      showLoadingSwal('Creating request...')
      form.post(route("jobrequests.store"), {
        preserveScroll: true,
        onSuccess: async () => {
          closeModal()
          await Swal.fire("Success", "IT Job Request has been created!", "success")
          window.location.reload()
        },
        onError: async () => {
          await Swal.fire("Error", "Please fill all required fields.", "error")
        },
      })
    }

    if (modalMode.value === "update" && form.id) {
      showLoadingSwal('Updating request...')
      form.put(route("job-requests.update", form.id), {
        preserveScroll: true,
        onSuccess: async () => {
          closeModal()
          await Swal.fire("Updated", "IT Job Request updated successfully!", "success")
          window.location.reload()
        },
        onError: async () => {
          await Swal.fire("Error", "Failed to update request. Please try again.", "error")
        },
      })
    }

    if (modalMode.value === "mis-assessment" && form.id) {
      showLoadingSwal('Saving assessment...')
      form.put(`/job-requests/${form.id}/update`, {
        preserveScroll: true,
        onSuccess: async () => {
          Swal.close()
          closeModal()
          await Swal.fire("Success", "MIS Assessment saved!", "success")
          window.location.reload()
        },
        onError: async (errors) => {
          Swal.close()
          const messages = Object.values(errors).flat().join('\n')
          await Swal.fire("Error", messages || "Failed to save assessment. Please try again.", "error")
        },
        onFinish: () => {
          if (form.processing === false) Swal.close()
        },
      })
    }
  }

  // Helpers
  const viewRequest = (req) => openModal("view", req)
  const updateRequest = (req) => openModal("update", req)
  const misAssessment = (req) => openModal("mis-assessment", req)

  const deleteRequest = async (req) => {
    const result = await Swal.fire({
      title: "Are you sure?",
      text: `Delete request #${req.id}?`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, delete it!",
    })
    if (result.isConfirmed) {
      // Send delete to backend
      router.delete(route("jobrequests.destroy", req.id), {
        onSuccess: () => {
          requestsList.value = requestsList.value.filter((r) => r.id !== req.id)
          Swal.fire("Deleted!", "The request has been deleted.", "success")
        }
      })
    }
  }

  const formatDate = (dateString) => {
    if (!dateString) return "—"
    return new Date(dateString).toLocaleDateString("en-US", {
      month: "long",
      day: "numeric",
      year: "numeric",
    })
  }

  const sortBy = (key) => {
    if (sortKey.value === key) {
      sortAsc.value = !sortAsc.value
    } else {
      sortKey.value = key
      sortAsc.value = true
    }
  }

  const exportCSV = () => {
    const headers = ["ITJR #", "Title", "Category", "Submitted By", "Date Filed", "Status"]
    const rows = filteredRequestsAll.value.map((req) => [
      req.id,
      req.title,
      req.category,
      req.user?.name ?? "—",
      formatDate(req.created_at),
      req.status,
    ])

    let csvContent = "data:text/csv;charset=utf-8,"
    csvContent += headers.join(",") + "\n"
    rows.forEach((row) => {
      csvContent += row.map((f) => `"${String(f).replace(/"/g, '""')}"`).join(",") + "\n"
    })

    const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" })
    const link = document.createElement("a")
    link.href = URL.createObjectURL(blob)
    link.download = "it_job_requests.csv"
    link.click()
  }

  const printTable = () => {
    const table = document.querySelector("#requestsTable")
    if (!table) {
      Swal.fire("Error", "No table found to print!", "error")
      return
    }
    const tableContent = table.outerHTML
    const printWindow = window.open("", "", "width=1366,height=1000")
    printWindow.document.write(`
      <html>
        <head>
          <title>Print IT Job Requests</title>
          <style>
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
            th { background: #f9f9f9; }
          </style>
        </head>
        <body>
          <h2>IT Job Requests</h2>
          ${tableContent}
        </body>
      </html>
    `)
    printWindow.document.close()
    printWindow.print()
  }

  return {
    // state
    requestsList,
    showModal,
    modalMode,
    selectedRequest,
    searchQuery,
    currentPage,
    perPage,
    sortKey,
    sortAsc,
    form,

    // computed
    filteredRequests,
    filteredRequestsAll,
    totalPages,

    // actions
    openModal,
    closeModal,
    submitRequest,
    viewRequest,
    updateRequest,
    misAssessment,
    deleteRequest,
    sortBy,
    exportCSV,
    printTable,

    // utils
    formatDate,
  }
}
