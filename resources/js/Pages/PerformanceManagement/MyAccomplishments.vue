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
const photoAccId      = ref(null)
const showPhotoModal  = ref(false)
const photoFile       = ref(null)
const driveLink       = ref("")
const uploading       = ref(false)
const driveConfigured = ref(false) // assume false; real check is server-side

function openPhotoModal(acc) {
  photoAccId.value   = acc.id
  photoFile.value    = null
  driveLink.value    = ""
  showPhotoModal.value = true
}

function closePhotoModal() { showPhotoModal.value = false }

async function submitPhoto() {
  if (!photoFile.value && !driveLink.value) {
    Swal.fire("Error", "Choose a file or paste a Drive link.", "error")
    return
  }

  const fd = new FormData()
  if (photoFile.value) fd.append("photo", photoFile.value)
  if (driveLink.value) fd.append("drive_link", driveLink.value)
  fd.append("_method", "POST")

  uploading.value = true
  try {
    await axios.post(route("my-accomplishments.upload-photo", photoAccId.value), fd, {
      headers: { "X-CSRF-TOKEN": page.props.csrf_token ?? document.querySelector('meta[name="csrf-token"]')?.content },
    })
    closePhotoModal()
    router.reload({ only: ["accomplishments"] })
    flash("Photo added.")
  } catch (err) {
    Swal.fire("Error", err.response?.data?.message || "Upload failed.", "error")
  } finally {
    uploading.value = false
  }
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
                <a v-for="photo in acc.photos" :key="photo.id"
                  :href="photo.google_drive_link" target="_blank"
                  class="text-xs text-blue-600 hover:underline flex items-center gap-1">
                  📎 {{ photo.file_name || "Photo" }}
                </a>
                <span v-if="!acc.photos.length" class="text-xs text-gray-400">—</span>
              </div>
              <button @click="openPhotoModal(acc)"
                class="mt-1 text-xs text-indigo-600 hover:underline">
                + Add photo
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

    <!-- ── Photo Upload Modal ───────────────────────────────────────────── -->
    <div v-if="showPhotoModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 relative">
        <button @click="closePhotoModal" class="absolute top-3 right-4 text-gray-400 hover:text-gray-700 text-xl">✕</button>
        <h2 class="text-lg font-semibold mb-4">Add Photo Proof</h2>

        <form @submit.prevent="submitPhoto" class="space-y-4">
          <div>
            <label class="text-sm font-medium text-gray-700">Upload File</label>
            <input type="file" accept="image/*,.pdf"
              @change="photoFile = $event.target.files[0]"
              class="w-full mt-1 text-sm text-gray-600 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:bg-blue-50 file:text-blue-700" />
            <p class="text-xs text-gray-400 mt-1">JPG, PNG, GIF, PDF — max 10 MB. Will be uploaded to Google Drive.</p>
          </div>

          <div class="relative">
            <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
            <div class="relative text-center text-xs text-gray-400 bg-white px-2 inline-block mx-auto">or paste a Drive link</div>
          </div>

          <div>
            <label class="text-sm font-medium text-gray-700">Google Drive Link</label>
            <input v-model="driveLink" type="url" placeholder="https://drive.google.com/..."
              class="w-full mt-1 rounded-lg border-gray-300 text-sm" />
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="closePhotoModal" class="px-4 py-2 bg-gray-200 rounded-lg text-sm">Cancel</button>
            <button type="submit" :disabled="uploading"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50">
              {{ uploading ? "Uploading…" : "Add Photo" }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ── Monthly Report Modal ──────────────────────────────────────────── -->
    <div v-if="showReport" class="fixed inset-0 bg-black bg-opacity-60 flex items-start justify-center z-50 overflow-y-auto py-8 print:static print:bg-transparent print:py-0">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl p-8 mx-4 print:shadow-none print:rounded-none print:mx-0 print:p-6">

        <!-- Print controls (hidden when printing) -->
        <div class="flex justify-between items-center mb-6 print:hidden">
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

        <!-- Report header -->
        <div class="text-center mb-6">
          <h1 class="text-2xl font-bold text-gray-800">Monthly Accomplishment Report</h1>
          <p class="text-gray-600 mt-1">
            {{ reportData?.employee?.name }} — {{ reportData?.employee?.position }}
          </p>
          <p class="text-sm text-gray-500 mt-0.5">
            {{ new Date(reportData?.month + "-01").toLocaleString("default", { month: "long", year: "numeric" }) }}
          </p>
        </div>

        <!-- Report table -->
        <table class="w-full text-sm border border-gray-300">
          <thead class="bg-gray-100 text-gray-700 text-xs uppercase">
            <tr>
              <th class="px-3 py-2 text-left border border-gray-300 w-28">Date</th>
              <th class="px-3 py-2 text-left border border-gray-300">Description</th>
              <th class="px-3 py-2 text-left border border-gray-300">Linked IPCR Plan</th>
              <th class="px-3 py-2 text-left border border-gray-300">Photo Proof</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="acc in reportData?.accomplishments" :key="acc.id" class="border-t border-gray-200">
              <td class="px-3 py-2 border border-gray-300 align-top whitespace-nowrap">
                {{ new Date(acc.accomplishment_date).toLocaleDateString("en-PH", { month:"short", day:"numeric" }) }}
              </td>
              <td class="px-3 py-2 border border-gray-300 align-top whitespace-pre-wrap">{{ acc.description }}</td>
              <td class="px-3 py-2 border border-gray-300 align-top text-xs text-gray-600">
                {{ acc.ipcr_plan?.plan?.success_indicator ?? "—" }}
              </td>
              <td class="px-3 py-2 border border-gray-300 align-top">
                <div v-for="photo in acc.photos" :key="photo.id">
                  <a :href="photo.google_drive_link" target="_blank"
                    class="text-blue-600 hover:underline text-xs print:text-gray-700">
                    {{ photo.file_name || "Link" }}
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

        <p class="text-xs text-gray-400 mt-4 print:mt-6">
          Total: {{ reportData?.accomplishments?.length ?? 0 }} accomplishment(s)
        </p>

        <!-- Signature line for print -->
        <div class="hidden print:flex justify-end mt-12">
          <div class="text-center">
            <div class="border-t border-gray-700 w-48 mt-8 pt-1 text-xs text-gray-600">
              {{ reportData?.employee?.name }}<br />Signature over Printed Name
            </div>
          </div>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<style>
@media print {
  body > *:not(.fixed) { display: none !important; }
  .fixed { position: static !important; display: block !important; }
  .print\:hidden { display: none !important; }
}
</style>
