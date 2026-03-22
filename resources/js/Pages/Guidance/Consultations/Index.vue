<template>
  <Head title="Guidance Consultations" />
  <AdminLayout :title="'Guidance Consultations'">
    <template #default>
      <div>
        <!-- Page header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
          <div>
            <h1 class="text-xl font-semibold text-slate-800">Guidance Consultations</h1>
            <p class="text-sm text-slate-500">Track and manage student consultation records</p>
          </div>
          <button v-if="canRefer" @click.prevent="openReferralModal" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            Add Referral
          </button>
        </div>

        <!-- Filter bar -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
          <input v-model="q" type="text" placeholder="Search consultations..." class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-64" />
        </div>

        <!-- Table card -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
              <thead class="bg-slate-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">#</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Requestor</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Concern</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap hidden md:table-cell">Referred By</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap hidden md:table-cell">Referral Category</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap hidden md:table-cell">Behavior Spotted</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap hidden md:table-cell">Description</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap hidden md:table-cell">Preferred</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap hidden md:table-cell">Assigned</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="c in paged" :key="c.id" class="hover:bg-slate-50/60">
                  <td class="px-4 py-3 text-sm text-slate-700">{{ c.id }}</td>
                  <td class="px-4 py-3 text-sm text-slate-700">{{ c.requestor_name || c.requestor_id || '—' }}</td>
                  <td class="px-4 py-3 text-sm text-slate-700">{{ c.concern }}</td>
                  <td class="px-4 py-3 text-sm text-slate-700 hidden md:table-cell">{{ c.referred_by_name || c.referred_by || '—' }}</td>
                  <td class="px-4 py-3 text-sm text-slate-700 hidden md:table-cell">
                    <div class="max-w-[20rem] truncate">{{ c.referral_category || '—' }}</div>
                  </td>
                  <td class="px-4 py-3 text-sm text-slate-700 hidden md:table-cell">
                    <div class="max-w-[24rem] truncate">{{ c.behavior_spotted || '—' }}</div>
                    <button v-if="c.behavior_spotted && c.behavior_spotted.length > 120" @click.prevent="openDesc(c.behavior_spotted)" class="text-xs text-indigo-600 hover:text-indigo-800 mt-1">View</button>
                  </td>
                  <td class="px-4 py-3 text-sm text-slate-700 hidden md:table-cell">
                    <div class="max-w-[28rem] truncate">{{ c.brief_description || c.description || '—' }}</div>
                    <button v-if="(c.brief_description || c.description) && (c.brief_description || c.description).length > 120" @click.prevent="openDesc(c.brief_description || c.description)" class="text-xs text-indigo-600 hover:text-indigo-800 mt-1">View</button>
                  </td>
                  <td class="px-4 py-3 text-sm text-slate-700 hidden md:table-cell">{{ formatDate(c.date_time_preferred) }}</td>
                  <td class="px-4 py-3 text-sm text-slate-700 hidden md:table-cell">{{ c.assigned_personnel_name || '—' }}</td>
                  <td class="px-4 py-3 text-sm text-slate-700">
                    <span
                      class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium"
                      :class="{
                        'bg-amber-50 text-amber-700': String(c.status).toLowerCase() === 'pending',
                        'bg-emerald-50 text-emerald-700': ['done intervention', 'for monitoring'].includes(String(c.status).toLowerCase()),
                        'bg-blue-50 text-blue-700': String(c.status).toLowerCase() === 'scheduled' || String(c.status).toLowerCase() === 'for follow-up',
                        'bg-slate-100 text-slate-600': !['pending','done intervention','for monitoring','scheduled','for follow-up'].includes(String(c.status).toLowerCase()),
                      }"
                    >{{ c.status }}</span>
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex gap-1">
                      <button v-if="canAssign && String(c.status).toLowerCase() === 'pending'" @click.prevent="openAssign(c)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" :title="'Update Appointment for #'+c.id" aria-label="Update Appointment">
                        <ClockIcon class="h-4 w-4" />
                      </button>
                      <button v-if="canAssign && String(c.status).toLowerCase() === 'scheduled'" @click.prevent="openIntervention(c)" class="p-1.5 rounded-lg hover:bg-emerald-50 text-slate-500 hover:text-emerald-700 transition-colors" :title="'Add Intervention for #'+c.id" aria-label="Add Intervention">
                        <HeartIcon class="h-4 w-4" />
                      </button>
                      <button v-if="canAssign && c.status === 'For Follow-up'" @click.prevent="openEditIntervention(c)" class="p-1.5 rounded-lg hover:bg-amber-50 text-slate-500 hover:text-amber-700 transition-colors" :title="'Update Intervention for #'+c.id" aria-label="Update Intervention">
                        <PencilIcon class="h-4 w-4" />
                      </button>
                      <span v-if="c.status === 'Done Intervention'" class="p-1.5 rounded-lg text-emerald-600" :title="'Done'" aria-hidden="true">
                        <CheckCircleIcon class="h-4 w-4" />
                      </span>
                      <button v-if="['For Follow-up','For Monitoring','Done Intervention','Refer to School Psychologist'].includes(c.status)" @click.prevent="openAdmissionSlip(c)" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors" :title="'Print Admission Slip'" aria-label="Print Admission Slip">
                        <PrinterIcon class="h-4 w-4" />
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="filtered.length === 0">
                  <td class="py-16 text-center text-slate-400 text-sm" colspan="11">No consultations found.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-600">
            <span>Page {{ page }} of {{ totalPages }}</span>
            <div class="flex gap-2">
              <button @click="prev" :disabled="page===1" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">Prev</button>
              <button @click="next" :disabled="page===totalPages" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Add Referral modal -->
      <div v-if="showReferralModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Student Referral to Guidance Office</h2>
            <button @click="closeReferralModal" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
            </button>
          </div>

          <div class="px-6 py-5 space-y-4">
            <div class="relative">
              <label class="block text-xs font-medium text-slate-600 mb-1">Student</label>
              <input
                v-model="studentSearchQuery"
                type="text"
                placeholder="Search student name..."
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"
                autocomplete="off"
              />
              <div v-if="searchingStudents" class="text-xs text-slate-500 mt-1">Searching...</div>
              <div v-if="studentSearchResults.length > 0" class="absolute z-10 mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg max-h-56 overflow-y-auto">
                <button
                  v-for="student in studentSearchResults"
                  :key="student.id"
                  type="button"
                  @click="selectStudent(student)"
                  class="w-full text-left px-3 py-2 hover:bg-slate-50 transition-colors"
                >
                  <div class="font-medium text-sm text-slate-800">{{ student.name }}</div>
                  <div v-if="student.pisay" class="text-xs text-slate-500">{{ student.pisay }}</div>
                </button>
              </div>
              <div v-if="selectedStudent" class="text-xs text-emerald-700 mt-2 font-medium">Selected: {{ selectedStudent.name }}</div>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-2">Referral Category</label>
              <div class="space-y-2">
                <label v-for="category in referralCategoryOptions" :key="category" class="flex items-center gap-2 text-sm text-slate-700">
                  <input type="checkbox" :value="category" v-model="referralForm.referral_category" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                  <span>{{ category }}</span>
                </label>
              </div>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Brief Description</label>
              <textarea v-model="referralForm.brief_description" rows="4" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"></textarea>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-2">Behavior Spotted</label>
              <div class="space-y-2 max-h-56 overflow-y-auto border border-slate-200 rounded-lg p-3">
                <label v-for="behavior in behaviorOptions" :key="behavior" class="flex items-start gap-2 text-sm text-slate-700">
                  <input type="checkbox" :value="behavior" v-model="referralForm.behavior_spotted" class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                  <span>{{ behavior }}</span>
                </label>
              </div>
              <div v-if="showBehaviorOtherInput" class="mt-3">
                <label class="block text-xs font-medium text-slate-600 mb-1">Others (please specify)</label>
                <input v-model="referralForm.behavior_other" type="text" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
              </div>
            </div>
          </div>

          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click="closeReferralModal" :disabled="loadingReferral" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button @click.prevent="submitReferral" :disabled="loadingReferral" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
              <svg v-if="loadingReferral" class="w-4 h-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
              <span>{{ loadingReferral ? 'Saving...' : 'Save Referral' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Description modal -->
      <div v-if="showDescModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Description</h2>
            <button @click="closeDesc" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
            </button>
          </div>
          <div class="px-6 py-5 whitespace-pre-wrap text-sm text-slate-700">{{ descModalText }}</div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end">
            <button @click="closeDesc" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Close</button>
          </div>
        </div>
      </div>

      <!-- Assign modal -->
      <div v-if="showAssignModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Update Appointment</h2>
            <button @click="closeAssign" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
            </button>
          </div>
          <div class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Assigned Date & Time</label>
              <input type="datetime-local" v-model="assignDateTime" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Class Adviser (Faculty)</label>
              <select v-model="adviserId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full">
                <option :value="null">-- select adviser (optional) --</option>
                <option v-for="f in facultyUsers" :key="f.id" :value="f.id">{{ f.name }}</option>
              </select>
            </div>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click="closeAssign" :disabled="loadingAssign" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button @click.prevent="submitAssign" :disabled="loadingAssign" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
              <svg v-if="loadingAssign" class="w-4 h-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
              <span>{{ loadingAssign ? 'Saving...' : 'Save' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Intervention modal -->
      <div v-if="showInterventionModal" class="intervention-modal fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Record Intervention</h2>
            <button @click="closeIntervention" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
            </button>
          </div>
          <div class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Intervention Details</label>
              <textarea v-model="interventionText" rows="6" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"></textarea>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
              <select v-model="interventionStatus" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full">
                <option value="">-- select status --</option>
                <option>For Follow-up</option>
                <option>For Monitoring</option>
                <option>Done Intervention</option>
                <option>Refer to School Psychologist</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Class Adviser (Faculty)</label>
              <select v-model="interventionTeacherId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full">
                <option :value="null">-- select adviser (optional) --</option>
                <option v-for="f in facultyUsers" :key="f.id" :value="f.id">{{ f.name }}</option>
              </select>
            </div>
            <div v-if="interventionStatus === 'For Follow-up'">
              <label class="block text-xs font-medium text-slate-600 mb-1">Follow-up Date</label>
              <input type="date" v-model="interventionFollowupDate" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
            </div>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click="closeIntervention" :disabled="loadingIntervention" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button @click.prevent="submitIntervention" :disabled="loadingIntervention" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
              <svg v-if="loadingIntervention" class="w-4 h-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
              <span>{{ loadingIntervention ? 'Saving...' : 'Save Intervention' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Edit Intervention modal (separate, not nested) -->
      <div v-if="showEditInterventionModal" class="edit-intervention-modal fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800">Update Intervention</h2>
            <button @click="closeEditIntervention" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
            </button>
          </div>
          <div class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Previous Intervention Details</label>
              <textarea v-model="editInterventionText" rows="6" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full"></textarea>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
              <select v-model="editInterventionStatus" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full">
                <option value="">-- select status --</option>
                <option>For Follow-up</option>
                <option>For Monitoring</option>
                <option>Done Intervention</option>
                <option>Refer to School Psychologist</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Class Adviser (Faculty)</label>
              <select v-model="editInterventionTeacherId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full">
                <option :value="null">-- select adviser (optional) --</option>
                <option v-for="f in facultyUsers" :key="f.id" :value="f.id">{{ f.name }}</option>
              </select>
            </div>
            <div v-if="editInterventionStatus === 'For Follow-up'">
              <label class="block text-xs font-medium text-slate-600 mb-1">Follow-up Date</label>
              <input type="date" v-model="editInterventionFollowupDate" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full" />
            </div>
          </div>
          <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
            <button @click="closeEditIntervention" :disabled="loadingEditIntervention" class="inline-flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Cancel</button>
            <button @click.prevent="submitEditIntervention" :disabled="loadingEditIntervention" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
              <svg v-if="loadingEditIntervention" class="w-4 h-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
              <span>{{ loadingEditIntervention ? 'Saving...' : 'Save Changes' }}</span>
            </button>
          </div>
        </div>
      </div>
    </template>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import Swal from 'sweetalert2'
import { Head, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ClockIcon, HeartIcon, PencilIcon, CheckCircleIcon, PrinterIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ consultations: Array })
const list = ref(props.consultations || [])
// keep faculty list sorted alphabetically by name
const _faculty = (usePage().props.facultyUsers || []).map(u => ({ id: u.id, name: u.name }))
_faculty.sort((a, b) => (a.name || '').localeCompare(b.name || ''))
const facultyUsers = ref(_faculty)
const q = ref('')
const page = ref(1)
const perPage = 10

const pageProps = usePage().props
const roleName = pageProps.auth?.user?.role?.name || ''
const roleNames = pageProps.auth?.user?.roleNames || []
const canAssign = (roleName === 'Administrator' || roleName === 'Guidance' || (Array.isArray(roleNames) && (roleNames.includes('Administrator') || roleNames.includes('Guidance'))))
const canRefer = (Array.isArray(roleNames) && (roleNames.includes('Administrator') || roleNames.includes('Guidance') || roleNames.includes('Faculty') || roleNames.includes('Staff')))

const referralCategoryOptions = ['Academic', 'Behavior', 'Personal / Social']
const behaviorOptions = [
  'Depressed or apathetic mood',
  'Expression of helplessness, hopelessness, or worthlessness',
  'Evidence of crying',
  'Verbal expressions or gestures of suicide',
  'Noticeable changes in mood and/or sudden outbursts',
  'Inappropriate or exaggerated emotional reactions to situations, including lack of emotional response to stressful events',
  'Excessive dependency on others or extreme withdrawal and isolation from others',
  'Excessive activity or talkativeness',
  'Unusual or noticeable changes in interaction patterns with friends or classmates',
  'New or continuous behavior that disrupts the class',
  'Noticeable changes in physical appearance (weight, dress, hygiene)',
  'Extremely poor academic performance or a drastic decline in grades',
  'Others (please specify)',
]
const behaviorOthersLabel = 'Others (please specify)'

const showReferralModal = ref(false)
const studentSearchQuery = ref('')
const studentSearchResults = ref([])
const selectedStudent = ref(null)
const searchingStudents = ref(false)
const loadingReferral = ref(false)
let studentSearchTimer = null

const referralForm = ref({
  requestor_id: null,
  referral_category: [],
  behavior_spotted: [],
  behavior_other: '',
  brief_description: '',
})

const showBehaviorOtherInput = computed(() => referralForm.value.behavior_spotted.includes(behaviorOthersLabel))

const showAssignModal = ref(false)
const selectedConsultation = ref(null)
const assignDateTime = ref('')
const adviserId = ref(null)
const loadingAssign = ref(false)

const showDescModal = ref(false)
const descModalText = ref('')

// Intervention modal state
const showInterventionModal = ref(false)
const selectedInterventionConsultation = ref(null)
const interventionText = ref('')
const interventionStatus = ref('')
const interventionFollowupDate = ref('')
const interventionTeacherId = ref(null)
const loadingIntervention = ref(false)

// Edit intervention state
const showEditInterventionModal = ref(false)
const editSelectedConsultation = ref(null)
const editInterventionText = ref('')
const editInterventionStatus = ref('')
const editInterventionFollowupDate = ref('')
const editInterventionTeacherId = ref(null)
const loadingEditIntervention = ref(false)

function openDesc(text) { descModalText.value = text; showDescModal.value = true }
function closeDesc() { showDescModal.value = false; descModalText.value = '' }

function openAssign(c) {
  selectedConsultation.value = c
  assignDateTime.value = c.date_time_assigned ? new Date(c.date_time_assigned).toISOString().slice(0,16) : ''
  adviserId.value = null
  showAssignModal.value = true
}

function closeAssign() { showAssignModal.value = false; selectedConsultation.value = null; assignDateTime.value = '' }

function resetReferralForm() {
  studentSearchQuery.value = ''
  studentSearchResults.value = []
  selectedStudent.value = null
  referralForm.value = {
    requestor_id: null,
    referral_category: [],
    behavior_spotted: [],
    behavior_other: '',
    brief_description: '',
  }
}

function openReferralModal() {
  resetReferralForm()
  showReferralModal.value = true
}

function closeReferralModal() {
  showReferralModal.value = false
  resetReferralForm()
}

function selectStudent(student) {
  selectedStudent.value = student
  referralForm.value.requestor_id = student.id
  studentSearchQuery.value = student.name
  studentSearchResults.value = []
}

async function fetchStudentSearch() {
  const term = (studentSearchQuery.value || '').trim()
  if (term.length < 2) {
    studentSearchResults.value = []
    return
  }

  try {
    searchingStudents.value = true
    const url = `${route('guidance.students.search')}?q=${encodeURIComponent(term)}`
    const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    const data = await response.json().catch(() => ({}))
    if (response.ok) {
      studentSearchResults.value = data.students || []
    } else {
      studentSearchResults.value = []
    }
  } catch (error) {
    studentSearchResults.value = []
  } finally {
    searchingStudents.value = false
  }
}

watch(studentSearchQuery, (value) => {
  if (selectedStudent.value && value !== selectedStudent.value.name) {
    selectedStudent.value = null
    referralForm.value.requestor_id = null
  }

  if (studentSearchTimer) clearTimeout(studentSearchTimer)
  studentSearchTimer = setTimeout(() => {
    fetchStudentSearch()
  }, 250)
})

watch(showBehaviorOtherInput, (visible) => {
  if (!visible) {
    referralForm.value.behavior_other = ''
  }
})

async function submitReferral() {
  if (loadingReferral.value) return

  if (!referralForm.value.requestor_id) {
    Swal.fire({ icon: 'warning', text: 'Please select a student.' })
    return
  }
  if (!referralForm.value.referral_category.length) {
    Swal.fire({ icon: 'warning', text: 'Please select at least one referral category.' })
    return
  }
  if (!referralForm.value.brief_description || !referralForm.value.brief_description.trim()) {
    Swal.fire({ icon: 'warning', text: 'Please enter a brief description.' })
    return
  }
  if (!referralForm.value.behavior_spotted.length) {
    Swal.fire({ icon: 'warning', text: 'Please select at least one behavior spotted.' })
    return
  }
  if (showBehaviorOtherInput.value && (!referralForm.value.behavior_other || !referralForm.value.behavior_other.trim())) {
    Swal.fire({ icon: 'warning', text: 'Please specify the Other behavior.' })
    return
  }

  loadingReferral.value = true
  try {
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    const response = await fetch(route('guidance.referrals.store'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': token,
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        requestor_id: referralForm.value.requestor_id,
        referral_category: referralForm.value.referral_category,
        behavior_spotted: referralForm.value.behavior_spotted,
        behavior_other: referralForm.value.behavior_other,
        brief_description: referralForm.value.brief_description,
      }),
    })

    const data = await response.json().catch(() => ({}))
    if (!response.ok) {
      Swal.fire({ icon: 'error', text: data.message || 'Failed to save referral.' })
      return
    }

    Swal.fire({ icon: 'success', title: data.message || 'Referral saved.', timer: 1300, showConfirmButton: false })
    closeReferralModal()
    router.reload({ only: ['consultations'], preserveScroll: true })
  } catch (error) {
    Swal.fire({ icon: 'error', text: 'Unexpected error while saving referral.' })
  } finally {
    loadingReferral.value = false
  }
}

async function openIntervention(c) {
  // debug log to confirm click handler fires
  console.log('openIntervention() called for consultation id=', c?.id)
  console.log('showInterventionModal before =', showInterventionModal.value)
  selectedInterventionConsultation.value = c
  interventionText.value = ''
  interventionStatus.value = ''
  interventionFollowupDate.value = ''
  interventionTeacherId.value = c.teacher || null
  showInterventionModal.value = true
  await nextTick()
  console.log('showInterventionModal after =', showInterventionModal.value)
  // check if modal element exists in DOM
  setTimeout(() => {
    const found = document.querySelector('.intervention-modal')
    console.log('intervention modal element found?', !!found, found)
  }, 80)
}

async function openEditIntervention(c) {
  editSelectedConsultation.value = c
  editInterventionText.value = ''
  editInterventionStatus.value = ''
  editInterventionFollowupDate.value = ''
  showEditInterventionModal.value = true
  // fetch decrypted intervention and current status/followup
  try {
    const url = route('guidance.consultations.intervention.get', c.id)
    const resp = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
    const data = await resp.json().catch(() => ({}))
    if (resp.ok) {
      editInterventionText.value = data.intervention || ''
      editInterventionStatus.value = data.status || ''
      editInterventionFollowupDate.value = data.followup_date || ''
      editInterventionTeacherId.value = data.consultation?.teacher || null
    } else {
      showEditInterventionModal.value = false
      Swal.fire({ icon: 'error', text: data.message || 'Failed to load intervention.' })
    }
  } catch (e) {
    showEditInterventionModal.value = false
    console.error(e)
    Swal.fire({ icon: 'error', text: 'Unexpected error while loading intervention.' })
  }
}

function closeEditIntervention() { showEditInterventionModal.value = false; editSelectedConsultation.value = null; editInterventionText.value = ''; editInterventionStatus.value = ''; editInterventionFollowupDate.value = ''; editInterventionTeacherId.value = null }

async function submitEditIntervention() {
  if (loadingEditIntervention.value) return
  if (!editInterventionText.value || !editInterventionStatus.value) {
    Swal.fire({ icon: 'warning', text: 'Please enter intervention details and select a status.' })
    return
  }
  if (editInterventionStatus.value === 'For Follow-up' && !editInterventionFollowupDate.value) {
    Swal.fire({ icon: 'warning', text: 'Please select a follow-up date for For Follow-up status.' })
    return
  }
  loadingEditIntervention.value = true
  try {
    const url = route('guidance.consultations.intervention', editSelectedConsultation.value.id)
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    const resp = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': token,
      },
      body: JSON.stringify({ intervention: editInterventionText.value, status: editInterventionStatus.value, followup_date: editInterventionFollowupDate.value || null, teacher: editInterventionTeacherId.value || null }),
      credentials: 'same-origin',
    })
    const data = await resp.json().catch(() => ({}))
    if (resp.ok) {
      if (data && data.consultation) {
        const updated = data.consultation
        const idx = list.value.findIndex(x => x.id === updated.id)
        if (idx !== -1) {
          list.value.splice(idx, 1, Object.assign({}, list.value[idx], updated))
        }
      }
      closeEditIntervention()
      Swal.fire({ icon: 'success', title: data.message || 'Intervention updated', timer: 1400, showConfirmButton: false })
    } else {
      const msg = data.message || 'Failed to update intervention.'
      Swal.fire({ icon: 'error', text: msg })
    }
  } catch (e) {
    console.error(e)
    Swal.fire({ icon: 'error', text: 'Unexpected error. See console.' })
  } finally {
    loadingEditIntervention.value = false
  }
}

