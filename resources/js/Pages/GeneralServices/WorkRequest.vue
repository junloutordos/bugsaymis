<template>
  <Head title="Work Requests" />
  <AdminLayout title="Work Requests">
    <div class="space-y-5">
      <!-- Page header -->
      <AppPageHeader title="Work Requests" subtitle="Manage building and maintenance work requests">
        <template #actions>
          <AppButton v-if="!hasRole('GSU Head')" @click.prevent="handleNewRequest()">
            + New Request
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Search -->
      <AppFilterBar>
        <div class="relative flex-1 min-w-[180px] sm:w-72 sm:flex-none">
          <AppInput
            v-model="searchQuery"
            type="text"
            placeholder="Search work requests…"
            @keydown.enter.prevent="applyFilters"
          />
        </div>
        <template #actions>
          <AppButton :disabled="isLoading" @click="applyFilters">Search</AppButton>
          <AppButton v-if="searchQuery" variant="secondary" :disabled="isLoading" @click="clearFilters">Clear</AppButton>
          <span v-if="isLoading" class="text-xs text-slate-400">Searching...</span>
        </template>
      </AppFilterBar>

      <!-- Table card -->
      <AppTable :is-empty="filteredWorkRequests.length === 0" :skeleton-cols="tableColCount">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">#</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Date Created</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Issue</th>
            <th v-if="hasAnyRole('Administrator','GSU Head','DivisionChief')" class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Requestor</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Inspection</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Action</th>
          </tr>
        </template>

        <tr v-for="wr in filteredWorkRequests" :key="wr.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm text-slate-700">{{ wr.id }}</td>
          <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">{{ wr.created_at ? new Date(wr.created_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">{{ wr.issue ?? '—' }}</td>
          <td v-if="hasAnyRole('Administrator','GSU Head','DivisionChief')" class="px-4 py-3 text-sm text-slate-700">{{ wr.requester?.name ?? '—' }}</td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <AppBadge :color="statusClass(wr.status)">{{ wr.status ?? '—' }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <AppBadge :color="inspectionStatusClass(wr)">{{ inspectionStatus(wr) }}</AppBadge>
          </td>
          <td class="px-4 py-3 text-center">
            <div class="flex items-center gap-1.5 justify-center">
              <AppIconButton
                label="View Details"
                @click.prevent="openDetailsModal(wr)"
              >
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>

              <AppIconButton
                v-if="((wr.status === 'Division Approved' && hasRole('Administrator')) || (wr.status === 'Pending' && hasRole('GSU Head')))"
                label="Assign"
                @click.prevent="openModal(wr)"
              >
                <UserPlusIcon class="w-4 h-4" />
              </AppIconButton>

              <AppIconButton
                v-if="hasRole('Administrator')"
                label="Edit"
                @click.prevent="openModal(wr)"
              >
                <PencilSquareIcon class="w-4 h-4" />
              </AppIconButton>

              <AppIconButton
                v-if="hasRole('Administrator')"
                label="Delete"
                variant="danger"
                @click.prevent="destroy(wr)"
              >
                <TrashIcon class="w-4 h-4" />
              </AppIconButton>

              <AppIconButton
                v-if="canOpenInspection(wr)"
                label="Pre-Repair Inspection"
                variant="warning"
                @click.prevent="openInspectionModal(wr)"
              >
                <ClipboardDocumentCheckIcon class="w-4 h-4" />
              </AppIconButton>

              <AppIconButton
                v-if="((wr.status === 'FAD Approved' && (hasRole('GSU Head') || hasRole('Administrator'))) || (wr.status === 'Division Approved' && hasRole('GSU Head')))"
                label="Mark Completed"
                variant="success"
                @click.prevent="openCompleteModal(wr)"
              >
                <CheckCircleIcon class="w-4 h-4" />
              </AppIconButton>

              <AppButton
                v-if="(wr.status === 'Completed') && (hasAnyRole('GSU Head','Administrator'))"
                as="a"
                :href="`/work-requests/${wr.id}/print`"
                target="_blank"
                size="sm"
                variant="secondary"
              >
                <PrinterIcon class="w-3.5 h-3.5" /> Print
              </AppButton>

              <AppButton
                v-if="wr.status === 'Completed' && wr.requester_id === page.props.auth.user.id"
                size="sm"
                variant="success"
                @click.prevent="openCsmModal(wr)"
              >Confirm &amp; Rate</AppButton>
            </div>
          </td>
        </tr>

        <template #mobileCard>
          <div v-for="wr in filteredWorkRequests" :key="wr.id" class="p-4 space-y-3">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="text-xs text-slate-500">Request #{{ wr.id }} &bull; {{ wr.created_at ? new Date(wr.created_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—' }}</p>
                <p class="text-sm font-semibold text-slate-800 mt-0.5 truncate">{{ wr.issue ?? '—' }}</p>
                <p v-if="hasAnyRole('Administrator','GSU Head','DivisionChief')" class="text-xs text-slate-600 mt-1">Requestor: {{ wr.requester?.name ?? '—' }}</p>
              </div>
              <AppIconButton label="View Details" @click.prevent="openDetailsModal(wr)">
                <EyeIcon class="w-4 h-4" />
              </AppIconButton>
            </div>
            <div class="flex items-center gap-2 text-xs text-slate-700">
              <AppBadge :color="statusClass(wr.status)">{{ wr.status }}</AppBadge>
              <AppBadge :color="inspectionStatusClass(wr)">{{ inspectionStatus(wr) }}</AppBadge>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <AppButton v-if="((wr.status === 'Division Approved' && hasRole('Administrator')) || (wr.status === 'Pending' && hasRole('GSU Head')))" size="sm" @click.prevent="openModal(wr)">Assign</AppButton>
              <AppButton v-if="hasRole('Administrator')" size="sm" @click.prevent="openModal(wr)">Edit</AppButton>
              <AppButton v-if="hasRole('Administrator')" size="sm" variant="danger" @click.prevent="destroy(wr)">Delete</AppButton>
              <AppButton v-if="canOpenInspection(wr)" size="sm" variant="warning" @click.prevent="openInspectionModal(wr)">Inspection</AppButton>
              <AppButton v-if="((wr.status === 'FAD Approved' && (hasRole('GSU Head') || hasRole('Administrator'))) || (wr.status === 'Division Approved' && hasRole('GSU Head')))" size="sm" variant="success" @click.prevent="openCompleteModal(wr)">Mark Completed</AppButton>
              <AppButton v-if="(wr.status === 'Completed') && (hasAnyRole('GSU Head','Administrator'))" as="a" :href="`/work-requests/${wr.id}/print`" target="_blank" size="sm" variant="secondary">Print</AppButton>
              <AppButton v-if="wr.status === 'Completed' && wr.requester_id === page.props.auth.user.id" size="sm" variant="success" @click.prevent="openCsmModal(wr)">Confirm &amp; Rate</AppButton>
            </div>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No work requests found." />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="currentPage"
            :total-pages="totalPages"
            :total="props.workRequests?.total ?? 0"
            @prev="goToPage(currentPage - 1)"
            @next="goToPage(currentPage + 1)"
            @page="goToPage"
          />
        </template>
      </AppTable>

      <!-- View Details Modal -->
      <AppModal :show="showDetailsModal" :title="`Work Request #${detailsWorkRequest?.id ?? ''}`" size="lg" @close="closeDetailsModal">
        <div class="grid grid-cols-2 gap-3 text-sm text-slate-700">
          <div><span class="text-xs font-medium text-slate-500 uppercase">Date Created</span><p class="mt-0.5">{{ detailsWorkRequest?.created_at ? new Date(detailsWorkRequest.created_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—' }}</p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase">Requestor</span><p class="mt-0.5">{{ detailsWorkRequest?.requester?.name ?? '—' }}</p></div>
          <div class="col-span-2"><span class="text-xs font-medium text-slate-500 uppercase">Issue</span><p class="mt-0.5">{{ detailsWorkRequest?.issue ?? '—' }}</p></div>
          <div class="col-span-2"><span class="text-xs font-medium text-slate-500 uppercase">Description</span><p class="mt-0.5">{{ detailsWorkRequest?.description ?? '—' }}</p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase">Assigned Personnel</span><p class="mt-0.5">{{ detailsWorkRequest?.assigned_user?.name ?? '—' }}</p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase">Acted By</span><p class="mt-0.5">{{ detailsWorkRequest?.actedBy?.name ?? '—' }}</p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase">Expected Completion</span><p class="mt-0.5">{{ detailsWorkRequest?.expected_completion_date ? new Date(detailsWorkRequest.expected_completion_date).toLocaleDateString() : '—' }}</p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase">Date Completed</span><p class="mt-0.5">{{ detailsWorkRequest?.date_completed ? new Date(detailsWorkRequest.date_completed).toLocaleDateString() : '—' }}</p></div>
          <div class="col-span-2"><span class="text-xs font-medium text-slate-500 uppercase">Action Taken</span><p class="mt-0.5">{{ detailsWorkRequest?.action_taken ?? '—' }}</p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase">Status</span><p class="mt-0.5"><AppBadge :color="statusClass(detailsWorkRequest?.status)">{{ detailsWorkRequest?.status ?? '—' }}</AppBadge></p></div>
          <div><span class="text-xs font-medium text-slate-500 uppercase">Inspection</span><p class="mt-0.5"><AppBadge :color="inspectionStatusClass(detailsWorkRequest)">{{ inspectionStatus(detailsWorkRequest) }}</AppBadge></p></div>
        </div>
        <template #footer>
          <AppButton variant="secondary" @click="closeDetailsModal">Close</AppButton>
        </template>
      </AppModal>

      <!-- Create / Edit Modal -->
      <AppModal :show="showModal" :title="editingId ? 'Edit Work Request' : 'New Work Request'" size="2xl" @close="closeModal">
        <form @submit.prevent="submitForm" class="space-y-4">
          <AppInput v-model="form.issue" label="Issue" required />

          <AppTextarea v-model="form.description" label="Description" :rows="4" />

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Building</label>
              <select v-model="form.location_division_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="">Select building</option>
                <option v-for="d in props.divisions" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Room</label>
              <select v-model="form.location_office_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="">Select room</option>
                <option v-for="o in filteredOffices" :key="o.id" :value="o.id">{{ o.name }}</option>
              </select>
            </div>

            <AppSelect v-model="form.priority" label="Priority" :show-blank="false">
              <option>Low</option>
              <option>Normal</option>
              <option>High</option>
            </AppSelect>

            <AppInput v-model="form.expected_completion_date" type="date" label="Expected Completion" />
          </div>

          <div v-if="editingId && (hasRole('GSU Head') || hasRole('Administrator'))" class="mt-3 grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Assign Personnel</label>
              <select v-model="form.assigned_user_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="">Select staff</option>
                <option v-for="u in (props.skilledUsers || [])" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Work Category <span class="text-danger-600">*</span></label>
              <select v-model="form.category" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
                <option value="">Select category</option>
                <option v-for="c in WORK_CATEGORIES" :key="c">{{ c }}</option>
              </select>
            </div>
          </div>
        </form>
        <template #footer>
          <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
          <AppButton :loading="form.processing" @click="editingId ? submitForm() : openPinModal()">
            Save
          </AppButton>
        </template>
      </AppModal>

      <!-- Completion Modal -->
      <AppModal :show="showCompleteModal" title="Mark Work Request Completed" size="md" @close="closeCompleteModal">
        <form @submit.prevent="submitCompletion" class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Acted By</label>
            <select v-model="completeForm.acted_by_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
              <option value="">Select staff</option>
              <option v-for="u in (props.skilledUsers || [])" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <AppTextarea v-model="completeForm.action_taken" label="Action Taken" :rows="4" required />
          <AppInput v-model="completeForm.date_completed" type="date" label="Date Completed" required />
        </form>
        <template #footer>
          <AppButton variant="secondary" @click="closeCompleteModal">Cancel</AppButton>
          <AppButton type="submit" :loading="completeForm.processing" @click="submitCompletion">
            Save
          </AppButton>
        </template>
      </AppModal>

      <!-- Pre-Repair Inspection Modal -->
      <AppModal
        :show="showInspectionModal"
        title="Pre-Repair Inspection Report"
        :subtitle="`PSHS-00-F-GSM-14-Ver02-Rev1 · Work Request #${inspectionWorkRequest?.id}`"
        size="4xl"
        @close="closeInspectionModal"
      >
        <div class="space-y-5">
          <div v-if="inspectionWorkRequest?.pre_repair_inspection?.return_reason" class="rounded-lg border border-warning-100 bg-warning-50 px-4 py-3 text-sm text-warning-700">
            Returned for revision: {{ inspectionWorkRequest.pre_repair_inspection.return_reason }}
          </div>

          <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Inspector</label>
              <select v-model="inspectionForm.inspector_id" :disabled="!canEditInspection(inspectionWorkRequest)" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-slate-50">
                <option value="">Select inspector</option>
                <option v-for="u in (props.skilledUsers || [])" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <AppSelect v-model="inspectionForm.asset_type" label="Asset Type" :disabled="!canEditInspection(inspectionWorkRequest)" :show-blank="false">
              <option value="building_facility">Building / Facilities</option>
              <option value="equipment_machinery">Equipment / Machinery</option>
            </AppSelect>
            <AppSelect v-model="inspectionForm.warranty_status" label="Warranty Status" :disabled="!canEditInspection(inspectionWorkRequest)" :show-blank="false">
              <option value="unknown">Unknown / Not indicated</option>
              <option value="with_warranty">With Warranty</option>
              <option value="without_warranty">Without Warranty</option>
            </AppSelect>
          </div>

          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <AppInput v-model="inspectionForm.building_facility" label="Building / Facility" :disabled="!canEditInspection(inspectionWorkRequest)" />
            <AppInput v-model="inspectionForm.property_no" label="Property No." :disabled="!canEditInspection(inspectionWorkRequest)" />
            <AppInput v-model="inspectionForm.location" label="Location" :disabled="!canEditInspection(inspectionWorkRequest)" />
            <AppInput v-model="inspectionForm.serial_no" label="Serial No." :disabled="!canEditInspection(inspectionWorkRequest)" />
            <AppInput v-model="inspectionForm.date_of_purchase" type="date" label="Date of Purchase" :disabled="!canEditInspection(inspectionWorkRequest)" />
            <AppInput v-model="inspectionForm.purchase_cost" type="number" min="0" step="0.01" label="Purchase Cost" :disabled="!canEditInspection(inspectionWorkRequest)" />
          </div>

          <AppTextarea v-model="inspectionForm.description" label="Description" :rows="2" :disabled="!canEditInspection(inspectionWorkRequest)" />

          <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <AppTextarea v-model="inspectionForm.location_of_defect" label="Location of Defect" :rows="3" :disabled="!canEditInspection(inspectionWorkRequest)" />
            <AppTextarea v-model="inspectionForm.nature_of_defect" label="Nature of Defect" :rows="3" required :disabled="!canEditInspection(inspectionWorkRequest)" />
            <AppTextarea v-model="inspectionForm.cause_of_defect" label="Cause of Defect" :rows="3" :disabled="!canEditInspection(inspectionWorkRequest)" />
          </div>

          <AppTextarea v-model="inspectionForm.recommendations" label="Recommendations" :rows="3" required :disabled="!canEditInspection(inspectionWorkRequest)" />

          <div class="space-y-3">
            <div class="flex items-center justify-between gap-3">
              <h3 class="text-sm font-semibold text-slate-800">Estimated Cost of Repair Materials</h3>
              <button v-if="canEditInspection(inspectionWorkRequest)" type="button" @click="addMaterialItem" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Add item</button>
            </div>
            <div class="overflow-x-auto rounded-lg border border-slate-100">
              <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                  <tr>
                    <th class="px-3 py-2 text-left">Item Description / Material</th>
                    <th class="px-3 py-2 text-right w-24">Qty</th>
                    <th class="px-3 py-2 text-right w-32">Unit Cost</th>
                    <th class="px-3 py-2 text-right w-32">Total</th>
                    <th v-if="canEditInspection(inspectionWorkRequest)" class="px-3 py-2 w-12"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="(item, idx) in inspectionForm.material_items" :key="idx">
                    <td class="px-3 py-2"><input v-model="item.item_description" :disabled="!canEditInspection(inspectionWorkRequest)" class="w-full rounded border border-slate-200 px-2 py-1 text-sm disabled:bg-slate-50" /></td>
                    <td class="px-3 py-2"><input v-model="item.qty" type="number" min="0" step="0.01" :disabled="!canEditInspection(inspectionWorkRequest)" class="w-full rounded border border-slate-200 px-2 py-1 text-right text-sm disabled:bg-slate-50" /></td>
                    <td class="px-3 py-2"><input v-model="item.unit_cost" type="number" min="0" step="0.01" :disabled="!canEditInspection(inspectionWorkRequest)" class="w-full rounded border border-slate-200 px-2 py-1 text-right text-sm disabled:bg-slate-50" /></td>
                    <td class="px-3 py-2 text-right text-slate-700">{{ money(rowTotal(item.qty, item.unit_cost)) }}</td>
                    <td v-if="canEditInspection(inspectionWorkRequest)" class="px-3 py-2 text-right"><button type="button" @click="removeMaterialItem(idx)" class="text-xs text-danger-600">Remove</button></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="space-y-3">
            <div class="flex items-center justify-between gap-3">
              <h3 class="text-sm font-semibold text-slate-800">Estimated Labor Cost</h3>
              <button v-if="canEditInspection(inspectionWorkRequest)" type="button" @click="addLaborItem" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Add labor</button>
            </div>
            <div class="overflow-x-auto rounded-lg border border-slate-100">
              <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                  <tr>
                    <th class="px-3 py-2 text-left">Work Description / Scope of Work</th>
                    <th class="px-3 py-2 text-right w-32">Man-days</th>
                    <th class="px-3 py-2 text-right w-32">Labor Rate</th>
                    <th class="px-3 py-2 text-right w-32">Total</th>
                    <th v-if="canEditInspection(inspectionWorkRequest)" class="px-3 py-2 w-12"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="(item, idx) in inspectionForm.labor_items" :key="idx">
                    <td class="px-3 py-2"><input v-model="item.work_description" :disabled="!canEditInspection(inspectionWorkRequest)" class="w-full rounded border border-slate-200 px-2 py-1 text-sm disabled:bg-slate-50" /></td>
                    <td class="px-3 py-2"><input v-model="item.man_days" type="number" min="0" step="0.01" :disabled="!canEditInspection(inspectionWorkRequest)" class="w-full rounded border border-slate-200 px-2 py-1 text-right text-sm disabled:bg-slate-50" /></td>
                    <td class="px-3 py-2"><input v-model="item.labor_rate" type="number" min="0" step="0.01" :disabled="!canEditInspection(inspectionWorkRequest)" class="w-full rounded border border-slate-200 px-2 py-1 text-right text-sm disabled:bg-slate-50" /></td>
                    <td class="px-3 py-2 text-right text-slate-700">{{ money(rowTotal(item.man_days, item.labor_rate)) }}</td>
                    <td v-if="canEditInspection(inspectionWorkRequest)" class="px-3 py-2 text-right"><button type="button" @click="removeLaborItem(idx)" class="text-xs text-danger-600">Remove</button></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Total Materials</label>
              <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ money(materialTotal) }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Total Labor</label>
              <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ money(laborTotal) }}</div>
            </div>
            <AppInput v-model="inspectionForm.indirect_cost" type="number" min="0" step="0.01" label="Indirect Cost" :disabled="!canEditInspection(inspectionWorkRequest)" />
          </div>
          <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800">
            Total Estimated Repair Cost: {{ money(materialTotal + laborTotal + Number(inspectionForm.indirect_cost || 0)) }}
          </div>
        </div>

        <template #footer>
          <AppButton v-if="inspectionWorkRequest?.pre_repair_inspection" as="a" :href="route('work-requests.pre-repair-inspection.print', inspectionWorkRequest.id)" target="_blank" variant="secondary">
            Print Report
          </AppButton>
          <AppButton v-if="canNoteInspection(inspectionWorkRequest) && inspectionWorkRequest?.pre_repair_inspection" variant="warning" @click="returnInspection">
            Return
          </AppButton>
          <AppButton v-if="canNoteInspection(inspectionWorkRequest) && inspectionWorkRequest?.pre_repair_inspection?.status === 'submitted'" variant="success" @click="noteInspection">
            Note Inspection
          </AppButton>
          <AppButton v-if="canEditInspection(inspectionWorkRequest)" variant="secondary" :loading="inspectionForm.processing" @click="saveInspection(false)">
            Save Draft
          </AppButton>
          <AppButton v-if="canEditInspection(inspectionWorkRequest)" :loading="inspectionForm.processing" @click="saveInspection(true)">
            Submit Inspection
          </AppButton>
        </template>
      </AppModal>
    </div>
    <DigitalSignaturePin
      :show="showSubmitPin"
      :hasPin="hasPin"
      :signatureUri="signatureUri"
      :loading="pinLoading"
      confirmLabel="Sign & Submit"
      @confirm="handlePinConfirm"
      @cancel="handlePinCancel"
    />
    <CsmForm
      :show="showCsmModal"
      respondable-type="work-request"
      :respondable-id="requestToCsm?.id ?? 0"
      :transaction-date="requestToCsm?.created_at?.slice(0,10) ?? ''"
      office-availed="General Services Unit"
      service-key="others"
      service-other-label="Work / Maintenance Request"
      @close="showCsmModal = false"
      @submitted="onCsmSubmitted"
    />
  </AdminLayout>
</template>

<script setup>
import { Head, usePage, useForm, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { PencilSquareIcon, TrashIcon, UserPlusIcon, CheckCircleIcon, PrinterIcon, ClipboardDocumentCheckIcon, EyeIcon } from '@heroicons/vue/24/outline'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'
import CsmForm from '@/Components/CsmForm.vue'
import DigitalSignaturePin from '@/Components/DigitalSignaturePin.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppTable from '@/Components/AppTable.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'

const props = defineProps({
  divisions: Array,
  offices: Array,
  users: Array,
  skilledUsers: Array,
  workRequests: Object,
  filters: { type: Object, default: () => ({}) },
  hasPendingCsm: { type: Boolean, default: false },
  hasPin: { type: Boolean, default: false },
  signatureUri: { type: String, default: null },
})

const showCsmModal = ref(false)
const requestToCsm = ref(null)
function openCsmModal(req) { requestToCsm.value = req; showCsmModal.value = true }

// Use server-side prop — accurate regardless of pagination or filters
const hasPendingConfirmation = computed(() => props.hasPendingCsm)

function onCsmSubmitted() {
  showCsmModal.value = false
}

async function handleNewRequest() {
  if (hasPendingConfirmation.value) {
    await Swal.fire({
      icon: 'warning',
      title: 'Action Required',
      text: 'You have a Work Request that has been completed and is pending your confirmation. Please rate the service first before submitting a new request.',
      confirmButtonText: 'OK',
    })
    return
  }
  openModal()
}

const searchQuery = ref(props.filters?.search ?? '')
const isLoading = ref(false)

const buildParams = (pageNum = undefined) => ({
  search: searchQuery.value || undefined,
  per_page: props.filters?.per_page || undefined,
  page: pageNum || undefined,
})

function applyFilters() {
    isLoading.value = true
    router.get(route('work-requests.index'), buildParams(), {
      preserveState: true,
      replace: true,
      only: ['workRequests', 'filters', 'hasPendingCsm'],
      onFinish: () => { isLoading.value = false },
    })
}

function clearFilters() {
  searchQuery.value = ''
  isLoading.value = true
  router.get(route('work-requests.index'), {}, {
    preserveState: true,
    replace: true,
    only: ['workRequests', 'filters', 'hasPendingCsm'],
    onFinish: () => { isLoading.value = false },
  })
}

function goToPage(pageNum) {
  isLoading.value = true
  router.get(route('work-requests.index'), buildParams(pageNum), {
    preserveState: true,
    replace: true,
    only: ['workRequests', 'filters', 'hasPendingCsm'],
    onFinish: () => { isLoading.value = false },
  })
}

const workRequestsList = computed(() => props.workRequests?.data ?? [])
const filteredWorkRequests = computed(() => props.workRequests?.data ?? [])
const currentPage = computed(() => props.workRequests?.current_page ?? 1)
const totalPages = computed(() => props.workRequests?.last_page ?? 1)

const page = usePage();
const roleName = computed(() => page.props.auth?.user?.role?.name ?? '');
const roleNames = computed(() => page.props.auth?.user?.roleNames ?? (roleName.value ? [roleName.value] : []));
const hasRole = (role) => roleNames.value.includes(role);
const hasAnyRole = (...roles) => roles.some(r => roleNames.value.includes(r));

// Number of visible columns in the desktop table — used for the AppTable skeleton/empty colspan
const tableColCount = computed(() => (hasAnyRole('Administrator','GSU Head','DivisionChief') ? 7 : 6))

const showDetailsModal = ref(false)
const detailsWorkRequest = ref(null)
const openDetailsModal = (wr) => { detailsWorkRequest.value = wr; showDetailsModal.value = true }
const closeDetailsModal = () => { showDetailsModal.value = false; detailsWorkRequest.value = null }

const showModal = ref(false)
const editingId = ref(null)

const showCompleteModal = ref(false)
const completeEditingId = ref(null)

// Skilled-personnel work categories — mirrors the in: validation list in
// WorkRequestController; feeds the General Services dashboard doughnut.
const WORK_CATEGORIES = ['Carpentry', 'Electrical', 'Plumbing', 'Aircon/HVAC', 'Painting', 'Welding', 'Grounds/Landscaping', 'Other']

const form = useForm({
  issue: '',
  description: '',
  category: '',
  priority: 'Normal',
  location_division_id: '',
  location_office_id: '',
  expected_completion_date: '',
  assigned_user_id: '',
  pin: null,
})

const showSubmitPin = ref(false)
const pinLoading = ref(false)

const openPinModal = () => { showSubmitPin.value = true }
const handlePinCancel = () => { showSubmitPin.value = false }
const handlePinConfirm = (pin) => {
  form.pin = pin || null
  showSubmitPin.value = false
  doPostStore()
}
const doPostStore = () => {
  form.post('/work-requests', {
    onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Work request created', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
    onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to create', text: Object.values(errors).flat().join('\n') || 'Failed to create' }) }
  })
}

const completeForm = useForm({
  acted_by_id: '',
  action_taken: '',
  date_completed: '',
})

const showInspectionModal = ref(false)
const inspectionWorkRequest = ref(null)
const inspectionForm = useForm({
  inspector_id: '',
  asset_type: 'building_facility',
  building_facility: '',
  property_no: '',
  location: '',
  description: '',
  serial_no: '',
  date_of_purchase: '',
  purchase_cost: '',
  warranty_status: 'unknown',
  location_of_defect: '',
  nature_of_defect: '',
  cause_of_defect: '',
  recommendations: '',
  material_items: [],
  labor_items: [],
  indirect_cost: '',
})

const currentUserId = computed(() => page.props.auth?.user?.id ?? null)

const blankMaterialItem = () => ({ item_description: '', qty: '', unit_cost: '' })
const blankLaborItem = () => ({ work_description: '', man_days: '', labor_rate: '' })
const rowTotal = (qty, rate) => Number(qty || 0) * Number(rate || 0)
const materialTotal = computed(() => inspectionForm.material_items.reduce((sum, item) => sum + rowTotal(item.qty, item.unit_cost), 0))
const laborTotal = computed(() => inspectionForm.labor_items.reduce((sum, item) => sum + rowTotal(item.man_days, item.labor_rate), 0))
const money = (value) => Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

function addMaterialItem() { inspectionForm.material_items.push(blankMaterialItem()) }
function removeMaterialItem(index) { inspectionForm.material_items.splice(index, 1) }
function addLaborItem() { inspectionForm.labor_items.push(blankLaborItem()) }
function removeLaborItem(index) { inspectionForm.labor_items.splice(index, 1) }

// Filter offices (rooms) by selected division (building)
const filteredOffices = computed(() => {
  if (!form.location_division_id) return props.offices || []
  return (props.offices || []).filter(o => String(o.building_id) === String(form.location_division_id))
})

// Clear room selection if it no longer belongs to the selected building
watch(() => form.location_division_id, (nv) => {
  if (!nv) return
  const ok = (props.offices || []).some(o => String(o.id) === String(form.location_office_id) && String(o.building_id) === String(nv))
  if (!ok) form.location_office_id = ''
})

const openModal = (wr = null) => {
  editingId.value = wr ? wr.id : null
  if (wr) {
    form.reset()
    form.issue = wr.issue ?? ''
    form.description = wr.description ?? ''
    form.category = wr.category ?? ''
    form.priority = wr.priority ?? 'Normal'
    form.location_division_id = wr.location_division_id ?? ''
    form.location_office_id = wr.location_office_id ?? ''
    form.expected_completion_date = wr.expected_completion_date ?? ''
    form.assigned_user_id = wr.assigned_user_id ?? ''
  } else {
    form.reset()
    form.priority = 'Normal'
  }
  showModal.value = true
}

const openCompleteModal = (wr) => {
  if (wr?.requires_pre_repair_inspection && wr?.pre_repair_inspection?.status !== 'noted') {
    Swal.fire({
      icon: 'warning',
      title: 'Pre-Repair Inspection Required',
      text: 'A noted Pre-Repair Inspection Report is required before this work request can be marked completed.',
    })
    return
  }
  completeEditingId.value = wr ? wr.id : null
  completeForm.reset()
  completeForm.acted_by_id = wr?.acted_by_id ?? ''
  completeForm.action_taken = wr?.action_taken ?? ''
  completeForm.date_completed = wr?.date_completed ?? ''
  showCompleteModal.value = true
}

const closeCompleteModal = () => { showCompleteModal.value = false; completeEditingId.value = null; completeForm.reset() }

const inspectionStatus = (wr) => {
  if (!wr?.requires_pre_repair_inspection) return 'Legacy'
  const status = wr?.pre_repair_inspection?.status
  if (!status) return 'No Inspection'
  if (status === 'draft') return 'Draft'
  if (status === 'submitted') return 'Submitted'
  if (status === 'noted') return 'Noted'
  if (status === 'returned') return 'Returned'
  return status
}

// Return AppBadge color keyword for the inspection status (was raw Tailwind classes pre-migration)
const inspectionStatusClass = (wr) => {
  const status = wr?.pre_repair_inspection?.status
  if (!wr?.requires_pre_repair_inspection) return 'slate'
  if (status === 'noted') return 'green'
  if (status === 'submitted') return 'blue'
  if (status === 'returned') return 'red'
  if (status === 'draft') return 'amber'
  return 'orange'
}

const canManageInspection = () => hasAnyRole('Administrator', 'GSU Head')
const canEditInspection = (wr) => {
  if (!wr) return false
  if (wr?.pre_repair_inspection?.status === 'noted') return false
  return canManageInspection()
    || Number(wr.assigned_user_id || 0) === Number(currentUserId.value || 0)
    || Number(wr.pre_repair_inspection?.inspector_id || 0) === Number(currentUserId.value || 0)
}
const canNoteInspection = (wr) => !!wr && canManageInspection()
const canOpenInspection = (wr) => {
  if (!wr?.requires_pre_repair_inspection && !wr?.pre_repair_inspection) return false
  return canEditInspection(wr)
    || canNoteInspection(wr)
    || hasRole('FAD Chief')
    || String(page.props.auth?.user?.position ?? '').includes('FAD')
    || Number(wr.requester_id || 0) === Number(currentUserId.value || 0)
}

const closeInspectionModal = () => {
  showInspectionModal.value = false
  inspectionWorkRequest.value = null
  inspectionForm.reset()
  inspectionForm.material_items = []
  inspectionForm.labor_items = []
}

const openInspectionModal = (wr) => {
  inspectionWorkRequest.value = wr
  const inspection = wr?.pre_repair_inspection ?? {}
  inspectionForm.reset()
  inspectionForm.inspector_id = inspection.inspector_id ?? wr?.assigned_user_id ?? ''
  inspectionForm.asset_type = inspection.asset_type ?? 'building_facility'
  inspectionForm.building_facility = inspection.building_facility ?? wr?.division?.name ?? ''
  inspectionForm.property_no = inspection.property_no ?? ''
  inspectionForm.location = inspection.location ?? [wr?.division?.name, wr?.office?.name].filter(Boolean).join(' / ')
  inspectionForm.description = inspection.description ?? wr?.description ?? ''
  inspectionForm.serial_no = inspection.serial_no ?? ''
  inspectionForm.date_of_purchase = inspection.date_of_purchase ?? ''
  inspectionForm.purchase_cost = inspection.purchase_cost ?? ''
  inspectionForm.warranty_status = inspection.warranty_status ?? 'unknown'
  inspectionForm.location_of_defect = inspection.location_of_defect ?? [wr?.division?.name, wr?.office?.name].filter(Boolean).join(' / ')
  inspectionForm.nature_of_defect = inspection.nature_of_defect ?? ''
  inspectionForm.cause_of_defect = inspection.cause_of_defect ?? ''
  inspectionForm.recommendations = inspection.recommendations ?? ''
  inspectionForm.material_items = (inspection.material_items?.length ? inspection.material_items : [blankMaterialItem()]).map(item => ({
    item_description: item.item_description ?? '',
    qty: item.qty ?? '',
    unit_cost: item.unit_cost ?? '',
  }))
  inspectionForm.labor_items = (inspection.labor_items?.length ? inspection.labor_items : [blankLaborItem()]).map(item => ({
    work_description: item.work_description ?? '',
    man_days: item.man_days ?? '',
    labor_rate: item.labor_rate ?? '',
  }))
  inspectionForm.indirect_cost = inspection.indirect_cost ?? ''
  showInspectionModal.value = true
}

const saveInspection = (submit = false) => {
  if (!inspectionWorkRequest.value) return
  const url = submit
    ? route('work-requests.pre-repair-inspection.submit', inspectionWorkRequest.value.id)
    : route('work-requests.pre-repair-inspection.save', inspectionWorkRequest.value.id)

  inspectionForm.post(url, {
    preserveScroll: true,
    onSuccess: () => {
      closeInspectionModal()
      Swal.fire({ icon: 'success', title: submit ? 'Inspection submitted' : 'Inspection saved', timer: 1200, showConfirmButton: false })
        .then(() => window.location.reload())
    },
    onError: (errors) => {
      Swal.fire({ icon: 'error', title: 'Failed to save inspection', text: Object.values(errors).flat().join('\n') || 'Failed to save inspection' })
    },
  })
}

const noteInspection = () => {
  if (!inspectionWorkRequest.value) return
  router.post(route('work-requests.pre-repair-inspection.note', inspectionWorkRequest.value.id), {}, {
    preserveScroll: true,
    onSuccess: () => {
      closeInspectionModal()
      Swal.fire({ icon: 'success', title: 'Inspection noted', timer: 1200, showConfirmButton: false }).then(() => window.location.reload())
    },
    onError: (errors) => Swal.fire({ icon: 'error', title: 'Failed to note inspection', text: Object.values(errors).flat().join('\n') || 'Failed to note inspection' }),
  })
}

const returnInspection = async () => {
  if (!inspectionWorkRequest.value) return
  const result = await Swal.fire({
    title: 'Return for revision',
    input: 'textarea',
    inputLabel: 'Reason',
    inputValidator: value => value ? null : 'Reason is required',
    showCancelButton: true,
    confirmButtonText: 'Return',
  })
  if (!result.isConfirmed) return

  router.post(route('work-requests.pre-repair-inspection.return', inspectionWorkRequest.value.id), { return_reason: result.value }, {
    preserveScroll: true,
    onSuccess: () => {
      closeInspectionModal()
      Swal.fire({ icon: 'success', title: 'Inspection returned', timer: 1200, showConfirmButton: false }).then(() => window.location.reload())
    },
    onError: (errors) => Swal.fire({ icon: 'error', title: 'Failed to return inspection', text: Object.values(errors).flat().join('\n') || 'Failed to return inspection' }),
  })
}

const submitCompletion = () => {
  if (!completeEditingId.value) return
  completeForm.post(`/work-requests/${completeEditingId.value}/complete`, {
    onSuccess: () => { closeCompleteModal(); Swal.fire({ icon: 'success', title: 'Marked completed', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
    onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to save', text: Object.values(errors).flat().join('\n') || 'Failed to save' }) }
  })
}

const closeModal = () => { showModal.value = false; editingId.value = null; form.reset() }

const submitForm = () => {
  if (editingId.value) {
    form.put(`/work-requests/${editingId.value}`, {
      onSuccess: () => { closeModal(); Swal.fire({ icon: 'success', title: 'Work request updated', timer: 1200, showConfirmButton: false }).then(() => { window.location.reload() }) },
      onError: (errors) => { Swal.fire({ icon: 'error', title: 'Failed to update', text: Object.values(errors).flat().join('\n') || 'Failed to update' }) }
    })
  }
}

const destroy = async (wr) => {
  const confirmed = await confirmDelete('This action cannot be undone.')
  if (!confirmed) return
  router.delete(`/work-requests/${wr.id}`, {
    onSuccess: () => { Swal.fire({ icon: 'success', title: 'Deleted', timer: 1000, showConfirmButton: false }).then(() => { window.location.reload() }) },
    onError: (e) => { Swal.fire({ icon: 'error', title: 'Failed to delete' }) }
  })
}


// Return AppBadge color keyword for a status (was raw Tailwind classes pre-migration)
const statusClass = (s) => {
  if (!s) return 'slate'
  const st = String(s).toLowerCase()
  if (st.includes('in progress')) return 'orange'
  if (st.includes('approve')) return 'green'
  if (st.includes('declin')) return 'red'
  if (st.includes('completed')) return 'blue'
  if (st.includes('pending')) return 'amber'
  return 'slate'
}
</script>
