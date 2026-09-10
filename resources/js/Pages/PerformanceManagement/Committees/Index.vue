<template>
  <Head title="Committees" />
  <AdminLayout title="Committees">
    <div class="space-y-5">

      <AppPageHeader title="Committees" subtitle="Committee catalog, per-term rosters, lifecycle, and ratings — all in one place.">
        <template #actions>
          <AppButton v-if="canManage" variant="secondary" as="link" :href="route('pm-committees.my')">
            <UserCircleIcon class="h-4 w-4" /> My Committees
          </AppButton>
          <AppButton v-if="canManage" @click="openCatalogModal('create')">
            <PlusIcon class="w-4 h-4" /> New Committee
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="Object.keys($page.props.errors ?? {}).length" class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm space-y-1">
        <p v-for="(msg, key) in $page.props.errors" :key="key">{{ msg }}</p>
      </div>

      <!-- ── Committee Catalog (single view — Assign Committee modal retired;
           membership is managed exclusively via each committee's own
           New/Edit modal below) ─────────────────────────────────────────── -->
      <AppFilterBar>
        <AppInput v-model="catalogSearch" placeholder="Search committees..." class="w-full sm:w-72" />
        <select v-model="fyFilter" @change="applyCatalogFilters"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400">
          <option v-for="y in fiscalYears" :key="y" :value="String(y)">FY {{ y }}</option>
          <option value="all">All years</option>
        </select>
        <select v-model="filters.term_id" @change="applyFilters"
          class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option v-for="t in terms" :key="t.id" :value="t.id">
            {{ t.label }}{{ t.is_current ? ' (current)' : '' }}
          </option>
        </select>
        <label v-if="canManage" class="flex items-center gap-1.5 text-sm text-slate-600 cursor-pointer">
          <input type="checkbox" v-model="showRevokedFilter" @change="applyCatalogFilters" class="rounded border-slate-300 text-indigo-600" />
          Show revoked/amended
        </label>
      </AppFilterBar>

      <AppTable :is-empty="paginatedCatalog.length === 0" :skeleton-cols="7">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Name</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Lifecycle</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Head</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Active This Term</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">SO / Issuance</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Plans</th>
            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
          </tr>
        </template>

        <template v-for="committee in paginatedCatalog" :key="committee.id">
          <tr class="hover:bg-indigo-50/40" :class="{ 'opacity-60': committee.is_revoked }">
            <td class="px-4 py-3">
              <p class="font-medium text-slate-800">{{ committee.name }}</p>
              <AppBadge v-if="committee.sub_committees?.length" color="slate" class="mt-1">Main ({{ committee.sub_committees.length }} sub)</AppBadge>
            </td>
            <td class="px-4 py-3">
              <AppBadge :color="lifecycleBadge(committee)">{{ lifecycleLabel(committee) }}</AppBadge>
              <p v-if="committee.scope_type === 'seasonal' && committee.season_starts_at" class="text-xs text-slate-400 mt-1">
                {{ committee.season_starts_at }} → {{ committee.season_ends_at }}
              </p>
            </td>
            <td class="px-4 py-3 text-slate-700">{{ committee.head?.name ?? "—" }}</td>
            <td class="px-4 py-3 text-slate-700">{{ committee.active_assignment_count ?? 0 }}</td>
            <td class="px-4 py-3 text-slate-700 text-xs">
              <p v-if="committee.issuance">{{ committee.issuance.label }}</p>
              <p v-else-if="committee.so_number">SO {{ committee.so_number }}</p>
              <p v-else class="text-slate-400">—</p>
            </td>
            <td class="px-4 py-3 text-slate-700">{{ committee.work_distribution_plans?.length ?? 0 }}</td>
            <td class="px-4 py-3 text-center">
              <div class="flex items-center justify-center gap-1">
                <Link :href="route('pm-committees.show', committee.id)" title="View Performance"
                  class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors">
                  <ArrowRightIcon class="w-4 h-4" />
                </Link>
                <AppIconButton v-if="canManage && !committee.is_revoked" label="Edit" @click="openCatalogModal('edit', committee)"><PencilSquareIcon class="w-4 h-4" /></AppIconButton>
                <div v-if="canManage && !committee.is_revoked" class="relative">
                  <AppIconButton label="More" @click="toggleMenu(committee.id)"><EllipsisVerticalIcon class="w-4 h-4" /></AppIconButton>
                  <div v-if="openMenuId === committee.id" v-click-outside="() => (openMenuId = null)"
                    class="absolute right-0 z-10 mt-1 w-44 rounded-lg border border-slate-200 bg-white shadow-lg py-1 text-sm">
                    <button class="w-full text-left px-3 py-2 hover:bg-slate-50" @click="openExportMenu(committee)">Export Roster</button>
                    <button class="w-full text-left px-3 py-2 hover:bg-slate-50 text-amber-600" @click="openAmendModal(committee)">Amend</button>
                    <button class="w-full text-left px-3 py-2 hover:bg-slate-50 text-danger-600" @click="openRevokeModal(committee)">Revoke</button>
                    <button class="w-full text-left px-3 py-2 hover:bg-slate-50 text-danger-600" @click="deleteCatalogCommittee(committee)">Delete</button>
                  </div>
                </div>
              </div>
            </td>
          </tr>
          <tr v-for="sub in committee.sub_committees" :key="'sub-' + sub.id"
            class="bg-slate-50/40 hover:bg-slate-50">
            <td class="px-4 py-2 pl-10 text-slate-600">
              <span class="text-slate-400 mr-1">└</span>
              {{ sub.name }}
              <span class="ml-1 text-xs italic text-slate-400">Sub-committee</span>
            </td>
            <td class="px-4 py-2"></td>
            <td class="px-4 py-2 text-slate-600 text-xs">{{ sub.head?.name ?? "—" }}</td>
            <td class="px-4 py-2 text-slate-600 text-xs">{{ sub.active_assignment_count ?? 0 }}</td>
            <td class="px-4 py-2 text-slate-400 text-xs">—</td>
            <td class="px-4 py-2 text-slate-400 text-xs">—</td>
            <td class="px-4 py-2 text-center">
              <Link :href="route('pm-committees.show', sub.id)"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors mx-auto" title="View Performance">
                <ArrowRightIcon class="w-4 h-4" />
              </Link>
            </td>
          </tr>
        </template>

        <template #empty>
          <EmptyState title="No committees found" />
        </template>

        <template #footer>
          <PaginationControl
            :current-page="catalogPage"
            :total-pages="totalCatalogPages"
            @prev="catalogPage--"
            @next="catalogPage++"
            @page="catalogPage = $event"
          />
        </template>
      </AppTable>

      <!-- ── Cross-committee assignments (read-only, filterable — admin
           visibility across every committee/term at a glance; editing a
           person's membership happens via that committee's own modal) ── -->
      <div v-if="canManage">
        <button type="button" @click="showAssignmentsPanel = !showAssignmentsPanel"
          class="flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-slate-800">
          <ChevronRightIcon class="h-4 w-4 transition-transform" :class="{ 'rotate-90': showAssignmentsPanel }" />
          All Assignments ({{ filtered.length }})
        </button>

        <div v-if="showAssignmentsPanel" class="mt-3 space-y-3">
          <AppFilterBar>
            <div class="w-56">
              <AppInput v-model="search" type="text" placeholder="Search faculty or committee…" />
            </div>
            <select v-model="filters.faculty_id" @change="applyFilters"
              class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
              <option :value="null">All Faculty</option>
              <option v-for="f in faculty" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </AppFilterBar>

          <AppTable :is-empty="displayed.length === 0" :skeleton-cols="5">
            <template #head>
              <tr>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Faculty</th>
                <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Committee</th>
                <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Role</th>
                <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Units</th>
                <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
              </tr>
            </template>

            <tr v-for="a in displayed" :key="a.id" class="hover:bg-slate-50/50">
              <td class="px-4 py-3 font-medium text-slate-800">{{ a.faculty?.name ?? '—' }}</td>
              <td class="px-4 py-3">
                <p v-if="getParentName(a)" class="text-xs text-indigo-500 font-medium">{{ getParentName(a) }} ›</p>
                <Link v-if="a.committee_id" :href="route('pm-committees.show', a.committee_id)" class="text-slate-800 hover:text-indigo-600">{{ a.committee_name }}</Link>
                <span v-else class="text-slate-800">{{ a.committee_name }}</span>
              </td>
              <td class="px-4 py-3 text-center">
                <AppBadge :color="roleBadge(a.role)">
                  <StarIcon v-if="a.is_chairperson" class="h-3 w-3" />
                  {{ roleLabel(a.role) }}
                </AppBadge>
              </td>
              <td class="px-4 py-3 text-center font-semibold text-slate-700">{{ a.load_units }}</td>
              <td class="px-4 py-3 text-center">
                <AppBadge :color="statusBadge(a.status)">{{ a.status }}</AppBadge>
              </td>
            </tr>

            <template #empty>
              <EmptyState title="No committee assignments found" :icon="UserGroupIcon" />
            </template>

            <template #footer>
              <PaginationControl
                :current-page="page"
                :total-pages="totalPages"
                :total="filtered.length"
                @prev="page--"
                @next="page++"
                @page="page = $event"
              />
            </template>
          </AppTable>
        </div>
      </div>
    </div>

    <!-- Create / Edit / Amend Catalog Committee Modal -->
    <AppModal :show="showCatalogModal" :title="catalogModalTitle" size="2xl" @close="closeCatalogModal">
      <form @submit.prevent="submitCatalogCommittee" class="space-y-4">
        <AppInput v-model="catalogForm.name" label="Name" required />

        <AppSelect v-model="catalogForm.head_id" :label="catalogForm.has_subcommittees ? 'Main Chairperson' : 'Committee Head'" placeholder="— None —">
          <option v-for="u in users" :key="u.id" :value="u.id">
            {{ u.name }}<span v-if="u.position"> ({{ u.position }})</span>
          </option>
        </AppSelect>

        <AppTextarea v-model="catalogForm.description" label="Description" :rows="2" />

        <AppInput v-model.number="catalogForm.max_members" type="number" min="1" label="Max Members (optional)"
          placeholder="Leave blank for unlimited — used for vacancy alerts" />

        <!-- Lifecycle / Scope -->
        <div class="border border-slate-100 rounded-lg p-3 space-y-3">
          <label class="block text-xs font-medium text-slate-600">Lifecycle</label>
          <div class="flex gap-2">
            <AppButton type="button" size="sm" :variant="catalogForm.scope_type === 'perpetual' ? 'primary' : 'secondary'" @click="catalogForm.scope_type = 'perpetual'">Perpetual</AppButton>
            <AppButton type="button" size="sm" :variant="catalogForm.scope_type === 'school_year' ? 'primary' : 'secondary'" @click="catalogForm.scope_type = 'school_year'">School Year</AppButton>
            <AppButton type="button" size="sm" :variant="catalogForm.scope_type === 'seasonal' ? 'primary' : 'secondary'" @click="catalogForm.scope_type = 'seasonal'">Seasonal</AppButton>
          </div>
          <p class="text-xs text-slate-400">
            Perpetual: active until explicitly revoked/amended. School Year: tied to one SY. Seasonal: explicit start/end window (e.g. Foundation Week).
          </p>

          <AppSelect v-if="catalogForm.scope_type === 'school_year'" v-model="catalogForm.school_year_id" label="School Year">
            <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">{{ sy.name }}</option>
          </AppSelect>

          <div v-if="catalogForm.scope_type === 'seasonal'" class="grid grid-cols-2 gap-3">
            <AppInput v-model="catalogForm.season_starts_at" type="date" label="Season Start" />
            <AppInput v-model="catalogForm.season_ends_at" type="date" label="Season End" />
          </div>
        </div>

        <!-- SO Number / Issuance -->
        <div class="border border-slate-100 rounded-lg p-3 space-y-2">
          <label class="block text-xs font-medium text-slate-600">Special/Office Order</label>
          <div class="relative">
            <AppInput v-model="issuanceSearch" placeholder="Search issuances by title / control no…" @input="searchIssuances" />
            <div v-if="issuanceResults.length" class="absolute z-10 mt-1 w-full rounded-lg border border-slate-200 bg-white shadow-lg max-h-48 overflow-y-auto">
              <button v-for="opt in issuanceResults" :key="opt.id" type="button"
                class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50"
                @click="selectIssuance(opt)">{{ opt.label }}</button>
            </div>
          </div>
          <p v-if="catalogForm.issuance_id" class="text-xs text-indigo-600">
            Linked: {{ selectedIssuanceLabel }}
            <button type="button" class="ml-2 text-slate-400 hover:text-danger-600" @click="clearIssuance">✕</button>
          </p>
          <AppInput v-model="catalogForm.so_number" label="SO Number (fallback, if not in Issuance module)" placeholder="e.g. SO 2026-014" />
        </div>

        <!-- Load conflict warning banner -->
        <div v-if="loadConflictWarning" class="bg-amber-50 border border-amber-200 text-amber-700 rounded-lg px-4 py-3 text-sm flex items-start gap-2">
          <ExclamationTriangleIcon class="h-4 w-4 shrink-0 mt-0.5" />
          <span>{{ loadConflictWarning }}</span>
        </div>

        <!-- Structure Toggle -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Committee Structure</label>
          <div class="flex gap-2">
            <AppButton type="button" size="sm" :variant="!catalogForm.has_subcommittees ? 'primary' : 'secondary'" @click="catalogForm.has_subcommittees = false">Simple Committee</AppButton>
            <AppButton type="button" size="sm" :variant="catalogForm.has_subcommittees ? 'primary' : 'secondary'" @click="catalogForm.has_subcommittees = true">Main Committee (with Sub-committees)</AppButton>
          </div>
        </div>

        <!-- WDP Plans (always at main committee level) -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Tagged Work Distribution Plans</label>
          <AppInput v-model="catalogPlanSearch" placeholder="Search plans..." class="mb-2" />
          <div class="border border-slate-200 rounded-lg p-2 max-h-40 overflow-y-auto space-y-1 text-sm">
            <div v-for="p in filteredCatalogPlans" :key="p.id" class="flex items-start gap-2">
              <input type="checkbox" :value="p.id" :checked="catalogForm.plan_ids.includes(p.id)"
                @change="toggleCatalogPlan(p.id)" class="mt-0.5 rounded border-slate-300" />
              <span class="text-slate-700">{{ p.success_indicator }}
                <span v-if="p.rated_by" class="text-slate-400 text-xs">({{ p.rated_by }})</span>
              </span>
            </div>
            <p v-if="!filteredCatalogPlans.length" class="text-slate-400 text-xs px-1">
              {{ catalogPlanSearch ? 'No plans match your search.' : 'No plans available.' }}
            </p>
          </div>
          <p class="text-xs text-slate-400 mt-1">{{ catalogForm.plan_ids.length }} plan(s) selected</p>
        </div>

        <!-- Simple: Members (role + load-unit override captured here now —
             this is the ONLY place membership/role/load is set; the retired
             Assign Committee modal is not coming back) -->
        <div v-if="!catalogForm.has_subcommittees">
          <label class="block text-xs font-medium text-slate-600 mb-1">Members</label>
          <AppInput v-model="catalogMemberSearch" placeholder="Search members..." class="mb-2" />
          <div class="border border-slate-200 rounded-lg p-2 max-h-64 overflow-y-auto space-y-2 text-sm">
            <div v-for="u in filteredCatalogUsers" :key="u.id" class="flex items-start gap-2">
              <input type="checkbox" :value="u.id" :checked="catalogForm.member_ids.includes(u.id)"
                @change="toggleCatalogMember(u.id)" class="mt-1 rounded border-slate-300" />
              <div class="flex-1">
                <span class="text-slate-700">{{ u.name }}<span v-if="u.position" class="text-slate-400"> ({{ u.position }})</span></span>
                <span v-if="u.id === catalogForm.head_id" class="ml-1 text-xs text-amber-600 font-medium">(Chairperson — set via Head above)</span>
                <div v-if="catalogForm.member_ids.includes(u.id) && u.id !== catalogForm.head_id" class="mt-1 flex gap-2 items-center">
                  <select v-model="catalogForm.member_roles[u.id]" @change="checkLoadConflict(u.id)"
                    class="text-xs rounded border border-slate-200 px-2 py-1">
                    <option value="member">Member</option>
                    <option value="secretary">Secretary</option>
                    <option value="co_chair">Co-Chairperson</option>
                  </select>
                  <input v-model="catalogForm.member_load_overrides[u.id]" @change="checkLoadConflict(u.id)"
                    type="number" step="0.25" min="0" max="5" placeholder="default"
                    class="w-20 text-xs rounded border border-slate-200 px-2 py-1" title="Load unit override" />
                  <input v-model="catalogForm.member_tasks[u.id]"
                    type="text" placeholder="Task / Role..."
                    class="flex-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400" />
                </div>
              </div>
            </div>
            <p v-if="!filteredCatalogUsers.length" class="text-slate-400 text-xs px-1">No members match.</p>
          </div>
          <p class="text-xs text-slate-400 mt-1">{{ catalogForm.member_ids.length }} member(s) selected</p>
        </div>

        <!-- Main: Sub-committees -->
        <div v-else>
          <div class="flex items-center justify-between mb-2">
            <label class="block text-xs font-medium text-slate-600">Sub-committees</label>
            <button type="button" @click="addCatalogSubCommittee"
              class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
              <PlusIcon class="w-3 h-3" /> Add Sub-committee
            </button>
          </div>
          <p v-if="catalogForm.sub_committees.length === 0" class="text-xs text-slate-400 border border-dashed border-slate-200 rounded-lg p-4 text-center">
            No sub-committees yet. Click "Add Sub-committee" to create one.
          </p>
          <div v-for="(sub, idx) in catalogForm.sub_committees" :key="idx"
            class="border border-slate-200 rounded-lg p-4 space-y-3 mb-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold text-slate-600">Sub-committee {{ idx + 1 }}</span>
              <button type="button" @click="removeCatalogSubCommittee(idx)"
                class="text-xs text-danger-600 hover:text-danger-700">Remove</button>
            </div>
            <AppInput v-model="sub.name" label="Name" required />
            <AppSelect v-model="sub.head_id" label="Head" placeholder="— None —">
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </AppSelect>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Members</label>
              <AppInput v-model="sub.memberSearch" placeholder="Search members..." class="mb-2" />
              <div class="border border-slate-100 rounded-lg p-2 max-h-40 overflow-y-auto space-y-1 text-sm">
                <div v-for="u in filteredCatalogSubUsers(sub)" :key="u.id" class="flex items-start gap-2">
                  <input type="checkbox" :checked="sub.member_ids.includes(u.id)"
                    @change="toggleCatalogSubMember(idx, u.id)" class="mt-1 rounded border-slate-300" />
                  <div class="flex-1">
                    <span class="text-slate-700 text-xs">{{ u.name }}</span>
                    <div v-if="sub.member_ids.includes(u.id)" class="mt-1 flex gap-2 items-center">
                      <select v-model="sub.member_roles[u.id]" class="text-xs rounded border border-slate-200 px-1 py-0.5">
                        <option value="member">Member</option>
                        <option value="secretary">Secretary</option>
                        <option value="co_chair">Co-Chair</option>
                      </select>
                      <input v-model="sub.member_load_overrides[u.id]" type="number" step="0.25" min="0" max="5" placeholder="default"
                        class="w-16 text-xs rounded border border-slate-200 px-1 py-0.5" />
                      <input v-model="sub.member_tasks[u.id]" type="text" placeholder="Task..."
                        class="flex-1 rounded border border-slate-200 bg-white px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                    </div>
                  </div>
                </div>
              </div>
              <p class="text-xs text-slate-400 mt-1">{{ sub.member_ids.length }} member(s)</p>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
          <AppButton type="button" variant="secondary" @click="closeCatalogModal">Cancel</AppButton>
          <AppButton type="submit" :loading="catalogIsSubmitting" :disabled="catalogIsSubmitting">{{ catalogIsSubmitting ? 'Saving…' : (catalogModalMode === 'amend' ? 'Amend' : 'Save') }}</AppButton>
        </div>
      </form>
    </AppModal>

    <!-- Revoke Modal -->
    <AppModal :show="showRevokeModal" title="Revoke Committee" size="md" @close="showRevokeModal = false">
      <div class="space-y-3">
        <p class="text-sm text-slate-600">
          Revoking <strong>{{ revokeTarget?.name }}</strong> ends its lifecycle immediately — every active member this term
          is deactivated and notified. This does not delete history.
        </p>
        <AppTextarea v-model="revokeReason" label="Reason (optional)" :rows="3" />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showRevokeModal = false">Cancel</AppButton>
        <AppButton variant="danger" :loading="revokeSubmitting" @click="submitRevoke">Revoke</AppButton>
      </template>
    </AppModal>

    <!-- Export Menu Modal -->
    <AppModal :show="showExportModal" title="Export Roster" size="sm" @close="showExportModal = false">
      <div class="space-y-3">
        <p class="text-sm text-slate-600">Export the current term's active roster for <strong>{{ exportTarget?.name }}</strong>.</p>
        <div class="flex gap-2">
          <AppButton as="a" :href="route('pm-committees.catalog.export.pdf', exportTarget?.id) + (filters.term_id ? '?term_id=' + filters.term_id : '')" target="_blank">
            <DocumentIcon class="h-4 w-4" /> PDF
          </AppButton>
          <AppButton as="a" variant="secondary" :href="route('pm-committees.catalog.export.excel', exportTarget?.id) + (filters.term_id ? '?term_id=' + filters.term_id : '')">
            <TableCellsIcon class="h-4 w-4" /> Excel
          </AppButton>
        </div>
      </div>
    </AppModal>

  </AdminLayout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppInput from '@/Components/AppInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import PaginationControl from '@/Components/PaginationControl.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import { useSubmit } from '@/Composables/useSubmit'
