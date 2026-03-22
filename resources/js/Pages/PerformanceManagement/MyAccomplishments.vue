<script setup>
import { ref, computed } from "vue"
import { Head, router, usePage } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import Swal from "sweetalert2"
import axios from "axios"
import { useSubmit } from "@/Composables/useSubmit"

const props = defineProps({
  accomplishments: Array,
  ipcrPlans:       Array,
  months:          Array,
  selectedMonth:   String,
})

const page = usePage()
const { isSubmitting, submit } = useSubmit()

// ─── Month filter ────────────────────────────────────────────────────────────
const filterMonth = ref(props.selectedMonth ?? "")

function applyFilter() {
  router.get(route("my-accomplishments.index"), { month: filterMonth.value || undefined }, { preserveState: true })
}

// ─── Add / Edit modal ────────────────────────────────────────────────────────
const showModal    = ref(false)
const editingId    = ref(null)
const form         = ref({ ipcr_plan_id: "", accomplishment_date: "", description: "" })
const formErrors   = ref({})

function openAdd() {
  editingId.value  = null
  form.value       = { ipcr_plan_id: "", accomplishment_date: today(), description: "" }
  formErrors.value = {}
  showModal.value  = true
}

function openEdit(acc) {
  editingId.value  = acc.id
  form.value       = {
    ipcr_plan_id:        acc.ipcr_plan_id ?? "",
    accomplishment_date: acc.accomplishment_date,
    description:         acc.description,
  }
  formErrors.value = {}
  showModal.value  = true
}

function closeModal() { showModal.value = false }

function submitForm() {
  const opts = {
    onSuccess: () => { closeModal(); flash("Accomplishment saved.") },
    onError:   (e) => { formErrors.value = e },
  }

  if (editingId.value) {
    submit.put(route("my-accomplishments.update", editingId.value), form.value, opts)
  } else {
    submit.post(route("my-accomplishments.store"), form.value, opts)
  }
}

function deleteAccomplishment(id) {
  Swal.fire({
    title: "Delete accomplishment?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#dc2626",
    confirmButtonText: "Delete",
  }).then((r) => {
    if (r.isConfirmed) {
      submit.delete(route("my-accomplishments.destroy", id), {
        onSuccess: () => flash("Deleted."),
      })
    }
  })
}

// ─── Photo upload ────────────────────────────────────────────────────────────
const photoAccId     = ref(null)
const showPhotoModal = ref(false)
const photoFile      = ref(null)
const driveLink      = ref("")
const uploading      = ref(false)
const photoErrors    = ref({})

function openPhotoModal(acc) {
  photoAccId.value     = acc.id
  photoFile.value      = null
  driveLink.value      = ""
  photoErrors.value    = {}
  showPhotoModal.value = true
}

function closePhotoModal() { showPhotoModal.value = false }

async function submitPhoto() {
  if (!photoFile.value && !driveLink.value.trim()) {
    photoErrors.value = { proof: "Upload a file or paste a Drive link." }
    return
  }

  const fd = new FormData()
  if (photoFile.value)      fd.append("photo", photoFile.value)
  if (driveLink.value.trim()) fd.append("drive_link", driveLink.value.trim())

  uploading.value = true
  try {
    await axios.post(route("my-accomplishments.upload-photo", photoAccId.value), fd, {
      headers: { "X-CSRF-TOKEN": page.props.csrf_token ?? document.querySelector('meta[name="csrf-token"]')?.content },
    })
    closePhotoModal()
    router.reload({ only: ["accomplishments"] })
    flash("Proof added.")
  } catch (err) {
    const errors = err.response?.data?.errors
    if (errors) {
      photoErrors.value = Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]]))
    } else {
      Swal.fire("Error", err.response?.data?.message || "Upload failed.", "error")
    }
  } finally {
    uploading.value = false
  }
}

function proofUrl(photo) {
  if (photo.local_path) return "/storage/" + photo.local_path
  return photo.google_drive_link
}

