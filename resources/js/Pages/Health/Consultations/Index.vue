<script setup>
import { Head, usePage, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref, computed, watch, onMounted } from 'vue'
import { EyeIcon, PrinterIcon, ClockIcon, HeartIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'
import { useSubmit } from '@/Composables/useSubmit'
import { statusBadgeClass, badgeBase } from '@/Composables/useStatusBadge.js'

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

function confirmDelete(c) {
  Swal.fire({ title: 'Delete consultation?', text: 'This action cannot be undone.', icon: 'warning', showCancelButton: true }).then((r) => {
    if (r.isConfirmed) {
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
  })
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
    <div>
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <h1 class="text-xl font-semibold text-slate-800">Consultations</h1>
        <button @click.prevent="openAppointmentFor()" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">New Appointment</button>
      </div>

      <!-- Consultation request form removed as requested -->

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100">
          <input v-model="searchQuery" @keydown.enter.prevent="doSearch(searchQuery)" type="text" placeholder="Search by name or Pisay ID" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-72" />
        </div>

        <!-- Appointment Modal (copy of kiosk fields, simplified) -->
        <div v-if="openAppointment" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
          <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-auto">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
              <h3 class="text-base font-semibold text-slate-800">New Appointment</h3>
              <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="openAppointment = null">✕</button>
            </div>
            <div class="px-6 py-5">
              <form @submit.prevent="submitAppointment" class="space-y-3">
                <!-- Patient selection: only visible to Nurse or Administrator -->
                <div v-if="hasAnyRole('Nurse', 'Administrator')">
                  <label class="block text-xs font-medium text-slate-600 mb-1">Patient</label>
                  <div class="flex gap-2">
                    <select v-model="appointmentForm.patient_type" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" @change="() => { if (appointmentForm.patient_type === 'employee') fetchEmployees(); }">
                      <option value="student">Student</option>
                      <option value="employee">Employee</option>
                    </select>
                    <div v-if="appointmentForm.patient_type === 'student'" class="flex-1">
                      <input v-model="appointmentForm.pisay" type="text" placeholder="PISAY ID" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                    </div>
                    <div v-else class="flex-1">
                      <select v-model="appointmentForm.employee_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                        <option value="" disabled>Select employee</option>
                        <option v-for="u in employees" :key="u.id" :value="u.id">{{ u.name }} — {{ u.office || '' }}</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div v-else>
                  <!-- For non-nurse/admin users, treat requestor as the logged-in user (handled on submit) -->
                </div>

                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Consultation Type</label>
                  <select v-model="appointmentForm.consultation_type" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                    <option value="walk-in">Walk-in</option>
                    <option value="scheduled">Scheduled</option>
                  </select>
                </div>

                <div v-if="appointmentForm.consultation_type === 'scheduled'">
                  <label class="block text-xs font-medium text-slate-600 mb-1">Date & Time</label>
                  <div class="mt-1">
                    <div v-if="physicianSchedules.length === 0" class="text-sm text-slate-500">No physician schedules available.</div>
                    <div v-else>
                      <select v-model="appointmentForm.physician_schedule_id" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                        <option value="" disabled>Select a schedule</option>
                        <option v-for="s in physicianSchedules" :key="s.id" :value="s.id">{{ formatDate(s.schedule_date) }} — {{ s.time_start }}{{ s.time_end ? ' - ' + s.time_end : '' }}</option>
                      </select>
                    </div>
                  </div>
                  <p v-if="appointmentForm.errors.physician_schedule_id" class="text-red-600 text-xs mt-1">{{ appointmentForm.errors.physician_schedule_id }}</p>
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
                    <label class="block text-xs font-medium text-slate-600 mb-1">Please specify</label>
                    <input v-model="appointmentForm.other_concern" type="text" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" placeholder="Describe other concern" />
                  </div>
                  <div v-if="appointmentForm.concerns.includes('Injury')" class="mt-3">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Type of injury</label>
                    <input v-model="appointmentForm.injury_type" type="text" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" placeholder="Describe injury (e.g. cut, sprain)" />
                  </div>
                </div>

                <div class="flex gap-2 pt-2">
                  <button :disabled="appointmentForm.processing" :aria-busy="appointmentForm.processing" :class="['inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm', appointmentForm.processing ? 'opacity-60 cursor-not-allowed' : '']">
                    <svg v-if="appointmentForm.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span v-if="appointmentForm.processing">Submitting...</span>
                    <span v-else>Create</span>
                  </button>
                  <button @click.prevent="openAppointment = null" :disabled="appointmentForm.processing" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm" :class="appointmentForm.processing ? 'opacity-50 cursor-not-allowed' : ''">Cancel</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Desktop / larger screens: table -->
        <div class="hidden sm:block overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
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
            </thead>
            <tbody class="divide-y divide-slate-100">
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
                    <button @click.prevent="openView(c)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" aria-label="View">
                      <EyeIcon class="h-5 w-5" />
                    </button>
                    <a v-if="String(c.status).toLowerCase() === 'completed'" :href="route('consultations.print', c.id)" target="_blank" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" aria-label="Print">
                      <PrinterIcon class="h-5 w-5" />
                    </a>
                    <button v-if="String(c.status).toLowerCase() === 'completed' && ['Administrator','Nurse'].includes(page.props.auth?.user?.role?.name)" @click.prevent="openVitalsFor(c)" class="p-1.5 rounded-lg hover:bg-amber-50 text-amber-600 transition-colors" aria-label="Edit Vitals">
                      <PencilIcon class="h-5 w-5" />
                    </button>
                    <button v-if="['Administrator','Nurse'].includes(page.props.auth?.user?.role?.name) && String(c.status).toLowerCase() !== 'completed'" @click.prevent="confirmDelete(c)" :disabled="isDeleting" class="p-1.5 rounded-lg hover:bg-red-50 text-slate-500 hover:text-red-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" aria-label="Delete">
                      <TrashIcon class="h-5 w-5" />
                    </button>
                    <button v-if="['Administrator','Nurse','Clinic'].includes(page.props.auth?.user?.role?.name) && !['active','completed'].includes(String(c.status).toLowerCase())" @click.prevent="openFor(c)" class="p-1.5 rounded-lg hover:bg-indigo-50 text-indigo-600 transition-colors" aria-label="Schedule">
                      <ClockIcon class="h-5 w-5" />
                    </button>
                    <button type="button" v-if="['Administrator','Nurse','Clinic'].includes(page.props.auth?.user?.role?.name) && ['active','scheduled'].includes(String(c.status).toLowerCase())" @click="openVitalsFor(c)" class="p-1.5 rounded-lg hover:bg-emerald-50 text-emerald-600 transition-colors" aria-label="Record Vitals">
                      <HeartIcon class="h-5 w-5" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="consultationsList.length === 0"><td :colspan="(isStaff ? 6 : 11)" class="py-16 text-center text-slate-400 text-sm">No consultations found.</td></tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile / small screens: stacked cards -->
        <div class="sm:hidden space-y-3 p-4">
          <div v-for="c in consultationsList" :key="'card-'+c.id" class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
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
              <button @click.prevent="openView(c)" class="inline-flex items-center gap-1 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">View</button>
              <a v-if="String(c.status).toLowerCase() === 'completed'" :href="route('consultations.print', c.id)" target="_blank" class="inline-flex items-center gap-1 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">Print</a>
              <button v-if="String(c.status).toLowerCase() === 'completed' && ['Administrator','Nurse'].includes(page.props.auth?.user?.role?.name)" @click.prevent="openVitalsFor(c)" class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 px-3 py-1.5 rounded-lg text-xs font-medium">Edit Vitals</button>
              <button v-if="['Administrator','Nurse'].includes(page.props.auth?.user?.role?.name) && String(c.status).toLowerCase() !== 'completed'" @click.prevent="confirmDelete(c)" :disabled="isDeleting" class="inline-flex items-center gap-1 bg-red-50 text-red-600 px-3 py-1.5 rounded-lg text-xs font-medium disabled:opacity-50 disabled:cursor-not-allowed">Delete</button>
              <button v-if="['Administrator','Nurse','Clinic'].includes(page.props.auth?.user?.role?.name) && !['active','completed'].includes(String(c.status).toLowerCase())" @click.prevent="openFor(c)" class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 px-3 py-1.5 rounded-lg text-xs font-medium">Schedule</button>
              <button v-if="['Administrator','Nurse','Clinic'].includes(page.props.auth?.user?.role?.name) && ['active','scheduled'].includes(String(c.status).toLowerCase())" @click="openVitalsFor(c)" class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-lg text-xs font-medium">Record Vitals</button>
            </div>
          </div>
          <div v-if="consultationsList.length === 0" class="py-16 text-center text-slate-400 text-sm">No consultations found.</div>
        </div>

        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
          <button @click.prevent="goTo(prevUrl)" :disabled="!prevUrl" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">Prev</button>
          <span>Page {{ currentPage }} of {{ lastPage }}</span>
          <button @click.prevent="goTo(nextUrl)" :disabled="!nextUrl" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50">Next</button>
        </div>
      </div>

      <!-- Schedule Modal -->
      <div v-if="openSchedule" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-800">Schedule Consultation</h3>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="openSchedule = null">✕</button>
          </div>
          <div class="px-6 py-5">
            <form @submit.prevent="submitSchedule" class="space-y-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date & Time</label>
                <input type="datetime-local" v-model="scheduleForm.scheduled_at" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                <p v-if="scheduleForm.errors.scheduled_at" class="text-red-600 text-xs mt-1">{{ scheduleForm.errors.scheduled_at }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Notes</label>
                <textarea v-model="scheduleForm.notes" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" rows="3"></textarea>
              </div>
            </form>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click.prevent="openSchedule = null" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button @click.prevent="submitSchedule" :disabled="scheduleForm.processing" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60">
              <svg v-if="scheduleForm.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
              </svg>
              <span v-if="scheduleForm.processing">Saving...</span>
              <span v-else>Save</span>
            </button>
          </div>
        </div>
      </div>

      <!-- View Modal (Consultation Details) -->
      <div v-if="viewConsultation" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-auto">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-800">Consultation Details</h3>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeView">✕</button>
          </div>
          <div class="px-6 py-5">
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

            <!-- Vitals Edit Modal (moved out so it can open independently) -->

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
          </div>
        </div>
      </div>

      <!-- Vitals Edit Modal (global) -->
      <div v-if="openVitals" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl sm:max-w-3xl max-h-[90vh] overflow-auto">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-800">Record Vitals / Treatment</h3>
            <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" @click="closeVitals">✕</button>
          </div>
          <div class="px-6 py-5">
            <form @submit.prevent="submitVitals" class="space-y-3">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Temperature</label>
                  <input type="number" step="0.1" v-model="vitalsForm.temperature" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                  <p v-if="vitalsForm.errors.temperature" class="text-red-600 text-xs mt-1">{{ vitalsForm.errors.temperature }}</p>
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Pulse Rate</label>
                  <input type="number" v-model="vitalsForm.pulse_rate" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                  <p v-if="vitalsForm.errors.pulse_rate" class="text-red-600 text-xs mt-1">{{ vitalsForm.errors.pulse_rate }}</p>
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Oxygen (SpO2)</label>
                  <input type="number" v-model="vitalsForm.oxygen" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                  <p v-if="vitalsForm.errors.oxygen" class="text-red-600 text-xs mt-1">{{ vitalsForm.errors.oxygen }}</p>
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Blood Pressure</label>
                  <input type="text" v-model="vitalsForm.blood_pressure" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                  <p v-if="vitalsForm.errors.blood_pressure" class="text-red-600 text-xs mt-1">{{ vitalsForm.errors.blood_pressure }}</p>
                </div>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Medicine Given</label>
                <textarea v-model="vitalsForm.medicine_given" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" rows="2"></textarea>
                <p v-if="vitalsForm.errors.medicine_given" class="text-red-600 text-xs mt-1">{{ vitalsForm.errors.medicine_given }}</p>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Action Taken</label>
                <div class="mt-1">
                  <template v-if="openVitals && openVitals.requestor_type === 'employee'">
                    <textarea v-model="vitalsForm.action_taken" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" rows="4"></textarea>
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
                          <label class="block text-xs font-medium text-slate-600 mb-1">Reason for head checkup</label>
                          <input type="text" v-model="vitalsForm.head_check_reason" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                        </div>
                      </div>
                      <div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                          <input type="checkbox" value="Parent/Guardian Notified" v-model="vitalsForm.actions" />
                          <span>Parent/Guardian Notified</span>
                        </label>
                        <div v-if="vitalsForm.actions.includes('Parent/Guardian Notified')" class="mt-2">
                          <label class="block text-xs font-medium text-slate-600 mb-1">Date & Time notified</label>
                          <input type="datetime-local" v-model="vitalsForm.parent_notified_at" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                        </div>
                      </div>
                      <div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                          <input type="checkbox" value="Medications Given" v-model="vitalsForm.actions" />
                          <span>Medications Given</span>
                        </label>
                        <div v-if="vitalsForm.actions.includes('Medications Given')" class="mt-2">
                          <label class="block text-xs font-medium text-slate-600 mb-1">Medications (describe)</label>
                          <textarea v-model="vitalsForm.medicine_given" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" rows="2"></textarea>
                        </div>
                      </div>
                      <div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                          <input type="checkbox" value="Others" v-model="vitalsForm.actions" />
                          <span>Others</span>
                        </label>
                        <div v-if="vitalsForm.actions.includes('Others')" class="mt-2">
                          <label class="block text-xs font-medium text-slate-600 mb-1">Please specify</label>
                          <input type="text" v-model="vitalsForm.others_action" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                        </div>
                      </div>
                    </div>
                  </template>
                </div>
                <p v-if="vitalsForm.errors.action_taken" class="text-red-600 text-xs mt-1">{{ vitalsForm.errors.action_taken }}</p>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Time Start</label>
                  <input type="datetime-local" v-model="vitalsForm.time_start" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Time Out</label>
                  <input type="datetime-local" v-model="vitalsForm.time_out" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                </div>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date Attended</label>
                <input type="datetime-local" v-model="vitalsForm.date_attended" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                <p v-if="vitalsForm.errors.date_attended" class="text-red-600 text-xs mt-1">{{ vitalsForm.errors.date_attended }}</p>
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
                          <label class="block text-xs font-medium text-slate-600 mb-1">Please specify</label>
                          <input type="text" v-model="vitalsForm.others_disposition" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                        </div>
                      </div>
                    </div>
                  </template>
                </div>
                <p v-if="vitalsForm.errors.disposition" class="text-red-600 text-xs mt-1">{{ vitalsForm.errors.disposition }}</p>
              </div>
            </form>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click.prevent="closeVitals" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button @click.prevent="submitVitals" :disabled="vitalsForm.processing" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-60">{{ vitalsForm.processing ? 'Saving...' : 'Save' }}</button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