import axios from 'axios'
import {
  ArrowRightIcon, CheckCircleIcon, ChevronRightIcon, DocumentIcon,
  EllipsisVerticalIcon, ExclamationTriangleIcon, PencilSquareIcon,
  PlusIcon, StarIcon, TableCellsIcon, UserCircleIcon, UserGroupIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  assignments: { type: Array,  default: () => [] },
  terms:       { type: Array,  default: () => [] },
  faculty:     { type: Array,  default: () => [] },
  committees:  { type: Array,  default: () => [] },
  catalog:     { type: Array,  default: () => [] },
  plans:       { type: Array,  default: () => [] },
  currentTerm: { type: Object, default: null },
  filters:     { type: Object, default: () => ({}) },
  users:       { type: Array,  default: () => [] },
  authUser:    { type: Object, default: null },
  canManage:   { type: Boolean, default: false },
  fiscalYears: { type: Array, default: () => [] },
  selectedFiscalYear: { type: [String, Number], default: "" },
  currentFiscalYear: { type: Number, default: null },
  schoolYears: { type: Array, default: () => [] },
  showRevoked: { type: Boolean, default: false },
})

const roles = [
  { value: 'member',      label: 'Member' },
  { value: 'secretary',   label: 'Secretary' },
  { value: 'co_chair',    label: 'Co-Chairperson' },
  { value: 'chairperson', label: 'Chairperson' },
]

