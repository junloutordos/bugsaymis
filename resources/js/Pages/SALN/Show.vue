<template>
  <Head :title="`SALN ${saln.year} — ${saln.user?.name}`" />
  <AdminLayout :title="`SALN ${saln.year}`">
    <div class="space-y-5">

      <!-- Back -->
      <Link :href="route('saln.index')" class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800">
        <ChevronLeftIcon class="h-4 w-4" />My SALN
      </Link>

      <!-- Header -->
      <AppPageHeader :title="`SALN ${saln.year} — ${saln.user?.name}`" :subtitle="`As of ${fmtDate(saln.as_of_date)}`">
        <template #actions>
          <AppBadge :color="statusBadge(saln.status)">{{ saln.status_label }}</AppBadge>
          <AppButton v-if="saln.status === 'approved' || saln.status === 'filed'"
            as="a" :href="route('saln.pdf', saln.id)" target="_blank" variant="secondary" size="sm">
            <DocumentArrowDownIcon class="h-4 w-4" />Export PDF
          </AppButton>
          <AppButton v-if="saln.is_submittable" size="sm" @click="confirmSubmit = true">
            <PaperAirplaneIcon class="h-4 w-4" />Submit for Review
          </AppButton>
        </template>
      </AppPageHeader>

      <!-- Return notice -->
      <div v-if="saln.status === 'returned'" class="flex gap-3 bg-danger-50 border border-danger-100 rounded-xl px-5 py-4">
        <ExclamationTriangleIcon class="h-5 w-5 text-danger-500 shrink-0 mt-0.5" />
        <div>
          <p class="text-sm font-semibold text-danger-700">Returned for Revision</p>
          <p class="text-xs text-danger-600 mt-0.5">{{ lastReturnRemark }}</p>
        </div>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">
        {{ $page.props.flash.success }}
      </div>

      <!-- Net Worth Summary Bar -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 px-4 py-3 text-center">
          <p class="text-xs text-slate-400 uppercase tracking-wide">Real Properties</p>
          <p class="text-base font-bold text-slate-800 mt-1">{{ fmtMoney(saln.total_real_properties) }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 px-4 py-3 text-center">
          <p class="text-xs text-slate-400 uppercase tracking-wide">Personal Properties</p>
          <p class="text-base font-bold text-slate-800 mt-1">{{ fmtMoney(saln.total_personal_properties) }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70 px-4 py-3 text-center">
          <p class="text-xs text-slate-400 uppercase tracking-wide">Total Liabilities</p>
          <p class="text-base font-bold text-danger-600 mt-1">{{ fmtMoney(saln.total_liabilities) }}</p>
        </div>
        <div class="rounded-xl border px-4 py-3 text-center shadow-sm"
          :class="saln.net_worth >= 0 ? 'bg-success-50 border-success-100' : 'bg-danger-50 border-danger-100'">
          <p class="text-xs text-slate-500 uppercase tracking-wide">Net Worth</p>
          <p class="text-base font-bold mt-1" :class="saln.net_worth >= 0 ? 'text-success-700' : 'text-danger-600'">
            {{ fmtMoney(saln.net_worth) }}
          </p>
          <p class="text-[10px] text-slate-400 mt-0.5">Assets − Liabilities</p>
        </div>
      </div>

      <!-- Main Tabs -->
      <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200/70">
        <!-- Tab nav -->
        <div class="px-4 pt-4 border-b border-slate-100 overflow-x-auto">
          <nav class="flex gap-0.5 min-w-max">
            <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
              :class="['px-3 py-2 text-sm font-medium rounded-t-lg border-b-2 transition-colors whitespace-nowrap',
                activeTab === tab.key
                  ? 'text-indigo-600 border-indigo-600 bg-indigo-50/50'
                  : 'text-slate-500 border-transparent hover:text-slate-700 hover:bg-slate-50']">
              {{ tab.label }}
              <span v-if="tab.count !== undefined"
                class="ml-1.5 inline-flex items-center justify-center rounded-full bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-600">
                {{ tab.count }}
              </span>
            </button>
          </nav>
        </div>

        <div class="p-5">

          <!-- ── Tab: Header Info ── -->
          <div v-if="activeTab === 'header'">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-sm font-semibold text-slate-700">Personal Information & Spouse</h2>
              <button v-if="saln.is_editable" @click="editHeader = !editHeader"
                class="text-xs text-indigo-600 hover:underline">{{ editHeader ? 'Cancel' : 'Edit' }}</button>
            </div>

            <div v-if="!editHeader" class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
              <InfoRow label="Name" :value="saln.user?.name" />
              <InfoRow label="Position" :value="saln.user?.position" />
              <InfoRow label="SALN Year" :value="saln.year" />
              <InfoRow label="As of Date" :value="fmtDate(saln.as_of_date)" />
              <InfoRow label="Spouse Name" :value="saln.spouse_name || '—'" />
              <InfoRow label="Spouse Position" :value="saln.spouse_position || '—'" />
              <InfoRow label="Spouse Office" :value="saln.spouse_office || '—'" />
              <InfoRow label="Spouse Gov't ID" :value="saln.spouse_government_id || '—'" />
            </div>

            <form v-else @submit.prevent="saveHeader" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <AppInput
                v-model="headerForm.as_of_date"
                type="date" label="As of Date" required
                :error="headerForm.errors.as_of_date" />
              <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <FieldInput label="Spouse Name" v-model="headerForm.spouse_name" />
                <FieldInput label="Spouse Position" v-model="headerForm.spouse_position" />
                <FieldInput label="Spouse Office / Employer" v-model="headerForm.spouse_office" />
                <FieldInput label="Spouse Gov't ID" v-model="headerForm.spouse_government_id" />
              </div>
              <div class="sm:col-span-2 flex gap-3 justify-end">
                <AppButton type="button" variant="secondary" @click="editHeader = false">Cancel</AppButton>
                <AppButton type="submit" :loading="headerForm.processing">
                  {{ headerForm.processing ? 'Saving…' : 'Save Changes' }}
                </AppButton>
              </div>
            </form>
          </div>

          <!-- ── Children section (inside Personal Info tab) ── -->
          <div v-if="activeTab === 'header'" class="mt-6 border-t border-slate-100 pt-5">
            <div class="flex items-center justify-between mb-3">
              <div>
                <h2 class="text-sm font-semibold text-slate-700">Unmarried Children Below 18 in Declarant's Household</h2>
                <p class="text-xs text-slate-400 mt-0.5">As required by CSC SALN Form No. SALN-1</p>
              </div>
              <AppButton v-if="saln.is_editable" variant="secondary" size="sm" @click="showAddChild = true">
                <PlusIcon class="h-3.5 w-3.5" />Add Child
              </AppButton>
            </div>

            <AppTable :card="false" :is-empty="saln.children.length === 0">
              <template #head>
                <tr>
                  <th class="px-4 py-2 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Name of Child</th>
                  <th class="px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider w-16">Age</th>
                  <th v-if="saln.is_editable" class="px-4 py-2 w-20"></th>
                </tr>
              </template>
              <tr v-for="c in saln.children" :key="c.id" class="group">
                <td class="px-4 py-2.5 font-medium text-slate-800">{{ c.name }}</td>
                <td class="px-4 py-2.5 text-center text-slate-600">{{ c.age }}</td>
                <td v-if="saln.is_editable" class="px-4 py-2.5 text-right">
                  <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-1 justify-end">
                    <AppIconButton label="Edit" size="sm" @click="openEditChild(c)"><PencilIcon class="h-4 w-4" /></AppIconButton>
                    <AppIconButton label="Delete" variant="danger" size="sm" @click="deleteChild(c.id)"><TrashIcon class="h-4 w-4" /></AppIconButton>
                  </div>
                </td>
              </tr>
              <template #empty>
                <p class="text-sm text-slate-400">No children declared.</p>
              </template>
              <template #mobileCard>
                <div v-for="c in saln.children" :key="c.id" class="p-4 flex items-center justify-between gap-3">
                  <div>
                    <p class="text-sm font-medium text-slate-800">{{ c.name }}</p>
                    <p class="text-xs text-slate-500">Age {{ c.age }}</p>
                  </div>
                  <div v-if="saln.is_editable" class="flex gap-1">
                    <AppIconButton label="Edit" size="sm" @click="openEditChild(c)"><PencilIcon class="h-4 w-4" /></AppIconButton>
                    <AppIconButton label="Delete" variant="danger" size="sm" @click="deleteChild(c.id)"><TrashIcon class="h-4 w-4" /></AppIconButton>
                  </div>
                </div>
              </template>
            </AppTable>
          </div>

          <!-- ── Tab: Real Properties ── -->
          <SectionTable v-if="activeTab === 'real'" :editable="saln.is_editable"
            title="Real Properties"
            description="Lands, residential/commercial/agricultural buildings owned or co-owned."
            @add="showAddReal = true">
            <template #table>
              <div v-if="saln.real_properties.length === 0" class="py-10 text-center text-slate-400 text-sm">
                No real properties declared.
              </div>
              <table v-else class="w-full text-sm">
                <thead><tr class="text-xs font-semibold text-slate-500 uppercase border-b border-slate-100">
                  <th class="pb-2 text-left">Kind</th>
                  <th class="pb-2 text-left hidden md:table-cell">Location</th>
                  <th class="pb-2 text-right">Assessed Value</th>
                  <th class="pb-2 text-right">Current FMV</th>
                  <th class="pb-2 text-center hidden sm:table-cell">Acq. Year</th>
                  <th class="pb-2 text-center hidden sm:table-cell">Mode</th>
                  <th class="pb-2 text-center hidden sm:table-cell">Owner</th>
                  <th v-if="saln.is_editable" class="pb-2"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="p in saln.real_properties" :key="p.id" class="group">
                    <td class="py-2.5 font-medium text-slate-800">{{ p.kind }}</td>
                    <td class="py-2.5 text-slate-600 hidden md:table-cell max-w-[180px] truncate">{{ p.exact_location }}</td>
                    <td class="py-2.5 text-right tabular-nums">{{ fmtMoney(p.assessed_value) }}</td>
                    <td class="py-2.5 text-right tabular-nums font-medium text-slate-800">{{ fmtMoney(p.current_fair_market_value) }}</td>
                    <td class="py-2.5 text-center hidden sm:table-cell">{{ p.year_acquired ?? '—' }}</td>
                    <td class="py-2.5 text-center hidden sm:table-cell capitalize">{{ p.acquisition_mode }}</td>
                    <td class="py-2.5 text-center hidden sm:table-cell capitalize">{{ p.owner }}</td>
                    <td v-if="saln.is_editable" class="py-2.5 text-right">
                      <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-1 justify-end">
                        <button @click="openEditReal(p)" class="text-xs text-indigo-600 hover:underline px-2">Edit</button>
                        <button @click="deleteReal(p.id)" class="text-xs text-danger-500 hover:underline px-2">Del</button>
                      </div>
                    </td>
                  </tr>
                </tbody>
                <tfoot><tr class="border-t border-slate-200">
                  <td colspan="3" class="pt-2 text-xs font-semibold text-slate-500 text-right">Total Current FMV:</td>
                  <td class="pt-2 text-right font-bold text-slate-800">{{ fmtMoney(saln.total_real_properties) }}</td>
                  <td colspan="4"></td>
                </tr></tfoot>
              </table>
            </template>
          </SectionTable>

          <!-- ── Tab: Personal Properties ── -->
          <SectionTable v-if="activeTab === 'personal'" :editable="saln.is_editable"
            title="Personal Properties"
            description="Motor vehicles, jewelry, shares of stock, cash on hand/bank, and other personal property."
            @add="showAddPersonal = true">
            <template #table>
              <div v-if="saln.personal_properties.length === 0" class="py-10 text-center text-slate-400 text-sm">
                No personal properties declared.
              </div>
              <table v-else class="w-full text-sm">
                <thead><tr class="text-xs font-semibold text-slate-500 uppercase border-b border-slate-100">
                  <th class="pb-2 text-left">Description</th>
                  <th class="pb-2 text-left hidden sm:table-cell">Category</th>
                  <th class="pb-2 text-center hidden sm:table-cell">Year</th>
                  <th class="pb-2 text-right">Acquisition Cost</th>
                  <th class="pb-2 text-center hidden sm:table-cell">Owner</th>
                  <th v-if="saln.is_editable" class="pb-2"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="p in saln.personal_properties" :key="p.id" class="group">
                    <td class="py-2.5 font-medium text-slate-800">{{ p.description }}</td>
                    <td class="py-2.5 text-slate-500 hidden sm:table-cell capitalize">{{ p.category?.replace(/_/g,' ') }}</td>
                    <td class="py-2.5 text-center hidden sm:table-cell">{{ p.year_acquired ?? '—' }}</td>
                    <td class="py-2.5 text-right tabular-nums font-medium text-slate-800">{{ fmtMoney(p.acquisition_cost) }}</td>
                    <td class="py-2.5 text-center hidden sm:table-cell capitalize">{{ p.owner }}</td>
                    <td v-if="saln.is_editable" class="py-2.5 text-right">
                      <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-1 justify-end">
                        <button @click="openEditPersonal(p)" class="text-xs text-indigo-600 hover:underline px-2">Edit</button>
                        <button @click="deletePersonal(p.id)" class="text-xs text-danger-500 hover:underline px-2">Del</button>
                      </div>
                    </td>
                  </tr>
                </tbody>
                <tfoot><tr class="border-t border-slate-200">
                  <td colspan="3" class="pt-2 text-xs font-semibold text-slate-500 text-right">Total Acquisition Cost:</td>
                  <td class="pt-2 text-right font-bold text-slate-800">{{ fmtMoney(saln.total_personal_properties) }}</td>
                  <td colspan="2"></td>
                </tr></tfoot>
              </table>
            </template>
          </SectionTable>

          <!-- ── Tab: Liabilities ── -->
          <SectionTable v-if="activeTab === 'liabilities'" :editable="saln.is_editable"
            title="Liabilities"
            description="All outstanding loans and obligations."
            @add="showAddLiability = true">
            <template #table>
              <div v-if="saln.liabilities.length === 0" class="py-10 text-center text-slate-400 text-sm">No liabilities declared.</div>
              <table v-else class="w-full text-sm">
                <thead><tr class="text-xs font-semibold text-slate-500 uppercase border-b border-slate-100">
                  <th class="pb-2 text-left">Nature</th>
                  <th class="pb-2 text-left">Creditor</th>
                  <th class="pb-2 text-right">Outstanding Balance</th>
                  <th v-if="saln.is_editable" class="pb-2"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="l in saln.liabilities" :key="l.id" class="group">
                    <td class="py-2.5 text-slate-700">{{ l.nature === 'others' ? l.nature_other : l.nature?.replace(/_/g,' ') }}</td>
                    <td class="py-2.5 font-medium text-slate-800">{{ l.creditor_name }}</td>
                    <td class="py-2.5 text-right tabular-nums font-medium text-danger-600">{{ fmtMoney(l.outstanding_balance) }}</td>
                    <td v-if="saln.is_editable" class="py-2.5 text-right">
                      <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-1 justify-end">
                        <button @click="openEditLiability(l)" class="text-xs text-indigo-600 hover:underline px-2">Edit</button>
                        <button @click="deleteLiability(l.id)" class="text-xs text-danger-500 hover:underline px-2">Del</button>
                      </div>
                    </td>
                  </tr>
                </tbody>
                <tfoot><tr class="border-t border-slate-200">
                  <td colspan="2" class="pt-2 text-xs font-semibold text-slate-500 text-right">Total Outstanding Balance:</td>
                  <td class="pt-2 text-right font-bold text-danger-600">{{ fmtMoney(saln.total_liabilities) }}</td>
                  <td v-if="saln.is_editable"></td>
                </tr></tfoot>
              </table>
            </template>
          </SectionTable>

          <!-- ── Tab: Business Interests ── -->
          <SectionTable v-if="activeTab === 'business'" :editable="saln.is_editable"
            title="Financial Connections & Business Interests"
            description="Businesses, partnerships, or financial interests of self and/or spouse."
            @add="showAddBusiness = true">
            <template #table>
              <div v-if="saln.business_interests.length === 0" class="py-10 text-center text-slate-400 text-sm">No business interests declared.</div>
              <table v-else class="w-full text-sm">
                <thead><tr class="text-xs font-semibold text-slate-500 uppercase border-b border-slate-100">
                  <th class="pb-2 text-left">Entity / Business</th>
                  <th class="pb-2 text-left hidden md:table-cell">Nature</th>
                  <th class="pb-2 text-right hidden sm:table-cell">Date Acquired</th>
                  <th class="pb-2 text-right">Present FMV</th>
                  <th class="pb-2 text-center hidden sm:table-cell">Owner</th>
                  <th v-if="saln.is_editable" class="pb-2"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="b in saln.business_interests" :key="b.id" class="group">
                    <td class="py-2.5 font-medium text-slate-800">{{ b.entity_name }}<br/><span class="text-xs text-slate-400 font-normal">{{ b.business_address }}</span></td>
                    <td class="py-2.5 text-slate-600 hidden md:table-cell">{{ b.nature_of_business }}</td>
                    <td class="py-2.5 text-right hidden sm:table-cell">{{ fmtDate(b.date_acquired) }}</td>
                    <td class="py-2.5 text-right tabular-nums font-medium text-slate-800">{{ fmtMoney(b.present_fair_market_value) }}</td>
                    <td class="py-2.5 text-center hidden sm:table-cell capitalize">{{ b.owner }}</td>
                    <td v-if="saln.is_editable" class="py-2.5 text-right">
                      <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-1 justify-end">
                        <button @click="openEditBusiness(b)" class="text-xs text-indigo-600 hover:underline px-2">Edit</button>
                        <button @click="deleteBusiness(b.id)" class="text-xs text-danger-500 hover:underline px-2">Del</button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </template>
          </SectionTable>

          <!-- ── Tab: Relatives ── -->
          <SectionTable v-if="activeTab === 'relatives'" :editable="saln.is_editable"
            title="Relatives in Government Service"
            description="Within the 4th civil degree of consanguinity or affinity."
            @add="showAddRelative = true">
            <template #table>
              <div v-if="saln.relatives.length === 0" class="py-10 text-center text-slate-400 text-sm">No relatives in government service declared.</div>
              <table v-else class="w-full text-sm">
                <thead><tr class="text-xs font-semibold text-slate-500 uppercase border-b border-slate-100">
                  <th class="pb-2 text-left">Name</th>
                  <th class="pb-2 text-left">Relationship</th>
                  <th class="pb-2 text-left hidden sm:table-cell">Position</th>
                  <th class="pb-2 text-left hidden md:table-cell">Agency / Office</th>
                  <th v-if="saln.is_editable" class="pb-2"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="r in saln.relatives" :key="r.id" class="group">
                    <td class="py-2.5 font-medium text-slate-800">{{ r.name }}</td>
                    <td class="py-2.5 text-slate-600">{{ r.relationship }}</td>
                    <td class="py-2.5 text-slate-600 hidden sm:table-cell">{{ r.position }}</td>
                    <td class="py-2.5 text-slate-500 hidden md:table-cell">{{ r.agency_office }}</td>
                    <td v-if="saln.is_editable" class="py-2.5 text-right">
                      <div class="opacity-0 group-hover:opacity-100 transition-opacity flex gap-1 justify-end">
                        <button @click="openEditRelative(r)" class="text-xs text-indigo-600 hover:underline px-2">Edit</button>
                        <button @click="deleteRelative(r.id)" class="text-xs text-danger-500 hover:underline px-2">Del</button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </template>
          </SectionTable>

          <!-- ── Tab: Audit Trail ── -->
          <div v-if="activeTab === 'trail'">
            <h2 class="text-sm font-semibold text-slate-700 mb-4">Action History</h2>
            <div v-if="saln.reviews.length === 0" class="py-10 text-center text-slate-400 text-sm">No actions recorded yet.</div>
            <ol v-else class="relative border-l border-slate-200 ml-3 space-y-4">
              <li v-for="rev in saln.reviews" :key="rev.id" class="pl-6">
                <span class="absolute -left-1.5 w-3 h-3 rounded-full border-2 border-white"
                  :class="trailDot(rev.action)"></span>
                <p class="text-sm font-medium text-slate-800">{{ rev.action_label }}</p>
                <p class="text-xs text-slate-500">{{ rev.actor?.name }} · {{ fmtDateTime(rev.created_at) }}</p>
                <p v-if="rev.remarks" class="text-xs mt-1 text-slate-600 bg-slate-50 rounded px-2 py-1 border border-slate-100">
                  "{{ rev.remarks }}"
                </p>
              </li>
            </ol>
          </div>

        </div>
      </div>

    </div><!-- end space-y-5 -->

    <!-- ── Modals ── -->
    <Teleport to="body">

      <!-- Submit Confirmation -->
      <ConfirmModal v-if="confirmSubmit"
        title="Submit SALN for Review?"
        message="Once submitted, you cannot edit this SALN unless the committee returns it. Make sure all sections are complete."
        confirm-label="Submit"
        @confirm="submitSaln"
        @cancel="confirmSubmit = false" />

      <!-- Add / Edit Real Property -->
      <FormModal v-if="showAddReal || editingReal"
        :title="editingReal ? 'Edit Real Property' : 'Add Real Property'"
        @close="closeRealModal" @save="saveReal">
        <RealPropertyForm :form="realForm" />
      </FormModal>

      <!-- Add / Edit Personal Property -->
      <FormModal v-if="showAddPersonal || editingPersonal"
        :title="editingPersonal ? 'Edit Personal Property' : 'Add Personal Property'"
        @close="closePersonalModal" @save="savePersonal">
        <PersonalPropertyForm :form="personalForm" />
      </FormModal>

      <!-- Add / Edit Liability -->
      <FormModal v-if="showAddLiability || editingLiability"
        :title="editingLiability ? 'Edit Liability' : 'Add Liability'"
        @close="closeLiabilityModal" @save="saveLiability">
        <LiabilityForm :form="liabilityForm" />
      </FormModal>

      <!-- Add / Edit Business Interest -->
      <FormModal v-if="showAddBusiness || editingBusiness"
        :title="editingBusiness ? 'Edit Business Interest' : 'Add Business Interest'"
        @close="closeBusinessModal" @save="saveBusiness">
        <BusinessInterestForm :form="businessForm" />
      </FormModal>

      <!-- Add / Edit Child -->
      <FormModal v-if="showAddChild || editingChild"
        :title="editingChild ? 'Edit Child' : 'Add Child'"
        @close="closeChildModal" @save="saveChild">
        <div class="space-y-4">
          <AppInput
            v-model="childForm.name"
            type="text" maxlength="255" placeholder="Full name"
            label="Name of Child" required
            :error="childForm.errors.name" />
          <AppInput
            v-model.number="childForm.age"
            type="number" min="0" max="17" placeholder="0–17"
            label="Age" required
            :error="childForm.errors.age" />
        </div>
      </FormModal>

      <!-- Add / Edit Relative -->
      <FormModal v-if="showAddRelative || editingRelative"
        :title="editingRelative ? 'Edit Relative' : 'Add Relative'"
        @close="closeRelativeModal" @save="saveRelative">
        <RelativeForm :form="relativeForm" />
      </FormModal>

    </Teleport>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppInput from '@/Components/AppInput.vue'
import AppTable from '@/Components/AppTable.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import {
  ChevronLeftIcon, PaperAirplaneIcon, DocumentArrowDownIcon, ExclamationTriangleIcon,
  PlusIcon, PencilIcon, TrashIcon,
} from '@heroicons/vue/24/outline'

