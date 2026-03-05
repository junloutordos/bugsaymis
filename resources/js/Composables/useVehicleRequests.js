// useVehicleRequests.js
import { ref, reactive, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

export function useVehicleRequests(initialRequests = [], vehicles = []) {
  // ── List / search / pagination ───────────────────────────────────────────
  const requestsList = ref([...initialRequests])
  const searchQuery  = ref('')
  const currentPage  = ref(1)
  const perPage      = 10

  const filteredRequestsAll = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    return requestsList.value.filter(req =>
      (req.purpose              || '').toLowerCase().includes(q) ||
      (req.vehicle_type         || '').toLowerCase().includes(q) ||
      (req.requester?.name      || '').toLowerCase().includes(q) ||
      (req.destination          || '').toLowerCase().includes(q) ||
      (req.status               || '').toLowerCase().includes(q) ||
      (req.driver?.name ?? req.driver_name ?? '').toLowerCase().includes(q) ||
      (req.date_needed          || '').toString().includes(q) ||
      (req.id                   ?? '').toString().includes(q)
    )
  })

  const filteredRequests = computed(() => {
    const start = (currentPage.value - 1) * perPage
    return filteredRequestsAll.value.slice(start, start + perPage)
  })

  const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredRequestsAll.value.length / perPage))
  )

  watch(searchQuery, () => { currentPage.value = 1 })

  // ── Banner ────────────────────────────────────────────────────────────────
  const banner = ref(null)

  const setBanner = (type, message, ms = 5000) => {
    banner.value = { type, message }
    if (ms > 0) setTimeout(() => { banner.value = null }, ms)
  }

  const sanitizeErrorMessage = (raw) => {
    if (!raw && raw !== '') return raw
    const s = String(raw || '').trim()
    const lower = s.toLowerCase()
    if (!s) return s
    if (
      lower.includes('trailing data') ||
      lower.includes('unexpected token') ||
      lower.includes('syntaxerror') ||
      lower.includes('json')
    ) return 'Conflict Detected'
    return s
  }

  // ── Assign Driver modal ───────────────────────────────────────────────────
  const showAssignDriverModal = ref(false)
  const assignDriverRequest   = ref(null)
  const drivers               = ref([])
  const selectedDriverId      = ref(null)
  const selectedVehicleId     = ref(null)
  const assignLoading         = ref(false)

  const openAssignDriverModal = async (req) => {
    assignDriverRequest.value = req
    showAssignDriverModal.value = true
    selectedDriverId.value = req.driver_id ?? req.driver?.id ?? null
    try {
      const v = vehicles?.find(v => (v.name ?? '') === (req.vehicle_type ?? ''))
      selectedVehicleId.value = v?.id ?? null
    } catch {
      selectedVehicleId.value = null
    }
    assignLoading.value = true
    try {
      const res = await fetch('/api/drivers')
      drivers.value = await res.json()
    } catch (e) {
      console.error('Failed to load drivers', e)
      setBanner('error', 'Failed to load drivers')
      drivers.value = []
    }
    assignLoading.value = false
  }

  const closeAssignDriverModal = () => {
    showAssignDriverModal.value = false
    assignDriverRequest.value   = null
    selectedDriverId.value      = null
    drivers.value               = []
  }

  const assignDriver = async () => {
    if (!selectedDriverId.value || !assignDriverRequest.value) return
    assignLoading.value = true
    try {
      const url     = `/vehicle-requests/${assignDriverRequest.value.id}/assign-driver`
      const payload = { driver_id: selectedDriverId.value }
      if (selectedVehicleId.value) payload.vehicle_id = selectedVehicleId.value

      if (window.axios && typeof window.axios.post === 'function') {
        await window.axios.post(url, payload)
      } else {
        const resp = await fetch(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
          credentials: 'same-origin',
        })
        if (!resp.ok) {
          const data = await resp.json().catch(() => null)
          throw { response: { data, status: resp.status } }
        }
      }

      setBanner('success', 'Driver assigned successfully')
      closeAssignDriverModal()
      window.location.reload()
    } catch (e) {
      console.error('Failed to assign driver', e)
      const srv    = e?.response?.data ?? (e?.response ?? null)
      const errMsg = e?.message ? String(e.message) : ''

      if (
        errMsg &&
        (errMsg.toLowerCase().includes('trailing data') ||
          errMsg.toLowerCase().includes('unexpected token') ||
          errMsg.toLowerCase().includes('syntaxerror') ||
          (e?.response?.status === 422 && !srv))
      ) {
        alert(sanitizeErrorMessage(errMsg))
      } else if (srv?.type && srv?.message) {
        const kind  = srv.type === 'vehicle' ? 'Vehicle conflict' : srv.type === 'driver' ? 'Driver conflict' : 'Conflict'
        const dates = Array.isArray(srv.dates) && srv.dates.length ? ` Dates: ${srv.dates.join(', ')}` : ''
        alert(`${kind}: ${sanitizeErrorMessage(srv.message)}${dates}`)
      } else if (srv?.message) {
        alert(sanitizeErrorMessage(srv.message))
      } else {
        alert(sanitizeErrorMessage(errMsg) || 'Failed to assign driver')
      }
    }
    assignLoading.value = false
  }

  // ── Calendar modal ────────────────────────────────────────────────────────
  const showCalendar  = ref(false)
  const calendarMonth = ref(new Date())
  const bookings      = ref([])

  const monthLabel = computed(() =>
    calendarMonth.value.toLocaleString(undefined, { month: 'long', year: 'numeric' })
  )

  const fetchBookings = async () => {
    try {
      const res = await fetch('/vehicle-bookings')
      const data = await res.json()
      bookings.value = Array.isArray(data) ? data : []
    } catch (e) {
      console.error('Failed to load bookings', e)
      bookings.value = []
    }
  }

  const openCalendar = async () => {
    await fetchBookings()
    showCalendar.value = true
  }

  const prevMonth = () => {
    const d = calendarMonth.value
    calendarMonth.value = new Date(d.getFullYear(), d.getMonth() - 1, 1)
  }

  const nextMonth = () => {
    const d = calendarMonth.value
    calendarMonth.value = new Date(d.getFullYear(), d.getMonth() + 1, 1)
  }

  const monthDays = computed(() => {
    const d     = calendarMonth.value
    const year  = d.getFullYear()
    const month = d.getMonth()
    const days  = []
    const firstWeekday = new Date(year, month, 1).getDay()
    for (let i = 0; i < firstWeekday; i++) days.push(null)
    const last = new Date(year, month + 1, 0).getDate()
    for (let i = 1; i <= last; i++) days.push(new Date(year, month, i))
    while (days.length % 7 !== 0) days.push(null)
    return days
  })

  const pad2 = (n) => (n < 10 ? '0' + n : String(n))
  const formatYMD = (d) => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`

  const bookingsForDate = (dt) => {
    if (!dt) return []
    const key = formatYMD(dt)
    return bookings.value.filter(b => (b.date || '').toString().slice(0, 10) === key)
  }

  // ── Form + validation ─────────────────────────────────────────────────────
  const form = useForm({
    purpose:            '',
    destination:        '',
    date_needed:        [],
    time_of_departure:  '',
    eta:                '',
    vehicle_type:       '',
    division_chief_id:  '',
    passengers:         1,
    status:             '',
  })

  const fieldErrors = reactive({
    purpose:           '',
    destination:       '',
    date_needed:       '',
    time_of_departure: '',
    eta:               '',
    vehicle_type:      '',
    division_chief_id: '',
    passengers:        '',
  })

  const validateField = (field) => {
    switch (field) {
      case 'purpose':
        fieldErrors.purpose = form.purpose?.trim() ? '' : 'Purpose is required'; break
      case 'destination':
        fieldErrors.destination = form.destination?.trim() ? '' : 'Destination is required'; break
      case 'date_needed':
        fieldErrors.date_needed = Array.isArray(form.date_needed) && form.date_needed.length ? '' : 'Add at least one date'; break
      case 'time_of_departure':
        fieldErrors.time_of_departure = form.time_of_departure ? '' : 'Time of departure is required'; break
      case 'eta':
        fieldErrors.eta = form.eta ? '' : 'Estimated time of arrival is required'; break
      case 'vehicle_type':
        fieldErrors.vehicle_type = form.vehicle_type ? '' : 'Select a vehicle type'; break
      case 'division_chief_id':
        fieldErrors.division_chief_id = form.division_chief_id ? '' : 'Select a division chief'; break
      case 'passengers':
        fieldErrors.passengers = form.passengers && Number(form.passengers) >= 1 ? '' : 'Passengers must be at least 1'; break
    }
  }

  const validateAll = () => {
    const fields = ['purpose','destination','date_needed','time_of_departure','eta','vehicle_type','division_chief_id','passengers']
    fields.forEach(f => validateField(f))
    return !Object.values(fieldErrors).some(v => v?.length > 0)
  }

  const dateInput = ref('')
  const addDate = () => {
    if (!dateInput.value) return
    if (!form.date_needed.includes(dateInput.value)) form.date_needed.push(dateInput.value)
    dateInput.value = ''
  }

  // ── Modal (create / edit) ─────────────────────────────────────────────────
  const showModal       = ref(false)
  const editingRequest  = ref(null)

  const openModal = (req = null) => {
    editingRequest.value = req
    if (req) {
      form.reset()
      form.purpose           = req.purpose
      form.destination       = req.destination
      form.date_needed       = req.date_needed_multiple ?? (req.date_needed ? [req.date_needed] : [])
      form.time_of_departure = req.time_of_departure ?? ''
      form.eta               = req.eta ?? ''
      form.vehicle_type      = req.vehicle_type ?? ''
      form.passengers        = req.passengers ?? 1
      form.division_chief_id = req.division_chief_id ?? ''
      form.status            = req.status ?? ''
    } else {
      form.reset()
      form.status = ''
    }
    showModal.value = true
  }

  const closeModal = () => {
    showModal.value      = false
    editingRequest.value = null
    form.reset()
  }

  const submit = () => {
    if (!validateAll()) return
    try {
      if (editingRequest.value) {
        const resolvedId = editingRequest.value?.id
        if (!resolvedId) { alert('Cannot determine request id for update.'); return }
        let updateUrl
        try { updateUrl = route('vehicle-requests.update', resolvedId) }
        catch { updateUrl = `/vehicle-requests/${resolvedId}` }
        form.put(updateUrl, {
          onSuccess: () => {
            closeModal()
            Swal.fire({ icon: 'success', title: 'Request updated', timer: 1200, showConfirmButton: false })
              .then(() => window.location.reload())
          },
          onError: (errs) => {
            const msg = Object.values(errs || {}).flat().join('\n') || 'Failed to update request'
            Swal.fire({ icon: 'error', title: 'Failed to update', text: msg })
          },
        })
      } else {
        const storeUrl = route('vehicle-requests.store')
        form.post(storeUrl, {
          onSuccess: () => {
            closeModal()
            Swal.fire({ icon: 'success', title: 'Request created', timer: 1200, showConfirmButton: false })
              .then(() => window.location.reload())
          },
          onError: (errs) => {
            const msg = Object.values(errs || {}).flat().join('\n') || 'Failed to create request'
            Swal.fire({ icon: 'error', title: 'Failed to create', text: msg })
          },
        })
      }
    } catch (e) {
      console.error('Submit failed', e)
      alert('Unexpected error. See console.')
    }
  }

  // ── Delete ────────────────────────────────────────────────────────────────
  const destroy = (req) => {
    Swal.fire({
      title: 'Delete this vehicle request?',
      text: 'This action cannot be undone.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete',
    }).then((res) => {
      if (!res.isConfirmed) return
      import('@inertiajs/vue3').then(({ router }) => {
        let url
        try { url = route('vehicle-requests.destroy', req.id) }
        catch { url = `/vehicle-requests/${req.id}` }
        router.delete(url, {
          onSuccess: () => {
            Swal.fire({ icon: 'success', title: 'Deleted', timer: 1000, showConfirmButton: false })
              .then(() => window.location.reload())
          },
          onError: () => { Swal.fire({ icon: 'error', title: 'Failed to delete' }) },
        })
      })
    })
  }

  // ── Approve / Decline ─────────────────────────────────────────────────────
  const approveRequest = (req) => {
    Swal.fire({ title: 'Approve this vehicle request?', icon: 'question', showCancelButton: true, confirmButtonText: 'Approve' })
      .then((res) => {
        if (!res.isConfirmed) return
        import('@inertiajs/vue3').then(({ router }) => {
          let url
          try { url = route('vehicle-requests.approve.inapp', req.id) }
          catch { url = `/vehicle-requests/${req.id}/approve` }
          router.post(url, {}, {
            onSuccess: () => {
              Swal.fire({ icon: 'success', title: 'Approved', timer: 1000, showConfirmButton: false })
                .then(() => window.location.reload())
            },
            onError: () => { Swal.fire({ icon: 'error', title: 'Failed to approve' }) },
          })
        })
      })
  }

  const declineRequest = (req) => {
    Swal.fire({ title: 'Reason for declining', input: 'text', inputPlaceholder: 'Enter reason', showCancelButton: true })
      .then((res) => {
        if (!res.isConfirmed || !res.value) return
        import('@inertiajs/vue3').then(({ router }) => {
          let url
          try { url = route('vehicle-requests.decline.inapp', req.id) }
          catch { url = `/vehicle-requests/${req.id}/decline` }
          router.post(url, { reason: res.value }, {
            onSuccess: () => {
              Swal.fire({ icon: 'success', title: 'Declined', timer: 1000, showConfirmButton: false })
                .then(() => window.location.reload())
            },
            onError: () => { Swal.fire({ icon: 'error', title: 'Failed to decline' }) },
          })
        })
      })
  }

  // ── Print ─────────────────────────────────────────────────────────────────
  const openPrint = (req) => {
    let url
    try { url = route('vehicle-requests.print', req.id) }
    catch { url = `/vehicle-requests/${req.id}/print` }
    window.open(url, '_blank')
  }

  return {
    // list
    requestsList, searchQuery, currentPage, perPage,
    filteredRequests, filteredRequestsAll, totalPages,
    // banner
    banner, setBanner,
    // assign driver
    showAssignDriverModal, assignDriverRequest, drivers,
    selectedDriverId, selectedVehicleId, assignLoading,
    openAssignDriverModal, closeAssignDriverModal, assignDriver,
    // calendar
    showCalendar, calendarMonth, bookings, monthLabel,
    fetchBookings, openCalendar, prevMonth, nextMonth,
    monthDays, bookingsForDate,
    // form
    form, fieldErrors, dateInput,
    validateField, addDate,
    // modal
    showModal, editingRequest,
    openModal, closeModal, submit,
    // actions
    destroy, approveRequest, declineRequest, openPrint,
  }
}