function closeIntervention() { showInterventionModal.value = false; selectedInterventionConsultation.value = null; interventionText.value = ''; interventionStatus.value = ''; interventionTeacherId.value = null }

async function submitIntervention() {
  if (loadingIntervention.value) return
  if (!interventionText.value || !interventionStatus.value) {
    // eslint-disable-next-line no-undef
    Swal.fire({ icon: 'warning', text: 'Please enter intervention details and select a status.' })
    return
  }
  if (interventionStatus.value === 'For Follow-up' && !interventionFollowupDate.value) {
    // eslint-disable-next-line no-undef
    Swal.fire({ icon: 'warning', text: 'Please select a follow-up date for For Follow-up status.' })
    return
  }
  loadingIntervention.value = true
  try {
    const url = route('guidance.consultations.intervention', selectedInterventionConsultation.value.id)
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    const resp = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': token,
      },
      body: JSON.stringify({ intervention: interventionText.value, status: interventionStatus.value, followup_date: interventionFollowupDate.value || null, teacher: interventionTeacherId.value || null }),
      credentials: 'same-origin',
    })
    const data = await resp.json().catch(() => ({}))
    if (resp.ok) {
      if (data && data.consultation) {
        const updated = data.consultation
        const idx = list.value.findIndex(x => x.id === updated.id)
        if (idx !== -1) {
          list.value.splice(idx, 1, Object.assign({}, list.value[idx], updated))
        }
      }
      closeIntervention()
      // eslint-disable-next-line no-undef
      Swal.fire({ icon: 'success', title: data.message || 'Intervention recorded', timer: 1400, showConfirmButton: false })
    } else {
      const msg = data.message || 'Failed to save intervention.'
      // eslint-disable-next-line no-undef
      Swal.fire({ icon: 'error', text: msg })
    }
  } catch (e) {
    console.error(e)
    // eslint-disable-next-line no-undef
    Swal.fire({ icon: 'error', text: 'Unexpected error. See console.' })
  } finally {
    loadingIntervention.value = false
  }
}