// Sub-components (defined inline below)
import SectionTable from './Partials/SectionTable.vue'
import ConfirmModal from './Partials/ConfirmModal.vue'
import FormModal from './Partials/FormModal.vue'
import RealPropertyForm from './Partials/RealPropertyForm.vue'
import PersonalPropertyForm from './Partials/PersonalPropertyForm.vue'
import LiabilityForm from './Partials/LiabilityForm.vue'
import BusinessInterestForm from './Partials/BusinessInterestForm.vue'
import RelativeForm from './Partials/RelativeForm.vue'
import InfoRow from './Partials/InfoRow.vue'
import FieldInput from './Partials/FieldInput.vue'

const props = defineProps({ saln: Object, canManage: Boolean })

// ── Tabs ──────────────────────────────────────────────────────────────────────
const activeTab = ref('header')

const tabs = computed(() => [
  { key: 'header',      label: 'Personal Info',       count: props.saln.children.length || undefined },
  { key: 'real',        label: 'Real Properties',    count: props.saln.real_properties.length },
  { key: 'personal',    label: 'Personal Properties',count: props.saln.personal_properties.length },
  { key: 'liabilities', label: 'Liabilities',        count: props.saln.liabilities.length },
  { key: 'business',    label: 'Business Interests',  count: props.saln.business_interests.length },
  { key: 'relatives',   label: 'Relatives in Gov\'t', count: props.saln.relatives.length },
  { key: 'trail',       label: 'Audit Trail',         count: props.saln.reviews.length },
])

