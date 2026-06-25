<template>
  <Head title="My DTR" />
  <AdminLayout title="My DTR">
    <div class="space-y-5">

      <!-- Employee Header -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-sm shrink-0">
              {{ initials(employee.name) }}
            </div>
            <div>
              <h1 class="text-lg font-semibold text-slate-800">{{ employee.name }}</h1>
              <p class="text-sm text-slate-500">
                {{ employee.position || 'No position' }}
                <span v-if="employee.badge_id" class="ml-1 text-slate-400">· Badge {{ employee.badge_id }}</span>
              </p>
              <p v-if="activeScheduleName" class="text-xs text-indigo-500 mt-0.5">
                Schedule: <span class="font-medium">{{ activeScheduleName }}</span>
                <span v-if="activeScheduleTimes" class="ml-1 font-mono text-indigo-400">({{ activeScheduleTimes }})</span>
              </p>
              <p v-else class="text-xs text-amber-500 mt-0.5">⚠ No schedule assigned — late & undertime will not be computed</p>
            </div>
          </div>
          <div class="flex items-center gap-2 flex-wrap">
            <button @click="changeMonth(-1)" class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50">
              <ChevronLeftIcon class="h-4 w-4 text-slate-600" />
            </button>
            <input
              v-model="currentMonth"
              type="month"
              @change="goMonth"
              class="border border-slate-200 rounded-lg px-3 py-1.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400"
            />
            <button @click="changeMonth(1)" class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50">
              <ChevronRightIcon class="h-4 w-4 text-slate-600" />
            </button>
            <button v-if="isCos && isCurrentMonth && !props.advanceRecord" @click="submitMyAdvanceGenerate" :disabled="advanceGenerating"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-white rounded-lg transition-colors font-medium"
              title="Generate your advance cut-off entry for tomorrow">
              <BoltIcon class="h-4 w-4" :class="{ 'animate-pulse': advanceGenerating }" />
              {{ advanceGenerating ? 'Generating…' : 'Generate Advance Entry' }}
            </button>
            <a :href="route('hr.my-dtr.checklist') + '?month=' + currentMonth" target="_blank"
               class="ml-1 inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors font-medium">
              <PrinterIcon class="h-4 w-4" />Print Checklist
            </a>
          </div>
        </div>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
        class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <CheckCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error"
        class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
        <ExclamationCircleIcon class="h-4 w-4 shrink-0" />{{ $page.props.flash.error }}
      </div>

      <!-- COS Advance Entry Banner -->
      <div v-if="isCos && props.advanceRecord"
           class="bg-sky-50 border border-sky-200 rounded-xl p-4 flex items-start gap-3 justify-between">
        <div class="flex items-start gap-3">
          <InformationCircleIcon class="h-5 w-5 text-sky-500 shrink-0 mt-0.5" />
          <div>
            <p class="text-sm font-semibold text-sky-800">
              Advance Entry — {{ toDateStr(props.advanceRecord.work_date) }}
            </p>
            <p v-if="advanceInCurrentMonth" class="text-xs text-sky-600 mt-0.5">
              This row is your cut-off date advance entry. Fill in your expected time directly in the highlighted row before your payroll cut-off.
            </p>
            <p v-else class="text-xs text-sky-600 mt-0.5">
              This advance entry falls in {{ advanceMonthLabel }} — switch month to fill in your expected time.
            </p>
          </div>
        </div>
        <button v-if="!advanceInCurrentMonth" @click="goToAdvanceMonth"
          class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-sky-600 hover:bg-sky-700 text-white rounded-lg font-medium transition-colors">
          Go to {{ advanceMonthLabel }}
        </button>
      </div>

      <!-- Submit Penned Entries Banner -->
      <div v-if="hasPenned && !allSubmitted"
           class="bg-amber-50 border border-amber-300 rounded-xl p-4 flex items-center justify-between gap-4">
        <div>
          <p class="text-sm font-semibold text-amber-800">You have pending penned entries</p>
          <p class="text-xs text-amber-600 mt-0.5">Once you submit, your entries will be locked for review. HR/Admin can unlock if corrections are needed.</p>
        </div>
        <button @click="confirmSubmitPenned"
          class="shrink-0 inline-flex items-center gap-2 bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors shadow-sm">
          <LockClosedIcon class="h-4 w-4" /> Submit & Lock
        </button>
      </div>

      <div v-if="allSubmitted"
           class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex items-center gap-3 text-sm text-emerald-700">
        <LockClosedIcon class="h-5 w-5 shrink-0 text-emerald-500" />
        <span>Penned entries for <strong>{{ currentMonth }}</strong> have been submitted and are locked. Contact HR if corrections are needed.</span>
      </div>

      <!-- Summary Stats -->
      <div class="grid grid-cols-3 sm:grid-cols-5 lg:grid-cols-9 gap-2">
        <div class="bg-emerald-50 rounded-xl border border-emerald-100 p-3 text-center">
          <p class="text-[10px] text-emerald-600 font-semibold uppercase tracking-wide">Present</p>
          <p class="text-xl font-bold text-emerald-700 mt-0.5">{{ summary.present }}</p>
        </div>
        <div class="bg-red-50 rounded-xl border border-red-100 p-3 text-center">
          <p class="text-[10px] text-red-500 font-semibold uppercase tracking-wide">Absent</p>
          <p class="text-xl font-bold text-red-600 mt-0.5">{{ summary.absent }}</p>
        </div>
        <div class="bg-amber-50 rounded-xl border border-amber-100 p-3 text-center">
          <p class="text-[10px] text-amber-600 font-semibold uppercase tracking-wide">Half Day</p>
          <p class="text-xl font-bold text-amber-700 mt-0.5">{{ summary.half_day }}</p>
        </div>
        <div class="bg-blue-50 rounded-xl border border-blue-100 p-3 text-center">
          <p class="text-[10px] text-blue-600 font-semibold uppercase tracking-wide">On Leave</p>
          <p class="text-xl font-bold text-blue-700 mt-0.5">{{ summary.on_leave }}</p>
        </div>
        <div class="bg-violet-50 rounded-xl border border-violet-100 p-3 text-center">
          <p class="text-[10px] text-violet-600 font-semibold uppercase tracking-wide">Holiday</p>
          <p class="text-xl font-bold text-violet-700 mt-0.5">{{ summary.holiday }}</p>
        </div>
        <div class="bg-rose-50 rounded-xl border border-rose-100 p-3 text-center">
          <p class="text-[10px] text-rose-600 font-semibold uppercase tracking-wide">WFH</p>
          <p class="text-xl font-bold text-rose-700 mt-0.5">{{ summary.wfh ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-3 text-center">
          <p class="text-[10px] text-slate-500 font-semibold uppercase tracking-wide">Total Hrs</p>
          <p class="text-xl font-bold text-slate-800 mt-0.5">{{ summary.total_hours }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-3 text-center">
          <p class="text-[10px] text-amber-500 font-semibold uppercase tracking-wide">Late (m)</p>
          <p class="text-xl font-bold text-amber-600 mt-0.5">{{ summary.total_late }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-3 text-center">
          <p class="text-[10px] text-orange-500 font-semibold uppercase tracking-wide">Undertime</p>
          <p class="text-xl font-bold text-orange-600 mt-0.5">{{ summary.total_ut }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-3 text-center">
          <p class="text-[10px] text-emerald-500 font-semibold uppercase tracking-wide">Overtime</p>
          <p class="text-xl font-bold text-emerald-600 mt-0.5">{{ summary.total_ot }}</p>
        </div>
      </div>

      <!-- Calendar Grid -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
        <h2 class="text-sm font-semibold text-slate-700 mb-3">{{ monthLabel }}</h2>
        <div class="grid grid-cols-7 mb-1">
          <div v-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="d"
               class="text-center text-[11px] font-semibold text-slate-400 py-1.5">{{ d }}</div>
        </div>
        <div class="grid grid-cols-7 gap-1">
          <div v-for="n in firstDayOfWeek" :key="'pad-'+n" class="min-h-[76px]"></div>
          <div
            v-for="cell in calendarCells"
            :key="cell.date"
            :class="['min-h-[76px] rounded-lg border p-1.5 text-xs relative flex flex-col', cellBg(cell)]"
          >
            <span :class="['text-[11px] font-bold leading-none mb-1', cell.isToday ? 'text-indigo-600' : 'text-slate-500']">
              {{ cell.day }}
              <span v-if="cell.isToday" class="ml-0.5 inline-block h-1.5 w-1.5 rounded-full bg-indigo-500 align-middle"></span>
            </span>
            <template v-if="cell.record">
              <div class="font-mono text-[9px] text-slate-500 leading-[1.4] space-y-px flex-1">
                <!-- AM In -->
                <div v-if="cell.record.time_in_am || cell.record.penned_time_in_am || cell.wfhIn" class="flex gap-1">
                  <span class="text-slate-400">in</span>
                  <span :class="timeColor(cell.record, 'time_in_am', cell.wfhIn)">
                    {{ fmtTime(cell.record.time_in_am || cell.record.penned_time_in_am || cell.wfhIn) }}
                  </span>
                </div>
                <!-- AM Out -->
                <div v-if="cell.record.time_out_am || cell.record.penned_time_out_am" class="flex gap-1">
                  <span class="text-slate-400">out</span>
                  <span :class="cell.record.time_out_am ? '' : 'text-red-500'">
                    {{ fmtTime(cell.record.time_out_am || cell.record.penned_time_out_am) }}
                  </span>
                </div>
                <!-- PM In -->
                <div v-if="cell.record.time_in_pm || cell.record.penned_time_in_pm" class="flex gap-1">
                  <span class="text-slate-400">in</span>
                  <span :class="cell.record.time_in_pm ? '' : 'text-red-500'">
                    {{ fmtTime(cell.record.time_in_pm || cell.record.penned_time_in_pm) }}
                  </span>
                </div>
                <!-- PM Out -->
                <div v-if="cell.record.time_out_pm || cell.record.penned_time_out_pm || cell.wfhOut" class="flex gap-1">
                  <span class="text-slate-400">out</span>
                  <span :class="timeColor(cell.record, 'time_out_pm', cell.wfhOut)">
                    {{ fmtTime(cell.record.time_out_pm || cell.record.penned_time_out_pm || cell.wfhOut) }}
                  </span>
                </div>
                <!-- Leave / Gate Pass label -->
                <div v-if="cellSpecialLabel(cell)" class="text-red-500 font-bold text-[9px]">
                  {{ cellSpecialLabel(cell) }}
                </div>
              </div>
              <div v-if="cell.schedIn || cell.schedOut"
                class="font-mono text-[8px] text-indigo-400 leading-tight border-t border-indigo-100 mt-0.5 pt-0.5 text-center">
                {{ fmtTime(cell.schedIn) }}{{ cell.schedIn && cell.schedOut ? '–' : '' }}{{ fmtTime(cell.schedOut) }}
              </div>
              <div :class="[statusBadge(cell.record.attendance_status), 'mt-1 text-center rounded text-[8px] font-bold py-0.5 px-1 uppercase tracking-wide']">
                {{ statusLabel(cell.record.attendance_status) }}
              </div>
              <div v-if="cell.record.late_minutes > 0 || cell.record.undertime_minutes > 0"
                class="flex gap-0.5 mt-0.5 justify-center flex-wrap">
                <span v-if="cell.record.late_minutes > 0"
                  class="text-[7px] font-bold bg-amber-100 text-amber-700 rounded px-1 leading-tight">
                  L {{ fmtMinutes(cell.record.late_minutes) }}
                </span>
                <span v-if="cell.record.undertime_minutes > 0"
                  class="text-[7px] font-bold bg-orange-100 text-orange-700 rounded px-1 leading-tight">
                  UT {{ fmtMinutes(cell.record.undertime_minutes) }}
                </span>
              </div>
            </template>
            <!-- WFH-only cell (no DTR record but WFH data exists) -->
            <template v-else-if="wfhByDate[cell.date]">
              <div class="font-mono text-[9px] text-red-500 leading-[1.4] space-y-px flex-1">
                <div class="flex gap-1"><span class="text-slate-400">in</span><span>{{ fmtTime(wfhByDate[cell.date].time_in) }}</span></div>
                <div v-if="wfhByDate[cell.date].break_out" class="flex gap-1"><span class="text-slate-400">out</span><span>{{ fmtTime(wfhByDate[cell.date].break_out) }}</span></div>
                <div v-if="wfhByDate[cell.date].break_in" class="flex gap-1"><span class="text-slate-400">in</span><span>{{ fmtTime(wfhByDate[cell.date].break_in) }}</span></div>
                <div class="flex gap-1"><span class="text-slate-400">out</span><span>{{ fmtTime(wfhByDate[cell.date].time_out) }}</span></div>
              </div>
              <div class="mt-1 text-center rounded text-[8px] font-bold py-0.5 px-1 uppercase tracking-wide bg-indigo-100 text-indigo-700">WFH</div>
            </template>
            <template v-else-if="!cell.isWorkDay">
              <span class="text-[9px] text-slate-300 mt-auto text-center">rest</span>
            </template>
            <template v-else>
              <span class="text-[9px] font-bold text-red-300 mt-auto text-center uppercase tracking-wide">absent</span>
            </template>
          </div>
        </div>
      </div>

      <!-- Detail Table -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-700">Detail Records</h2>
          <p class="text-xs text-slate-400">Click the pencil icon on any row to submit a penned entry for missing time slots.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Day</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">AM In</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">AM Out</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">PM In</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">PM Out</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-indigo-400 uppercase tracking-wide whitespace-nowrap">Sched. In</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-indigo-400 uppercase tracking-wide whitespace-nowrap">Sched. Out</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Hrs</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-amber-500 uppercase tracking-wide">Late</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-orange-500 uppercase tracking-wide">UT</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-emerald-500 uppercase tracking-wide">OT</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="!records.length">
                <td colspan="14" class="px-4 py-12 text-center text-slate-400 text-sm">No records for this month.</td>
              </tr>
              <tr v-for="r in records" :key="r.id" :class="r.is_advance ? 'bg-amber-50/60 hover:bg-amber-50' : 'hover:bg-slate-50/60'">
                <td class="px-4 py-2.5 text-slate-700 whitespace-nowrap text-xs">{{ toDateStr(r.work_date) }}</td>
                <td class="px-4 py-2.5 text-slate-500 text-xs font-medium">{{ getDayName(r.work_date) }}</td>
                <template v-if="r.is_advance">
                  <td v-for="pf in ['penned_time_in_am','penned_time_out_am','penned_time_in_pm','penned_time_out_pm']" :key="pf" class="px-2 py-1.5">
                    <input v-model="advancePennedForm[pf]" type="time"
                      class="w-[90px] border border-amber-200 rounded px-1.5 py-0.5 text-xs font-mono text-amber-700 focus:outline-none focus:ring-1 focus:ring-amber-400 bg-white" />
                  </td>
                </template>
                <template v-else>
                  <!-- AM In -->
                  <td class="px-4 py-2.5 font-mono text-xs whitespace-nowrap" :class="tableCellClass(r, 'time_in_am')">
                    {{ tableCellText(r, 'time_in_am') }}
                  </td>
                  <!-- AM Out -->
                  <td class="px-4 py-2.5 font-mono text-xs whitespace-nowrap" :class="tableCellClass(r, 'time_out_am')">
                    {{ tableCellText(r, 'time_out_am') }}
                  </td>
                  <!-- PM In -->
                  <td class="px-4 py-2.5 font-mono text-xs whitespace-nowrap" :class="tableCellClass(r, 'time_in_pm')">
                    {{ tableCellText(r, 'time_in_pm') }}
                  </td>
                  <!-- PM Out -->
                  <td class="px-4 py-2.5 font-mono text-xs whitespace-nowrap" :class="tableCellClass(r, 'time_out_pm')">
                    {{ tableCellText(r, 'time_out_pm') }}
                  </td>
                </template>
                <td class="px-4 py-2.5 font-mono text-xs whitespace-nowrap text-indigo-400">{{ fmtTime(r.scheduled_time_in) || '–' }}</td>
                <td class="px-4 py-2.5 font-mono text-xs whitespace-nowrap text-indigo-400">{{ fmtTime(r.scheduled_time_out) || '–' }}</td>
                <td class="px-4 py-2.5 text-right text-slate-700 text-xs tabular-nums">{{ r.hours_worked > 0 ? r.hours_worked : '—' }}</td>
                <td class="px-4 py-2.5 text-right text-xs tabular-nums" :class="r.late_minutes > 0 ? 'text-amber-600 font-semibold' : 'text-slate-300'">
                  <span v-if="!r.schedule_name" class="cursor-help text-slate-200" title="No schedule">n/a</span>
                  <span v-else>{{ fmtMinutes(r.late_minutes) }}</span>
                </td>
                <td class="px-4 py-2.5 text-right text-xs tabular-nums" :class="r.undertime_minutes > 0 ? 'text-orange-600 font-semibold' : 'text-slate-300'">
                  <span v-if="!r.schedule_name" class="cursor-help text-slate-200" title="No schedule">n/a</span>
                  <span v-else>{{ fmtMinutes(r.undertime_minutes) }}</span>
                </td>
                <td class="px-4 py-2.5 text-right text-xs tabular-nums" :class="r.overtime_minutes > 0 ? 'text-emerald-600 font-semibold' : 'text-slate-300'">
                  {{ fmtMinutes(r.overtime_minutes) }}
                </td>
                <td class="px-4 py-2.5">
                  <div class="flex items-center gap-1 flex-wrap">
                    <!-- Main status badge — hidden when a specific badge below replaces it -->
                    <span v-if="r.attendance_status !== 'on_leave' && !r.is_travel && !gatepassByDate[toDateStr(r.work_date)]"
                          :class="statusBadge(r.attendance_status)"
                          class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium whitespace-nowrap">
                      {{ statusLabel(r.attendance_status) }}
                    </span>
                    <!-- Gate pass badge (replaces main status badge) -->
                    <span v-if="gatepassByDate[toDateStr(r.work_date)]"
                          class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-600 whitespace-nowrap"
                          :title="gatepassTitle(toDateStr(r.work_date))">
                      {{ gatepassByDate[toDateStr(r.work_date)].label }}
                      <span v-if="gatepassByDate[toDateStr(r.work_date)].consumed_minutes > 0"
                            class="ml-1 font-normal opacity-80">
                        -{{ fmtMinutes(gatepassByDate[toDateStr(r.work_date)].consumed_minutes) }}
                      </span>
                    </span>
                    <!-- WFH badge — only when supplementary (status is not already 'wfh') -->
                    <span v-if="r.wfh_attendance_id && r.attendance_status !== 'wfh'"
                          class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-600 whitespace-nowrap"
                          title="Times sourced from WFH attendance log">WFH</span>
                    <!-- Leave type badge (replaces main status badge) -->
                    <span v-if="r.attendance_status === 'on_leave'"
                          class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 whitespace-nowrap"
                          :title="leaveTitle(r)">
                      {{ r.leave_application?.leave_type?.code || 'L' }}
                    </span>
                    <!-- Travel badge (replaces main status badge) -->
                    <span v-if="r.is_travel"
                          class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 text-sky-600 whitespace-nowrap"
                          :title="r.penned_remarks || 'On official travel'">T</span>
                    <!-- Advance entry badge -->
                    <span v-if="r.is_advance"
                          class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 whitespace-nowrap"
                          title="Advance entry — cut-off date, pending biometric confirmation">Advance</span>
                  </div>
                </td>
                <td class="px-4 py-2.5">
                  <template v-if="r.is_advance">
                    <button @click="saveAdvancePenned(r)" :disabled="advancePennedForm.processing"
                      class="text-xs bg-amber-500 hover:bg-amber-600 text-white px-2.5 py-1 rounded font-medium disabled:opacity-50 whitespace-nowrap">
                      {{ advancePennedForm.processing ? '…' : 'Save' }}
                    </button>
                  </template>
                  <template v-else>
                    <!-- Penned-submitted lock (employee submitted) -->
                    <LockClosedIcon v-if="r.penned_submitted_at" class="h-4 w-4 text-amber-400" title="Penned entries submitted — contact HR to unlock" />
                    <!-- HR lock -->
                    <LockClosedIcon v-else-if="r.is_locked" class="h-4 w-4 text-red-300" title="Record locked by HR" />
                    <!-- Edit button -->
                    <button v-else-if="hasMissingSlots(r) || r.is_travel"
                      @click="openEdit(r)"
                      class="text-slate-300 hover:text-indigo-600 transition-colors"
                      title="Submit penned entry">
                      <PencilSquareIcon class="h-4 w-4" />
                    </button>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- Penned Entry Modal -->
    <Teleport to="body">
      <div v-if="editModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm mx-4">
          <h3 class="text-base font-semibold text-slate-800 mb-0.5">
            {{ editModal.record?.is_advance ? 'Submit Advance Entry' : 'Submit Penned Entry' }}
          </h3>
          <p class="text-sm text-slate-400 mb-1">{{ toDateStr(editModal.record?.work_date) }} — {{ getDayName(editModal.record?.work_date) }}</p>
          <p v-if="editModal.record?.is_advance" class="text-[11px] text-amber-600 mb-4 font-medium">
            This is an advance cut-off entry. Enter your <span class="font-semibold">expected</span> time for this date. It will be replaced by biometric data once the actual day passes.
          </p>
          <p v-else class="text-[11px] text-slate-400 mb-4">
            Biometric punches are read-only. Fill in the <span class="text-red-500 font-medium">empty slots</span> with your actual time.
            This will be reviewed by HR.
          </p>

          <!-- Travel toggle -->
          <div class="mb-4 p-3 rounded-lg border border-slate-100 bg-slate-50">
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <input type="checkbox" v-model="editForm.is_travel"
                class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-400" />
              <span class="text-sm font-medium text-slate-700">On Official Travel</span>
            </label>
            <p class="text-[11px] text-slate-400 mt-1 ml-6">Check if you were on official travel this day. Time slots become optional.</p>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div v-for="field in ['time_in_am','time_out_am','time_in_pm','time_out_pm']" :key="field">
              <label class="block text-xs font-medium mb-1"
                :class="editModal.record?.[field] ? 'text-slate-500' : (editForm.is_travel ? 'text-slate-400' : 'text-red-500')">
                {{ fieldLabel(field) }}
                <span v-if="editModal.record?.[field]" class="font-normal text-slate-400">(biometric)</span>
                <span v-else-if="editForm.is_travel" class="font-normal text-slate-400">(optional)</span>
                <span v-else class="font-normal text-red-400">(enter penned)</span>
              </label>
              <div v-if="editModal.record?.[field]"
                class="w-full border border-slate-100 bg-slate-50 rounded-lg px-3 py-2 text-sm font-mono text-slate-400 select-none">
                {{ fmtTime(editModal.record[field]) }}
              </div>
              <input v-else
                v-model="editForm['penned_' + field]"
                type="time"
                :class="editForm.is_travel
                  ? 'w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono text-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-400'
                  : 'w-full border border-red-200 rounded-lg px-3 py-2 text-sm font-mono text-red-600 focus:outline-none focus:ring-2 focus:ring-red-400'" />
            </div>
            <div class="col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Remarks</label>
              <input v-model="editForm.penned_remarks" type="text" placeholder="Reason for penned entry (e.g. biometric malfunction)…"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400" />
            </div>
          </div>

          <div class="flex gap-3 justify-end mt-5">
            <button @click="editModal.open = false" class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50">Cancel</button>
            <button @click="submitEdit" :disabled="editForm.processing"
              class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg transition-colors">
              {{ editForm.processing ? 'Submitting…' : 'Submit Penned Entry' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'
import axios from 'axios'
import {
  ChevronLeftIcon, ChevronRightIcon, PrinterIcon,
  PencilSquareIcon, LockClosedIcon, CheckCircleIcon, ExclamationCircleIcon,
  InformationCircleIcon, BoltIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  employee:       Object,
  records:        Array,
  summary:        Object,
  month:          String,
  wfhByDate:      { type: Object, default: () => ({}) },
  gatepassByDate: { type: Object, default: () => ({}) },
  hasPenned:      { type: Boolean, default: false },
  allSubmitted:   { type: Boolean, default: false },
  advanceRecord:  { type: Object, default: null },
})

const currentMonth = ref(props.month)

const isCos = computed(() =>
  ['COS Teaching', 'COS Non Teaching'].includes(props.employee?.emp_category)
)

const advanceInCurrentMonth = computed(() =>
  !!props.advanceRecord && toDateStr(props.advanceRecord.work_date).slice(0, 7) === currentMonth.value
)

const advanceMonthLabel = computed(() => {
  if (!props.advanceRecord) return ''
  const [y, m] = toDateStr(props.advanceRecord.work_date).slice(0, 7).split('-').map(Number)
  return new Date(y, m - 1, 1).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })
})

function goToAdvanceMonth() {
  if (!props.advanceRecord) return
  currentMonth.value = toDateStr(props.advanceRecord.work_date).slice(0, 7)
  goMonth()
}

const isCurrentMonth = computed(() => {
  const n = new Date()
  const curr = `${n.getFullYear()}-${String(n.getMonth()+1).padStart(2,'0')}`
  return currentMonth.value === curr
})

const advanceGenerating = ref(false)

function submitMyAdvanceGenerate() {
  advanceGenerating.value = true
  router.post(route('hr.my-dtr.generate-advance'), {}, {
    onFinish: () => { advanceGenerating.value = false },
  })
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function toDateStr(val) {
  if (!val) return ''
  return String(val).slice(0, 10)
}

function initials(name) {
  return (name ?? '').split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase()
}

function fmtTime(t) {
  if (!t) return ''
  return String(t).slice(0, 5)
}

function fmtMinutes(m) {
  const n = Math.round(Number(m) || 0)
  if (n <= 0) return '—'
  const h = Math.floor(n / 60)
  const r = n % 60
  if (h > 0 && r > 0) return `${h}h ${r}m`
  if (h > 0)           return `${h}h`
  return `${r}m`
}

function getDayName(dateVal) {
  const d = toDateStr(dateVal)
  if (!d) return ''
  return new Date(d + 'T00:00:00').toLocaleDateString('en-PH', { weekday: 'short' })
}

function hasMissingSlots(r) {
  return !r.time_in_am || !r.time_out_am || !r.time_in_pm || !r.time_out_pm
}

/**
 * Returns CSS class for a time cell in the detail table.
 * Priority: WFH-sourced (rose) > biometric (grey) > penned (red) > WFH fallback (red) > leave/gatepass (red) > empty (light)
 */
function tableCellClass(r, field) {
  if (r[field]) return r.wfh_attendance_id ? 'text-rose-600 font-medium' : 'text-slate-700'
  if (r['penned_' + field]) return 'text-red-600 font-semibold'
  const dateStr = toDateStr(r.work_date)
  const wfh = props.wfhByDate[dateStr]
  // WFH fallback covers all four slots when biometric slot is empty
  if (field === 'time_in_am'  && wfh?.time_in)   return 'text-rose-500 font-semibold'
  if (field === 'time_out_am' && wfh?.break_out)  return 'text-rose-500 font-semibold'
  if (field === 'time_in_pm'  && wfh?.break_in)   return 'text-rose-500 font-semibold'
  if (field === 'time_out_pm' && wfh?.time_out)   return 'text-rose-500 font-semibold'
  if (r.is_travel) return 'text-sky-600 font-bold'
  if (r.attendance_status === 'on_leave') return 'text-red-600 font-bold'
  if (r.attendance_status === 'on_official_business') return 'text-red-600 font-bold'
  if (props.gatepassByDate[dateStr]) return 'text-red-500 font-bold'
  return 'text-slate-200'
}

function tableCellText(r, field) {
  if (r[field] || r['penned_' + field]) return fmtTime(r[field] || r['penned_' + field])
  const dateStr = toDateStr(r.work_date)
  const wfh = props.wfhByDate[dateStr]
  if (field === 'time_in_am'  && wfh?.time_in)   return fmtTime(wfh.time_in)
  if (field === 'time_out_am' && wfh?.break_out)  return fmtTime(wfh.break_out)
  if (field === 'time_in_pm'  && wfh?.break_in)   return fmtTime(wfh.break_in)
  if (field === 'time_out_pm' && wfh?.time_out)   return fmtTime(wfh.time_out)
  if (r.is_travel) return 'T'
  const gp = props.gatepassByDate[dateStr]
  if (gp) return gp.label
  const s = r.attendance_status
  if (s === 'on_leave')             return 'L'
  if (s === 'on_official_business') return 'OB'
  return '–'
}

/**
 * Calendar cell: WFH color helper for AM-in/PM-out slots.
 */
function timeColor(record, field, wfhFallback) {
  if (record[field]) return record.wfh_attendance_id ? 'text-rose-500' : '' // WFH-sourced = red, biometric = normal
  if (record['penned_' + field]) return 'text-red-500'
  if (wfhFallback) return 'text-red-500'
  return 'text-red-500'
}

/**
 * Returns the special label (L / OB / OT / UT) for a calendar cell.
 */
function cellSpecialLabel(cell) {
  const s  = cell.record?.attendance_status
  const gp = props.gatepassByDate[cell.date]
  if (gp) return gp.label
  if (cell.record?.is_travel) return 'T'
  if (s === 'on_leave') return 'L'
  if (s === 'on_official_business') return 'OB'
  return null
}

// ── Month navigation ─────────────────────────────────────────────────────────

function goMonth() {
  router.get(route('hr.my-dtr.index'), { month: currentMonth.value }, { preserveState: false })
}

function changeMonth(delta) {
  const [y, m] = currentMonth.value.split('-').map(Number)
  const d = new Date(y, m - 1 + delta, 1)
  currentMonth.value = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0')
  goMonth()
}

const monthLabel = computed(() => {
  const [y, m] = currentMonth.value.split('-')
  return new Date(+y, +m - 1, 1).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })
})

const activeScheduleName = computed(() => {
  const rec = [...props.records].reverse().find(r => r.schedule_name)
  return rec?.schedule_name ?? null
})

const activeScheduleTimes = computed(() => {
  const rec = [...props.records].reverse().find(r => r.scheduled_time_in || r.scheduled_time_out)
  if (!rec) return null
  const i = fmtTime(rec.scheduled_time_in)
  const o = fmtTime(rec.scheduled_time_out)
  if (i && o) return `${i} – ${o}`
  return i || o || null
})

// ── Calendar grid ─────────────────────────────────────────────────────────────

const firstDayOfWeek = computed(() => {
  const [y, m] = currentMonth.value.split('-').map(Number)
  return new Date(y, m - 1, 1).getDay()
})

const daysInMonth = computed(() => {
  const [y, m] = currentMonth.value.split('-').map(Number)
  return new Date(y, m, 0).getDate()
})

const recordMap = computed(() => {
  const map = {}
  props.records.forEach(r => { map[toDateStr(r.work_date)] = r })
  return map
})

const now = new Date()
const todayStr = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`

const calendarCells = computed(() => {
  const cells = []
  const [y, m] = currentMonth.value.split('-').map(Number)
  for (let d = 1; d <= daysInMonth.value; d++) {
    const dateStr = `${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`
    const dow = new Date(y, m - 1, d).getDay()
    const rec = recordMap.value[dateStr] ?? null
    const wfh = props.wfhByDate[dateStr] ?? null
    cells.push({
      day:       d,
      date:      dateStr,
      record:    rec,
      isWorkDay: rec ? rec.day_type !== 'rest_day' : (dow >= 1 && dow <= 5),
      isToday:   dateStr === todayStr,
      schedIn:   rec?.scheduled_time_in  ?? null,
      schedOut:  rec?.scheduled_time_out ?? null,
      // WFH fallback only when biometric/penned slot is empty
      wfhIn:  (!rec?.time_in_am  && !rec?.penned_time_in_am  && wfh?.time_in)  ? wfh.time_in  : null,
      wfhOut: (!rec?.time_out_pm && !rec?.penned_time_out_pm && wfh?.time_out) ? wfh.time_out : null,
    })
  }
  return cells
})

function cellBg(cell) {
  if (cell.wfhByDate && !cell.record) return 'bg-rose-50/60 border-rose-100'
  if (!cell.record) {
    if (!cell.isWorkDay) return 'bg-slate-50 border-slate-100'
    return 'bg-red-50/30 border-red-100'
  }
  if (cell.record.is_advance) return 'bg-amber-50 border-amber-300'
  const s = cell.record.attendance_status
  if (s === 'present')    return 'bg-emerald-50/70 border-emerald-100'
  if (s === 'absent')     return 'bg-red-50 border-red-200'
  if (s === 'half_day')   return 'bg-amber-50 border-amber-200'
  if (s === 'on_leave')   return 'bg-blue-50 border-blue-100'
  if (s === 'holiday')    return 'bg-violet-50 border-violet-100'
  if (s === 'wfh')        return 'bg-rose-50/70 border-rose-100'
  return 'bg-white border-slate-100'
}

function statusBadge(status) {
  return {
    present:              'bg-emerald-100 text-emerald-700',
    absent:               'bg-red-100 text-red-600',
    half_day:             'bg-amber-100 text-amber-700',
    on_leave:             'bg-blue-100 text-blue-700',
    holiday:              'bg-violet-100 text-violet-700',
    on_official_business: 'bg-cyan-100 text-cyan-700',
    wfh:                  'bg-rose-100 text-rose-700',
  }[status] ?? 'bg-slate-100 text-slate-600'
}

function statusLabel(status) {
  return {
    present:              'Present',
    absent:               'Absent',
    half_day:             'Half Day',
    on_leave:             'On Leave',
    holiday:              'Holiday',
    on_official_business: 'OB',
    wfh:                  'WFH',
  }[status] ?? (status ?? '').replace(/_/g, ' ')
}

function fieldLabel(field) {
  return { time_in_am: 'AM In', time_out_am: 'AM Out', time_in_pm: 'PM In', time_out_pm: 'PM Out' }[field] ?? field
}

function gatepassTitle(dateStr) {
  const gp = props.gatepassByDate[dateStr]
  if (!gp) return ''
  let title = gp.type || gp.label
  if (gp.purpose) title += ` — ${gp.purpose}`
  if (gp.consumed_minutes > 0) title += ` (${fmtMinutes(gp.consumed_minutes)} deducted)`
  return title
}

function fmtDateRange(from, to) {
  const fmt = (d) => {
    if (!d) return ''
    const [y, m, day] = String(d).slice(0, 10).split('-').map(Number)
    return new Date(y, m - 1, day).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
  }
  if (!from) return ''
  return from === to ? fmt(from) : `${fmt(from)} – ${fmt(to)}`
}

function leaveTitle(r) {
  const type = r.leave_application?.leave_type?.name ?? 'Leave'
  const range = fmtDateRange(r.leave_application?.date_from, r.leave_application?.date_to)
  return range ? `${type} — ${range}` : type
}

// ── Submit Penned Entries ─────────────────────────────────────────────────────

async function confirmSubmitPenned() {
  const result = await Swal.fire({
    title:             'Submit Penned Entries?',
    text:              'Your penned entries for this month will be locked. You will not be able to make further changes unless HR unlocks them.',
    icon:              'warning',
    showCancelButton:  true,
    confirmButtonColor: '#d97706',
    confirmButtonText: 'Yes, Submit & Lock',
    cancelButtonText:  'Cancel',
  })
  if (!result.isConfirmed) return

  try {
    await axios.post(route('hr.my-dtr.submit-penned'), { month: currentMonth.value })
    router.reload({ only: ['records', 'hasPenned', 'allSubmitted'] })
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message ?? 'Could not submit penned entries.', 'error')
  }
}

// ── Penned entry modal ────────────────────────────────────────────────────────

const editModal = reactive({ open: false, record: null })
const editForm  = useForm({
  penned_time_in_am: '', penned_time_out_am: '',
  penned_time_in_pm: '', penned_time_out_pm: '',
  penned_remarks: '',
  is_travel: false,
})

function openEdit(record) {
  editModal.record = record
  editModal.open   = true
  const p = (val) => fmtTime(val) || ''
  editForm.penned_time_in_am  = p(record.penned_time_in_am)
  editForm.penned_time_out_am = p(record.penned_time_out_am)
  editForm.penned_time_in_pm  = p(record.penned_time_in_pm)
  editForm.penned_time_out_pm = p(record.penned_time_out_pm)
  editForm.penned_remarks     = record.penned_remarks ?? ''
  editForm.is_travel = record.is_travel ?? false
}

function submitEdit() {
  editForm.patch(route('hr.my-dtr.penned', editModal.record.id), {
    onSuccess: () => { editModal.open = false },
  })
}

// ── Inline advance penned entry ─────────────────────────────────────────────

const advancePennedForm = useForm({
  penned_time_in_am:  fmtTime(props.advanceRecord?.penned_time_in_am)  || '',
  penned_time_out_am: fmtTime(props.advanceRecord?.penned_time_out_am) || '',
  penned_time_in_pm:  fmtTime(props.advanceRecord?.penned_time_in_pm)  || '',
  penned_time_out_pm: fmtTime(props.advanceRecord?.penned_time_out_pm) || '',
  penned_remarks:     props.advanceRecord?.penned_remarks || '',
})

function saveAdvancePenned(record) {
  advancePennedForm
    .transform(data => ({
      penned_time_in_am:  data.penned_time_in_am  ? data.penned_time_in_am  + ':00' : null,
      penned_time_out_am: data.penned_time_out_am ? data.penned_time_out_am + ':00' : null,
      penned_time_in_pm:  data.penned_time_in_pm  ? data.penned_time_in_pm  + ':00' : null,
      penned_time_out_pm: data.penned_time_out_pm ? data.penned_time_out_pm + ':00' : null,
      penned_remarks:     data.penned_remarks || null,
    }))
    .patch(route('hr.my-dtr.penned', record.id), { preserveScroll: true })
}
</script>
