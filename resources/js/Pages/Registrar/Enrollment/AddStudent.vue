<script setup>
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AddressPicker from '@/Components/AddressPicker.vue'
import { UserPlusIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  schoolYear: Object,
})

const form = useForm({
  pisays_id:               '',
  grade_level:              7,
  enrollment_type:          'new',
  lastname:                '',
  firstname:               '',
  middlename:              '',
  birthday:                '',
  sex:                     '',
  birth_place:             '',
  lrn:                     '',
  previous_school:         '',
  father_name:             '',
  father_occupation:       '',
  father_contact:          '',
  mother_name:             '',
  mother_occupation:       '',
  mother_contact:          '',
  guardian_name:           '',
  guardian_contact:        '',
  address:              '',
  address_house:        '',
  address_street:       '',
  address_subdivision:  '',
  address_barangay:     '',
  address_city:         '',
  address_province:     '',
  address_region:       '',
  address_zip:          '',
  contact_no:              '',
  email:                   '',
})

const addressModel = computed({
  get: () => ({
    house:       form.address_house,
    street:      form.address_street,
    subdivision: form.address_subdivision,
    barangay:    form.address_barangay,
    city:        form.address_city,
    province:    form.address_province,
    region:      form.address_region,
    zip:         form.address_zip,
  }),
  set: (val) => {
    form.address_house       = val.house        ?? ''
    form.address_street      = val.street       ?? ''
    form.address_subdivision = val.subdivision  ?? ''
    form.address_barangay    = val.barangay     ?? ''
    form.address_city        = val.city         ?? ''
    form.address_province    = val.province     ?? ''
    form.address_region      = val.region       ?? ''
    form.address_zip         = val.zip          ?? ''
    form.address = [
      val.house, val.street, val.subdivision,
      val.barangay ? 'Brgy. ' + val.barangay : '',
      val.city, val.province,
    ].filter(Boolean).join(', ')
  },
})

function submit() {
  form.post(route('registrar.enrollment.create-student'))
}

const inputCls = 'rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full'
const labelCls = 'block text-xs font-medium text-slate-600 mb-1'
</script>

