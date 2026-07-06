<script setup>
import { Head, usePage, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref, computed, watch, onMounted } from 'vue'
import { EyeIcon, PrinterIcon, ClockIcon, HeartIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'
import { useSubmit } from '@/Composables/useSubmit'
import { statusBadgeClass, badgeBase } from '@/Composables/useStatusBadge.js'
import { confirmDelete as confirmDeleteDialog } from '@/Composables/useConfirm.js'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import EmptyState from '@/Components/EmptyState.vue'

const props = defineProps({ consultations: Object, physicianSchedules: Array });
const page = usePage();
const { isSubmitting: isDeleting, submit: deleteSubmit } = useSubmit();

const physicianSchedules = ref(props.physicianSchedules || []);

function formatDate(d) {
  if (!d) return '—'
  try {
    const dt = new Date(d)
    return dt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
  } catch (e) {
    return d
  }
}

function formatDateTime(d) {
  if (!d) return '—'
  try {
    const dt = new Date(d)
    return dt.toLocaleString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' })
  } catch (e) {
    return d
  }
}

const openSchedule = ref(null);
const scheduleForm = useForm({ scheduled_at: '', notes: '' });
const openAppointment = ref(null);
const appointmentForm = useForm({
  consultation_type: 'walk-in',
  scheduled_at: '',
  doctor_schedule_id: null,
  physician_schedule_id: null,
  concerns: [],
  other_concern: '',
  injury_type: '',
  concern: '',
  reason: '',
  requestor_id: null,
  requestor_type: '',
  patient_type: 'student',
  pisay: '',
  employee_id: null,
  requestor: '',
});
const viewConsultation = ref(null);
const openVitals = ref(null);
const vitalsForm = useForm({
  temperature: '',
  pulse_rate: '',
  oxygen: '',
  blood_pressure: '',
  medicine_given: '',
  action_taken: '',
  time_start: '',
  time_out: '',
  date_attended: '',
  disposition: '',
  dispositions: [],
  employee_disposition: '',
  others_disposition: '',
  actions: [],
  head_check_reason: '',
  parent_notified_at: '',
  others_action: '',

});

const openView = (c) => { viewConsultation.value = c };
const closeView = () => { viewConsultation.value = null };
const openVitalsFor = (c) => {
  openVitals.value = c;
  // populate form with existing values when available
  vitalsForm.reset();
  vitalsForm.temperature = c.temperature ?? c.temp ?? '';
  vitalsForm.pulse_rate = c.pulse_rate ?? c.pulse ?? c.heart_rate ?? '';
  vitalsForm.oxygen = c.oxygen ?? c.oxygen_saturation ?? c.spo2 ?? '';
  vitalsForm.blood_pressure = c.blood_pressure ?? c.bp ?? c.bloodpressure ?? '';
  vitalsForm.medicine_given = c.medicine_given ?? c.medicines ?? '';
  // reset structured action fields; keep original action text as fallback
  vitalsForm.actions = [];
  vitalsForm.head_check_reason = '';
  vitalsForm.parent_notified_at = '';
  vitalsForm.others_action = '';
  vitalsForm.action_taken = c.action_taken ?? '';

  // helper: convert various datetime formats into `YYYY-MM-DDTHH:MM` for datetime-local inputs
  const toDatetimeLocal = (val) => {
    if (!val) return '';
    try {
      const d = new Date(val);
      if (isNaN(d.getTime())) return '';
      const pad = (n) => String(n).padStart(2, '0');
      const yyyy = d.getFullYear();
      const mm = pad(d.getMonth() + 1);
      const dd = pad(d.getDate());
      const hh = pad(d.getHours());
      const min = pad(d.getMinutes());
      return `${yyyy}-${mm}-${dd}T${hh}:${min}`;
    } catch (e) {
      return '';
    }
  };

  vitalsForm.time_start = toDatetimeLocal(c.time_start ?? c.time_in ?? '');
  vitalsForm.time_out = toDatetimeLocal(c.time_out ?? c.time_end ?? '');
  vitalsForm.date_attended = toDatetimeLocal(c.date_attended ?? '');

  // Parse structured action_taken into checkbox values when possible
  const parseActions = (text) => {
    if (!text) return [];
    const parts = text.split(';').map(s => s.trim()).filter(Boolean);
    const known = [
      'Student laid/sat in clinic for 20 minutes or less',
      'Student laid/sat in clinic for 20 minutes or more',
      'Temperature Taken',
      'Ice Pack applied to the affected Area',
      'Affected area cleaned',
      'Band aid applied to affected area',
      'Head checkup',
      'Parent/Guardian Notified',
      'Medications Given',
      'Others'
    ];
    const res = [];
    parts.forEach(p => {
      for (const k of known) {
        if (p.toLowerCase().startsWith(k.toLowerCase()) || p.toLowerCase().includes(k.toLowerCase())) {
          if (!res.includes(k)) res.push(k);
          return;
        }
      }
      if (p.toLowerCase().includes('head check')) { if (!res.includes('Head checkup')) res.push('Head checkup'); }
      else if (p.toLowerCase().includes('parent')) { if (!res.includes('Parent/Guardian Notified')) res.push('Parent/Guardian Notified'); }
      else if (p.toLowerCase().includes('medicat') || p.toLowerCase().includes('medic')) { if (!res.includes('Medications Given')) res.push('Medications Given'); }
      else if (!res.includes('Others')) res.push('Others');
    });
    return res;
  };

  if (c.action_taken && (!openVitals.value || openVitals.value.requestor_type !== 'employee')) {
    vitalsForm.actions = parseActions(c.action_taken);
    const at = c.action_taken || '';
    const headMatch = at.match(/Head checkup\s*:?\s*(.+)/i);
    if (headMatch) vitalsForm.head_check_reason = headMatch[1].trim();
    const parentMatch = at.match(/Parent\/Guardian Notified\s*:?\s*(.+)/i);
    if (parentMatch) vitalsForm.parent_notified_at = parentMatch[1].trim();
    const othersMatch = at.match(/Others\s*:?\s*(.+)/i);
    if (othersMatch) vitalsForm.others_action = othersMatch[1].trim();
  }

  // Parse disposition into selections when possible
  vitalsForm.dispositions = [];
  vitalsForm.employee_disposition = '';
  vitalsForm.others_disposition = '';
  if (c.disposition) {
    if (c.requestor_type === 'employee') {
      vitalsForm.employee_disposition = c.disposition;
    } else {
      const dparts = String(c.disposition).split(';').map(s => s.trim()).filter(Boolean);
      const knownD = [
        'Returned to class, feeling better',
        "Returned to class at parent's/guardian's request",
        'Returned to class, unable to contact parent/guardian',
        'Sent home',
        'Teacher Notified',
        'Referral was made to Health Care Provider',
        'Transported to Hospital',
        'Copy of clinic pass sent home',
        'Others'
      ];
      dparts.forEach(dp => {
        for (const kd of knownD) {
          if (dp.toLowerCase().startsWith(kd.toLowerCase()) || dp.toLowerCase().includes(kd.toLowerCase())) {
            if (!vitalsForm.dispositions.includes(kd)) vitalsForm.dispositions.push(kd);
            return;
          }
        }
        if (!vitalsForm.dispositions.includes('Others')) {
          vitalsForm.dispositions.push('Others');
          vitalsForm.others_disposition = dp;
        }
      });
    }
  }
};
const closeVitals = () => { openVitals.value = null; if (vitalsForm.reset) vitalsForm.reset(); };
// Exclusive selection helpers: prevent selecting both 20 minutes less and more
const hasLaidLess = computed(() => (vitalsForm.actions || []).includes('Student laid/sat in clinic for 20 minutes or less'));
const hasLaidMore = computed(() => (vitalsForm.actions || []).includes('Student laid/sat in clinic for 20 minutes or more'));
const removeAction = (val) => {
  vitalsForm.actions = (vitalsForm.actions || []).filter(a => a !== val);
};
const onExclusiveChange = (e) => {
  const val = e.target.value;
  const checked = e.target.checked;
  if (!checked) return;
  if (val === 'Student laid/sat in clinic for 20 minutes or less') {
    removeAction('Student laid/sat in clinic for 20 minutes or more');
  } else if (val === 'Student laid/sat in clinic for 20 minutes or more') {
    removeAction('Student laid/sat in clinic for 20 minutes or less');
  }
};

// Disposition exclusivity helpers
const hasReturnedFeelingBetter = computed(() => (vitalsForm.dispositions || []).includes('Returned to class, feeling better'));
const hasReturnedParentRequest = computed(() => (vitalsForm.dispositions || []).includes("Returned to class at parent's/guardian's request"));
const hasReturnedUnableContact = computed(() => (vitalsForm.dispositions || []).includes('Returned to class, unable to contact parent/guardian'));
const hasSentHome = computed(() => (vitalsForm.dispositions || []).includes('Sent home'));
const hasTransported = computed(() => (vitalsForm.dispositions || []).includes('Transported to Hospital'));

const removeDisposition = (val) => {
  vitalsForm.dispositions = (vitalsForm.dispositions || []).filter(d => d !== val);
};

const onDispositionChange = (e) => {
  const val = e.target.value;
  const checked = e.target.checked;
  if (!checked) return;

  // Mutual exclusivity between the two "returned to class" parent-related options
  if (val === "Returned to class at parent's/guardian's request") {
    removeDisposition('Returned to class, unable to contact parent/guardian');
  }
  if (val === 'Returned to class, unable to contact parent/guardian') {
    removeDisposition("Returned to class at parent's/guardian's request");
  }

  // If sent home or transported, remove any "returned to class" options
  if (val === 'Sent home' || val === 'Transported to Hospital') {
    removeDisposition('Returned to class, feeling better');
    removeDisposition("Returned to class at parent's/guardian's request");
    removeDisposition('Returned to class, unable to contact parent/guardian');
  }

  // If a returned-to-class option is selected, remove sent home / transported
  if (['Returned to class, feeling better', "Returned to class at parent's/guardian's request", 'Returned to class, unable to contact parent/guardian'].includes(val)) {
    removeDisposition('Sent home');
    removeDisposition('Transported to Hospital');
  }
};
const submitVitals = () => {
  if (!openVitals.value) return;

  // If the consultation requestor is an employee, allow free-text action_taken (do not overwrite)
  if (!(openVitals.value && openVitals.value.requestor_type === 'employee')) {
    // assemble action_taken from selected actions and conditional fields
    const parts = [];
    (vitalsForm.actions || []).forEach(a => {
      if (a === 'Head checkup') {
        const reason = vitalsForm.head_check_reason ? `: ${vitalsForm.head_check_reason}` : '';
        parts.push(`Head checkup${reason}`);
      } else if (a === 'Parent/Guardian Notified') {
        const dt = vitalsForm.parent_notified_at ? `: ${vitalsForm.parent_notified_at}` : '';
        parts.push(`Parent/Guardian Notified${dt}`);
      } else if (a === 'Others') {
        const o = vitalsForm.others_action ? `: ${vitalsForm.others_action}` : '';
        parts.push(`Others${o}`);
      } else if (a === 'Medications Given') {
        const med = vitalsForm.medicine_given ? `: ${vitalsForm.medicine_given}` : '';
        parts.push(`Medications Given${med}`);
      } else {
        parts.push(a);
      }
    });

    vitalsForm.action_taken = parts.join('; ');
  }

  // assemble disposition from selected dispositions
  if (openVitals.value && openVitals.value.requestor_type === 'employee') {
    // For employees use the single selected employee_disposition
    vitalsForm.disposition = vitalsForm.employee_disposition || '';
  } else {
    const disp = [];
    (vitalsForm.dispositions || []).forEach(d => {
      if (d === 'Others') {
        const o = vitalsForm.others_disposition ? `: ${vitalsForm.others_disposition}` : '';
        disp.push(`Others${o}`);
      } else {
        disp.push(d);
      }
    });
    vitalsForm.disposition = disp.join('; ');
  }

  // Truncate disposition on the client side as a safety net for DB column limits
  if (vitalsForm.disposition && vitalsForm.disposition.length > 255) {
    vitalsForm.disposition = vitalsForm.disposition.substring(0, 255);
  }

  if (vitalsForm.put) {
    vitalsForm.put(route('consultations.update', openVitals.value.id), {
      onSuccess: () => {
        Swal.fire({ icon: 'success', text: 'Vitals saved.' })
        openVitals.value = null;
        if (vitalsForm.reset) vitalsForm.reset();
      },
    });
  }
};

async function confirmDelete(c) {
  const confirmed = await confirmDeleteDialog('This action cannot be undone.')
  if (confirmed) {
    deleteSubmit.delete(route('consultations.destroy', c.id), {
      onSuccess: () => {
        Swal.fire({ icon: 'success', title: 'Consultation deleted' }).then(() => {
          router.get(route('consultations.index'), { q: searchQuery.value }, { replace: true })
        })
      },
      onError: () => {
        Swal.fire({ icon: 'error', title: 'Failed to delete consultation' })
      }
    })
  }
}
const openFor = (c) => { openSchedule.value = c; if (scheduleForm.reset) scheduleForm.reset(); };
const openAppointmentFor = (c = null) => { openAppointment.value = c || true; if (appointmentForm.reset) appointmentForm.reset(); if (hasAnyRole('Nurse', 'Administrator') && appointmentForm.patient_type === 'employee') fetchEmployees(); };

const employees = ref([]);
const loadingEmployees = ref(false);
const fetchEmployees = async () => {
  if (loadingEmployees.value) return;
  loadingEmployees.value = true;
  try {
    const res = await fetch(route('users.select'), { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error('Failed to load users');
    const data = await res.json();
    // handle both paginated ({data: [...]}) and array responses
    employees.value = data.data || data || [];
  } catch (e) {
    employees.value = [];
  } finally {
    loadingEmployees.value = false;
  }
};
// AppSelect forwards @change via $attrs BEFORE emitting update:modelValue,
// so a @change handler sees the stale patient_type — watch the form instead.
watch(() => appointmentForm.patient_type, (type) => {
  if (type === 'employee' && !employees.value.length) fetchEmployees();
});
const submitAppointment = () => {
  // assemble concern string from selected checkboxes similar to kiosk
  const assembled = (appointmentForm.concerns || []).map(c => {
    if (c === 'Others' && appointmentForm.other_concern) return `Others: ${appointmentForm.other_concern}`
    if (c === 'Injury' && appointmentForm.injury_type) return `Injury: ${appointmentForm.injury_type}`
    return c
  }).filter(Boolean).join(', ')
  appointmentForm.concern = assembled
  // backend expects `reason` — set it as well
  appointmentForm.reason = assembled

  // Determine requestor based on role and selected patient type
  const _role = String(page.props.auth?.user?.role?.name || '').toLowerCase();
  if (_role === 'staff') {
    appointmentForm.requestor_id = page.props.auth?.user?.id;
    appointmentForm.requestor_type = 'employee';
  } else if (['nurse', 'administrator'].includes(_role)) {
    if (appointmentForm.patient_type === 'student') {
      appointmentForm.requestor_type = 'student';
      appointmentForm.requestor_id = null;
      appointmentForm.requestor = appointmentForm.pisay || '';
    } else {
      appointmentForm.requestor_type = 'employee';
      appointmentForm.requestor_id = appointmentForm.employee_id || null;
      appointmentForm.requestor = '';
    }
  }

  appointmentForm.post(route('consultations.store'), {
    onSuccess: () => {
      Swal.fire({ icon: 'success', text: 'Appointment created.' })
      openAppointment.value = null;
      if (appointmentForm.reset) appointmentForm.reset();
    },
    onError: (errors) => {
      const msg = Object.values(errors || {}).flat().join(' ')
      if (msg) Swal.fire({ icon: 'error', text: msg })
      else Swal.fire({ icon: 'error', text: 'Failed to create appointment.' })
    }
  })
};
const submitSchedule = () => {
  if (!openSchedule.value) return;
  if (scheduleForm.put) {
    scheduleForm.put(route('consultations.update', openSchedule.value.id), {
      onSuccess: () => {
        Swal.fire({ icon: 'success', text: 'Consultation scheduled.' })
        openSchedule.value = null;
        if (scheduleForm.reset) scheduleForm.reset();
      },
    });
  }
}

// Pagination & search helpers (server-driven)
const consultationsList = computed(() => props.consultations?.data ?? props.consultations ?? []);
const pager = computed(() => page.props.consultations || null);
const prevUrl = computed(() => pager.value?.prev_page_url ?? null);
const nextUrl = computed(() => pager.value?.next_page_url ?? null);
const currentPage = computed(() => pager.value?.current_page ?? null);
const lastPage = computed(() => pager.value?.last_page ?? null);

const searchQuery = ref(page.props.q ?? '');
let searchTimer = null;
const doSearch = (val) => {
  router.get(route('consultations.index'), { q: val }, { replace: true, preserveState: false });
};
watch(searchQuery, (val) => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    doSearch(val);
  }, 300);
});