function proofLabel(photo) {
  if (photo.local_path) return photo.file_name || "File"
  if (photo.file_name && photo.file_name !== "Drive Link") return photo.file_name
  return "Drive Link"
}

function proofIcon(photo) {
  if (photo.local_path) return "📄"
  return "🔗"
}

function deletePhoto(photoId) {
  Swal.fire({ title: "Remove photo?", icon: "warning", showCancelButton: true, confirmButtonColor: "#dc2626", confirmButtonText: "Remove" })
    .then((r) => {
      if (r.isConfirmed) {
        submit.delete(route("my-accomplishments.delete-photo", photoId), {
          onSuccess: () => flash("Photo removed."),
        })
      }
    })
}

// ─── Monthly report (print) ──────────────────────────────────────────────────
const reportMonth   = ref(filterMonth.value || currentMonth())
const reportData    = ref(null)
const showReport    = ref(false)
const loadingReport = ref(false)

async function loadReport() {
  loadingReport.value = true
  try {
    const { data } = await axios.get(route("my-accomplishments.monthly-report"), { params: { month: reportMonth.value } })
    reportData.value = data
    showReport.value = true
  } catch {
    Swal.fire("Error", "Could not load report.", "error")
  } finally {
    loadingReport.value = false
  }
}

function printReport() { window.print() }

// ─── Helpers ─────────────────────────────────────────────────────────────────
function today()        { return new Date().toISOString().slice(0, 10) }
function currentMonth() { return new Date().toISOString().slice(0, 7) }
function flash(msg)     { Swal.fire({ icon: "success", title: msg, timer: 1400, showConfirmButton: false }) }

function planLabel(acc) {
  if (!acc.ipcr_plan) return "—"
  return (acc.ipcr_plan.ipcr?.rating_period ?? "—") + " — " + (acc.ipcr_plan.plan?.success_indicator ?? "—")
}

const monthLabel = computed(() => {
  if (!filterMonth.value) return "All"
  const [y, m] = filterMonth.value.split("-")
  return new Date(y, m - 1).toLocaleString("default", { month: "long", year: "numeric" })
})
</script>

