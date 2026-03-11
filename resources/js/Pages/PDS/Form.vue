<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm, router } from '@inertiajs/vue3'
import { useSubmit } from '@/Composables/useSubmit'
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
      { position: '', agency: '', salary: '', salary_grade: '', appointment_status: '', government_service: '', from_date: '', to_date: '' },
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
      onError: (page) => {
        const serverErrors = Object.values(page.props.errors).join('<br>')
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
      onError: (page) => {
        const serverErrors = Object.values(page.props.errors).join('<br>')
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
    <div class="max-w-7xl mx-auto p-6">
      <div class="bg-white shadow rounded-lg p-6">

        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
          <h1 class="text-2xl font-bold">Personal Data Sheet</h1>

          <div class="flex gap-2">
            <button v-if="props.pds && !editMode" @click="editMode = true" class="btn-icon">
              <PencilSquareIcon class="h-5 w-5 text-white" />
            </button>
            <!-- 
            <button
              v-if="props.pds && !editMode"
              class="btn-icon bg-green-600"
              @click="exportPDF(props.pds.id)"
            >
              <PrinterIcon class="h-5 w-5 text-white" />
            </button>
          -->
            <button v-if="props.pds && !editMode" @click="exportPDS(props.pds.id)" class="btn-icon bg-indigo-600">
              <DocumentArrowDownIcon class="h-5 w-5 text-white" />
            </button>
          </div>
        </div>

        <!-- Tabs -->
        <div class="border-b mb-6 flex gap-4">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            class="px-4 py-2 font-semibold border-b-2"
            :class="activeTab === tab.id ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-400'"
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
    <input v-model="form.personal_info.surname" placeholder="Surname" class="input" :readonly="!editMode" />
    <input v-model="form.personal_info.first_name" placeholder="First Name" class="input" :readonly="!editMode" />
    <input v-model="form.personal_info.middle_name" placeholder="Middle Name" class="input" :readonly="!editMode" />
  </div>

  <div class="grid grid-cols-3 gap-4 mt-2">
    <input v-model="form.personal_info.name_ext" placeholder="Extension" class="input" :readonly="!editMode" />
    <input type="date" v-model="form.personal_info.date_of_birth" class="input" :readonly="!editMode" />
    <input v-model="form.personal_info.place_of_birth" placeholder="Place of Birth" class="input" :readonly="!editMode" />
  </div>

  <!-- Sex, Civil Status, Citizenship -->
  <div class="grid grid-cols-3 gap-4 mt-2">
  <!-- Sex at Birth -->
  <select v-model="form.personal_info.sex_at_birth" class="input" :disabled="!editMode">
    <option value="" disabled>Select Sex</option>
    <option value="Male">Male</option>
    <option value="Female">Female</option>
  </select>

  <!-- Civil Status -->
  <select v-model="form.personal_info.civil_status" class="input" :disabled="!editMode">
    <option value="" disabled>Select Civil Status</option>
    <option value="Single">Single</option>
    <option value="Married">Married</option>
    <option value="Widowed">Widowed</option>
    <option value="Separated">Separated</option>
  </select>

  <!-- Citizenship Type -->
  <select v-model="form.personal_info.citizenship_type" class="input" :disabled="!editMode">
    <option value="" disabled>Select Citizenship</option>
    <option value="Filipino">Filipino</option>
    <option value="Dual">Dual Citizenship</option>
  </select>

  <!-- Dual Citizenship Fields -->
  <template v-if="form.personal_info.citizenship_type === 'Dual'">
    <select
      v-model="form.personal_info.citizenship_dual_type"
      class="input col-span-1"
      :disabled="!editMode"
    >
      <option value="" disabled>Select Type</option>
      <option value="By Birth">By Birth</option>
      <option value="By Naturalization">By Naturalization</option>
    </select>

    <input
      v-model="form.personal_info.citizenship_dual_country"
      placeholder="Country"
      class="input col-span-1"
      :readonly="!editMode"
    />
  </template>
</div>

  

  <!-- Physical Info -->
  <div class="grid grid-cols-3 gap-4 mt-2">
    <input v-model="form.personal_info.height" placeholder="Height" class="input" :readonly="!editMode" />
    <input v-model="form.personal_info.weight" placeholder="Weight" class="input" :readonly="!editMode" />
    <input v-model="form.personal_info.blood_type" placeholder="Blood Type" class="input" :readonly="!editMode" />
  </div>

  <!-- Government Numbers -->
  <div class="grid grid-cols-3 gap-4 mt-2">
    <input v-model="form.personal_info.umid_id_no" placeholder="UMID ID No." class="input" :readonly="!editMode" />
    <input v-model="form.personal_info.pagibig_id_no" placeholder="PAG-IBIG ID No." class="input" :readonly="!editMode" />
    <input v-model="form.personal_info.philhealth_no" placeholder="PhilHealth No." class="input" :readonly="!editMode" />
  </div>

  <div class="grid grid-cols-3 gap-4 mt-2">
    <input v-model="form.personal_info.philsys_no" placeholder="PhilSys No." class="input" :readonly="!editMode" />
    <input v-model="form.personal_info.tin_no" placeholder="TIN No." class="input" :readonly="!editMode" />
    <input v-model="form.personal_info.agency_employee_no" placeholder="Agency Employee No." class="input" :readonly="!editMode" />
  </div>

  <div class="grid grid-cols-3 gap-4 mt-2">
    <input v-model="form.personal_info.telephone_no" placeholder="Telephone No." class="input" :readonly="!editMode" />
    <input v-model="form.personal_info.mobile_no" placeholder="Mobile No." class="input" :readonly="!editMode" />
    <input v-model="form.personal_info.email_address" placeholder="Email Address" class="input" :readonly="!editMode" />
  </div>

  <!-- Residential Address -->
  <h4 class="font-semibold text-lg mt-4 mb-2">Residential Address</h4>
  <div class="grid grid-cols-3 gap-4">
    <input v-model="form.personal_info.residential_house" placeholder="House/Block/Lot No." class="input" :readonly="!editMode" />
    <input v-model="form.personal_info.residential_street" placeholder="Street" class="input" :readonly="!editMode" />
    <input v-model="form.personal_info.residential_subdivision" placeholder="Subdivision/Village" class="input" :readonly="!editMode" />
  </div>
  <div class="grid grid-cols-3 gap-4 mt-2">
    <input v-model="form.personal_info.residential_barangay" placeholder="Barangay" class="input" :readonly="!editMode" />
    <input v-model="form.personal_info.residential_city" placeholder="City/Municipality" class="input" :readonly="!editMode" />
    <input v-model="form.personal_info.residential_province" placeholder="Province" class="input" :readonly="!editMode" />
  </div>
  <div class="grid grid-cols-4 gap-4 mt-2">
    <input v-model="form.personal_info.residential_zip_code" placeholder="Zip Code" class="input" :readonly="!editMode" />
  </div>

  <!-- Permanent Address -->
  <div class="flex items-center gap-2 mt-4">
    <input type="checkbox" v-model="sameAsResidential" :disabled="!editMode" id="sameAsRes" />
    <label for="sameAsRes" class="text-sm">Permanent Address is same as Residential</label>
  </div>

  <h4 class="font-semibold text-lg mt-2 mb-2">Permanent Address</h4>
  <div class="grid grid-cols-3 gap-4">
    <input v-model="form.personal_info.permanent_house" placeholder="House/Block/Lot No." class="input" :readonly="!editMode || sameAsResidential" />
    <input v-model="form.personal_info.permanent_street" placeholder="Street" class="input" :readonly="!editMode || sameAsResidential" />
    <input v-model="form.personal_info.permanent_subdivision" placeholder="Subdivision/Village" class="input" :readonly="!editMode || sameAsResidential" />
  </div>
  <div class="grid grid-cols-3 gap-4 mt-2">
    <input v-model="form.personal_info.permanent_barangay" placeholder="Barangay" class="input" :readonly="!editMode || sameAsResidential" />
    <input v-model="form.personal_info.permanent_city" placeholder="City/Municipality" class="input" :readonly="!editMode || sameAsResidential" />
    <input v-model="form.personal_info.permanent_province" placeholder="Province" class="input" :readonly="!editMode || sameAsResidential" />
  </div>
  <div class="grid grid-cols-4 gap-4 mt-2">
    <input v-model="form.personal_info.permanent_zip_code" placeholder="Zip Code" class="input" :readonly="!editMode || sameAsResidential" />
  </div>
</section>
        <br>
        <!-- II. Family Background -->
        <section>
        <h2 class="font-semibold text-lg mb-4">II. Family Background</h2>

        <!-- Spouse -->
        <div class="grid grid-cols-4 gap-4">
            <input v-model="form.family_background.spouse_surname" placeholder="Spouse's Surname" class="input" :readonly="!editMode" />
            <input v-model="form.family_background.spouse_first_name" placeholder="Spouse's First Name" class="input" :readonly="!editMode" />
            <input v-model="form.family_background.spouse_middle_name" placeholder="Spouse's Middle Name" class="input" :readonly="!editMode" />
            <input v-model="form.family_background.spouse_name_ext" placeholder="Spouse's Name Extension" class="input" :readonly="!editMode" />
        </div>
        <div class="grid grid-cols-4 gap-4 mt-2">
            <input v-model="form.family_background.spouse_occupation" placeholder="Spouse's Occupation" class="input" :readonly="!editMode" />
            <input v-model="form.family_background.spouse_employer" placeholder="Spouse's Employer/Business" class="input" :readonly="!editMode" />
            <input v-model="form.family_background.spouse_business_address" placeholder="Business Address" class="input" :readonly="!editMode" />
            <input v-model="form.family_background.spouse_telephone_no" placeholder="Telephone No." class="input" :readonly="!editMode" />
        </div>

        <!-- Father -->
        <div class="grid grid-cols-3 gap-4 mt-4">
            <input v-model="form.family_background.father_surname" placeholder="Father's Surname" class="input" :readonly="!editMode" />
            <input v-model="form.family_background.father_first_name" placeholder="Father's First Name" class="input" :readonly="!editMode" />
            <input v-model="form.family_background.father_middle_name" placeholder="Father's Middle Name" class="input" :readonly="!editMode" />
        </div>
        <div class="grid grid-cols-4 gap-4 mt-2">
            <input v-model="form.family_background.father_name_ext" placeholder="Father's Name Extension (Jr., Sr., III)" class="input" :readonly="!editMode" />
        </div>

        <!-- Mother -->
        <div class="grid grid-cols-3 gap-4 mt-4">
            <input v-model="form.family_background.mother_maiden_surname" placeholder="Mother's Maiden Surname" class="input" :readonly="!editMode" />
            <input v-model="form.family_background.mother_maiden_first_name" placeholder="Mother's First Name" class="input" :readonly="!editMode" />
            <input v-model="form.family_background.mother_maiden_middle_name" placeholder="Mother's Maiden Middle Name" class="input" :readonly="!editMode" />
        </div>
        </section>
        <br>

        <!-- III. Children -->
        <section>
        <h2 class="font-semibold text-lg mb-4">Children</h2>

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
            year_graduated: '',
            degree: '',
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
            @click="addRow('eligibility', { eligibility: '', rating: '', date_of_examination: '', place_of_examination: '', license_no: '', date_validity: '' })"
            class="btn-icon h-10 w-10 mt-2"
        >
            <PlusIcon class="h-5 w-5 text-white" />
        </button>
        </section>


        <!-- VI. Work Experience -->
        <section>
        <h2 class="font-semibold text-lg mb-4">V. Work Experience</h2>

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
            @click="addRow('work_experience', { position: '', agency: '', salary: '', salary_grade: '', status_of_appointment: '', government_service: '', date_from: '', date_to: '' })"
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
            @click="addRow('voluntary_work', { organization: '', position: '', date_from: '', date_to: '', hours: '' })"
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
        <div v-if="editMode" class="mb-6 p-4 border-2 border-dashed border-green-500 rounded-lg bg-green-50 flex flex-col md:flex-row items-center gap-4">
          
          <!-- File Input -->
          <div class="flex-1">
            <label class="block text-sm font-medium text-green-700 mb-1">Upload Trainings CSV</label>
            <input
              type="file"
              accept=".csv"
              @change="handleTrainingCSV"
              class="block w-full text-sm text-green-900 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-green-500 file:text-white hover:file:bg-green-600"
            />
          </div>

          <!-- Buttons -->
          <div class="flex gap-2 mt-2 md:mt-0">
            <!-- Upload -->
            <button
              type="button"
              @click="uploadTrainingCSV"
              :disabled="isUploading"
              class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold rounded shadow disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ isUploading ? 'Uploading…' : 'Upload Trainings CSV' }}
            </button>

            <!-- Download Template -->
            <a
              :href="route('pds.trainings.download-template')" 
              class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded shadow flex items-center gap-2"
              download
            >
              Download Template
            </a>
          </div>
        </div>

        <!-- Table Header 
        <div class="grid grid-cols-[2fr_1fr_1fr_1fr_1fr_2fr_auto] gap-4 font-semibold text-gray-700 mb-2">
          <div>Title</div>
          <div>Date From</div>
          <div>Date To</div>
          <div>Hours</div>
          <div>L&D Type</div>
          <div>Conducted By</div>
          <div v-if="editMode">Actions</div>
        </div> -->

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
            <input
                type="date"
                v-model="form.questions.q35b_date_filed"
                class="input"
                :readonly="!editMode"
            />
            <input
                v-model="form.questions.q35b_status"
                placeholder="Status"
                class="input"
                :readonly="!editMode"
            />
            <input
                v-model="form.questions.q35b_details"
                placeholder="Details"
                class="input"
                :readonly="!editMode"
            />
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
            <input v-model="form.other_info.government_id" placeholder="Government ID" class="input" :readonly="!editMode" />
            <input v-model="form.other_info.id_no" placeholder="ID Number" class="input" :readonly="!editMode" />
        </div>

        <div class="grid grid-cols-2 gap-4 mt-2">
            <input v-model="form.other_info.date_place_issuance" placeholder="Date & Place of Issuance" class="input" :readonly="!editMode" />
            <input v-if="editMode" type="file" @change="e => form.other_info.path_passport_photo = e.target.files[0]" class="input" />
        </div>

        <!-- Display existing photo if editing -->
        <div v-if="form.other_info.path_passport_photo && typeof form.other_info.path_passport_photo === 'string'" class="mt-2">
            <img :src="`/storage/${form.other_info.path_passport_photo}`" alt="Passport Photo" class="h-32 w-32 object-cover rounded border" />
        </div>
        </section>
        </div>

        <!-- Submit -->
        <div v-if="editMode" class="pt-6 flex justify-end">
          <button @click="submit" :disabled="form.processing" class="btn-success disabled:opacity-50 disabled:cursor-not-allowed">
            {{ form.processing ? 'Saving…' : (props.pds ? 'Update PDS' : 'Save PDS') }}
          </button>
        </div>

      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
.input { @apply border rounded px-3 py-2 w-full; }
.btn-icon { @apply bg-blue-600 p-2 rounded flex items-center justify-center; }
.btn-success { @apply bg-green-600 text-white px-6 py-2 rounded; }
</style>