// ── Header edit ───────────────────────────────────────────────────────────────
const editHeader = ref(false)
const headerForm = useForm({
  as_of_date:          props.saln.as_of_date,
  spouse_name:         props.saln.spouse_name ?? '',
  spouse_position:     props.saln.spouse_position ?? '',
  spouse_office:       props.saln.spouse_office ?? '',
  spouse_government_id:props.saln.spouse_government_id ?? '',
})
function saveHeader() {
  headerForm.put(route('saln.update', props.saln.id), {
    onSuccess: () => { editHeader.value = false },
  })
}

// ── Submit ────────────────────────────────────────────────────────────────────
const confirmSubmit = ref(false)
function submitSaln() {
  router.post(route('saln.submit', props.saln.id), {}, {
    onSuccess: () => { confirmSubmit.value = false },
  })
}

// ── Returned remark ───────────────────────────────────────────────────────────
const lastReturnRemark = computed(() => {
  const r = [...props.saln.reviews].reverse().find(r => r.action === 'returned')
  return r?.remarks ?? ''
})

// ── Real Properties ───────────────────────────────────────────────────────────
const showAddReal = ref(false)
const editingReal = ref(null)
const realForm = useForm({
  kind: '', exact_location: '', assessed_value: '', current_fair_market_value: '',
  year_acquired: '', acquisition_mode: 'purchased', acquisition_mode_other: '',
  acquisition_cost: '', owner: 'self',
})
function openEditReal(p) {
  editingReal.value = p
  realForm.kind = p.kind; realForm.exact_location = p.exact_location
  realForm.assessed_value = p.assessed_value; realForm.current_fair_market_value = p.current_fair_market_value
  realForm.year_acquired = p.year_acquired ?? ''; realForm.acquisition_mode = p.acquisition_mode
  realForm.acquisition_mode_other = p.acquisition_mode_other ?? ''
  realForm.acquisition_cost = p.acquisition_cost; realForm.owner = p.owner
}
function saveReal() {
  if (editingReal.value) {
    realForm.put(route('saln.real-properties.update', [props.saln.id, editingReal.value.id]), { onSuccess: closeRealModal })
  } else {
    realForm.post(route('saln.real-properties.store', props.saln.id), { onSuccess: closeRealModal })
  }
}
function closeRealModal() { showAddReal.value = false; editingReal.value = null; realForm.reset() }
async function deleteReal(id) {
  if (await confirmDelete('Remove this real property?'))
    router.delete(route('saln.real-properties.destroy', [props.saln.id, id]))
}

