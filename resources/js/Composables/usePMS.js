import { ref, reactive, computed, watch } from "vue"
import { router } from "@inertiajs/vue3"
import Swal from "sweetalert2"

export default function usePMS(initialSchedules = []) {
  const schedules = ref(initialSchedules)
  const errors = ref({})
  const showModal = ref(false)
  const modalMode = ref("create")
  const selectedSchedule = ref(null)
  const showPinModal = ref(false)
  const pinModalLoading = ref(false)

  // Form (added school_year & office_area)
  const form = reactive({
    title: "",
    frequency: "",
    schedule_dates: [],
    status: "Pending",
    remarks: "",
    school_year: "",
    office_area: "",
    pin: null,
  })

  const scheduleDates = ref([])

  // Update dates based on frequency
  watch(
    () => form.frequency,
    (newFreq) => {
      scheduleDates.value = []
      if (!newFreq) return
      let count = { Monthly: 12, Quarterly: 4, "Bi-Annual": 2, Annually: 1 }[newFreq] || 1
      for (let i = 0; i < count; i++) {
        scheduleDates.value.push({ id: i, date: "" })
      }
    },
    { immediate: true }
  )

  const formatDateForInput = (d) => {
    if (!d) return ""
    const date = new Date(d)
    return isNaN(date) ? "" : date.toISOString().split("T")[0]
  }

  const formatDateForDisplay = (d) => {
    if (!d) return "—"
    const date = new Date(d)
    return isNaN(date)
      ? d
      : date.toLocaleDateString("en-US", { month: "long", day: "numeric", year: "numeric" })
  }

  // Table State
  const searchQuery = ref("")
  const currentPage = ref(1)
  const perPage = ref(10)
  const sortKey = ref("id")
  const sortAsc = ref(true)

  const sortedSchedules = computed(() => {
    return [...schedules.value].sort((a, b) => {
      let valA = a[sortKey.value] ?? ""
      let valB = b[sortKey.value] ?? ""
      if (valA < valB) return sortAsc.value ? -1 : 1
      if (valA > valB) return sortAsc.value ? 1 : -1
      return 0
    })
  })

  const filteredSchedules = computed(() => {
    const q = searchQuery.value.toLowerCase()
    return sortedSchedules.value
      .filter((s) => Object.values(s).some((v) => String(v).toLowerCase().includes(q)))
      .slice((currentPage.value - 1) * perPage.value, currentPage.value * perPage.value)
  })

  const totalPages = computed(() =>
    Math.ceil(
      sortedSchedules.value.filter((s) =>
        Object.values(s).some((v) => String(v).toLowerCase().includes(searchQuery.value.toLowerCase()))
      ).length / perPage.value
    ) || 1
  )

  watch(searchQuery, () => { currentPage.value = 1 })

  // CRUD
  const getSchedules = () => {
    router.get(route("ict-pms.index"), {}, {
      preserveState: true,
      replace: true,
      onSuccess: (page) => (schedules.value = page.props.pmsSchedules),
    })
  }

  const showLoadingSwal = (title) => {
    Swal.fire({ title, allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => { Swal.showLoading() } })
  }

  const storeSchedule = (pin = null) => {
    form.schedule_dates = scheduleDates.value.map((d) => ({ date: d.date }))
    form.pin = pin
    showLoadingSwal('Saving schedule...')
    router.post(route("ict-pms.store"), form, {
      onError: (e) => { errors.value = e; showPinModal.value = false },
      onSuccess: () => {
        showPinModal.value = false
        closeModal()
        Swal.fire({ icon: "success", title: "Schedule Added", timer: 2000, showConfirmButton: false })
      },
      onFinish: () => {
        pinModalLoading.value = false
        if (Swal.isLoading()) Swal.close()
      },
    })
  }

  const updateSchedule = (id) => {
    form.schedule_dates = scheduleDates.value.map((d) => ({ date: d.date }))
    showLoadingSwal('Updating schedule...')
    router.put(route("ict-pms.update", id), form, {
      onError: (e) => { errors.value = e },
      onSuccess: () => {
        closeModal()
        Swal.fire({ icon: "success", title: "Schedule Updated", timer: 2000, showConfirmButton: false })
      },
      onFinish: () => {
        if (Swal.isLoading()) Swal.close()
      },
    })
  }

  const destroySchedule = (id) => {
    Swal.fire({
      title: "Are you sure?",
      text: "This schedule will be permanently deleted!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#dc2626",
      cancelButtonColor: "#3085d6",
      confirmButtonText: "Yes, delete it!",
    }).then((result) => {
      if (result.isConfirmed) {
        router.delete(route("ict-pms.destroy", id), {
          onSuccess: () => {
            Swal.fire({ icon: "success", title: "Deleted", timer: 2000, showConfirmButton: false })
          },
        })
      }
    })
  }

  // Modal Handling
  const openModal = (mode, schedule = null) => {
    modalMode.value = mode
    selectedSchedule.value = schedule
    errors.value = {}

    if (mode === "edit" && schedule) {
      form.title = schedule.title || ""
      form.frequency = schedule.frequency || ""
      form.status = schedule.status || "Pending"
      form.remarks = schedule.remarks || ""
      form.school_year = schedule.school_year || ""
      form.office_area = schedule.office_area || ""
      scheduleDates.value = (schedule.schedule_dates || []).map((d, i) => ({
        id: i,
        date: formatDateForInput(d.date || d),
      }))
    } else if (mode === "create") {
      form.title = ""
      form.frequency = ""
      form.status = "Pending"
      form.remarks = ""
      form.school_year = ""
      form.office_area = ""
      scheduleDates.value = []
    }

    showModal.value = true
  }

  const closeModal = () => {
    showModal.value = false
    selectedSchedule.value = null
  }

  // Mirror of the backend `store`/`update` required rules — catches incomplete
  // forms before the PIN modal opens instead of round-tripping to a 422.
  const validateForm = () => {
    const e = {}
    if (!form.title.trim()) e.title = "Title is required."
    if (!form.school_year.trim()) e.school_year = "School year is required."
    if (!form.office_area.trim()) e.office_area = "Office/Area is required."
    if (!form.frequency) e.frequency = "Frequency is required."
    if (!scheduleDates.value.length || scheduleDates.value.some((d) => !d.date)) {
      e.schedule_dates = "Fill in every scheduled date."
    }
    errors.value = e
    return Object.keys(e).length === 0
  }

  const submitSchedule = () => {
    if (!validateForm()) return
    if (modalMode.value === "create") showPinModal.value = true
    else if (modalMode.value === "edit" && selectedSchedule.value) updateSchedule(selectedSchedule.value.id)
  }

  const confirmPinAndStore = (pin) => {
    pinModalLoading.value = true
    storeSchedule(pin)
  }

  const cancelPinModal = () => {
    showPinModal.value = false
  }

  const viewSchedule = (s) => openModal("view", s)

  // Export & Print (added school_year & office_area)
  const exportCSV = () => {
    const csv = schedules.value.map((s) =>
      `${s.id},"${s.title}",${s.frequency},"${(s.schedule_dates || []).map((d) => d.date || d).join(" | ")}",${s.status},${s.remarks},"${s.school_year}","${s.office_area}"`
    )
    const blob = new Blob(
      [["ID,Title,Frequency,Dates,Status,Remarks,School Year,Office/Area\n", ...csv].join("\n")],
      { type: "text/csv" }
    )
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement("a")
    a.href = url
    a.download = "pms.csv"
    a.click()
  }

  const printTable = () => {
    const w = window.open("", "_blank")
    let html = `<h3>Preventive Maintenance Schedule</h3>
      <table border="1" cellspacing="0" cellpadding="5">
      <tr><th>ID</th><th>Title</th><th>Frequency</th><th>Dates</th><th>Status</th><th>Remarks</th><th>School Year</th><th>Office/Area</th></tr>`
    schedules.value.forEach((s) => {
      html += `<tr>
        <td>${s.id}</td>
        <td>${s.title}</td>
        <td>${s.frequency}</td>
        <td>${(s.schedule_dates || []).map((d) => d.date || d).join(", ")}</td>
        <td>${s.status}</td>
        <td>${s.remarks}</td>
        <td>${s.school_year || ""}</td>
        <td>${s.office_area || ""}</td>
      </tr>`
    })
    html += "</table>"
    w.document.write(html)
    w.print()
    w.close()
  }

  return {
    schedules,
    errors,
    showModal,
    modalMode,
    selectedSchedule,
    form,
    scheduleDates,
    searchQuery,
    currentPage,
    totalPages,
    filteredSchedules,
    getSchedules,
    storeSchedule,
    updateSchedule,
    destroySchedule,
    openModal,
    closeModal,
    submitSchedule,
    showPinModal,
    pinModalLoading,
    confirmPinAndStore,
    cancelPinModal,
    viewSchedule,
    exportCSV,
    printTable,
    formatDateForDisplay,
    formatDateForInput,
    sortBy: (key) => {
      if (sortKey.value === key) sortAsc.value = !sortAsc.value
      else {
        sortKey.value = key
        sortAsc.value = true
      }
    },
  }
}
