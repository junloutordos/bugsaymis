<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm, router } from '@inertiajs/vue3'
import { useSubmit } from '@/Composables/useSubmit'
import { storageUrl } from "@/Composables/useStorage.js"
import {
  PlusIcon,
  TrashIcon,
  PencilSquareIcon,
  PrinterIcon,
  DocumentTextIcon,
  DocumentArrowDownIcon,
} from '@heroicons/vue/24/solid'
import { ref, watch, computed } from 'vue'
import Swal from 'sweetalert2'

const passportPhotoUploading = ref(false)
const passportPhotoError = ref(null)

async function uploadPassportPhoto(file) {
  if (!file || !props.pds) return
  passportPhotoUploading.value = true
  passportPhotoError.value = null
  try {
    const dataUri = await new Promise((resolve, reject) => {
      const reader = new FileReader()
      reader.onload = e => resolve(e.target.result)
      reader.onerror = reject
      reader.readAsDataURL(file)
    })
    const { data } = await axios.post(route('pds.passport-photo', props.pds.id), { photo_base64: dataUri })
    form.other_info.path_passport_photo = data.path
  } catch {
    passportPhotoError.value = 'Failed to upload photo. Please try again.'
  } finally {
    passportPhotoUploading.value = false
  }
}

const props = defineProps({
  pds: { type: Object, default: null },
})
const { isSubmitting: isUploading, submit: submitUpload } = useSubmit()

function exportPDF(pdsId) {
  router.get(`/pds/${pdsId}/export-pdf`, {}, { target: '_blank' })
}

/* =========================
   DATE NORMALIZER
========================= */
const normalizeDate = (date) => {
  if (!date) return ''
  if (typeof date === 'string' && date.includes('T')) return date.split('T')[0]
  if (typeof date === 'string' && date.includes(' ')) return date.split(' ')[0]
  return date
}

// 🔹 Tabs
const activeTab = ref(1)
const tabs = [
  { id: 1, label: 'C1' },
  { id: 2, label: 'C2' },
  { id: 3, label: 'C3' },
  { id: 4, label: 'C4' },
  { id: 5, label: 'WES' },
]

// 🔹 Edit mode
const editMode = ref(!props.pds)

// Initialize form
const personalInfoFromDB = props.pds?.personal_info ?? {}

// Set citizenship_type
if (personalInfoFromDB.citizenship_filipino === 'Yes') personalInfoFromDB.citizenship_type = 'Filipino'
else if (personalInfoFromDB.citizenship_dual === 'Yes') personalInfoFromDB.citizenship_type = 'Dual'
else personalInfoFromDB.citizenship_type = ''

// 🔹 Form with validation
const form = useForm(
  {
    personal_info: {
      ...(props.pds?.personal_info ?? {
        surname: '',
        first_name: '',
        middle_name: '',
        extension: '',
        date_of_birth: '',
        place_of_birth: '',
        sex_at_birth: '',
        civil_status: '',
        citizenship: '',
        height: '',
        weight: '',
        blood_type: '',
        umid_id_no: '',
        pagibig_id_no: '',
        philhealth_no: '',
        philsys_no: '',
        tin_no: '',
        agency_employee_no: '',
        mobile_no: '',
        telephone_no: '',
        email_address: '',
        citizenship_type: '',
        citizenship_filipino: false,
        citizenship_dual: false,
        citizenship_dual_type: '',
        citizenship_dual_country: '',
        residential_house: '',
        residential_street: '',
        residential_subdivision: '',
        residential_barangay: '',
        residential_city: '',
        residential_province: '',
        residential_zip_code: '',
        permanent_house: '',
        permanent_street: '',
        permanent_subdivision: '',
        permanent_barangay: '',
        permanent_city: '',
        permanent_province: '',
        permanent_zip_code: '',
      }),
      date_of_birth: normalizeDate(props.pds?.personal_info?.date_of_birth),
    },

    family_background: props.pds?.family_background ?? {},

    children: (props.pds?.children ?? [{ child_name: '', child_date_of_birth: '' }]).map((c) => ({
      ...c,
      child_date_of_birth: normalizeDate(c.child_date_of_birth),
    })),

    education:
      props.pds?.education ??
      [
        {
          level: '',
          school_name: '',
          from: '',
          to: '',
          highest_level: '',
          year_graduated: '',
          degree: '',
          honors: '',
        },
      ],

    eligibility: (props.pds?.eligibility ?? [
      { eligibility: '', rating: '', exam_date: '', place_taken: '', license_number: '', license_validity: '' },
    ]).map((e) => ({
      ...e,
      exam_date: normalizeDate(e.exam_date),
      license_validity: normalizeDate(e.license_validity),
    })),

    work_experience: (props.pds?.work_experience ?? [
      { position: '', agency: '', appointment_status: '', government_service: '', from_date: '', to_date: '' },
    ]).map((w) => ({
      ...w,
      from_date: normalizeDate(w.from_date),
      to_date: normalizeDate(w.to_date),
    })),

    voluntary_work: (props.pds?.voluntary_work ?? [
      { organization: '', nature_of_work: '', from_date: '', to_date: '', hours: '' },
    ]).map((v) => ({
      ...v,
      from_date: normalizeDate(v.from_date),
      to_date: normalizeDate(v.to_date),
    })),

    trainings: (props.pds?.trainings ?? [
      { training_title: '', date_from: '', date_to: '', hours: '', training_type: '', conducted_by: '' },
    ]).map((t) => ({
      ...t,
      date_from: normalizeDate(t.date_from),
      date_to: normalizeDate(t.date_to),
    })),

    skills_hobbies: props.pds?.skills_hobbies ?? [{ skills_hobbies: '' }],
    non_academic_recognition: props.pds?.non_academic_recognition ?? [{ recognition: '' }],
    membership_organizations: props.pds?.membership_organizations ?? [{ organization_name: '' }],

    questions: {
      ...(props.pds?.questions ?? {}),
      q35b_date_filed: normalizeDate(props.pds?.questions?.q35b_date_filed),
    },

    references: props.pds?.references ?? [{ name: '', office_address: '', contact_no_email: '' }],

    other_info: props.pds?.other_info ?? { government_id: '', id_no: '', date_place_issuance: '', path_passport_photo: '' },

    work_experience_sheets: (props.pds?.work_experience_sheets ?? []).map((w, i) => ({
      from_date:                    w.from_date ?? '',
      to_date:                      w.to_date ?? '',
      position:                     w.position ?? '',
      office_unit:                  w.office_unit ?? '',
      immediate_supervisor:         w.immediate_supervisor ?? '',
      agency_organization_location: w.agency_organization_location ?? '',
      accomplishments:              Array.isArray(w.accomplishments) ? w.accomplishments : [''],
      summary_of_duties:            Array.isArray(w.summary_of_duties) ? w.summary_of_duties : [''],
      sort_order:                   i,
    })),
  },
  {
    // 🔹 Validation rules
    personal_info: {
      surname: (v) => (!!v && v.length > 0) || 'Surname is required',
      first_name: (v) => (!!v && v.length > 0) || 'First name is required',
      date_of_birth: (v) => (!!v && /^\d{4}-\d{2}-\d{2}$/.test(v)) || 'Valid date of birth required',
      email_address: (v) => (!v || /^\S+@\S+\.\S+$/.test(v)) || 'Email must be valid',
      mobile_no: (v) => (!!v && /^\d{10,15}$/.test(v)) || 'Mobile number must be valid',
      citizenship_type: (v) => (!!v && v.length > 0) || 'Select citizenship type',
      ...(props.pds?.personal_info?.citizenship_type === 'Dual'
        ? {
            citizenship_dual_type: (v) => (!!v && v.length > 0) || 'Dual type required',
            citizenship_dual_country: (v) => (!!v && v.length > 0) || 'Country required',
          }
        : {}),
    },
  }
)

