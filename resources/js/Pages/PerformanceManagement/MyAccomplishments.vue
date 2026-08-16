<script setup>
import { ref, computed } from "vue"
import { Head, router, usePage } from "@inertiajs/vue3"
import AdminLayout from "@/Layouts/AdminLayout.vue"
import AppFilterBar from "@/Components/AppFilterBar.vue"
import AppSelect from "@/Components/AppSelect.vue"
import AppInput from "@/Components/AppInput.vue"
import AppTextarea from "@/Components/AppTextarea.vue"
import AppButton from "@/Components/AppButton.vue"
import AppIconButton from "@/Components/AppIconButton.vue"
import AppTable from "@/Components/AppTable.vue"
import AppModal from "@/Components/AppModal.vue"
import EmptyState from "@/Components/EmptyState.vue"
import { PlusIcon, PencilSquareIcon, TrashIcon, XMarkIcon } from "@heroicons/vue/24/outline"
import Swal from "sweetalert2"
import axios from "axios"
import { useSubmit } from "@/Composables/useSubmit"
import { storageUrl } from "@/Composables/useStorage.js"
import { confirmDelete } from "@/Composables/useConfirm.js"

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

function onMonthChange(val) {
  filterMonth.value = val
  applyFilter()
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
    resetOnSuccess: true,
    onSuccess: () => { closeModal(); flash("Accomplishment saved.") },
    onError:   (e) => { formErrors.value = e },
  }

  if (editingId.value) {
    submit.put(route("my-accomplishments.update", editingId.value), form.value, opts)
  } else {
    submit.post(route("my-accomplishments.store"), form.value, opts)
  }
}

async function deleteAccomplishment(id) {
  if (await confirmDelete("This action cannot be undone.")) {
    submit.delete(route("my-accomplishments.destroy", id), {
      resetOnSuccess: true,
      onSuccess: () => flash("Deleted."),
    })
  }
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

function readFileAsDataUrl(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(reader.result)
    reader.onerror = reject
    reader.readAsDataURL(file)
  })
}