// ── Personal Properties ───────────────────────────────────────────────────────
const showAddPersonal = ref(false)
const editingPersonal = ref(null)
const personalForm = useForm({ description: '', category: 'others', year_acquired: '', acquisition_cost: '', owner: 'self' })
function openEditPersonal(p) {
  editingPersonal.value = p
  personalForm.description = p.description; personalForm.category = p.category
  personalForm.year_acquired = p.year_acquired ?? ''; personalForm.acquisition_cost = p.acquisition_cost
  personalForm.owner = p.owner
}
function savePersonal() {
  if (editingPersonal.value) {
    personalForm.put(route('saln.personal-properties.update', [props.saln.id, editingPersonal.value.id]), { onSuccess: closePersonalModal })
  } else {
    personalForm.post(route('saln.personal-properties.store', props.saln.id), { onSuccess: closePersonalModal })
  }
}
function closePersonalModal() { showAddPersonal.value = false; editingPersonal.value = null; personalForm.reset() }
async function deletePersonal(id) {
  if (await confirmDelete('Remove this personal property?'))
    router.delete(route('saln.personal-properties.destroy', [props.saln.id, id]))
}

// ── Liabilities ───────────────────────────────────────────────────────────────
const showAddLiability = ref(false)
const editingLiability = ref(null)
const liabilityForm = useForm({ nature: 'others', nature_other: '', creditor_name: '', outstanding_balance: '' })
function openEditLiability(l) {
  editingLiability.value = l
  liabilityForm.nature = l.nature; liabilityForm.nature_other = l.nature_other ?? ''
  liabilityForm.creditor_name = l.creditor_name; liabilityForm.outstanding_balance = l.outstanding_balance
}
function saveLiability() {
  if (editingLiability.value) {
    liabilityForm.put(route('saln.liabilities.update', [props.saln.id, editingLiability.value.id]), { onSuccess: closeLiabilityModal })
  } else {
    liabilityForm.post(route('saln.liabilities.store', props.saln.id), { onSuccess: closeLiabilityModal })
  }
}
function closeLiabilityModal() { showAddLiability.value = false; editingLiability.value = null; liabilityForm.reset() }
async function deleteLiability(id) {
  if (await confirmDelete('Remove this liability?'))
    router.delete(route('saln.liabilities.destroy', [props.saln.id, id]))
}