async function submitAssign() {
  if (loadingAssign.value) return
  if (!assignDateTime.value) { alert('Please select a date and time'); return }
  const chosen = new Date(assignDateTime.value)
  const now = new Date()
  if (chosen < now) { alert('Selected date/time cannot be in the past'); return }
  try {
    loadingAssign.value = true
    const url = route('guidance.consultations.assign', selectedConsultation.value.id)
    try {
      const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      const resp = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': token,
        },
        body: JSON.stringify({ date_time_assigned: assignDateTime.value, adviser_id: adviserId.value }),
        credentials: 'same-origin',
      })

      const data = await resp.json().catch(() => ({}))
      if (resp.ok) {
        // update the local list with the returned consultation if provided
        if (data && data.consultation) {
          const updated = data.consultation
          const idx = list.value.findIndex(x => x.id === updated.id)
          if (idx !== -1) {
            // merge to preserve any existing fields not returned
            list.value.splice(idx, 1, Object.assign({}, list.value[idx], updated))
          } else {
            // if not found, prepend
            list.value.unshift(updated)
          }
        }
        closeAssign()
        // eslint-disable-next-line no-undef
        Swal.fire({ icon: 'success', title: data.message || 'Appointment updated', timer: 1200, showConfirmButton: false })
      } else {
        closeAssign()
        const msg = data.message || 'Failed to assign appointment or send email.'
        // eslint-disable-next-line no-undef
        Swal.fire({ icon: 'error', text: msg })
      }
    } finally {
      loadingAssign.value = false
    }
  } catch (e) {
    closeAssign()
    // eslint-disable-next-line no-undef
    Swal.fire({ icon: 'error', text: 'Unexpected error. See console.' })
    console.error(e)
  }
}