async function submitPhoto() {
  if (!photoFile.value && !driveLink.value.trim()) {
    photoErrors.value = { proof: "Upload a file or paste a Drive link." }
    return
  }

  const payload = {}
  if (photoFile.value)      payload.photo_base64 = await readFileAsDataUrl(photoFile.value)
  if (driveLink.value.trim()) payload.drive_link  = driveLink.value.trim()

  uploading.value = true
  try {
    await axios.post(route("my-accomplishments.upload-photo", photoAccId.value), payload, {
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
  if (photo.local_path) return storageUrl(photo.local_path)
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

async function deletePhoto(photoId) {
  if (await confirmDelete("This will remove the photo permanently.")) {
    submit.delete(route("my-accomplishments.delete-photo", photoId), {
      resetOnSuccess: true,
      onSuccess: () => flash("Photo removed."),
    })
  }
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
    <div class="space-y-5 print:hidden">

      <!-- ── Toolbar ─────────────────────────────────────────────────────── -->
      <AppFilterBar>
        <AppSelect :model-value="filterMonth" @update:model-value="onMonthChange" placeholder="All months" class="min-w-[160px]">
          <option v-for="m in months" :key="m" :value="m">{{ m }}</option>
        </AppSelect>

        <template #actions>
          <AppButton @click="openAdd">
            <PlusIcon class="w-4 h-4" /> Add Accomplishment
          </AppButton>

          <div class="flex items-center gap-2">
            <AppInput v-model="reportMonth" type="month" />
            <AppButton variant="secondary" :loading="loadingReport" @click="loadReport">
              {{ loadingReport ? "Loading…" : "Monthly Report" }}
            </AppButton>
          </div>
        </template>
      </AppFilterBar>

      <!-- ── Accomplishments table ────────────────────────────────────────── -->
      <AppTable :is-empty="!accomplishments.length" :skeleton-cols="5">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Date</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Description</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Linked IPCR Plan</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Photo Proofs</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
          </tr>
        </template>

        <tr v-for="acc in accomplishments" :key="acc.id" class="hover:bg-indigo-50/40">
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
                  class="text-danger-500 hover:text-danger-600 text-xs leading-none" title="Remove">
                  <XMarkIcon class="h-4 w-4 shrink-0" />
                </button>
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
              <AppIconButton label="Edit" :disabled="isSubmitting" @click="openEdit(acc)">
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>
              <AppIconButton label="Delete" variant="danger" :disabled="isSubmitting" @click="deleteAccomplishment(acc.id)">
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #empty>
          <EmptyState title="No accomplishments found" :subtitle="filterMonth ? `No accomplishments found for ${monthLabel}.` : null" />
        </template>
      </AppTable>

    </div>

    <!-- ── Add / Edit Modal ─────────────────────────────────────────────── -->
    <AppModal :show="showModal" :title="(editingId ? 'Edit' : 'Add') + ' Accomplishment'" size="lg" @close="closeModal">
      <form id="accomplishment-form" @submit.prevent="submitForm" class="space-y-4">
        <AppInput
          v-model="form.accomplishment_date"
          type="date"
          label="Date"
          required
          :error="formErrors.accomplishment_date"
        />

        <AppSelect v-model="form.ipcr_plan_id" label="Linked IPCR Plan" placeholder="— Not linked —">
          <option v-for="p in ipcrPlans" :key="p.id" :value="p.id">{{ p.label }}</option>
        </AppSelect>

        <AppTextarea
          v-model="form.description"
          label="Description"
          :rows="4"
          required
          placeholder="Describe what you accomplished..."
          :error="formErrors.description"
        />
      </form>

      <template #footer>
        <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
        <AppButton type="submit" form="accomplishment-form" :loading="isSubmitting">
          {{ isSubmitting ? 'Saving…' : (editingId ? 'Update' : 'Save') }}
        </AppButton>
      </template>
    </AppModal>

    <!-- ── Proof Upload Modal ───────────────────────────────────────────── -->
    <AppModal :show="showPhotoModal" title="Add Proof" subtitle="You can upload a file, paste a link, or both at once." size="md" @close="closePhotoModal">
      <form id="proof-form" @submit.prevent="submitPhoto" class="space-y-4">
        <p v-if="photoErrors.proof" class="text-danger-600 text-xs">{{ photoErrors.proof }}</p>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Upload File <span class="text-slate-400 font-normal">(optional)</span></label>
          <input type="file" accept="image/*,.pdf"
            @change="photoFile = $event.target.files[0]"
            class="w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-sm" />
          <p class="text-xs text-slate-400 mt-1">JPG, PNG, GIF, PDF — max 10 MB.</p>
          <p v-if="photoErrors.photo" class="text-danger-600 text-xs mt-1">{{ photoErrors.photo }}</p>
        </div>

        <AppInput
          v-model="driveLink"
          type="url"
          label="Google Drive / External Link"
          placeholder="https://drive.google.com/…"
          :error="photoErrors.drive_link"
        />
      </form>

      <template #footer>
        <AppButton variant="secondary" @click="closePhotoModal">Cancel</AppButton>
        <AppButton type="submit" form="proof-form" :loading="uploading">
          {{ uploading ? "Uploading…" : "Add Proof" }}
        </AppButton>
      </template>
    </AppModal>

    <!-- ── Monthly Report Modal (screen only) ───────────────────────────── -->
    <AppModal :show="showReport" title="Monthly Accomplishment Report" size="3xl" @close="showReport = false">
      <template #header>
        <AppButton size="sm" @click="printReport">Print</AppButton>
      </template>

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
        <thead class="bg-slate-50/80">
          <tr>
            <th class="px-3 py-2 text-left border border-slate-200 w-28 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Date</th>
            <th class="px-3 py-2 text-left border border-slate-200 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Description</th>
            <th class="px-3 py-2 text-left border border-slate-200 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Linked IPCR Plan</th>
            <th class="px-3 py-2 text-left border border-slate-200 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Proof</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="acc in reportData?.accomplishments" :key="acc.id" class="border-t border-slate-100 hover:bg-indigo-50/40">
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

      <template #footer>
        <AppButton variant="secondary" @click="showReport = false">Close</AppButton>
      </template>
    </AppModal>

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