/* =========================
   WATCHERS
========================= */
watch(() => form.personal_info.citizenship_type, (val) => {
  if (val === 'Filipino') {
    form.personal_info.citizenship_filipino = true
    form.personal_info.citizenship_dual = false
    form.personal_info.citizenship_dual_type = ''
    form.personal_info.citizenship_dual_country = ''
  } else if (val === 'Dual') {
    form.personal_info.citizenship_filipino = false
    form.personal_info.citizenship_dual = true
  } else {
    form.personal_info.citizenship_filipino = false
    form.personal_info.citizenship_dual = false
    form.personal_info.citizenship_dual_type = ''
    form.personal_info.citizenship_dual_country = ''
  }
})

const sameAsResidential = ref(false)
const copyResidentialToPermanent = () => {
  form.personal_info.permanent_house = form.personal_info.residential_house
  form.personal_info.permanent_street = form.personal_info.residential_street
  form.personal_info.permanent_subdivision = form.personal_info.residential_subdivision
  form.personal_info.permanent_barangay = form.personal_info.residential_barangay
  form.personal_info.permanent_city = form.personal_info.residential_city
  form.personal_info.permanent_province = form.personal_info.residential_province
  form.personal_info.permanent_zip_code = form.personal_info.residential_zip_code
}

watch(sameAsResidential, (val) => { if (val) copyResidentialToPermanent() })
watch(
  () => [
    form.personal_info.residential_house,
    form.personal_info.residential_street,
    form.personal_info.residential_subdivision,
    form.personal_info.residential_barangay,
    form.personal_info.residential_city,
    form.personal_info.residential_province,
    form.personal_info.residential_zip_code,
  ],
  () => { if (sameAsResidential.value) copyResidentialToPermanent() }
)

/* =========================
   SUBMIT WITH VALIDATION
========================= */
const validatePersonalInfo = () => {
  const errors = {}

  const p = form.personal_info

  if (!p.surname) errors.surname = 'Surname is required'
  if (!p.first_name) errors.first_name = 'First name is required'
  if (!p.date_of_birth || !/^\d{4}-\d{2}-\d{2}$/.test(p.date_of_birth))
    errors.date_of_birth = 'Valid date of birth required'
  if (p.email_address && !/^\S+@\S+\.\S+$/.test(p.email_address))
    errors.email_address = 'Email must be valid'
  if (!p.mobile_no || !/^\d{10,15}$/.test(p.mobile_no))
    errors.mobile_no = 'Mobile number must be valid'
  if (!p.citizenship_type) errors.citizenship_type = 'Select citizenship type'

  if (p.citizenship_type === 'Dual') {
    if (!p.citizenship_dual_type) errors.citizenship_dual_type = 'Dual type required'
    if (!p.citizenship_dual_country) errors.citizenship_dual_country = 'Country required'
  }

  return errors
}

const submit = () => {
  const errors = validatePersonalInfo()

  if (Object.keys(errors).length > 0) {
    form.setError(errors) // sets errors in form.errors

    // Combine all error messages into one string
    const errorMessages = Object.values(errors).join('<br>')

    Swal.fire({
      icon: 'error',
      title: 'Validation Error',
      html: errorMessages, // display all errors in the alert
    })

    return
  }

  if (props.pds) {
    form.put(route('pds.update', props.pds.id), {
      preserveScroll: true,
      onSuccess: () => {
        editMode.value = false
        Swal.fire('Success', 'PDS updated successfully', 'success')
      },
      onError: (errors) => {
        const serverErrors = Object.values(errors).join('<br>')
        Swal.fire({
          icon: 'error',
          title: 'Server Error',
          html: serverErrors || 'An error occurred while updating PDS',
        })
      },
    })
  } else {
    form.post(route('pds.store'), {
      preserveScroll: true,
      onSuccess: () => {
        editMode.value = false
        Swal.fire('Success', 'PDS saved successfully', 'success')
      },
      onError: (errors) => {
        const serverErrors = Object.values(errors).join('<br>')
        Swal.fire({
          icon: 'error',
          title: 'Server Error',
          html: serverErrors || 'An error occurred while saving PDS',
        })
      },
    })
  }
}


/* =========================
   TRAINING ROW TEMPLATE
========================= */
const emptyTraining = () => ({
  training_title: '',
  date_from: '',
  date_to: '',
  hours: '',
  training_type: '',
  conducted_by: '',
})

/* =========================
   CSV FILE STATE
========================= */
const csvFile = ref(null)

/* =========================
   HANDLE FILE SELECT
========================= */
const handleTrainingCSV = (event) => {
  csvFile.value = event.target.files[0] || null
}

/* =========================
   UPLOAD TO SERVER
========================= */
const uploadTrainingCSV = () => {
  if (!csvFile.value) {
    Swal.fire('No file selected', 'Please choose a CSV file.', 'warning')
    return
  }

  if (!props.pds?.id) {
    Swal.fire('Save Required', 'Please save the PDS first before uploading trainings.', 'warning')
    return
  }

  const formData = new FormData()
  formData.append('file', csvFile.value)

  submitUpload.post(route('pds.trainings.upload-csv', props.pds.id), formData, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: (page) => {
      // Show success message from flash
      const successMessage = page.props?.flash?.success || 'CSV uploaded successfully!'
      Swal.fire('Upload Complete', successMessage, 'success')

      // Optionally, refresh trainings if you want to display them
      if (page.props?.pds?.trainings) {
        form.trainings = page.props.pds.trainings.map(t => ({
          training_title: t.training_title || '',
          date_from: normalizeDate(t.date_from),
          date_to: normalizeDate(t.date_to),
          hours: t.hours || '',
          training_type: t.training_type || '',
          conducted_by: t.conducted_by || '',
        }))
      }

      csvFile.value = null
    },
    onError: (errors) => {
      Swal.fire('Upload failed', errors.file ?? 'Invalid CSV file.', 'error')
    },
  })
}





// 🔹 Helpers
const addRow = (section, row) => form[section].push({ ...row })
const removeRow = (section, index) => form[section].splice(index, 1)

