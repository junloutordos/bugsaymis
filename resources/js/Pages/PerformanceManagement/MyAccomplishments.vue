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
    <div class="flex flex-wrap items-center gap-3 mb-4 print:hidden">
      <!-- Month filter -->
      <select v-model="filterMonth" @change="applyFilter"
        class="rounded-lg border-gray-300 text-sm">
        <option value="">All months</option>
        <option v-for="m in months" :key="m" :value="m">{{ m }}</option>
      </select>

      <button @click="openAdd"
        class="ml-auto px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
        + Add Accomplishment
      </button>

      <!-- Monthly report -->
      <div class="flex items-center gap-2">
        <input v-model="reportMonth" type="month"
          class="rounded-lg border-gray-300 text-sm" />
        <button @click="loadReport" :disabled="loadingReport"
          class="px-3 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 disabled:opacity-50">
          {{ loadingReport ? "Loading…" : "Monthly Report" }}
        </button>
      </div>
    </div>

    <!-- ── Accomplishments table ────────────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow overflow-x-auto print:hidden">
      <div v-if="!accomplishments.length" class="p-8 text-center text-gray-400 text-sm">
        No accomplishments found{{ filterMonth ? ` for ${monthLabel}` : "" }}.
      </div>
      <table v-else class="min-w-full text-sm divide-y divide-gray-200">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
          <tr>
            <th class="px-4 py-3 text-left">Date</th>
            <th class="px-4 py-3 text-left">Description</th>
            <th class="px-4 py-3 text-left">Linked IPCR Plan</th>
            <th class="px-4 py-3 text-left">Photo Proofs</th>
            <th class="px-4 py-3 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="acc in accomplishments" :key="acc.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-800">
              {{ new Date(acc.accomplishment_date).toLocaleDateString("en-PH", { year:"numeric", month:"short", day:"numeric" }) }}
            </td>
            <td class="px-4 py-3 max-w-sm">
              <p class="whitespace-pre-wrap break-words">{{ acc.description }}</p>
            </td>
            <td class="px-4 py-3 text-gray-600 text-xs max-w-xs">
              {{ planLabel(acc) }}
            </td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap gap-1">
                <span v-for="photo in acc.photos" :key="photo.id" class="flex items-center gap-1">
                  <a v-if="proofUrl(photo)" :href="proofUrl(photo)" target="_blank"
                    class="text-xs text-blue-600 hover:underline flex items-center gap-1">
                    {{ proofIcon(photo) }} {{ proofLabel(photo) }}
                  </a>
                  <button @click="deletePhoto(photo.id)"
                    class="text-red-400 hover:text-red-600 text-xs leading-none" title="Remove">✕</button>
                </span>
                <span v-if="!acc.photos.length" class="text-xs text-gray-400">—</span>
              </div>
              <button @click="openPhotoModal(acc)"
                class="mt-1 text-xs text-indigo-600 hover:underline">
                + Add proof
              </button>
            </td>
            <td class="px-4 py-3 text-center">
              <div class="flex justify-center gap-2">
                <button @click="openEdit(acc)" :disabled="isSubmitting"
                  class="px-2 py-1 text-xs bg-yellow-500 text-white rounded hover:bg-yellow-600 disabled:opacity-50 disabled:cursor-not-allowed">
                  Edit
                </button>
                <button @click="deleteAccomplishment(acc.id)" :disabled="isSubmitting"
                  class="px-2 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
                  Delete
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Add / Edit Modal ─────────────────────────────────────────────── -->
    <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 relative">
        <button @click="closeModal" class="absolute top-3 right-4 text-gray-400 hover:text-gray-700 text-xl">✕</button>
        <h2 class="text-lg font-semibold mb-4">{{ editingId ? "Edit" : "Add" }} Accomplishment</h2>

        <form @submit.prevent="submitForm" class="space-y-4">
          <!-- Date -->
          <div>
            <label class="text-sm font-medium text-gray-700">Date <span class="text-red-500">*</span></label>
            <input v-model="form.accomplishment_date" type="date"
              class="w-full mt-1 rounded-lg border-gray-300 text-sm" required />
            <p v-if="formErrors.accomplishment_date" class="text-red-500 text-xs mt-1">
              {{ formErrors.accomplishment_date }}
            </p>
          </div>

          <!-- IPCR Plan -->
          <div>
            <label class="text-sm font-medium text-gray-700">Linked IPCR Plan</label>
            <select v-model="form.ipcr_plan_id" class="w-full mt-1 rounded-lg border-gray-300 text-sm">
              <option value="">— Not linked —</option>
              <option v-for="p in ipcrPlans" :key="p.id" :value="p.id">{{ p.label }}</option>
            </select>
          </div>

          <!-- Description -->
          <div>
            <label class="text-sm font-medium text-gray-700">Description <span class="text-red-500">*</span></label>
            <textarea v-model="form.description" rows="4" required
              class="w-full mt-1 rounded-lg border-gray-300 text-sm"
              placeholder="Describe what you accomplished..."></textarea>
            <p v-if="formErrors.description" class="text-red-500 text-xs mt-1">{{ formErrors.description }}</p>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="closeModal"
              class="px-4 py-2 bg-gray-200 rounded-lg text-sm">Cancel</button>
            <button type="submit" :disabled="isSubmitting"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
              {{ isSubmitting ? 'Saving…' : (editingId ? 'Update' : 'Save') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ── Proof Upload Modal ───────────────────────────────────────────── -->
    <div v-if="showPhotoModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 relative">
        <button @click="closePhotoModal" class="absolute top-3 right-4 text-gray-400 hover:text-gray-700 text-xl">✕</button>
        <h2 class="text-lg font-semibold mb-1">Add Proof</h2>
        <p class="text-xs text-gray-400 mb-4">You can upload a file, paste a link, or both at once.</p>

        <p v-if="photoErrors.proof" class="text-red-500 text-xs mb-3">{{ photoErrors.proof }}</p>

        <form @submit.prevent="submitPhoto" class="space-y-4">
          <div>
            <label class="text-sm font-medium text-gray-700">Upload File <span class="text-gray-400 font-normal">(optional)</span></label>
            <input type="file" accept="image/*,.pdf"
              @change="photoFile = $event.target.files[0]"
              class="w-full mt-1 text-sm text-gray-600 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:bg-blue-50 file:text-blue-700" />
            <p class="text-xs text-gray-400 mt-1">JPG, PNG, GIF, PDF — max 10 MB.</p>
            <p v-if="photoErrors.photo" class="text-red-500 text-xs mt-1">{{ photoErrors.photo }}</p>
          </div>

          <div>
            <label class="text-sm font-medium text-gray-700">Google Drive / External Link <span class="text-gray-400 font-normal">(optional)</span></label>
            <input v-model="driveLink" type="url" placeholder="https://drive.google.com/…"
              class="w-full mt-1 rounded-lg border-gray-300 text-sm" />
            <p v-if="photoErrors.drive_link" class="text-red-500 text-xs mt-1">{{ photoErrors.drive_link }}</p>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="closePhotoModal" class="px-4 py-2 bg-gray-200 rounded-lg text-sm">Cancel</button>
            <button type="submit" :disabled="uploading"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50">
              {{ uploading ? "Uploading…" : "Add Proof" }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ── Monthly Report Modal (screen only) ───────────────────────────── -->
    <div v-if="showReport" class="fixed inset-0 bg-black bg-opacity-60 flex items-start justify-center z-50 overflow-y-auto py-8">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl p-8 mx-4">

        <!-- Controls -->
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-xl font-bold">Monthly Accomplishment Report</h2>
          <div class="flex gap-2">
            <button @click="printReport" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
              🖨 Print
            </button>
            <button @click="showReport = false" class="px-4 py-2 bg-gray-200 text-sm rounded-lg">
              Close
            </button>
          </div>
        </div>

        <!-- Preview header -->
        <div class="text-center mb-4">
          <p class="font-semibold text-gray-700">
            {{ reportData?.employee?.name }} — {{ reportData?.employee?.position }}
          </p>
          <p class="text-sm text-gray-500 mt-0.5">
            {{ new Date(reportData?.month + "-01").toLocaleString("default", { month: "long", year: "numeric" }) }}
          </p>
        </div>

        <!-- Preview table -->
        <div class="overflow-x-auto">
        <table class="w-full text-sm border border-gray-300 min-w-[600px]">
          <thead class="bg-gray-100 text-gray-700 text-xs uppercase">
            <tr>
              <th class="px-3 py-2 text-left border border-gray-300 w-28">Date</th>
              <th class="px-3 py-2 text-left border border-gray-300">Description</th>
              <th class="px-3 py-2 text-left border border-gray-300">Linked IPCR Plan</th>
              <th class="px-3 py-2 text-left border border-gray-300">Proof</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="acc in reportData?.accomplishments" :key="acc.id" class="border-t border-gray-200">
              <td class="px-3 py-2 border border-gray-300 align-top whitespace-nowrap">
                {{ new Date(acc.accomplishment_date).toLocaleDateString("en-PH", { month:"short", day:"numeric" }) }}
              </td>
              <td class="px-3 py-2 border border-gray-300 align-top whitespace-pre-wrap">{{ acc.description }}</td>
              <td class="px-3 py-2 border border-gray-300 align-top text-xs text-gray-600">
                {{ planLabel(acc) }}
              </td>
              <td class="px-3 py-2 border border-gray-300 align-top">
                <div v-for="photo in acc.photos" :key="photo.id">
                  <a v-if="proofUrl(photo)" :href="proofUrl(photo)" target="_blank"
                    class="text-blue-600 hover:underline text-xs">
                    {{ proofLabel(photo) }}
                  </a>
                </div>
                <span v-if="!acc.photos?.length" class="text-xs text-gray-400">—</span>
              </td>
            </tr>
            <tr v-if="!reportData?.accomplishments?.length">
              <td colspan="4" class="px-3 py-6 text-center text-gray-400 border border-gray-300">
                No accomplishments recorded for this month.
              </td>
            </tr>
          </tbody>
        </table>
        </div>

        <p class="text-xs text-gray-400 mt-3">
          Total: {{ reportData?.accomplishments?.length ?? 0 }} accomplishment(s)
        </p>
      </div>
    </div>

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