const showAssignmentsPanel = ref(false)
const openMenuId = ref(null)
function toggleMenu(id) { openMenuId.value = openMenuId.value === id ? null : id }

// ── Assignments panel: filters + search + pagination ──────────────────────
const search = ref('')
const page   = ref(1)
const PER_PAGE = 15

const filters = reactive({
  term_id:    props.filters.term_id    ?? props.currentTerm?.id ?? null,
  faculty_id: props.filters.faculty_id ?? null,
})

watch(search, () => { page.value = 1 })

function applyFilters() {
  router.get(route('pm-committees.index'), { ...filters, fiscal_year: fyFilter.value, show_revoked: showRevokedFilter.value }, { preserveState: true })
}

const filtered = computed(() => {
  const q = search.value.toLowerCase()
  if (!q) return props.assignments
  return props.assignments.filter(a =>
    a.faculty?.name?.toLowerCase().includes(q) ||
    a.committee_name?.toLowerCase().includes(q)
  )
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const displayed  = computed(() => {
  const s = (page.value - 1) * PER_PAGE
  return filtered.value.slice(s, s + PER_PAGE)
})

function getParentName(assignment) {
  const parentId = assignment.committee?.parent_committee_id
  if (!parentId) return null
  return props.committees.find(c => c.id === parentId)?.name ?? null
}

function roleBadge(role) {
  return { chairperson: 'amber', co_chair: 'amber', secretary: 'blue', member: 'slate' }[role] ?? 'slate'
}
function roleLabel(role) { return roles.find(r => r.value === role)?.label ?? role }
function statusBadge(status) { return { active: 'green', inactive: 'slate' }[status] ?? 'slate' }

function lifecycleBadge(committee) {
  if (committee.is_revoked) return 'red'
  return { perpetual: 'green', school_year: 'blue', seasonal: 'amber' }[committee.scope_type] ?? 'slate'
}
function lifecycleLabel(committee) {
  if (committee.is_revoked) return committee.amended_into ? 'Amended' : 'Revoked'
  return { perpetual: 'Perpetual', school_year: 'School Year', seasonal: 'Seasonal' }[committee.scope_type] ?? committee.scope_type
}

// ── Catalog tab ─────────────────────────────────────────────────────────
const { isSubmitting: catalogIsSubmitting, submit: catalogSubmit } = useSubmit()

const fyFilter = ref(String(props.selectedFiscalYear ?? ""))
const showRevokedFilter = ref(props.showRevoked)
const applyCatalogFilters = () => {
  router.get(route("pm-committees.index"), { fiscal_year: fyFilter.value, show_revoked: showRevokedFilter.value, term_id: filters.term_id }, { preserveState: true, preserveScroll: true })
}

const catalogSearch = ref("")
const catalogPage = ref(1)
const CATALOG_PER_PAGE = 10

const filteredCatalog = computed(() => {
  const q = catalogSearch.value.toLowerCase()
  return props.catalog.filter(c => c.name?.toLowerCase().includes(q))
})
const paginatedCatalog = computed(() => {
  const start = (catalogPage.value - 1) * CATALOG_PER_PAGE
  return filteredCatalog.value.slice(start, start + CATALOG_PER_PAGE)
})
const totalCatalogPages = computed(() => Math.max(1, Math.ceil(filteredCatalog.value.length / CATALOG_PER_PAGE)))

const showCatalogModal = ref(false)
const catalogModalMode = ref("create") // create | edit | amend
const catalogMemberSearch = ref("")
const catalogPlanSearch = ref("")
const amendSourceId = ref(null)

const catalogModalTitle = computed(() => ({
  create: 'New Committee',
  edit: 'Edit Committee',
  amend: 'Amend Committee (creates a new version)',
}[catalogModalMode.value]))

const emptyCatalogSubCommittee = () => ({ id: null, name: '', head_id: '', member_ids: [], member_tasks: {}, member_roles: {}, member_load_overrides: {}, memberSearch: '' })

const emptyCatalogForm = () => ({
  id: null,
  name: "",
  head_id: "",
  description: "",
  fiscal_year: props.currentFiscalYear ?? null,
  max_members: null,
  scope_type: 'perpetual',
  school_year_id: null,
  season_starts_at: '',
  season_ends_at: '',
  so_number: '',
  issuance_id: null,
  has_subcommittees: false,
  member_ids: [],
  member_tasks: {},
  member_roles: {},
  member_load_overrides: {},
  plan_ids: [],
  sub_committees: [],
})

const catalogForm = ref(emptyCatalogForm())
const selectedIssuanceLabel = ref('')

const filteredCatalogUsers = computed(() => {
  const q = catalogMemberSearch.value.toLowerCase()
  if (!q) return props.users
  return props.users.filter(u => u.name.toLowerCase().includes(q))
})

const filteredCatalogSubUsers = (sub) => {
  const q = (sub.memberSearch || '').toLowerCase()
  if (!q) return props.users
  return props.users.filter(u => u.name.toLowerCase().includes(q))
}

const filteredCatalogPlans = computed(() => {
  const q = catalogPlanSearch.value.toLowerCase()
  if (!q) return props.plans
  return props.plans.filter(p => p.success_indicator.toLowerCase().includes(q))
})

const openCatalogModal = (mode, committee = null) => {
  catalogModalMode.value = mode
  showCatalogModal.value = true
  catalogMemberSearch.value = ''
  catalogPlanSearch.value = ''
  issuanceSearch.value = ''
  issuanceResults.value = []
  loadConflictWarning.value = ''
  amendSourceId.value = null

  if (committee && (mode === "edit" || mode === "amend")) {
    if (mode === 'amend') amendSourceId.value = committee.id

    const memberTasks = {}, memberRoles = {}, memberLoadOverrides = {}
    committee.members?.forEach(m => {
      memberTasks[m.id] = m.pivot?.task ?? ""
      memberRoles[m.id] = m.pivot?.role ?? 'member'
      memberLoadOverrides[m.id] = m.pivot?.load_units_override ?? ''
    })
    const hasSubs = (committee.sub_committees?.length ?? 0) > 0
    catalogForm.value = {
      id: mode === 'edit' ? committee.id : null,
      name: committee.name ?? "",
      head_id: committee.head_id ?? "",
      description: committee.description ?? "",
      fiscal_year: committee.fiscal_year ?? null,
      max_members: committee.max_members ?? null,
      scope_type: committee.scope_type ?? 'perpetual',
      school_year_id: committee.school_year_id ?? null,
      season_starts_at: committee.season_starts_at ?? '',
      season_ends_at: committee.season_ends_at ?? '',
      so_number: committee.so_number ?? '',
      issuance_id: committee.issuance_id ?? null,
      has_subcommittees: hasSubs,
      member_ids: hasSubs ? [] : (committee.members?.map(m => m.id) ?? []),
      member_tasks: hasSubs ? {} : memberTasks,
      member_roles: hasSubs ? {} : memberRoles,
      member_load_overrides: hasSubs ? {} : memberLoadOverrides,
      plan_ids: committee.work_distribution_plans?.map(p => p.id) ?? [],
      sub_committees: hasSubs ? committee.sub_committees.map(sub => {
        const subTasks = {}, subRoles = {}, subOverrides = {}
        sub.members?.forEach(m => {
          subTasks[m.id] = m.pivot?.task ?? ""
          subRoles[m.id] = m.pivot?.role ?? 'member'
          subOverrides[m.id] = m.pivot?.load_units_override ?? ''
        })
        return {
          id: mode === 'edit' ? sub.id : null,
          name: sub.name,
          head_id: sub.head_id ?? '',
          member_ids: sub.members?.map(m => m.id) ?? [],
          member_tasks: subTasks,
          member_roles: subRoles,
          member_load_overrides: subOverrides,
          memberSearch: '',
        }
      }) : [],
    }
    selectedIssuanceLabel.value = committee.issuance?.label ?? ''
  } else {
    catalogForm.value = emptyCatalogForm()
    selectedIssuanceLabel.value = ''
  }
}

const closeCatalogModal = () => { showCatalogModal.value = false }

const toggleCatalogMember = (userId) => {
  const idx = catalogForm.value.member_ids.indexOf(userId)
  if (idx === -1) {
    catalogForm.value.member_ids.push(userId)
    if (!catalogForm.value.member_roles[userId]) catalogForm.value.member_roles[userId] = 'member'
  } else {
    catalogForm.value.member_ids.splice(idx, 1)
    delete catalogForm.value.member_tasks[userId]
    delete catalogForm.value.member_roles[userId]
    delete catalogForm.value.member_load_overrides[userId]
  }
}

const toggleCatalogSubMember = (subIdx, userId) => {
  const sub = catalogForm.value.sub_committees[subIdx]
  const idx = sub.member_ids.indexOf(userId)
  if (idx === -1) {
    sub.member_ids.push(userId)
    if (!sub.member_roles[userId]) sub.member_roles[userId] = 'member'
  } else {
    sub.member_ids.splice(idx, 1)
    delete sub.member_tasks[userId]
    delete sub.member_roles[userId]
    delete sub.member_load_overrides[userId]
  }
}

const toggleCatalogPlan = (planId) => {
  const idx = catalogForm.value.plan_ids.indexOf(planId)
  if (idx === -1) catalogForm.value.plan_ids.push(planId)
  else catalogForm.value.plan_ids.splice(idx, 1)
}

const addCatalogSubCommittee = () => catalogForm.value.sub_committees.push(emptyCatalogSubCommittee())
const removeCatalogSubCommittee = (idx) => catalogForm.value.sub_committees.splice(idx, 1)

// ── Issuance picker ─────────────────────────────────────────────────────
const issuanceSearch = ref('')
const issuanceResults = ref([])
let issuanceDebounce = null
function searchIssuances() {
  clearTimeout(issuanceDebounce)
  issuanceDebounce = setTimeout(async () => {
    if (!issuanceSearch.value.trim()) { issuanceResults.value = []; return }
    const { data } = await axios.get(route('pm-committees.search-issuances'), { params: { q: issuanceSearch.value } })
    issuanceResults.value = data
  }, 250)
}
function selectIssuance(opt) {
  catalogForm.value.issuance_id = opt.id
  selectedIssuanceLabel.value = opt.label
  issuanceSearch.value = ''
  issuanceResults.value = []
}
function clearIssuance() {
  catalogForm.value.issuance_id = null
  selectedIssuanceLabel.value = ''
}

// ── Load conflict check (soft warning, never blocks save) ──────────────
const loadConflictWarning = ref('')
async function checkLoadConflict(userId) {
  if (!filters.term_id) return
  const override = catalogForm.value.member_load_overrides[userId]
  const { data } = await axios.get(route('pm-committees.load-conflict-check'), {
    params: {
      user_id: userId,
      term_id: filters.term_id,
      additional_units: override || 0,
      exclude_committee_id: catalogForm.value.id,
    },
  })
  loadConflictWarning.value = data.exceeds
    ? `This member's total active committee load this term would be ${data.projected_total} units — above the ${data.threshold}-unit guideline. This is a warning only; saving is still allowed.`
    : ''
}

const submitCatalogCommittee = () => {
  const payload = {
    name: catalogForm.value.name,
    head_id: catalogForm.value.head_id || null,
    description: catalogForm.value.description,
    fiscal_year: catalogForm.value.fiscal_year ? Number(catalogForm.value.fiscal_year) : null,
    max_members: catalogForm.value.max_members || null,
    scope_type: catalogForm.value.scope_type,
    school_year_id: catalogForm.value.scope_type === 'school_year' ? catalogForm.value.school_year_id : null,
    season_starts_at: catalogForm.value.scope_type === 'seasonal' ? catalogForm.value.season_starts_at : null,
    season_ends_at: catalogForm.value.scope_type === 'seasonal' ? catalogForm.value.season_ends_at : null,
    so_number: catalogForm.value.so_number || null,
    issuance_id: catalogForm.value.issuance_id,
    has_subcommittees: catalogForm.value.has_subcommittees,
    plan_ids: catalogForm.value.plan_ids,
  }

  if (catalogForm.value.has_subcommittees) {
    payload.sub_committees = catalogForm.value.sub_committees.map(sub => ({
      id: sub.id || undefined,
      name: sub.name,
      head_id: sub.head_id || null,
      member_ids: sub.member_ids,
      member_tasks: sub.member_tasks,
      member_roles: sub.member_roles,
      member_load_overrides: sub.member_load_overrides,
    }))
  } else {
    payload.member_ids = catalogForm.value.member_ids
    payload.member_tasks = catalogForm.value.member_tasks
    payload.member_roles = catalogForm.value.member_roles
    payload.member_load_overrides = catalogForm.value.member_load_overrides
  }

  if (catalogModalMode.value === "create") {
    catalogSubmit.post(route("pm-committees.catalog.store"), payload, { onSuccess: () => closeCatalogModal() })
  } else if (catalogModalMode.value === "amend") {
    catalogSubmit.post(route("pm-committees.catalog.amend", amendSourceId.value), payload, { onSuccess: () => closeCatalogModal() })
  } else {
    catalogSubmit.put(route("pm-committees.catalog.update", catalogForm.value.id), payload, { onSuccess: () => closeCatalogModal() })
  }
}

const deleteCatalogCommittee = async (committee) => {
  const ok = await confirmDelete(`Delete "${committee.name}"? This cannot be undone.`)
  if (!ok) return
  catalogSubmit.delete(route("pm-committees.catalog.destroy", committee.id))
  openMenuId.value = null
}

// ── Revoke ───────────────────────────────────────────────────────────────
const showRevokeModal = ref(false)
const revokeTarget = ref(null)
const revokeReason = ref('')
const revokeSubmitting = ref(false)
function openRevokeModal(committee) {
  revokeTarget.value = committee
  revokeReason.value = ''
  showRevokeModal.value = true
  openMenuId.value = null
}
function submitRevoke() {
  revokeSubmitting.value = true
  router.post(route('pm-committees.catalog.revoke', revokeTarget.value.id), { revocation_reason: revokeReason.value }, {
    onFinish: () => { revokeSubmitting.value = false; showRevokeModal.value = false },
  })
}

// ── Amend ──────────────────────────────────────────────────────────────
function openAmendModal(committee) {
  openCatalogModal('amend', committee)
  openMenuId.value = null
}

// ── Export ───────────────────────────────────────────────────────────────
const showExportModal = ref(false)
const exportTarget = ref(null)
function openExportMenu(committee) {
  exportTarget.value = committee
  showExportModal.value = true
  openMenuId.value = null
}

// simple click-outside directive for the row action menu
const vClickOutside = {
  mounted(el, binding) {
    el._clickOutside = (e) => { if (!el.contains(e.target)) binding.value(e) }
    document.addEventListener('click', el._clickOutside)
  },
  unmounted(el) { document.removeEventListener('click', el._clickOutside) },
}
</script>