// 🔹 Export
const exportPDS = (id) => { window.location.href = `/pds/${id}/export` }
</script>


<template>
  <AdminLayout title="Personal Data Sheet">
    <div class="max-w-7xl mx-auto">
      <!-- Page header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-slate-800">Personal Data Sheet</h1>
          <p class="text-sm text-slate-500">Manage your personal information for civil service records</p>
        </div>
        <div class="flex gap-2">
          <button v-if="props.pds && !editMode" @click="editMode = true" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            <PencilSquareIcon class="h-4 w-4" />
            Edit PDS & WES
          </button>
          <button v-if="props.pds && !editMode" @click="exportPDS(props.pds.id)" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            <DocumentArrowDownIcon class="h-4 w-4" />
            Export PDS XLSX
          </button>
          <a v-if="props.pds && !editMode"
            :href="route('pds.wes.pdf', props.pds.id)"
            target="_blank"
            class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            <DocumentArrowDownIcon class="h-4 w-4" />
            Export WES PDF
          </a>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6">

        <!-- Tabs -->
        <div class="border-b border-slate-200 mb-6 flex gap-1">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            class="px-4 py-2 text-sm font-semibold border-b-2 transition-colors"
            :class="activeTab === tab.id ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-400 hover:text-slate-600'"
          >
            {{ tab.label }}
          </button>
        </div>

        <!-- ================= TAB 1 ================= -->
        <div v-show="activeTab === 1">
          <section>
  <h2 class="font-semibold text-lg mb-4">I. Personal Information</h2>

  <!-- Name Fields -->
  <div class="grid grid-cols-3 gap-4">
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Surname</label>
      <input v-model="form.personal_info.surname" class="input w-full" :readonly="!editMode" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">First Name</label>
      <input v-model="form.personal_info.first_name" class="input w-full" :readonly="!editMode" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Middle Name</label>
      <input v-model="form.personal_info.middle_name" class="input w-full" :readonly="!editMode" />
    </div>
  </div>

  <div class="grid grid-cols-3 gap-4 mt-2">
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Extension (Jr., Sr., III)</label>
      <input v-model="form.personal_info.name_ext" class="input w-full" :readonly="!editMode" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Date of Birth</label>
      <input type="date" v-model="form.personal_info.date_of_birth" class="input w-full" :readonly="!editMode" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Place of Birth</label>
      <input v-model="form.personal_info.place_of_birth" class="input w-full" :readonly="!editMode" />
    </div>
  </div>

  <!-- Sex, Civil Status, Citizenship -->
  <div class="grid grid-cols-3 gap-4 mt-2">
  <!-- Sex at Birth -->
  <div>
    <label class="block text-xs font-medium text-slate-500 mb-1">Sex at Birth</label>
    <select v-model="form.personal_info.sex_at_birth" class="input w-full" :disabled="!editMode">
      <option value="" disabled>Select Sex</option>
      <option value="Male">Male</option>
      <option value="Female">Female</option>
    </select>
  </div>

  <!-- Civil Status -->
  <div>
    <label class="block text-xs font-medium text-slate-500 mb-1">Civil Status</label>
    <select v-model="form.personal_info.civil_status" class="input w-full" :disabled="!editMode">
      <option value="" disabled>Select Civil Status</option>
      <option value="Single">Single</option>
      <option value="Married">Married</option>
      <option value="Widowed">Widowed</option>
      <option value="Separated">Separated</option>
    </select>
  </div>

  <!-- Citizenship Type -->
  <div>
    <label class="block text-xs font-medium text-slate-500 mb-1">Citizenship</label>
    <select v-model="form.personal_info.citizenship_type" class="input w-full" :disabled="!editMode">
      <option value="" disabled>Select Citizenship</option>
      <option value="Filipino">Filipino</option>
      <option value="Dual">Dual Citizenship</option>
    </select>
  </div>

  <!-- Dual Citizenship Fields -->
  <template v-if="form.personal_info.citizenship_type === 'Dual'">
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Dual Citizenship Type</label>
      <select
        v-model="form.personal_info.citizenship_dual_type"
        class="input w-full"
        :disabled="!editMode"
      >
        <option value="" disabled>Select Type</option>
        <option value="By Birth">By Birth</option>
        <option value="By Naturalization">By Naturalization</option>
      </select>
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Country</label>
      <input
        v-model="form.personal_info.citizenship_dual_country"
        class="input w-full"
        :readonly="!editMode"
      />
    </div>
  </template>
</div>

  

  <!-- Physical Info -->
  <div class="grid grid-cols-3 gap-4 mt-2">
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Height (m)</label>
      <input v-model="form.personal_info.height" class="input w-full" :readonly="!editMode" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Weight (kg)</label>
      <input v-model="form.personal_info.weight" class="input w-full" :readonly="!editMode" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Blood Type</label>
      <input v-model="form.personal_info.blood_type" class="input w-full" :readonly="!editMode" />
    </div>
  </div>

  <!-- Government Numbers -->
  <div class="grid grid-cols-3 gap-4 mt-2">
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">UMID ID No.</label>
      <input v-model="form.personal_info.umid_id_no" class="input w-full" :readonly="!editMode" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">PAG-IBIG ID No.</label>
      <input v-model="form.personal_info.pagibig_id_no" class="input w-full" :readonly="!editMode" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">PhilHealth No.</label>
      <input v-model="form.personal_info.philhealth_no" class="input w-full" :readonly="!editMode" />
    </div>
  </div>

  <div class="grid grid-cols-3 gap-4 mt-2">
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">PhilSys No.</label>
      <input v-model="form.personal_info.philsys_no" class="input w-full" :readonly="!editMode" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">TIN No.</label>
      <input v-model="form.personal_info.tin_no" class="input w-full" :readonly="!editMode" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Agency Employee No.</label>
      <input v-model="form.personal_info.agency_employee_no" class="input w-full" :readonly="!editMode" />
    </div>
  </div>

  <div class="grid grid-cols-3 gap-4 mt-2">
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Telephone No.</label>
      <input v-model="form.personal_info.telephone_no" class="input w-full" :readonly="!editMode" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Mobile No.</label>
      <input v-model="form.personal_info.mobile_no" class="input w-full" :readonly="!editMode" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Email Address</label>
      <input v-model="form.personal_info.email_address" class="input w-full" :readonly="!editMode" />
    </div>
  </div>

  <!-- Residential Address -->
  <h4 class="font-semibold text-lg mt-4 mb-2">Residential Address</h4>
  <div class="grid grid-cols-3 gap-4">
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">House/Block/Lot No.</label>
      <input v-model="form.personal_info.residential_house" class="input w-full" :readonly="!editMode" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Street</label>
      <input v-model="form.personal_info.residential_street" class="input w-full" :readonly="!editMode" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Subdivision/Village</label>
      <input v-model="form.personal_info.residential_subdivision" class="input w-full" :readonly="!editMode" />
    </div>
  </div>
  <div class="grid grid-cols-3 gap-4 mt-2">
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Barangay</label>
      <input v-model="form.personal_info.residential_barangay" class="input w-full" :readonly="!editMode" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">City/Municipality</label>
      <input v-model="form.personal_info.residential_city" class="input w-full" :readonly="!editMode" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Province</label>
      <input v-model="form.personal_info.residential_province" class="input w-full" :readonly="!editMode" />
    </div>
  </div>
  <div class="grid grid-cols-4 gap-4 mt-2">
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Zip Code</label>
      <input v-model="form.personal_info.residential_zip_code" class="input w-full" :readonly="!editMode" />
    </div>
  </div>

  <!-- Permanent Address -->
  <div class="flex items-center gap-2 mt-4">
    <input type="checkbox" v-model="sameAsResidential" :disabled="!editMode" id="sameAsRes" />
    <label for="sameAsRes" class="text-sm">Permanent Address is same as Residential</label>
  </div>

  <h4 class="font-semibold text-lg mt-2 mb-2">Permanent Address</h4>
  <div class="grid grid-cols-3 gap-4">
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">House/Block/Lot No.</label>
      <input v-model="form.personal_info.permanent_house" class="input w-full" :readonly="!editMode || sameAsResidential" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Street</label>
      <input v-model="form.personal_info.permanent_street" class="input w-full" :readonly="!editMode || sameAsResidential" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Subdivision/Village</label>
      <input v-model="form.personal_info.permanent_subdivision" class="input w-full" :readonly="!editMode || sameAsResidential" />
    </div>
  </div>
  <div class="grid grid-cols-3 gap-4 mt-2">
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Barangay</label>
      <input v-model="form.personal_info.permanent_barangay" class="input w-full" :readonly="!editMode || sameAsResidential" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">City/Municipality</label>
      <input v-model="form.personal_info.permanent_city" class="input w-full" :readonly="!editMode || sameAsResidential" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Province</label>
      <input v-model="form.personal_info.permanent_province" class="input w-full" :readonly="!editMode || sameAsResidential" />
    </div>
  </div>
  <div class="grid grid-cols-4 gap-4 mt-2">
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Zip Code</label>
      <input v-model="form.personal_info.permanent_zip_code" class="input w-full" :readonly="!editMode || sameAsResidential" />
    </div>
  </div>