// ── Business Interests ────────────────────────────────────────────────────────
const showAddBusiness = ref(false)
const editingBusiness = ref(null)
const businessForm = useForm({ entity_name: '', business_address: '', nature_of_business: '', date_acquired: '', acquisition_cost: '', present_fair_market_value: '', owner: 'self' })
function openEditBusiness(b) {
  editingBusiness.value = b
  businessForm.entity_name = b.entity_name; businessForm.business_address = b.business_address
  businessForm.nature_of_business = b.nature_of_business; businessForm.date_acquired = b.date_acquired ?? ''
  businessForm.acquisition_cost = b.acquisition_cost; businessForm.present_fair_market_value = b.present_fair_market_value
  businessForm.owner = b.owner
}
function saveBusiness() {
  if (editingBusiness.value) {
    businessForm.put(route('saln.business-interests.update', [props.saln.id, editingBusiness.value.id]), { onSuccess: closeBusinessModal })
  } else {
    businessForm.post(route('saln.business-interests.store', props.saln.id), { onSuccess: closeBusinessModal })
  }
}
function closeBusinessModal() { showAddBusiness.value = false; editingBusiness.value = null; businessForm.reset() }
async function deleteBusiness(id) {
  if (await confirmDelete('Remove this business interest?'))
    router.delete(route('saln.business-interests.destroy', [props.saln.id, id]))
}