function openAdmissionSlip(c) {
  try {
    const url = route('guidance.consultations.admission-slip', c.id)
    window.open(url, '_blank')
  } catch (e) {
    console.error('Failed to open admission slip', e)
    Swal.fire({ icon: 'error', text: 'Failed to open admission slip.' })
  }
}

const filtered = computed(() => {
  const term = (q.value || '').toString().toLowerCase().trim()
  if (!term) return list.value
  return list.value.filter(c => {
    return (c.concern||'').toString().toLowerCase().includes(term)
      || (c.requestor_name||'').toString().toLowerCase().includes(term)
      || (c.status||'').toString().toLowerCase().includes(term)
      || (c.id||'').toString().includes(term)
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)))
const paged = computed(() => {
  const start = (page.value - 1) * perPage
  return filtered.value.slice(start, start + perPage)
})

watch(q, () => { page.value = 1 })

// keep local list in sync when Inertia updates the shared `consultations` prop
watch(() => usePage().props.consultations, (val) => {
  list.value = val || []
})

const prev = () => { if (page.value>1) page.value-- }
const next = () => { if (page.value<totalPages.value) page.value++ }

function formatDate(d) {
  if (!d) return '—'
  try { return new Date(d).toLocaleString() } catch (e) { return d }
}
</script>