</section>
        <br>
        <!-- II. Family Background -->
        <section>
        <h2 class="font-semibold text-lg mb-4">II. Family Background</h2>

        <!-- Spouse -->
        <div class="grid grid-cols-4 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Spouse's Surname</label>
              <input v-model="form.family_background.spouse_surname" class="input w-full" :readonly="!editMode" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Spouse's First Name</label>
              <input v-model="form.family_background.spouse_first_name" class="input w-full" :readonly="!editMode" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Spouse's Middle Name</label>
              <input v-model="form.family_background.spouse_middle_name" class="input w-full" :readonly="!editMode" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Spouse's Name Extension</label>
              <input v-model="form.family_background.spouse_name_ext" class="input w-full" :readonly="!editMode" />
            </div>
        </div>
        <div class="grid grid-cols-4 gap-4 mt-2">
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Spouse's Occupation</label>
              <input v-model="form.family_background.spouse_occupation" class="input w-full" :readonly="!editMode" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Employer/Business</label>
              <input v-model="form.family_background.spouse_employer" class="input w-full" :readonly="!editMode" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Business Address</label>
              <input v-model="form.family_background.spouse_business_address" class="input w-full" :readonly="!editMode" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Telephone No.</label>
              <input v-model="form.family_background.spouse_telephone_no" class="input w-full" :readonly="!editMode" />
            </div>
        </div>

        <!-- Father -->
        <div class="grid grid-cols-3 gap-4 mt-4">
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Father's Surname</label>
              <input v-model="form.family_background.father_surname" class="input w-full" :readonly="!editMode" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Father's First Name</label>
              <input v-model="form.family_background.father_first_name" class="input w-full" :readonly="!editMode" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Father's Middle Name</label>
              <input v-model="form.family_background.father_middle_name" class="input w-full" :readonly="!editMode" />
            </div>
        </div>
        <div class="grid grid-cols-4 gap-4 mt-2">
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Father's Name Extension (Jr., Sr., III)</label>
              <input v-model="form.family_background.father_name_ext" class="input w-full" :readonly="!editMode" />
            </div>
        </div>

        <!-- Mother -->
        <div class="grid grid-cols-3 gap-4 mt-4">
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Mother's Maiden Surname</label>
              <input v-model="form.family_background.mother_maiden_surname" class="input w-full" :readonly="!editMode" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Mother's First Name</label>
              <input v-model="form.family_background.mother_maiden_first_name" class="input w-full" :readonly="!editMode" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Mother's Maiden Middle Name</label>
              <input v-model="form.family_background.mother_maiden_middle_name" class="input w-full" :readonly="!editMode" />
            </div>
        </div>
        </section>
        <br>

        <!-- III. Children -->
        <section>
        <h2 class="font-semibold text-lg mb-4">Children</h2>

        <div class="grid grid-cols-[1fr_1fr_auto] gap-4 mb-1 px-1 text-xs font-medium text-slate-500">
          <span>Child Name</span>
          <span>Date of Birth</span>
          <span></span>
        </div>

        <div
            v-for="(child, index) in form.children"
            :key="index"
            class="grid grid-cols-[1fr_1fr_auto] gap-4 mb-2 items-center"
        >
            <input
            v-model="child.child_name"
            placeholder="Child Name"
            class="input"
            :readonly="!editMode"
            />

            <input
            v-model="child.child_date_of_birth"
            type="date"
            class="input"
            :readonly="!editMode"
            />

            <button
            v-if="editMode"
            @click="removeRow('children', index)"
            class="btn-icon h-10 w-10"
            >
            <TrashIcon class="h-5 w-5 text-white" />
            </button>
        </div>

        <button
            v-if="editMode"
            @click="addRow('children', { child_name: '', child_date_of_birth: '' })"
            class="btn-icon h-10 w-10 mt-2"
        >
            <PlusIcon class="h-5 w-5 text-white" />
        </button>
        </section>

        <br>
        <!-- IV. Educational Background -->
        <section>
        <h2 class="font-semibold text-lg mb-4">III. Educational Background</h2>

        <div class="grid grid-cols-[1fr_3fr_3fr_1fr_1fr_1fr_1fr_1fr] gap-4 mb-1 px-1 text-xs font-medium text-slate-500">
          <span>Level</span>
          <span>School Name</span>
          <span>Degree/Course</span>
          <span>From</span>
          <span>To</span>
          <span>Highest Level</span>
          <span>Year Graduated</span>
          <span>Honors</span>
        </div>

        <div
            v-for="(edu, index) in form.education"
            :key="index"
            class="grid grid-cols-[1fr_3fr_3fr_1fr_1fr_1fr_1fr_1fr] gap-4 mb-2 items-center"
        >
            <input
            v-model="edu.level"
            placeholder="Level"
            class="input"
            :readonly="!editMode"
            />

            <input
            v-model="edu.school_name"
            placeholder="School Name"
            class="input"
            :readonly="!editMode"
            />

            <input
            v-model="edu.degree"
            placeholder="Degree"
            class="input"
            :readonly="!editMode"
            />

            <input
            v-model="edu.from"
            placeholder="From"
            class="input"
            :readonly="!editMode"
            /> 
            
            <input
            v-model="edu.to"
            placeholder="To"
            class="input"
            :readonly="!editMode"
            />

            <input
            v-model="edu.highest_level"
            placeholder="Highest Level"
            class="input"
            :readonly="!editMode"
            />

            <input
            v-model="edu.year_graduated"
            placeholder="Year Graduated"
            class="input"
            :readonly="!editMode"
            />

            <input
            v-model="edu.honors"
            placeholder="Honors"
            class="input"
            :readonly="!editMode"
            />

            <button
            v-if="editMode"
            @click="removeRow('education', index)"
            class="btn-icon h-10 w-10"
            >
            <TrashIcon class="h-5 w-5 text-white" />
            </button>
        </div>

        <button
            v-if="editMode"
            @click="addRow('education', {
            level: '',
            school_name: '',
            degree: '',
            from: '',
            to: '',
            highest_level: '',
            year_graduated: '',
            honors: ''
            })"
            class="btn-icon h-10 w-10 mt-2"
        >
            <PlusIcon class="h-5 w-5 text-white" />
        </button>
        </section>
        </div>

        <!-- ================= TAB 2 ================= -->
        <div v-show="activeTab === 2">
          <!-- V. Civil Service Eligibility -->
        <section>
        <h2 class="font-semibold text-lg mb-4">IV. Civil Service Eligibility</h2>

        <div class="grid grid-cols-[2fr_1fr_1fr_2fr_1fr_1fr_auto] gap-4 mb-1 px-1 text-xs font-medium text-slate-500">
          <span>Eligibility / Exam</span>
          <span>Rating</span>
          <span>Exam Date</span>
          <span>Place of Exam</span>
          <span>License No.</span>
          <span>License Validity</span>
          <span></span>
        </div>

        <div
            v-for="(elig, index) in form.eligibility"
            :key="index"
            class="grid grid-cols-[2fr_1fr_1fr_2fr_1fr_1fr_auto] gap-4 mb-2 items-center"
        >
            <input v-model="elig.eligibility" placeholder="Eligibility" class="input" :readonly="!editMode" />
            <input v-model="elig.rating" placeholder="Rating" class="input" :readonly="!editMode" />
            <input type="date" v-model="elig.exam_date" class="input" :readonly="!editMode" />
            <input v-model="elig.place_taken" placeholder="Place of Exam" class="input" :readonly="!editMode" />
            <input v-model="elig.license_number" placeholder="License No." class="input" :readonly="!editMode" />
            <input type="date" v-model="elig.license_validity" class="input" :readonly="!editMode" />

            <button
            v-if="editMode"
            @click="removeRow('eligibility', index)"
            class="btn-icon h-10 w-10"
            >
            <TrashIcon class="h-5 w-5 text-white" />
            </button>
        </div>

        <button
            v-if="editMode"
            @click="addRow('eligibility', { eligibility: '', rating: '', exam_date: '', place_taken: '', license_number: '', license_validity: '' })"
            class="btn-icon h-10 w-10 mt-2"
        >
            <PlusIcon class="h-5 w-5 text-white" />
        </button>
        </section>


        <!-- VI. Work Experience -->
        <section>
        <h2 class="font-semibold text-lg mb-4">V. Work Experience</h2>

        <div class="grid grid-cols-[2fr_2fr_1fr_1fr_1fr_1fr_auto] gap-4 mb-1 px-1 text-xs font-medium text-slate-500">
          <span>Position</span>
          <span>Department / Agency</span>
          <span>Appointment Status</span>
          <span>Govt Service</span>
          <span>Date From</span>
          <span>Date To</span>
          <span></span>
        </div>

        <div
            v-for="(work, index) in form.work_experience"
            :key="index"
            class="grid grid-cols-[2fr_2fr_1fr_1fr_1fr_1fr_auto] gap-4 mb-2 items-center"
        >
            <input v-model="work.position" placeholder="Position" class="input" :readonly="!editMode" />
            <input v-model="work.agency" placeholder="Department" class="input" :readonly="!editMode" />    
            <input v-model="work.appointment_status" placeholder="Status" class="input" :readonly="!editMode" />
            <input v-model="work.government_service" placeholder="Govt Service?" class="input" :readonly="!editMode" />
            <input type="date" v-model="work.from_date" class="input" :readonly="!editMode" />
            <input type="date" v-model="work.to_date" class="input" :readonly="!editMode" />

            <button
            v-if="editMode"
            @click="removeRow('work_experience', index)"
            class="btn-icon h-10 w-10"
            >
            <TrashIcon class="h-5 w-5 text-white" />
            </button>
        </div>

        <button
            v-if="editMode"
            @click="addRow('work_experience', { position: '', agency: '', appointment_status: '', government_service: '', from_date: '', to_date: '' })"
            class="btn-icon h-10 w-10 mt-2"
        >
            <PlusIcon class="h-5 w-5 text-white" />
        </button>
        </section>

        </div>

        <!-- ================= TAB 3 ================= -->
        <div v-show="activeTab === 3">
          <section>
        <h2 class="font-semibold text-lg mb-4">VI. Voluntary Work</h2>

        <div class="grid grid-cols-[2fr_1fr_1fr_1fr_1fr_auto] gap-4 mb-1 px-1 text-xs font-medium text-slate-500">
          <span>Organization</span>
          <span>Position / Nature of Work</span>
          <span>Date From</span>
          <span>Date To</span>
          <span>Hours</span>
          <span></span>
        </div>

        <div
            v-for="(vol, index) in form.voluntary_work"
            :key="index"
            class="grid grid-cols-[2fr_1fr_1fr_1fr_1fr_auto] gap-4 mb-2 items-center"
        >
            <input v-model="vol.organization" placeholder="Organization" class="input" :readonly="!editMode" />
            <input v-model="vol.nature_of_work" placeholder="Position" class="input" :readonly="!editMode" />
            <input type="date" v-model="vol.from_date" class="input" :readonly="!editMode" />
            <input type="date" v-model="vol.to_date" class="input" :readonly="!editMode" />
            <input v-model="vol.hours" placeholder="Hours" class="input" :readonly="!editMode" />

            <button
            v-if="editMode"
            @click="removeRow('voluntary_work', index)"
            class="btn-icon h-10 w-10"
            >
            <TrashIcon class="h-5 w-5 text-white" />
            </button>
        </div>

        <button
            v-if="editMode"
            @click="addRow('voluntary_work', { organization: '', nature_of_work: '', from_date: '', to_date: '', hours: '' })"
            class="btn-icon h-10 w-10 mt-2"
        >
            <PlusIcon class="h-5 w-5 text-white" />
        </button>
        </section>


        <!-- VIII. Trainings -->
        <section>
        <h2 class="font-semibold text-lg mb-4">
          VII. Learning & Development / Trainings
        </h2>

        <!-- CSV Upload & Download (Only in Edit Mode) -->
        <div v-if="editMode" class="mb-6 p-4 border-2 border-dashed border-emerald-300 rounded-xl bg-emerald-50 flex flex-col md:flex-row items-center gap-4">

          <!-- File Input -->
          <div class="flex-1">
            <label class="block text-xs font-medium text-emerald-700 mb-1">Upload Trainings CSV</label>
            <input
              type="file"
              accept=".csv"
              @change="handleTrainingCSV"
              class="block w-full text-sm text-emerald-900 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700"
            />
          </div>

          <!-- Buttons -->
          <div class="flex gap-2 mt-2 md:mt-0">
            <!-- Upload -->
            <button
              type="button"
              @click="uploadTrainingCSV"
              :disabled="isUploading"
              class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ isUploading ? 'Uploading…' : 'Upload Trainings CSV' }}
            </button>

            <!-- Download Template -->
            <a
              :href="route('pds.trainings.download-template')"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm"
              download
            >
              Download Template
            </a>
          </div>
        </div>

        <!-- Table Header -->
        <div class="grid grid-cols-[2fr_1fr_1fr_1fr_1fr_2fr_auto] gap-4 mb-2 px-1 text-xs font-medium text-slate-500">
          <span>Title</span>
          <span>Date From</span>
          <span>Date To</span>
          <span>Hours</span>
          <span>L&amp;D Type</span>
          <span>Conducted By</span>
          <span></span>
        </div>

        <!-- Training Rows -->
        <div v-for="(train, index) in form.trainings" :key="index" class="grid grid-cols-[2fr_1fr_1fr_1fr_1fr_2fr_auto] gap-4 mb-2 items-center">
          <input v-model="train.training_title" placeholder="Title" class="input" :readonly="!editMode" />
          <input type="date" v-model="train.date_from" class="input" :readonly="!editMode" />
          <input type="date" v-model="train.date_to" class="input" :readonly="!editMode" />
          <input v-model="train.hours" placeholder="Hours" class="input" :readonly="!editMode" />
          <input v-model="train.training_type" placeholder="L&D Type" class="input" :readonly="!editMode" />
          <input v-model="train.conducted_by" placeholder="Conducted By" class="input" :readonly="!editMode" />

          <!-- Remove Row -->
          <button v-if="editMode" @click="removeRow('trainings', index)" class="btn-icon h-10 w-10 bg-red-500 hover:bg-red-600 rounded flex items-center justify-center">
            <TrashIcon class="h-5 w-5 text-white" />
          </button>
        </div>

        <!-- Add Row -->
        <div v-if="editMode" class="mt-2">
          <button @click="addRow('trainings', emptyTraining())" class="btn-icon h-10 w-10 bg-green-500 hover:bg-green-600 rounded flex items-center justify-center">
            <PlusIcon class="h-5 w-5 text-white" />
          </button>
        </div>
      </section>




        <!-- IX. Skills / Hobbies -->
        <section>
        <h2 class="font-semibold text-lg mb-4">VIII. Other Information</h2>
        <h4 class="font-semibold text-lg mb-4">Special Skills and Hobbies</h4>
        <div
            v-for="(skill, index) in form.skills_hobbies"
            :key="index"
            class="grid grid-cols-[1fr_auto] gap-4 mb-2 items-center"
        >
            <input v-model="skill.skills_hobbies" placeholder="Skill / Hobby" class="input" :readonly="!editMode" />

            <button
            v-if="editMode"
            @click="removeRow('skills_hobbies', index)"
            class="btn-icon h-10 w-10"
            >
            <TrashIcon class="h-5 w-5 text-white" />
            </button>
        </div>

        <button
            v-if="editMode"
            @click="addRow('skills_hobbies', { skills_hobbies: '' })"
            class="btn-icon h-10 w-10 mt-2"
        >
            <PlusIcon class="h-5 w-5 text-white" />
        </button>
        </section>


        <!-- X. Non-Academic Recognition -->
        <section>
        <h4 class="font-semibold text-lg mb-4"> Non-Academic Recognition</h4>

        <div
            v-for="(recog, index) in form.non_academic_recognition"
            :key="index"
            class="grid grid-cols-[1fr_auto] gap-4 mb-2 items-center"
        >
            <input v-model="recog.recognition" placeholder="Recognition" class="input" :readonly="!editMode" />
           

            <button
            v-if="editMode"
            @click="removeRow('non_academic_recognition', index)"
            class="btn-icon h-10 w-10"
            >
            <TrashIcon class="h-5 w-5 text-white" />
            </button>
        </div>

        <button
            v-if="editMode"
            @click="addRow('non_academic_recognition', { recognition: '' })"
            class="btn-icon h-10 w-10 mt-2"
        >
            <PlusIcon class="h-5 w-5 text-white" />
        </button>
        </section>
        <!-- XI. Membership in Organizations -->
        
        <section>
        
        <h4 class="font-semibold text-lg mb-4">Membership in Association/Organization</h4>
        <div
            v-for="(org, index) in form.membership_organizations"
            :key="index"
            class="grid grid-cols-[1fr_auto] gap-4 mb-2 items-center"
        >
            <input v-model="org.organization_name" placeholder="Organization Name" class="input" :readonly="!editMode" />

            <button
            v-if="editMode"
            @click="removeRow('membership_organizations', index)"
            class="btn-icon h-10 w-10"
            >
            <TrashIcon class="h-5 w-5 text-white" />
            </button>
        </div>

        <button
            v-if="editMode"
            @click="addRow('membership_organizations', { organization_name: '' })"
            class="btn-icon h-10 w-10 mt-2"
        >
            <PlusIcon class="h-5 w-5 text-white" />
        </button>
        </section>
        </div>

        <!-- ================= TAB 4 ================= -->
        <div v-show="activeTab === 4">
           <section>
        <h2 class="font-semibold text-lg mb-4">IX. Questions</h2>

        <!-- 34. Consanguinity / Affinity -->
        <div class="mb-2">
            <label class="flex items-center gap-2">
            <input
                type="checkbox"
                v-model="form.questions.q34a_third_degree"
                true-value="1"
                false-value="0"
                :disabled="!editMode"
            />
            Related within the third degree?
            </label>
            <textarea
            v-model="form.questions.q34a_details"
            placeholder="Details"
            class="input w-full mt-1"
            :readonly="!editMode"
            ></textarea>

            <label class="flex items-center gap-2 mt-2">
            <input
                type="checkbox"
                v-model="form.questions.q34b_fourth_degree"
                true-value="1"
                false-value="0"
                :disabled="!editMode"
            />
            Related within the fourth degree?
            </label>
            <textarea
            v-model="form.questions.q34b_details"
            placeholder="Details"
            class="input w-full mt-1"
            :readonly="!editMode"
            ></textarea>
        </div>

        <!-- 35. Administrative / Criminal Cases -->
        <div class="mb-2">
            <label class="flex items-center gap-2">
            <input
                type="checkbox"
                v-model="form.questions.q35a_admin_offense"
                true-value="1"
                false-value="0"
                :disabled="!editMode"
            />
            Have you been administratively sanctioned?
            </label>
            <textarea
            v-model="form.questions.q35a_details"
            placeholder="Details"
            class="input w-full mt-1"
            :readonly="!editMode"
            ></textarea>

            <label class="flex items-center gap-2 mt-2">
            <input
                type="checkbox"
                v-model="form.questions.q35b_criminal_charge"
                true-value="1"
                false-value="0"
                :disabled="!editMode"
            />
            Have you been criminally charged?
            </label>
            <div class="grid grid-cols-3 gap-4 mt-1">
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Date Filed</label>
              <input
                type="date"
                v-model="form.questions.q35b_date_filed"
                class="input w-full"
                :readonly="!editMode"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
              <input
                v-model="form.questions.q35b_status"
                placeholder="Status"
                class="input w-full"
                :readonly="!editMode"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Details</label>
              <input
                v-model="form.questions.q35b_details"
                placeholder="Details"
                class="input w-full"
                :readonly="!editMode"
              />
            </div>
            </div>
        </div>

        <!-- 36 -->
        <div class="mb-2">
            <label class="flex items-center gap-2">
            <input
                type="checkbox"
                v-model="form.questions.q36_convicted"
                true-value="1"
                false-value="0"
                :disabled="!editMode"
            />
            Convicted of any crime or violation?
            </label>
            <textarea
            v-model="form.questions.q36_details"
            placeholder="Details"
            class="input w-full mt-1"
            :readonly="!editMode"
            ></textarea>
        </div>

        <!-- 37 -->
        <div class="mb-2">
            <label class="flex items-center gap-2">
            <input
                type="checkbox"
                v-model="form.questions.q37_separated_service"
                true-value="1"
                false-value="0"
                :disabled="!editMode"
            />
            Separated from government service?
            </label>
            <textarea
            v-model="form.questions.q37_details"
            placeholder="Details"
            class="input w-full mt-1"
            :readonly="!editMode"
            ></textarea>
        </div>

        <!-- 38 -->
        <div class="mb-2">
            <label class="flex items-center gap-2">
            <input
                type="checkbox"
                v-model="form.questions.q38a_candidate"
                true-value="1"
                false-value="0"
                :disabled="!editMode"
            />
            Candidate for elective office?
            </label>
            <textarea
            v-model="form.questions.q38a_details"
            placeholder="Details"
            class="input w-full mt-1"
            :readonly="!editMode"
            ></textarea>

            <label class="flex items-center gap-2 mt-2">
            <input
                type="checkbox"
                v-model="form.questions.q38b_resigned_for_campaign"
                true-value="1"
                false-value="0"
                :disabled="!editMode"
            />
            Resigned from government post for candidacy?
            </label>
            <textarea
            v-model="form.questions.q38b_details"
            placeholder="Details"
            class="input w-full mt-1"
            :readonly="!editMode"
            ></textarea>
        </div>

        <!-- 39 -->
        <div class="mb-2">
            <label class="flex items-center gap-2">
            <input
                type="checkbox"
                v-model="form.questions.q39_immigrant"
                true-value="1"
                false-value="0"
                :disabled="!editMode"
            />
            Immigrant or permanent resident?
            </label>
            <input
            v-model="form.questions.q39_country"
            placeholder="Country"
            class="input w-full mt-1"
            :readonly="!editMode"
            />
        </div>

        <!-- 40 -->
        <div class="mb-2">
            <label class="flex items-center gap-2">
            <input
                type="checkbox"
                v-model="form.questions.q40a_indigenous"
                true-value="1"
                false-value="0"
                :disabled="!editMode"
            />
            Member of Indigenous Group?
            </label>
            <input
            v-model="form.questions.q40a_group"
            placeholder="Group Name"
            class="input w-full mt-1"
            :readonly="!editMode"
            />

            <label class="flex items-center gap-2 mt-2">
            <input
                type="checkbox"
                v-model="form.questions.q40b_pwd"
                true-value="1"
                false-value="0"
                :disabled="!editMode"
            />
            Person with Disability (PWD)?
            </label>
            <input
            v-model="form.questions.q40b_pwd_id"
            placeholder="PWD ID No."
            class="input w-full mt-1"
            :readonly="!editMode"
            />

            <label class="flex items-center gap-2 mt-2">
            <input
                type="checkbox"
                v-model="form.questions.q40c_solo_parent"
                true-value="1"
                false-value="0"
                :disabled="!editMode"
            />
            Solo Parent?
            </label>
            <input
            v-model="form.questions.q40c_solo_parent_id"
            placeholder="Solo Parent ID No."
            class="input w-full mt-1"
            :readonly="!editMode"
            />
        </div>
        </section>

        <!-- XII. References -->
        <section>
        <h2 class="font-semibold text-lg mb-4">X. References</h2>

        <div class="grid grid-cols-[1fr_2fr_1fr_auto] gap-4 mb-1 px-1 text-xs font-medium text-slate-500">
          <span>Name</span>
          <span>Office Address</span>
          <span>Contact No. / Email</span>
          <span></span>
        </div>

        <div
            v-for="(ref, index) in form.references"
            :key="index"
            class="grid grid-cols-[1fr_2fr_1fr_auto] gap-4 mb-2 items-center"
        >
            <input
            v-model="ref.name"
            placeholder="Name"
            class="input"
            :readonly="!editMode"
            />

            <input
            v-model="ref.office_address"
            placeholder="Office Address"
            class="input"
            :readonly="!editMode"
            />

            <input
            v-model="ref.contact_no_email"
            placeholder="Contact"
            class="input"
            :readonly="!editMode"
            />

            <button
            v-if="editMode"
            @click="removeRow('references', index)"
            class="btn-icon h-10 w-10"
            >
            <TrashIcon class="h-5 w-5 text-white" />
            </button>
        </div>

        <button
            v-if="editMode"
            @click="addRow('references', {
            name: '',
            office_address: '',
            contact_no_email: ''
            })"
            class="btn-icon h-10 w-10 mt-2"
        >
            <PlusIcon class="h-5 w-5 text-white" />
        </button>
        </section>


        <!-- XII. Other Information -->
        <section>
        <h2 class="font-semibold text-lg mb-4">XI. Other Information</h2>

        <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Government ID</label>
              <input v-model="form.other_info.government_id" class="input w-full" :readonly="!editMode" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">ID Number</label>
              <input v-model="form.other_info.id_no" class="input w-full" :readonly="!editMode" />
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mt-2">
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Date &amp; Place of Issuance</label>
              <input v-model="form.other_info.date_place_issuance" class="input w-full" :readonly="!editMode" />
            </div>
            <div v-if="editMode && props.pds">
              <label class="block text-xs font-medium text-slate-500 mb-1">Passport Photo</label>
              <input type="file" accept="image/*" :disabled="passportPhotoUploading" @change="e => uploadPassportPhoto(e.target.files[0])" class="input" />
              <p v-if="passportPhotoUploading" class="text-xs text-indigo-600 mt-1">Uploading…</p>
              <p v-if="passportPhotoError" class="text-xs text-red-600 mt-1">{{ passportPhotoError }}</p>
            </div>
        </div>

        <!-- Display existing photo if editing -->
        <div v-if="form.other_info.path_passport_photo && typeof form.other_info.path_passport_photo === 'string'" class="mt-2">
            <img :src="storageUrl(form.other_info.path_passport_photo)" alt="Passport Photo" class="h-32 w-32 object-cover rounded border" />
        </div>
        </section>
        </div>

        <!-- ── WES Tab (Work Experience Sheet — CS Form 212 Attachment) ──────── -->
        <div v-show="activeTab === 5" class="space-y-6 p-6">

          <!-- Header row -->
          <div>
            <h2 class="text-lg font-semibold text-slate-800">Work Experience Sheet</h2>
            <p class="text-xs text-slate-500 mt-0.5">Attachment to CS Form No. 212 — list from most recent first.</p>
          </div>

          <!-- Entries -->
          <div v-for="(wes, wi) in form.work_experience_sheets" :key="wi"
            class="bg-white border border-slate-200 rounded-xl p-5 space-y-4 shadow-sm">

            <!-- Entry header -->
            <div class="flex items-center justify-between">
              <span class="text-sm font-semibold text-indigo-700">Entry #{{ wi + 1 }}</span>
              <button v-if="editMode"
                @click="form.work_experience_sheets.splice(wi, 1)"
                class="p-1.5 rounded-lg hover:bg-red-50 text-slate-300 hover:text-red-500 transition-colors">
                <TrashIcon class="h-4 w-4" />
              </button>
            </div>

            <!-- Duration -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Duration From</label>
                <input v-model="wes.from_date" type="text" placeholder="e.g. 11 February 2011 or 2002"
                  :readonly="!editMode"
                  class="input w-full" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Duration To</label>
                <input v-model="wes.to_date" type="text" placeholder="e.g. Present or 10 February 2011"
                  :readonly="!editMode"
                  class="input w-full" />
              </div>
            </div>

            <!-- Position -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Position</label>
              <input v-model="wes.position" type="text" placeholder="e.g. Human Resource Management Officer III"
                :readonly="!editMode"
                class="input w-full" />
            </div>

            <!-- Office/Unit -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Name of Office/Unit</label>
              <input v-model="wes.office_unit" type="text" placeholder="e.g. Finance and Administrative Service"
                :readonly="!editMode"
                class="input w-full" />
            </div>

            <!-- Immediate Supervisor -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Immediate Supervisor</label>
              <input v-model="wes.immediate_supervisor" type="text" placeholder="e.g. Maria Estrada"
                :readonly="!editMode"
                class="input w-full" />
            </div>

            <!-- Agency/Organization -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Name of Agency/Organization and Location</label>
              <textarea v-model="wes.agency_organization_location"
                rows="2" placeholder="e.g. Department of Human Resources, Metro Manila"
                :readonly="!editMode"
                class="input w-full resize-none"></textarea>
            </div>

            <!-- Accomplishments -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-2">List of Accomplishments and Contributions <span class="text-slate-400">(if any)</span></label>
              <div v-for="(item, ai) in wes.accomplishments" :key="ai" class="flex items-center gap-2 mb-1.5">
                <span class="text-slate-400 text-sm">•</span>
                <input v-model="wes.accomplishments[ai]" type="text"
                  :readonly="!editMode"
                  placeholder="e.g. Developed recruitment plan"
                  class="input flex-1" />
                <button v-if="editMode && wes.accomplishments.length > 1"
                  @click="wes.accomplishments.splice(ai, 1)"
                  class="p-1 rounded hover:bg-red-50 text-slate-300 hover:text-red-500">
                  <TrashIcon class="h-3.5 w-3.5" />
                </button>
              </div>
              <button v-if="editMode"
                @click="wes.accomplishments.push('')"
                class="mt-1 text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                + Add Item
              </button>
            </div>

            <!-- Summary of Duties -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-2">Summary of Actual Duties</label>
              <div v-for="(item, di) in wes.summary_of_duties" :key="di" class="flex items-start gap-2 mb-1.5">
                <span class="text-slate-400 text-sm mt-1.5">•</span>
                <textarea v-model="wes.summary_of_duties[di]"
                  rows="2" :readonly="!editMode"
                  placeholder="e.g. Responsible for the management of the recruitment and selection process…"
                  class="input flex-1 resize-none text-sm"></textarea>
                <button v-if="editMode && wes.summary_of_duties.length > 1"
                  @click="wes.summary_of_duties.splice(di, 1)"
                  class="p-1 rounded hover:bg-red-50 text-slate-300 hover:text-red-500 mt-1">
                  <TrashIcon class="h-3.5 w-3.5" />
                </button>
              </div>
              <button v-if="editMode"
                @click="wes.summary_of_duties.push('')"
                class="mt-1 text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                + Add Item
              </button>
            </div>
          </div>

          <!-- Empty state -->
          <div v-if="form.work_experience_sheets.length === 0"
            class="py-12 text-center text-slate-400 text-sm border border-dashed border-slate-200 rounded-xl">
            No work experience entries yet. Click "Add Work Experience" to start.
          </div>

          <!-- Add entry button -->
          <button v-if="editMode"
            @click="form.work_experience_sheets.push({
              from_date: '', to_date: '', position: '', office_unit: '',
              immediate_supervisor: '', agency_organization_location: '',
              accomplishments: [''], summary_of_duties: [''],
              sort_order: form.work_experience_sheets.length,
            })"
            class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            <PlusIcon class="h-4 w-4" /> Add Work Experience
          </button>
        </div>

        <!-- Submit -->
        <div v-if="editMode" class="pt-6 flex justify-end gap-2 border-t border-slate-100">
          <button @click="submit" :disabled="form.processing" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
            {{ form.processing ? 'Saving…' : (props.pds ? 'Update PDS' : 'Save PDS') }}
          </button>
        </div>

      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
.input { @apply rounded-lg border border-slate-200 bg-white px-3 py-2 w-full text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400; }
.btn-icon { @apply bg-indigo-600 p-2 rounded-lg flex items-center justify-center; }
.btn-success { @apply bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition-colors; }
</style>