// ── Children ──────────────────────────────────────────────────────────────────
const showAddChild  = ref(false)
const editingChild  = ref(null)
const childForm     = useForm({ name: '', age: '' })

function openEditChild(c) {
  editingChild.value = c
  childForm.name = c.name
  childForm.age  = c.age
}
function saveChild() {
  if (editingChild.value) {
    childForm.put(route('saln.children.update', [props.saln.id, editingChild.value.id]), { onSuccess: closeChildModal })
  } else {
    childForm.post(route('saln.children.store', props.saln.id), { onSuccess: closeChildModal })
  }
}
function closeChildModal() { showAddChild.value = false; editingChild.value = null; childForm.reset() }
async function deleteChild(id) {
  if (await confirmDelete('Remove this child?'))
    router.delete(route('saln.children.destroy', [props.saln.id, id]))
}

// ── Relatives ─────────────────────────────────────────────────────────────────
const showAddRelative = ref(false)
const editingRelative = ref(null)
const relativeForm = useForm({ name: '', relationship: '', position: '', agency_office: '', agency_address: '' })
function openEditRelative(r) {
  editingRelative.value = r
  relativeForm.name = r.name; relativeForm.relationship = r.relationship
  relativeForm.position = r.position; relativeForm.agency_office = r.agency_office
  relativeForm.agency_address = r.agency_address ?? ''
}
function saveRelative() {
  if (editingRelative.value) {
    relativeForm.put(route('saln.relatives.update', [props.saln.id, editingRelative.value.id]), { onSuccess: closeRelativeModal })
  } else {
    relativeForm.post(route('saln.relatives.store', props.saln.id), { onSuccess: closeRelativeModal })
  }
}
function closeRelativeModal() { showAddRelative.value = false; editingRelative.value = null; relativeForm.reset() }
async function deleteRelative(id) {
  if (await confirmDelete('Remove this relative?'))
    router.delete(route('saln.relatives.destroy', [props.saln.id, id]))
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const fmtDate     = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' }) : '—'
const fmtDateTime = (d) => d ? new Date(d).toLocaleString('en-PH', { dateStyle:'medium', timeStyle:'short' }) : '—'
const fmtMoney    = (v) => new Intl.NumberFormat('en-PH', { style:'currency', currency:'PHP', maximumFractionDigits:2 }).format(v ?? 0)

const statusBadge = (s) => ({
  draft:        'slate',
  submitted:    'blue',
  under_review: 'amber',
  approved:     'green',
  returned:     'red',
  filed:        'indigo',
}[s] ?? 'slate')

const trailDot = (action) => ({
  created:'bg-slate-400', updated:'bg-slate-400', submitted:'bg-blue-500',
  reviewed:'bg-warning-500', approved:'bg-success-500', returned:'bg-danger-500',
  filed:'bg-indigo-500', reopened:'bg-purple-400',
}[action] ?? 'bg-slate-400')
</script>
