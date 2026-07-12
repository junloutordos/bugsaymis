<template>
  <Head title="Guidance Consultations" />
  <AdminLayout :title="'Guidance Consultations'">
    <template #default>
      <div class="space-y-5">

        <AppPageHeader title="Guidance Consultations" subtitle="Track and manage student consultation records">
          <template #actions>
            <AppButton v-if="canRefer" @click.prevent="openReferralModal">+ Add Referral</AppButton>
          </template>
        </AppPageHeader>

        <!-- sub-nav -->
        <div class="flex items-center gap-2 text-sm flex-wrap">
          <Link href="/guidance/dashboard" class="px-3 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition-colors">Dashboard</Link>
          <span class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-medium">Consultations</span>
          <Link href="/guidance/session-reports" class="px-3 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition-colors">Session Reports</Link>
          <Link href="/guidance/reports" class="px-3 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 font-medium transition-colors">Reports</Link>
        </div>

        <!-- Filter bar -->
        <AppFilterBar>
          <input
            v-model="q"
            type="text"
            placeholder="Search consultations..."
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 w-full sm:w-64"
          />
        </AppFilterBar>

        <!-- Table -->
        <AppTable :is-empty="filtered.length === 0" :skeleton-cols="11">
          <template #head>
            <tr>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">#</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Requestor</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Concern</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap hidden md:table-cell">Referred By</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap hidden md:table-cell">Referral Category</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap hidden md:table-cell">Behavior Spotted</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap hidden md:table-cell">Description</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap hidden md:table-cell">Preferred</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap hidden md:table-cell">Assigned</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
            </tr>
          </template>

          <tr v-for="c in paged" :key="c.id" class="hover:bg-indigo-50/40">
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
              <AppBadge :color="statusBadgeColor(c.status)">{{ c.status }}</AppBadge>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-1">
                <AppIconButton v-if="canAssign && String(c.status).toLowerCase() === 'pending'" label="Update Appointment" variant="ghost" @click.prevent="openAssign(c)">
                  <ClockIcon class="h-4 w-4" />
                </AppIconButton>
                <AppIconButton v-if="canAssign && String(c.status).toLowerCase() === 'scheduled'" label="Add Intervention" variant="success" @click.prevent="openIntervention(c)">
                  <HeartIcon class="h-4 w-4" />
                </AppIconButton>
                <AppIconButton v-if="canAssign && c.status === 'For Follow-up'" label="Update Intervention" variant="warning" @click.prevent="openEditIntervention(c)">
                  <PencilIcon class="h-4 w-4" />
                </AppIconButton>
                <span v-if="c.status === 'Done Intervention'" class="p-1.5 rounded-lg text-success-600" title="Done" aria-hidden="true">
                  <CheckCircleIcon class="h-4 w-4" />
                </span>
                <AppIconButton v-if="['For Follow-up','For Monitoring','Done Intervention','Refer to School Psychologist'].includes(c.status)" label="Print Admission Slip" variant="ghost" @click.prevent="openAdmissionSlip(c)">
                  <PrinterIcon class="h-4 w-4" />
                </AppIconButton>
                <AppIconButton v-if="canAssign" label="Create Session Report" variant="ghost" @click.prevent="openSessionReport(c)">
                  <DocumentTextIcon class="h-4 w-4" />
                </AppIconButton>
              </div>
            </td>
          </tr>

          <template #mobileCard>
            <div v-for="c in paged" :key="c.id" class="p-4 space-y-2">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="text-xs text-slate-400">#{{ c.id }}</p>
                  <p class="font-medium text-slate-800">{{ c.requestor_name || c.requestor_id || '—' }}</p>
                  <p class="text-xs text-slate-500">{{ c.concern }}</p>
                </div>
                <AppBadge :color="statusBadgeColor(c.status)">{{ c.status }}</AppBadge>
              </div>
              <p class="text-xs text-slate-500">Referred by {{ c.referred_by_name || c.referred_by || '—' }} &middot; Assigned {{ c.assigned_personnel_name || '—' }}</p>
              <div class="flex items-center gap-1 pt-1">
                <AppIconButton v-if="canAssign && String(c.status).toLowerCase() === 'pending'" label="Update Appointment" variant="ghost" @click.prevent="openAssign(c)">
                  <ClockIcon class="h-4 w-4" />
                </AppIconButton>
                <AppIconButton v-if="canAssign && String(c.status).toLowerCase() === 'scheduled'" label="Add Intervention" variant="success" @click.prevent="openIntervention(c)">
                  <HeartIcon class="h-4 w-4" />
                </AppIconButton>
                <AppIconButton v-if="canAssign && c.status === 'For Follow-up'" label="Update Intervention" variant="warning" @click.prevent="openEditIntervention(c)">
                  <PencilIcon class="h-4 w-4" />
                </AppIconButton>
                <span v-if="c.status === 'Done Intervention'" class="p-1.5 rounded-lg text-success-600" title="Done" aria-hidden="true">
                  <CheckCircleIcon class="h-4 w-4" />
                </span>
                <AppIconButton v-if="['For Follow-up','For Monitoring','Done Intervention','Refer to School Psychologist'].includes(c.status)" label="Print Admission Slip" variant="ghost" @click.prevent="openAdmissionSlip(c)">
                  <PrinterIcon class="h-4 w-4" />
                </AppIconButton>
                <AppIconButton v-if="canAssign" label="Create Session Report" variant="ghost" @click.prevent="openSessionReport(c)">
                  <DocumentTextIcon class="h-4 w-4" />
                </AppIconButton>
              </div>
            </div>
          </template>

          <template #empty>
            <EmptyState title="No consultations found." />
          </template>

          <template #footer>
            <PaginationControl :current-page="page" :total-pages="totalPages" @prev="prev" @next="next" @page="page = $event" />
          </template>
        </AppTable>
      </div>

      <!-- Add Referral modal -->
      <AppModal :show="showReferralModal" title="Student Referral to Guidance Office" size="2xl" @close="closeReferralModal">
        <div class="space-y-4">
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
            <div v-if="selectedStudent" class="text-xs text-success-700 mt-2 font-medium">Selected: {{ selectedStudent.name }}</div>
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

        <template #footer>
          <AppButton variant="secondary" @click="closeReferralModal" :disabled="loadingReferral">Cancel</AppButton>
          <AppButton @click.prevent="submitReferral" :loading="loadingReferral">{{ loadingReferral ? 'Saving...' : 'Save Referral' }}</AppButton>
        </template>
      </AppModal>

      <!-- Description modal -->
      <AppModal :show="showDescModal" title="Description" size="2xl" @close="closeDesc">
        <p class="whitespace-pre-wrap text-sm text-slate-700">{{ descModalText }}</p>
        <template #footer>
          <AppButton variant="secondary" @click="closeDesc">Close</AppButton>
        </template>
      </AppModal>

      <!-- Assign modal -->
      <AppModal :show="showAssignModal" title="Update Appointment" size="md" @close="closeAssign">
        <div class="space-y-4">
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
        <template #footer>
          <AppButton variant="secondary" @click="closeAssign" :disabled="loadingAssign">Cancel</AppButton>
          <AppButton @click.prevent="submitAssign" :loading="loadingAssign">{{ loadingAssign ? 'Saving...' : 'Save' }}</AppButton>
        </template>
      </AppModal>

      <!-- Intervention modal -->
      <AppModal :show="showInterventionModal" title="Record Intervention" size="md" panel-class="intervention-modal" @close="closeIntervention">
        <div class="space-y-4">
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
        <template #footer>
          <AppButton variant="secondary" @click="closeIntervention" :disabled="loadingIntervention">Cancel</AppButton>
          <AppButton @click.prevent="submitIntervention" :loading="loadingIntervention">{{ loadingIntervention ? 'Saving...' : 'Save Intervention' }}</AppButton>
        </template>
      </AppModal>

      <!-- Edit Intervention modal (separate, not nested) -->
      <AppModal :show="showEditInterventionModal" title="Update Intervention" size="md" panel-class="edit-intervention-modal" @close="closeEditIntervention">
        <div class="space-y-4">
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
        <template #footer>
          <AppButton variant="secondary" @click="closeEditIntervention" :disabled="loadingEditIntervention">Cancel</AppButton>
          <AppButton @click.prevent="submitEditIntervention" :loading="loadingEditIntervention">{{ loadingEditIntervention ? 'Saving...' : 'Save Changes' }}</AppButton>
        </template>
      </AppModal>

      <!-- Session Report Modal -->
      <AppModal :show="showSessionReportModal" title="New Counseling Session Report" size="2xl" @close="showSessionReportModal = false">
        <form @submit.prevent="submitSessionReport" class="space-y-4">

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Date Filed <span class="text-danger-600">*</span></label>
              <input v-model="srForm.date_filed" type="date" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">School Year <span class="text-danger-600">*</span></label>
              <input v-model="srForm.school_year" type="text" placeholder="e.g. 2025-2026" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
          </div>

          <fieldset class="border border-slate-200 rounded-xl p-4 space-y-3">
            <legend class="text-xs font-semibold text-slate-600 px-1">I. Client Profile</legend>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Name <span class="text-danger-600">*</span></label>
              <input v-model="srForm.client_name" type="text" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Age</label>
                <input v-model.number="srForm.client_age" type="number" min="1" max="99" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Sex</label>
                <select v-model="srForm.client_sex" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option value="">—</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Batch</label>
                <input v-model="srForm.batch" type="text" placeholder="e.g. 2028" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Grade &amp; Section</label>
              <input v-model="srForm.grade_section" type="text" placeholder="e.g. Grade 11 — Einstein" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
          </fieldset>

          <fieldset class="border border-slate-200 rounded-xl p-4">
            <legend class="text-xs font-semibold text-slate-600 px-1">II. Referral Details / Strategies <span class="text-danger-600">*</span></legend>
            <div class="flex flex-wrap gap-4 mt-2">
              <label v-for="s in srStrategyOptions" :key="s" class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                <input type="checkbox" :value="s" v-model="srForm.referral_strategies" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                {{ s }}
              </label>
            </div>
            <p v-if="srStrategyError" class="text-xs text-danger-600 mt-1">Please select at least one strategy.</p>
          </fieldset>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Concern</label>
            <input v-model="srForm.concern" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Difficulties Presented</label>
            <textarea v-model="srForm.difficulties_presented" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Counselor's Notes</label>
            <textarea v-model="srForm.counselor_notes" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
          </div>

          <p v-if="srError" class="text-sm text-danger-600">{{ srError }}</p>

          <div class="flex justify-end gap-3 pt-1">
            <AppButton type="button" variant="secondary" @click="showSessionReportModal = false">Cancel</AppButton>
            <AppButton type="submit" :loading="srSaving">{{ srSaving ? 'Saving…' : 'Create Session Report' }}</AppButton>
          </div>
        </form>
      </AppModal>

    </template>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import Swal from 'sweetalert2'