<template>
  <Head title="My Accomplishments" />
  <AdminLayout title="My Accomplishments">

    <!-- ── Toolbar ─────────────────────────────────────────────────────── -->
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3 print:hidden">
      <!-- Month filter -->
      <select v-model="filterMonth" @change="applyFilter"
        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
        <option value="">All months</option>
        <option v-for="m in months" :key="m" :value="m">{{ m }}</option>
      </select>

      <button @click="openAdd"
        class="ml-auto inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
        + Add Accomplishment
      </button>

      <!-- Monthly report -->
      <div class="flex items-center gap-2">
        <input v-model="reportMonth" type="month"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
        <button @click="loadReport" :disabled="loadingReport"
          class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
          {{ loadingReport ? "Loading…" : "Monthly Report" }}
        </button>
      </div>
    </div>

    <!-- ── Accomplishments table ────────────────────────────────────────── -->
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-x-auto print:hidden">
      <div v-if="!accomplishments.length" class="py-16 text-center text-slate-400 text-sm">
        No accomplishments found{{ filterMonth ? ` for ${monthLabel}` : "" }}.
      </div>
      <table v-else class="min-w-full text-sm divide-y divide-slate-100">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Description</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Linked IPCR Plan</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Photo Proofs</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="acc in accomplishments" :key="acc.id" class="hover:bg-slate-50/60">
            <td class="px-4 py-3 whitespace-nowrap font-medium text-slate-800 text-sm">
              {{ new Date(acc.accomplishment_date).toLocaleDateString("en-PH", { year:"numeric", month:"short", day:"numeric" }) }}
            </td>
            <td class="px-4 py-3 max-w-sm text-sm text-slate-700">
              <p class="whitespace-pre-wrap break-words">{{ acc.description }}</p>
            </td>
            <td class="px-4 py-3 text-slate-600 text-xs max-w-xs">
              {{ planLabel(acc) }}
            </td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap gap-1">
                <span v-for="photo in acc.photos" :key="photo.id" class="flex items-center gap-1">
                  <a v-if="proofUrl(photo)" :href="proofUrl(photo)" target="_blank"
                    class="text-xs text-indigo-600 hover:underline flex items-center gap-1">
                    {{ proofIcon(photo) }} {{ proofLabel(photo) }}
                  </a>
                  <button @click="deletePhoto(photo.id)"
                    class="text-red-400 hover:text-red-600 text-xs leading-none" title="Remove">✕</button>
                </span>
                <span v-if="!acc.photos.length" class="text-xs text-slate-400">—</span>
              </div>
              <button @click="openPhotoModal(acc)"
                class="mt-1 text-xs text-indigo-600 hover:underline">
                + Add proof
              </button>
            </td>
            <td class="px-4 py-3 text-center">
              <div class="flex justify-center gap-1">
                <button @click="openEdit(acc)" :disabled="isSubmitting"
                  class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors disabled:opacity-50" title="Edit">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <button @click="deleteAccomplishment(acc.id)" :disabled="isSubmitting"
                  class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors disabled:opacity-50" title="Delete">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Add / Edit Modal ─────────────────────────────────────────────── -->
    <Teleport to="body">
    <div v-if="showModal" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">{{ editingId ? "Edit" : "Add" }} Accomplishment</h2>
          <button @click="closeModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition-colors">✕</button>
        </div>

        <form @submit.prevent="submitForm">
          <div class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Date <span class="text-red-500">*</span></label>
              <input v-model="form.accomplishment_date" type="date" required
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p v-if="formErrors.accomplishment_date" class="text-red-500 text-xs mt-1">
                {{ formErrors.accomplishment_date }}
              </p>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Linked IPCR Plan</label>
              <select v-model="form.ipcr_plan_id"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="">— Not linked —</option>
                <option v-for="p in ipcrPlans" :key="p.id" :value="p.id">{{ p.label }}</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Description <span class="text-red-500">*</span></label>
              <textarea v-model="form.description" rows="4" required
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400"
                placeholder="Describe what you accomplished..."></textarea>
              <p v-if="formErrors.description" class="text-red-500 text-xs mt-1">{{ formErrors.description }}</p>
            </div>
          </div>

          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button type="button" @click="closeModal"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button type="submit" :disabled="isSubmitting"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
              {{ isSubmitting ? 'Saving…' : (editingId ? 'Update' : 'Save') }}
            </button>
          </div>
        </form>
      </div>
    </div>
    </Teleport>

    <!-- ── Proof Upload Modal ───────────────────────────────────────────── -->
    <Teleport to="body">
    <div v-if="showPhotoModal" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h2 class="text-base font-semibold text-slate-800">Add Proof</h2>
            <p class="text-xs text-slate-400 mt-0.5">You can upload a file, paste a link, or both at once.</p>
          </div>
          <button @click="closePhotoModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition-colors">✕</button>
        </div>

        <form @submit.prevent="submitPhoto">
          <div class="px-6 py-5 space-y-4">
            <p v-if="photoErrors.proof" class="text-red-500 text-xs">{{ photoErrors.proof }}</p>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Upload File <span class="text-slate-400 font-normal">(optional)</span></label>
              <input type="file" accept="image/*,.pdf"
                @change="photoFile = $event.target.files[0]"
                class="w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-sm" />
              <p class="text-xs text-slate-400 mt-1">JPG, PNG, GIF, PDF — max 10 MB.</p>
              <p v-if="photoErrors.photo" class="text-red-500 text-xs mt-1">{{ photoErrors.photo }}</p>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Google Drive / External Link <span class="text-slate-400 font-normal">(optional)</span></label>
              <input v-model="driveLink" type="url" placeholder="https://drive.google.com/…"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
              <p v-if="photoErrors.drive_link" class="text-red-500 text-xs mt-1">{{ photoErrors.drive_link }}</p>
            </div>
          </div>

          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button type="button" @click="closePhotoModal"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button type="submit" :disabled="uploading"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">
              {{ uploading ? "Uploading…" : "Add Proof" }}
            </button>
          </div>
        </form>
      </div>
    </div>
    </Teleport>

    <!-- ── Monthly Report Modal (screen only) ───────────────────────────── -->
    <Teleport to="body">
    <div v-if="showReport" class="fixed inset-0 bg-slate-900/60 flex items-start justify-center z-50 overflow-y-auto py-8">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl mx-4">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-800">Monthly Accomplishment Report</h2>
          <div class="flex gap-2">
            <button @click="printReport"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
              Print
            </button>
            <button @click="showReport = false"
              class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
              Close
            </button>
          </div>
        </div>

        <div class="px-6 py-5">
          <!-- Preview header -->
          <div class="text-center mb-4">
            <p class="font-semibold text-slate-700">
              {{ reportData?.employee?.name }} — {{ reportData?.employee?.position }}
            </p>
            <p class="text-sm text-slate-500 mt-0.5">
              {{ new Date(reportData?.month + "-01").toLocaleString("default", { month: "long", year: "numeric" }) }}
            </p>
          </div>

          <!-- Preview table -->
          <div class="overflow-x-auto">
          <table class="w-full text-sm border border-slate-200 min-w-[600px]">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-3 py-2 text-left border border-slate-200 w-28 text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</th>
                <th class="px-3 py-2 text-left border border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wide">Description</th>
                <th class="px-3 py-2 text-left border border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wide">Linked IPCR Plan</th>
                <th class="px-3 py-2 text-left border border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wide">Proof</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="acc in reportData?.accomplishments" :key="acc.id" class="border-t border-slate-100 hover:bg-slate-50/60">
                <td class="px-3 py-2 border border-slate-200 align-top whitespace-nowrap text-sm text-slate-700">
                  {{ new Date(acc.accomplishment_date).toLocaleDateString("en-PH", { month:"short", day:"numeric" }) }}
                </td>
                <td class="px-3 py-2 border border-slate-200 align-top whitespace-pre-wrap text-sm text-slate-700">{{ acc.description }}</td>
                <td class="px-3 py-2 border border-slate-200 align-top text-xs text-slate-600">
                  {{ planLabel(acc) }}
                </td>
                <td class="px-3 py-2 border border-slate-200 align-top">
                  <div v-for="photo in acc.photos" :key="photo.id">
                    <a v-if="proofUrl(photo)" :href="proofUrl(photo)" target="_blank"
                      class="text-indigo-600 hover:underline text-xs">
                      {{ proofLabel(photo) }}
                    </a>
                  </div>
                  <span v-if="!acc.photos?.length" class="text-xs text-slate-400">—</span>
                </td>
              </tr>
              <tr v-if="!reportData?.accomplishments?.length">
                <td colspan="4" class="px-3 py-6 text-center text-slate-400 border border-slate-200">
                  No accomplishments recorded for this month.
                </td>
              </tr>
            </tbody>
          </table>
          </div>

          <p class="text-xs text-slate-400 mt-3">
            Total: {{ reportData?.accomplishments?.length ?? 0 }} accomplishment(s)
          </p>
        </div>
      </div>
    </div>
    </Teleport>

  </AdminLayout>

  <!-- ── Print-only area (teleported outside #app for clean isolation) ── -->
  <Teleport to="body">
  <div v-if="reportData" id="print-area">
    <!--
      Outer wrapper table:
        <thead> → header image repeats at top of every page (browser-native, never cut)
        <tfoot> → footer image repeats at bottom of every page
        <tbody> → single cell holds all flowing content with 1in side margins
    -->
    <!-- Footer fixed to bottom of every page -->
    <div id="print-footer">
      <img src="/images/report_footer.jpeg" style="width:100%; display:block;" />
    </div>

    <table id="pt-wrap">
      <thead>
        <tr><td id="pt-head">
          <img src="/images/report_header.jpeg" style="width:100%; display:block;" />
        </td></tr>
      </thead>
      <tbody>
        <tr><td id="pt-body">

          <!-- Title -->
          <div style="text-align:center; margin:10px 0 10px;">
            <h2 style="font-size:14pt; font-weight:bold; margin:0;">MONTHLY ACCOMPLISHMENT REPORT</h2>
            <p style="margin:2px 0 0; font-size:9pt; color:#555;">
              {{ new Date(reportData?.month + "-01").toLocaleString("default", { month: "long", year: "numeric" }) }}
            </p>
          </div>

          <!-- Data table -->
          <table style="width:100%; border-collapse:collapse; font-size:9pt;">
            <thead>
              <tr style="background:#f3f4f6;">
                <th style="border:1px solid #999; padding:5px 8px; text-align:left; width:80px;">Date</th>
                <th style="border:1px solid #999; padding:5px 8px; text-align:left;">Description</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="acc in reportData?.accomplishments" :key="'p'+acc.id">
                <td style="border:1px solid #999; padding:5px 8px; vertical-align:top; white-space:nowrap;">
                  {{ new Date(acc.accomplishment_date).toLocaleDateString("en-PH", { month:"short", day:"numeric" }) }}
                </td>
                <td style="border:1px solid #999; padding:5px 8px; vertical-align:top; white-space:pre-wrap;">{{ acc.description }}</td>
              </tr>
              <tr v-if="!reportData?.accomplishments?.length">
                <td colspan="2" style="border:1px solid #999; padding:16px; text-align:center; color:#999;">
                  No accomplishments recorded for this month.
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Signatures -->
          <div style="display:flex; justify-content:space-between; margin-top:40px;">
            <div style="text-align:center;">
              <p style="margin:0 0 4px; font-size:9pt;">Prepared by:</p>
              <div style="margin-top:32px; border-top:1px solid #333; padding-top:4px; min-width:180px; font-size:9pt;">
                <strong>{{ reportData?.employee?.name }}</strong><br />
                <span>{{ reportData?.employee?.position }}</span>
              </div>
            </div>
            <div style="text-align:center;">
              <p style="margin:0 0 4px; font-size:9pt;">Noted by:</p>
              <div style="margin-top:32px; border-top:1px solid #333; padding-top:4px; min-width:180px; font-size:9pt;">
                <strong>{{ reportData?.immediate_head?.name ?? '________________________________' }}</strong><br />
                <span>{{ reportData?.immediate_head?.position ?? '' }}</span>
              </div>
            </div>
          </div>

        </td></tr>
      </tbody>
    </table>
  </div>
  </Teleport>

</template>

<style>
#print-area {
  display: none;
}

@page {
  /* Small top margin prevents header from being clipped by printer unprintable area.
     Bottom/sides are 0 so images reach the edge; side margins set via #pt-body padding. */
  margin: 0.25in 0 0 0;
}

@media print {
  /* Hide entire Inertia app; #print-area is teleported to <body> */
  #app {
    display: none !important;
  }

  #print-area {
    display: block !important;
  }

  /* Outer wrapper table fills the full page */
  #pt-wrap {
    width: 100%;
    border-collapse: collapse;
  }

  /* Footer: fixed to the bottom of every page */
  #print-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    z-index: 10;
  }

  /* Header cell: no padding so image reaches the page edge */
  #pt-head {
    padding: 0;
  }

  /* Content cell: 1-inch side margins; bottom padding clears the fixed footer */
  #pt-body {
    padding: 10px 1in 90px;  /* increase 90px if footer image is taller */
    vertical-align: top;
  }
}
</style>