onMounted(() => {
  // noop; keeps parity with other pages if needed
});

const goTo = (url) => { if (!url) return; window.location.href = url };
const roleName = page.props.auth?.user?.role?.name ?? '';
const roleNames = page.props.auth?.user?.roleNames ?? (roleName ? [roleName] : []);
const hasRole = (role) => roleNames.includes(role);
const hasAnyRole = (...roles) => roles.some(r => roleNames.includes(r));
const isStaff = roleNames.length > 0 && roleNames.every(r => r === 'Staff');
</script>

<template>
  <Head title="Consultations" />
  <AdminLayout title="Consultations">
    <div class="space-y-5">
      <AppPageHeader title="Consultations">
        <template #actions>
          <AppButton @click.prevent="openAppointmentFor()">New Appointment</AppButton>
        </template>
      </AppPageHeader>

      <AppFilterBar>
        <AppInput v-model="searchQuery" @keydown.enter.prevent="doSearch(searchQuery)" type="text" placeholder="Search by name or Pisay ID" class="w-full sm:w-72" />
      </AppFilterBar>

      <!-- Appointment Modal (copy of kiosk fields, simplified) -->
      <AppModal :show="!!openAppointment" title="New Appointment" size="2xl" @close="openAppointment = null">
        <form @submit.prevent="submitAppointment" id="appointment-form" class="space-y-3">
          <!-- Patient selection: only visible to Nurse or Administrator -->
          <div v-if="hasAnyRole('Nurse', 'Administrator')">
            <label class="block text-xs font-medium text-slate-600 mb-1">Patient</label>
            <div class="flex gap-2">
              <AppSelect v-model="appointmentForm.patient_type" :show-blank="false">
                <option value="student">Student</option>
                <option value="employee">Employee</option>
              </AppSelect>
              <div v-if="appointmentForm.patient_type === 'student'" class="flex-1">
                <AppInput v-model="appointmentForm.pisay" type="text" placeholder="PISAY ID" />
              </div>
              <div v-else class="flex-1">
                <AppSelect v-model="appointmentForm.employee_id" :show-blank="false">
                  <option value="" disabled>Select employee</option>
                  <option v-for="u in employees" :key="u.id" :value="u.id">{{ u.name }}{{ u.office ? ' — ' + u.office : '' }}</option>
                </AppSelect>
              </div>
            </div>
          </div>
          <div v-else>
            <!-- For non-nurse/admin users, treat requestor as the logged-in user (handled on submit) -->
          </div>

          <AppSelect v-model="appointmentForm.consultation_type" label="Consultation Type" :show-blank="false">
            <option value="walk-in">Walk-in</option>
            <option value="scheduled">Scheduled</option>
          </AppSelect>

          <div v-if="appointmentForm.consultation_type === 'scheduled'">
            <label class="block text-xs font-medium text-slate-600 mb-1">Date & Time</label>
            <div class="mt-1">
              <div v-if="physicianSchedules.length === 0" class="text-sm text-slate-500">No physician schedules available.</div>
              <AppSelect v-else v-model="appointmentForm.physician_schedule_id" :show-blank="false">
                <option value="" disabled>Select a schedule</option>
                <option v-for="s in physicianSchedules" :key="s.id" :value="s.id">{{ formatDate(s.schedule_date) }} — {{ s.time_start }}{{ s.time_end ? ' - ' + s.time_end : '' }}</option>
              </AppSelect>
            </div>
            <p v-if="appointmentForm.errors.physician_schedule_id" class="text-danger-600 text-xs mt-1">{{ appointmentForm.errors.physician_schedule_id }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Concern (select all that apply)</label>
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
              <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" value="Not feeling well" v-model="appointmentForm.concerns" />
                <span>Not feeling well</span>
              </label>
              <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" value="Stomachache" v-model="appointmentForm.concerns" />
                <span>Stomachache</span>
              </label>
              <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" value="Headache" v-model="appointmentForm.concerns" />
                <span>Headache</span>
              </label>
              <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" value="Toothache" v-model="appointmentForm.concerns" />
                <span>Toothache</span>
              </label>
              <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" value="Injury" v-model="appointmentForm.concerns" />
                <span>Injury</span>
              </label>
              <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" value="Others" v-model="appointmentForm.concerns" />
                <span>Others</span>
              </label>
            </div>

            <div v-if="appointmentForm.concerns.includes('Others')" class="mt-3">
              <AppInput v-model="appointmentForm.other_concern" type="text" label="Please specify" placeholder="Describe other concern" />
            </div>
            <div v-if="appointmentForm.concerns.includes('Injury')" class="mt-3">
              <AppInput v-model="appointmentForm.injury_type" type="text" label="Type of injury" placeholder="Describe injury (e.g. cut, sprain)" />
            </div>
          </div>
        </form>

        <template #footer>
          <AppButton variant="secondary" @click.prevent="openAppointment = null" :disabled="appointmentForm.processing">Cancel</AppButton>
          <AppButton type="submit" form="appointment-form" :loading="appointmentForm.processing" @click.prevent="submitAppointment">Create</AppButton>
        </template>
      </AppModal>

      <!-- Desktop / larger screens: table -->
      <AppTable :is-empty="consultationsList.length === 0" :skeleton-cols="isStaff ? 6 : 11">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
            <th v-if="!isStaff" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Requestor Name</th>
            <th v-if="!isStaff" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Sex</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Selected Date &amp; Time</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Request Type</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actual Appointment</th>
            <th v-if="!isStaff" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Grade Level</th>
            <th v-if="!isStaff" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Section</th>
            <th v-if="!isStaff" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Office</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Action</th>
          </tr>
        </template>

        <tr v-for="c in consultationsList" :key="c.id" class="hover:bg-slate-50/60">
          <td class="px-4 py-3 text-sm text-slate-700">{{ c.id }}</td>
          <td v-if="!isStaff" class="px-4 py-3 text-sm text-slate-700">{{ c.requestor_data?.name || c.requestor || '—' }}</td>
          <td v-if="!isStaff" class="px-4 py-3 text-sm text-slate-700">{{ c.requestor_data?.sex || '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ c.selected_schedule_display || c.date_scheduled_display || '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ c.consultation_type || c.type || '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <span v-if="String(c.status).toLowerCase() !== 'pending'">{{ c.actual_scheduled_display || formatDateTime(c.scheduled_at) || '—' }}</span>
            <span v-else>—</span>
          </td>
          <td v-if="!isStaff" class="px-4 py-3 text-sm text-slate-700">{{ c.requestor_data?.grade_level || '—' }}</td>
          <td v-if="!isStaff" class="px-4 py-3 text-sm text-slate-700">{{ c.requestor_data?.section || '—' }}</td>
          <td v-if="!isStaff" class="px-4 py-3 text-sm text-slate-700">{{ c.requestor_data?.office || '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <span :class="[badgeBase, statusBadgeClass(c.status)]">{{ c.status }}</span>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <div class="flex gap-1">
              <AppIconButton label="View" @click.prevent="openView(c)">
                <EyeIcon class="h-5 w-5" />
              </AppIconButton>
              <a v-if="String(c.status).toLowerCase() === 'completed'" :href="route('consultations.print', c.id)" target="_blank" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors" aria-label="Print" title="Print">
                <PrinterIcon class="h-5 w-5" />
              </a>
              <AppIconButton v-if="String(c.status).toLowerCase() === 'completed' && ['Administrator','Nurse'].includes(page.props.auth?.user?.role?.name)" label="Edit Vitals" variant="warning" @click.prevent="openVitalsFor(c)">
                <PencilIcon class="h-5 w-5" />
              </AppIconButton>
              <AppIconButton v-if="['Administrator','Nurse'].includes(page.props.auth?.user?.role?.name) && String(c.status).toLowerCase() !== 'completed'" label="Delete" variant="danger" :disabled="isDeleting" @click.prevent="confirmDelete(c)">
                <TrashIcon class="h-5 w-5" />
              </AppIconButton>
              <AppIconButton v-if="['Administrator','Nurse','Clinic'].includes(page.props.auth?.user?.role?.name) && !['active','completed'].includes(String(c.status).toLowerCase())" label="Schedule" @click.prevent="openFor(c)">
                <ClockIcon class="h-5 w-5" />
              </AppIconButton>
              <AppIconButton type="button" v-if="['Administrator','Nurse','Clinic'].includes(page.props.auth?.user?.role?.name) && ['active','scheduled'].includes(String(c.status).toLowerCase())" label="Record Vitals" variant="success" @click="openVitalsFor(c)">
                <HeartIcon class="h-5 w-5" />
              </AppIconButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="c in consultationsList" :key="'card-'+c.id" class="p-4">
            <div class="flex justify-between items-start">
              <div>
                <div class="text-xs text-slate-500">#{{ c.id }}</div>
                <div class="font-medium text-slate-800">{{ c.requestor_data?.name || c.requestor || '—' }}</div>
                <div class="text-xs text-slate-500">{{ c.requestor_data?.sex || '—' }}</div>
              </div>
              <div class="text-right text-sm">
                <div class="text-slate-700">{{ c.selected_schedule_display || c.date_scheduled_display || '—' }}</div>
                <div class="text-xs text-slate-500">{{ c.consultation_type || c.type || '—' }}</div>
              </div>
            </div>
            <div class="mt-3 text-sm text-slate-700">
              <div><strong>Appointment:</strong> <span>{{ String(c.status).toLowerCase() !== 'pending' ? (c.actual_scheduled_display || formatDateTime(c.scheduled_at) || '—') : '—' }}</span></div>
              <div><strong>Grade/Section:</strong> <span>{{ c.requestor_data?.grade_level || '—' }} {{ c.requestor_data?.section ? '· ' + c.requestor_data.section : '' }}</span></div>
              <div><strong>Office:</strong> <span>{{ c.requestor_data?.office || '—' }}</span></div>
              <div class="mt-2">
                <span :class="[badgeBase, statusBadgeClass(c.status)]">{{ c.status }}</span>
              </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
              <AppButton size="sm" variant="secondary" @click.prevent="openView(c)">View</AppButton>
              <AppButton v-if="String(c.status).toLowerCase() === 'completed'" size="sm" variant="secondary" as="a" :href="route('consultations.print', c.id)" target="_blank">Print</AppButton>
              <AppButton v-if="String(c.status).toLowerCase() === 'completed' && ['Administrator','Nurse'].includes(page.props.auth?.user?.role?.name)" size="sm" variant="warning" @click.prevent="openVitalsFor(c)">Edit Vitals</AppButton>
              <AppButton v-if="['Administrator','Nurse'].includes(page.props.auth?.user?.role?.name) && String(c.status).toLowerCase() !== 'completed'" size="sm" variant="danger" :disabled="isDeleting" @click.prevent="confirmDelete(c)">Delete</AppButton>
              <AppButton v-if="['Administrator','Nurse','Clinic'].includes(page.props.auth?.user?.role?.name) && !['active','completed'].includes(String(c.status).toLowerCase())" size="sm" variant="secondary" @click.prevent="openFor(c)">Schedule</AppButton>
              <AppButton v-if="['Administrator','Nurse','Clinic'].includes(page.props.auth?.user?.role?.name) && ['active','scheduled'].includes(String(c.status).toLowerCase())" size="sm" variant="success" @click="openVitalsFor(c)">Record Vitals</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No consultations found" />
        </template>

        <template #footer>
          <div class="flex items-center justify-between px-4 py-3 text-sm text-slate-600">
            <AppButton size="sm" variant="secondary" :disabled="!prevUrl" @click.prevent="goTo(prevUrl)">Prev</AppButton>
            <span>Page {{ currentPage }} of {{ lastPage }}</span>
            <AppButton size="sm" variant="secondary" :disabled="!nextUrl" @click.prevent="goTo(nextUrl)">Next</AppButton>
          </div>
        </template>
      </AppTable>

      <!-- Schedule Modal -->
      <AppModal :show="!!openSchedule" title="Schedule Consultation" @close="openSchedule = null">
        <form @submit.prevent="submitSchedule" id="schedule-consultation-form" class="space-y-3">
          <div>
            <AppInput type="datetime-local" v-model="scheduleForm.scheduled_at" label="Date & Time" />
            <p v-if="scheduleForm.errors.scheduled_at" class="text-danger-600 text-xs mt-1">{{ scheduleForm.errors.scheduled_at }}</p>
          </div>
          <AppTextarea v-model="scheduleForm.notes" label="Notes" :rows="3" />
        </form>

        <template #footer>
          <AppButton variant="secondary" @click.prevent="openSchedule = null">Cancel</AppButton>
          <AppButton type="submit" form="schedule-consultation-form" :loading="scheduleForm.processing" @click.prevent="submitSchedule">Save</AppButton>
        </template>
      </AppModal>

      <!-- View Modal (Consultation Details) -->
      <AppModal :show="!!viewConsultation" title="Consultation Details" @close="closeView">
        <template v-if="viewConsultation">
          <div class="flex items-start gap-4 mb-4">
            <div>
              <img v-if="viewConsultation.requestor_data?.photo" :src="viewConsultation.requestor_data.photo" alt="Profile" class="w-24 h-24 object-cover rounded-lg border border-slate-100" />
              <div v-else class="w-24 h-24 bg-slate-100 rounded-lg border border-slate-100 flex items-center justify-center text-xs text-slate-400">No photo</div>
            </div>
            <div class="text-sm text-slate-700">
              <p><strong>ID:</strong> {{ viewConsultation.id }}</p>
              <p><strong>Requestor:</strong> {{ viewConsultation.requestor_data?.name || viewConsultation.requestor || '—' }}</p>
              <p class="text-xs text-slate-500">{{ viewConsultation.requestor_data?.grade_level || '' }} {{ viewConsultation.requestor_data?.section ? '· ' + viewConsultation.requestor_data.section : '' }}</p>
              <p class="mt-2"><strong>Type:</strong> {{ viewConsultation.consultation_type || viewConsultation.type || '—' }}</p>
            </div>
          </div>

          <div class="text-sm text-slate-700 space-y-1">
            <p><strong>Concern:</strong> {{ viewConsultation.concern || viewConsultation.reason || '—' }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 my-2">
              <div><p><strong>Temperature:</strong> {{ viewConsultation.temperature || viewConsultation.temp || viewConsultation.body_temperature || '—' }}</p></div>
              <div><p><strong>Pulse Rate:</strong> {{ viewConsultation.pulse_rate || viewConsultation.pulse || viewConsultation.heart_rate || '—' }}</p></div>
              <div><p><strong>Oxygen:</strong> {{ viewConsultation.oxygen || viewConsultation.oxygen_saturation || viewConsultation.spo2 || '—' }}</p></div>
              <div><p><strong>Blood Pressure:</strong> {{ viewConsultation.blood_pressure || viewConsultation.bp || viewConsultation.bloodpressure || '—' }}</p></div>
            </div>
            <p><strong>Action Taken:</strong> {{ viewConsultation.action_taken || '—' }}</p>
            <p><strong>Medicine Given:</strong> {{ viewConsultation.medicine_given || viewConsultation.medicines || '—' }}</p>
            <p><strong>Time Out:</strong> {{ viewConsultation.time_out || '—' }}</p>
            <p><strong>Disposition:</strong> {{ viewConsultation.disposition || '—' }}</p>
          </div>
        </template>
      </AppModal>

      <!-- Vitals Edit Modal (global) -->
      <AppModal :show="!!openVitals" title="Record Vitals / Treatment" size="3xl" @close="closeVitals">
        <form @submit.prevent="submitVitals" id="vitals-form" class="space-y-3">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <AppInput type="number" step="0.1" v-model="vitalsForm.temperature" label="Temperature" />
              <p v-if="vitalsForm.errors.temperature" class="text-danger-600 text-xs mt-1">{{ vitalsForm.errors.temperature }}</p>
            </div>
            <div>
              <AppInput type="number" v-model="vitalsForm.pulse_rate" label="Pulse Rate" />
              <p v-if="vitalsForm.errors.pulse_rate" class="text-danger-600 text-xs mt-1">{{ vitalsForm.errors.pulse_rate }}</p>
            </div>
            <div>
              <AppInput type="number" v-model="vitalsForm.oxygen" label="Oxygen (SpO2)" />
              <p v-if="vitalsForm.errors.oxygen" class="text-danger-600 text-xs mt-1">{{ vitalsForm.errors.oxygen }}</p>
            </div>
            <div>
              <AppInput type="text" v-model="vitalsForm.blood_pressure" label="Blood Pressure" />
              <p v-if="vitalsForm.errors.blood_pressure" class="text-danger-600 text-xs mt-1">{{ vitalsForm.errors.blood_pressure }}</p>
            </div>
          </div>

          <div>
            <AppTextarea v-model="vitalsForm.medicine_given" label="Medicine Given" :rows="2" />
            <p v-if="vitalsForm.errors.medicine_given" class="text-danger-600 text-xs mt-1">{{ vitalsForm.errors.medicine_given }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Action Taken</label>
            <div class="mt-1">
              <template v-if="openVitals && openVitals.requestor_type === 'employee'">
                <AppTextarea v-model="vitalsForm.action_taken" :rows="4" />
              </template>
              <template v-else>
                <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" value="Student laid/sat in clinic for 20 minutes or less" v-model="vitalsForm.actions" @change="onExclusiveChange" :disabled="hasLaidMore" />
                    <span>Student laid/sat in clinic for 20 minutes or less</span>
                  </label>
                  <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" value="Student laid/sat in clinic for 20 minutes or more" v-model="vitalsForm.actions" @change="onExclusiveChange" :disabled="hasLaidLess" />
                    <span>Student laid/sat in clinic for 20 minutes or more</span>
                  </label>
                  <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" value="Temperature Taken" v-model="vitalsForm.actions" />
                    <span>Temperature Taken</span>
                  </label>
                  <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" value="Ice Pack applied to the affected Area" v-model="vitalsForm.actions" />
                    <span>Ice Pack applied to the affected Area</span>
                  </label>
                  <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" value="Affected area cleaned" v-model="vitalsForm.actions" />
                    <span>Affected area cleaned</span>
                  </label>
                  <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" value="Band aid applied to affected area" v-model="vitalsForm.actions" />
                    <span>Band aid applied to affected area</span>
                  </label>
                  <div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                      <input type="checkbox" value="Head checkup" v-model="vitalsForm.actions" />
                      <span>Head checkup</span>
                    </label>
                    <div v-if="vitalsForm.actions.includes('Head checkup')" class="mt-2">
                      <AppInput type="text" v-model="vitalsForm.head_check_reason" label="Reason for head checkup" />
                    </div>
                  </div>
                  <div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                      <input type="checkbox" value="Parent/Guardian Notified" v-model="vitalsForm.actions" />
                      <span>Parent/Guardian Notified</span>
                    </label>
                    <div v-if="vitalsForm.actions.includes('Parent/Guardian Notified')" class="mt-2">
                      <AppInput type="datetime-local" v-model="vitalsForm.parent_notified_at" label="Date & Time notified" />
                    </div>
                  </div>
                  <div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                      <input type="checkbox" value="Medications Given" v-model="vitalsForm.actions" />
                      <span>Medications Given</span>
                    </label>
                    <div v-if="vitalsForm.actions.includes('Medications Given')" class="mt-2">
                      <AppTextarea v-model="vitalsForm.medicine_given" label="Medications (describe)" :rows="2" />
                    </div>
                  </div>
                  <div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                      <input type="checkbox" value="Others" v-model="vitalsForm.actions" />
                      <span>Others</span>
                    </label>
                    <div v-if="vitalsForm.actions.includes('Others')" class="mt-2">
                      <AppInput type="text" v-model="vitalsForm.others_action" label="Please specify" />
                    </div>
                  </div>
                </div>
              </template>
            </div>
            <p v-if="vitalsForm.errors.action_taken" class="text-danger-600 text-xs mt-1">{{ vitalsForm.errors.action_taken }}</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <AppInput type="datetime-local" v-model="vitalsForm.time_start" label="Time Start" />
            <AppInput type="datetime-local" v-model="vitalsForm.time_out" label="Time Out" />
          </div>

          <div>
            <AppInput type="datetime-local" v-model="vitalsForm.date_attended" label="Date Attended" />
            <p v-if="vitalsForm.errors.date_attended" class="text-danger-600 text-xs mt-1">{{ vitalsForm.errors.date_attended }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Disposition</label>
            <div class="mt-1">
              <template v-if="openVitals && openVitals.requestor_type === 'employee'">
                <div class="space-y-2">
                  <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="radio" value="Returned to Work, feeling better" v-model="vitalsForm.employee_disposition" />
                    <span>Returned to Work, feeling better</span>
                  </label>
                  <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="radio" value="Went Home" v-model="vitalsForm.employee_disposition" />
                    <span>Went Home</span>
                  </label>
                  <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="radio" value="Transported to Hospital" v-model="vitalsForm.employee_disposition" />
                    <span>Transported to Hospital</span>
                  </label>
                </div>
              </template>
              <template v-else>
                <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" value="Returned to class, feeling better" v-model="vitalsForm.dispositions" @change="onDispositionChange" :disabled="hasSentHome || hasTransported" />
                    <span>Returned to class, feeling better</span>
                  </label>
                  <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" value="Returned to class at parent's/guardian's request" v-model="vitalsForm.dispositions" @change="onDispositionChange" :disabled="hasSentHome || hasTransported || hasReturnedUnableContact" />
                    <span>Returned to class at parent's/guardian's request</span>
                  </label>
                  <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" value="Returned to class, unable to contact parent/guardian" v-model="vitalsForm.dispositions" @change="onDispositionChange" :disabled="hasSentHome || hasTransported || hasReturnedParentRequest" />
                    <span>Returned to class, unable to contact parent/guardian</span>
                  </label>
                  <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" value="Sent home" v-model="vitalsForm.dispositions" @change="onDispositionChange" :disabled="hasReturnedFeelingBetter || hasReturnedParentRequest || hasReturnedUnableContact" />
                    <span>Sent home</span>
                  </label>
                  <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" value="Teacher Notified" v-model="vitalsForm.dispositions" />
                    <span>Teacher Notified</span>
                  </label>
                  <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" value="Referral was made to Health Care Provider" v-model="vitalsForm.dispositions" />
                    <span>Referral was made to Health Care Provider</span>
                  </label>
                  <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" value="Transported to Hospital" v-model="vitalsForm.dispositions" @change="onDispositionChange" :disabled="hasReturnedFeelingBetter || hasReturnedParentRequest || hasReturnedUnableContact" />
                    <span>Transported to Hospital</span>
                  </label>
                  <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" value="Copy of clinic pass sent home" v-model="vitalsForm.dispositions" />
                    <span>Copy of clinic pass sent home</span>
                  </label>
                  <div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                      <input type="checkbox" value="Others" v-model="vitalsForm.dispositions" />
                      <span>Others</span>
                    </label>
                    <div v-if="vitalsForm.dispositions.includes('Others')" class="mt-2">
                      <AppInput type="text" v-model="vitalsForm.others_disposition" label="Please specify" />
                    </div>
                  </div>
                </div>
              </template>
            </div>
            <p v-if="vitalsForm.errors.disposition" class="text-danger-600 text-xs mt-1">{{ vitalsForm.errors.disposition }}</p>
          </div>
        </form>

        <template #footer>
          <AppButton variant="secondary" @click.prevent="closeVitals">Cancel</AppButton>
          <AppButton type="submit" form="vitals-form" :loading="vitalsForm.processing" @click.prevent="submitVitals">Save</AppButton>
        </template>
      </AppModal>

    </div>
  </AdminLayout>
</template>