import { Head, Link, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { ClockIcon, HeartIcon, PencilIcon, CheckCircleIcon, PrinterIcon, DocumentTextIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ consultations: Array })
const list = ref(props.consultations || [])
// Keep list in sync when Inertia does partial reloads
watch(() => props.consultations, (val) => { list.value = val || [] })
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

function statusBadgeColor(status) {
  const s = String(status).toLowerCase()
  if (s === 'pending') return 'amber'
  if (['done intervention', 'for monitoring'].includes(s)) return 'green'
  if (s === 'scheduled' || s === 'for follow-up') return 'blue'
  return 'slate'
}

// ─── Session Report ───────────────────────────────────────────────────────────
const srStrategyOptions = ['Referral', 'Called-In', 'Walk-In', 'Case Conference', 'Homevisit']
const showSessionReportModal = ref(false)
const srSaving = ref(false)
const srStrategyError = ref(false)
const srError = ref('')
const srConsultationId = ref(null)

function currentSchoolYear() {
  const now = new Date()
  return now.getMonth() >= 5
    ? `${now.getFullYear()}-${now.getFullYear() + 1}`
    : `${now.getFullYear() - 1}-${now.getFullYear()}`
}

const srForm = ref({})

function openSessionReport(c) {
  srConsultationId.value = c.id
  srStrategyError.value = false
  srError.value = ''
  // Pre-fill from consultation data where available
  const strategy = c.consultation_type === 'referred' ? ['Referral'] : ['Walk-In']
  srForm.value = {
    date_filed:             new Date().toISOString().slice(0, 10),
    school_year:            currentSchoolYear(),
    client_name:            c.requestor_name ?? '',
    client_age:             null,
    client_sex:             '',
    grade_section:          '',
    batch:                  '',
    referral_strategies:    strategy,
    concern:                c.concern ?? '',
    difficulties_presented: c.brief_description ?? c.description ?? '',
    counselor_notes:        '',
  }
  showSessionReportModal.value = true
}

async function submitSessionReport() {
  srStrategyError.value = false
  srError.value = ''

  if (!srForm.value.referral_strategies.length) {
    srStrategyError.value = true
    return
  }

  const currentUser = usePage().props.auth?.user
  srSaving.value = true
  try {
    const resp = await fetch('/guidance/session-reports', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        ...srForm.value,
        consultation_id:  srConsultationId.value,
        accomplished_by:  currentUser?.id,
      }),
    })
    const data = await resp.json().catch(() => ({}))
    if (resp.ok) {
      showSessionReportModal.value = false
      Swal.fire({
        icon: 'success',
        title: 'Session report created.',
        html: `CS No: <strong>${data.report?.cs_no ?? ''}</strong><br/><a href="/guidance/session-reports/${data.report?.id}/print" target="_blank" class="text-indigo-600 underline text-sm">Print now</a>`,
        confirmButtonText: 'OK',
      })
    } else {
      srError.value = data.message ?? 'Failed to save. Please try again.'
    }
  } catch {
    srError.value = 'Network error. Please try again.'
  } finally {
    srSaving.value = false
  }
}
</script>