<template>
  <Head title="Add Student" />
  <AdminLayout title="Add Student">
    <AppPageHeader title="Add Student" subtitle="Register a walk-in student directly — for admissions without a self-service application on file">
      <template #actions>
        <a :href="route('registrar.enrollment.index')" class="text-sm text-indigo-600 hover:underline">Back to Enrollment Management</a>
      </template>
    </AppPageHeader>

    <div class="mb-4 rounded-lg bg-warning-50 border border-warning-100 px-4 py-2 text-xs text-warning-700">
      <strong>All fields are required.</strong> Enter <strong>N/A</strong> for any field that does not apply.
    </div>

    <form @submit.prevent="submit" class="space-y-5">

      <!-- Enrollment -->
      <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 p-5">
        <h2 class="text-base font-semibold text-slate-800 mb-4">Enrollment</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label :class="labelCls">PISAY ID <span class="text-danger-500">*</span></label>
            <input v-model="form.pisays_id" :class="inputCls" placeholder="Assign a unique PISAY ID" />
            <p v-if="form.errors.pisays_id" class="text-xs text-danger-500 mt-1">{{ form.errors.pisays_id }}</p>
          </div>
          <div>
            <label :class="labelCls">Grade Level <span class="text-danger-500">*</span></label>
            <select v-model="form.grade_level" :class="inputCls">
              <option v-for="g in [7,8,9,10,11,12]" :key="g" :value="g">Grade {{ g }}</option>
            </select>
          </div>
          <div>
            <label :class="labelCls">Enrollment Type <span class="text-danger-500">*</span></label>
            <select v-model="form.enrollment_type" :class="inputCls">
              <option value="new">New</option>
              <option value="returning">Returning</option>
              <option value="transferee">Transferee</option>
              <option value="returnee">Returnee</option>
            </select>
          </div>
        </div>
        <p v-if="schoolYear" class="text-xs text-slate-500 mt-3">Enrolling for School Year {{ schoolYear.name }}. Section is assigned afterward via "Assign by Grade List".</p>
      </div>

      <!-- Personal Info -->
      <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 p-5">
        <h2 class="text-base font-semibold text-slate-800 mb-4">Personal Information</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2 grid grid-cols-2 sm:grid-cols-3 gap-4">
            <div>
              <label :class="labelCls">Last Name <span class="text-danger-500">*</span></label>
              <input v-model="form.lastname" :class="inputCls" placeholder="Dela Cruz" />
              <p v-if="form.errors.lastname" class="text-xs text-danger-500 mt-1">{{ form.errors.lastname }}</p>
            </div>
            <div>
              <label :class="labelCls">First Name <span class="text-danger-500">*</span></label>
              <input v-model="form.firstname" :class="inputCls" placeholder="Juan" />
              <p v-if="form.errors.firstname" class="text-xs text-danger-500 mt-1">{{ form.errors.firstname }}</p>
            </div>
            <div>
              <label :class="labelCls">Middle Name <span class="text-danger-500">*</span></label>
              <input v-model="form.middlename" :class="inputCls" placeholder="Santos or N/A" />
              <p v-if="form.errors.middlename" class="text-xs text-danger-500 mt-1">{{ form.errors.middlename }}</p>
            </div>
          </div>
          <div>
            <label :class="labelCls">Birthday <span class="text-danger-500">*</span></label>
            <input v-model="form.birthday" type="date" :class="inputCls" />
            <p v-if="form.errors.birthday" class="text-xs text-danger-500 mt-1">{{ form.errors.birthday }}</p>
          </div>
          <div>
            <label :class="labelCls">Sex <span class="text-danger-500">*</span></label>
            <select v-model="form.sex" :class="inputCls">
              <option value="">— Select —</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
            <p v-if="form.errors.sex" class="text-xs text-danger-500 mt-1">{{ form.errors.sex }}</p>
          </div>
          <div class="sm:col-span-2">
            <label :class="labelCls">Place of Birth <span class="text-danger-500">*</span></label>
            <input v-model="form.birth_place" :class="inputCls" placeholder="City/Municipality, Province" />
            <p v-if="form.errors.birth_place" class="text-xs text-danger-500 mt-1">{{ form.errors.birth_place }}</p>
          </div>
          <div class="sm:col-span-2">
            <label :class="labelCls">LRN (Learner Reference Number) <span class="text-danger-500">*</span></label>
            <input v-model="form.lrn" :class="inputCls" placeholder="12-digit LRN or N/A" maxlength="30" />
            <p v-if="form.errors.lrn" class="text-xs text-danger-500 mt-1">{{ form.errors.lrn }}</p>
          </div>
        </div>
      </div>

      <!-- Previous School -->
      <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 p-5">
        <h2 class="text-base font-semibold text-slate-800 mb-4">Previous School</h2>
        <div>
          <label :class="labelCls">Previous School Name <span class="text-danger-500">*</span></label>
          <input v-model="form.previous_school" :class="inputCls" placeholder="School name or N/A" />
          <p v-if="form.errors.previous_school" class="text-xs text-danger-500 mt-1">{{ form.errors.previous_school }}</p>
        </div>
      </div>

      <!-- Parent / Guardian -->
      <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 p-5">
        <h2 class="text-base font-semibold text-slate-800 mb-4">Parent / Guardian Information</h2>
        <div class="space-y-5">
          <div>
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">Father</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <input v-model="form.father_name" :class="inputCls" placeholder="Full name or N/A *" />
                <p v-if="form.errors.father_name" class="text-xs text-danger-500 mt-1">{{ form.errors.father_name }}</p>
              </div>
              <div>
                <input v-model="form.father_occupation" :class="inputCls" placeholder="Occupation or N/A *" />
                <p v-if="form.errors.father_occupation" class="text-xs text-danger-500 mt-1">{{ form.errors.father_occupation }}</p>
              </div>
              <div>
                <input v-model="form.father_contact" :class="inputCls" placeholder="Contact number or N/A *" />
                <p v-if="form.errors.father_contact" class="text-xs text-danger-500 mt-1">{{ form.errors.father_contact }}</p>
              </div>
            </div>
          </div>
          <div>
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">Mother</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <input v-model="form.mother_name" :class="inputCls" placeholder="Full name or N/A *" />
                <p v-if="form.errors.mother_name" class="text-xs text-danger-500 mt-1">{{ form.errors.mother_name }}</p>
              </div>
              <div>
                <input v-model="form.mother_occupation" :class="inputCls" placeholder="Occupation or N/A *" />
                <p v-if="form.errors.mother_occupation" class="text-xs text-danger-500 mt-1">{{ form.errors.mother_occupation }}</p>
              </div>
              <div>
                <input v-model="form.mother_contact" :class="inputCls" placeholder="Contact number or N/A *" />
                <p v-if="form.errors.mother_contact" class="text-xs text-danger-500 mt-1">{{ form.errors.mother_contact }}</p>
              </div>
            </div>
          </div>
          <div>
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3">Guardian <span class="font-normal text-slate-400">(if different from parents)</span></p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <input v-model="form.guardian_name" :class="inputCls" placeholder="Full name or N/A *" />
                <p v-if="form.errors.guardian_name" class="text-xs text-danger-500 mt-1">{{ form.errors.guardian_name }}</p>
              </div>
              <div>
                <input v-model="form.guardian_contact" :class="inputCls" placeholder="Contact number or N/A *" />
                <p v-if="form.errors.guardian_contact" class="text-xs text-danger-500 mt-1">{{ form.errors.guardian_contact }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Contact & Address -->
      <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200/70 p-5">
        <h2 class="text-base font-semibold text-slate-800 mb-4">Contact & Address</h2>
        <div class="space-y-4">
          <div>
            <label :class="labelCls">Home Address <span class="text-danger-500">*</span></label>
            <AddressPicker v-model="addressModel" />
            <p v-if="form.errors.address_region" class="text-xs text-danger-500 mt-1">{{ form.errors.address_region }}</p>
            <p v-if="form.errors.address_city" class="text-xs text-danger-500 mt-1">{{ form.errors.address_city }}</p>
            <p v-if="form.errors.address_barangay" class="text-xs text-danger-500 mt-1">{{ form.errors.address_barangay }}</p>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label :class="labelCls">Student Contact No. <span class="text-danger-500">*</span></label>
              <input v-model="form.contact_no" :class="inputCls" placeholder="09XXXXXXXXX or N/A" />
              <p v-if="form.errors.contact_no" class="text-xs text-danger-500 mt-1">{{ form.errors.contact_no }}</p>
            </div>
            <div>
              <label :class="labelCls">Email Address <span class="text-danger-500">*</span></label>
              <input v-model="form.email" :class="inputCls" placeholder="email@example.com or N/A" />
              <p v-if="form.errors.email" class="text-xs text-danger-500 mt-1">{{ form.errors.email }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-3">
        <a :href="route('registrar.enrollment.index')">
          <AppButton variant="secondary" type="button">Cancel</AppButton>
        </a>
        <AppButton type="submit" :loading="form.processing">
          <UserPlusIcon class="w-4 h-4" />
          {{ form.processing ? 'Enrolling…' : 'Enroll Student' }}
        </AppButton>
      </div>
    </form>
  </AdminLayout>
</template>
