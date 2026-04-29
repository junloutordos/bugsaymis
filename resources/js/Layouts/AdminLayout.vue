<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
const props = defineProps({ title: { type: String, default: '' } });
const title = props.title;
import { Head, usePage, router, useForm } from "@inertiajs/vue3";
import SidebarLink from "@/Components/SidebarLink.vue";
import ProfileEditModal from '@/Components/ProfileEditModal.vue';
import {
  HomeIcon,
  UsersIcon,
  DocumentTextIcon,
  Bars3Icon,
  ChevronDownIcon,
  ClipboardDocumentListIcon,
  UserGroupIcon,
  ChartBarIcon,
  ServerStackIcon,
  QueueListIcon,
  ComputerDesktopIcon,
  BookOpenIcon,
  ArchiveBoxIcon,
  WrenchScrewdriverIcon,
  ShoppingCartIcon,
  CreditCardIcon,
  BanknotesIcon,
  CurrencyDollarIcon,
  HeartIcon,
  ChatBubbleLeftRightIcon,
  ChatBubbleOvalLeftEllipsisIcon,
  HomeModernIcon,
  UserIcon,
  CursorArrowRippleIcon,
  ClockIcon,
  XMarkIcon,
  ShieldCheckIcon,
  KeyIcon,
  TableCellsIcon,
  StarIcon,
  DocumentChartBarIcon,
  AcademicCapIcon,
  CalendarDaysIcon,
  SparklesIcon,
  ScaleIcon,
  CpuChipIcon,
  AdjustmentsHorizontalIcon,
  CheckCircleIcon,
  BuildingLibraryIcon,
  IdentificationIcon,
  UserPlusIcon,
  UserCircleIcon,
} from "@heroicons/vue/24/outline";

// (menu insertion removed here; menu items are defined later in `menuItems`)
// --- State ---
const collapsed = ref(false);
const mobileOpen = ref(false);
const showDropdown = ref(false);
const expanded = ref({});
const showVersionModal = ref(false);
const showAddVersionModal = ref(false);
const versionForm = useForm({
  version:    '',
  date:       new Date().toISOString().slice(0, 10),
  remarks:    '',
  is_current: true,
});
function openAddVersionModal() {
  versionForm.reset();
  versionForm.date       = new Date().toISOString().slice(0, 10);
  versionForm.is_current = true;
  showAddVersionModal.value = true;
}
function submitVersion() {
  versionForm.post(route('app-versions.store'), {
    preserveScroll: true,
    onSuccess: () => {
      showAddVersionModal.value = false;
      versionForm.reset();
    },
  });
}

// ─── Chat unread badge (Phase 8) ──────────────────────────────────────────
const chatUnreadCount = ref(0);

async function fetchChatUnread() {
  try {
    const res = await window.axios.get('/api/chat/unread-count');
    chatUnreadCount.value = res.data.unread_count ?? 0;
  } catch {
    // silently ignore — badge just won't show
  }
}

let chatEchoChannel = null;

function setupChatNotifications() {
  if (!window.Echo) return;

  const userId = user?.id;
  if (!userId) return;

  chatEchoChannel = window.Echo.private(`user.${userId}`)
    .listen('.new.message', (e) => {
      // Increment badge if not currently on the Chat page
      if (!route().current('chat.index')) {
        chatUnreadCount.value += 1;
      }

      // Browser notification when tab is not focused
      if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
        const senderName = e.message?.sender_name ?? 'Someone';
        const body = e.message?.body || '📎 Attachment';
        new Notification(`New message from ${senderName}`, {
          body,
          icon: '/favicon.ico',
        });
      }
    });
}

// Reset badge when navigating to Chat
watch(() => route().current('chat.index'), (onChat) => {
  if (onChat) chatUnreadCount.value = 0;
});

// Close mobile sidebar on Inertia navigation
let removeNavListener;
onMounted(() => {
  removeNavListener = router.on('navigate', () => {
    mobileOpen.value = false;
    // Reset badge when navigating to Chat page
    if (route().current('chat.index')) chatUnreadCount.value = 0;
  });

  fetchChatUnread();
  setupChatNotifications();

  // Request browser notification permission (non-blocking)
  if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
  }
});
onUnmounted(() => {
  if (removeNavListener) removeNavListener();
  if (chatEchoChannel) {
    window.Echo?.leave(`user.${user?.id}`);
    chatEchoChannel = null;
  }
});

// --- Page + Auth ---
const page = usePage();
const appVersion = computed(() => page.props.appVersion ?? { current: '1.0.0', history: [] });
const user = page.props.auth?.user || { role: { name: "Guest" }, name: "Guest" };
const roleName = user.role?.name || "Guest";
// Support multiple roles: array of role name strings
const baseRoleNames = user.roleNames?.length ? user.roleNames : (roleName !== "Guest" ? [roleName] : []);
// Inject synthetic 'PMRater' role when user is a committee head or SA coordinator
const roleNames = page.props.isPMRater ? [...baseRoleNames, 'PMRater'] : baseRoleNames;

// Permission set — populated by HandleInertiaRequests via shared Inertia props
// Using a Set for O(1) lookups on every sidebar render
const userPermissions = new Set(user.permissions ?? []);
const hasPerm = (...perms) => perms.some(p => userPermissions.has(p));

// Also expose hasPerm for use in template (e.g. version modal button)
const isAdmin = hasPerm('roles.assign');


// --- Helpers ---
const toggleDropdown = () => (showDropdown.value = !showDropdown.value);
const logout = () => router.post(route("logout"));
const isActive = (name) => name && route().current(name); // ✅ check via routeName

// Safely coerce a raw Inertia prop value to a non-negative integer
const toBadgeInt = (val) => {
  const n = parseInt(val, 10);
  return isNaN(n) || n < 0 ? 0 : n;
};

// Return numeric badge from shared Inertia props based on child routeName
const getBadge = (child) => {
  const rn = child?.routeName || null;
  // Chat badge is available to all roles — check it first
  if (rn === 'chat.index') return toBadgeInt(chatUnreadCount.value);
  if (!page || !page.props) return 0;
  switch (rn) {
    case 'consultations.index':
      return toBadgeInt(page.props.consultationsNotificationCount);
    case 'jobrequests.index':
      return toBadgeInt(page.props.itJobRequestsNotificationCount);
    case 'vehicle-requests.index':
      return toBadgeInt(page.props.vehicleRequestsNotificationCount);
    case 'gatepass.index':
      return toBadgeInt(page.props.gatepassNotificationCount);
    case 'messengerial.index':
      return toBadgeInt(page.props.messengerialRequestsNotificationCount);
    case 'facility-requests.index':
      return toBadgeInt(page.props.facilityRequestsNotificationCount);
    case 'service-requests.index':
      return toBadgeInt(page.props.serviceRequestsNotificationCount);
    case 'work-requests.index':
      return toBadgeInt(page.props.workRequestsNotificationCount);
    case 'library.borrowings.index':
      return toBadgeInt(page.props.borrowingsOverdueCount);
    case 'document-tracking.index':
      return toBadgeInt(page.props.documentTrackingNotificationCount);
    default:
      return 0;
  }
};

// Return aggregate badge count for a group (sum of all children badges), capped at 99
const getGroupBadge = (item) => {
  if (!item.children?.length) return 0;
  const total = item.children.reduce((sum, child) => sum + getBadge(child), 0);
  return Math.min(total, 99);
};

// --- Profile Modal State ---
const showProfileModal = ref(false);
const openProfileModal = () => {
  showDropdown.value = false;
  showProfileModal.value = true;
};
const closeProfileModal = () => {
  showProfileModal.value = false;
};
// Consultation Log modal state
const showConsultationLogModal = ref(false);
const consultationLogStart = ref("");
const consultationLogEnd = ref("");
const consultationLogRouteName = ref(null);
const consultationLogType = ref('student');
const openConsultationLogModal = (routeName = null) => {
  consultationLogRouteName.value = routeName;
  // set default type based on incoming routeName, allow user to change in modal
  consultationLogType.value = (routeName && String(routeName).includes('employee')) ? 'employee' : 'student';
  showConsultationLogModal.value = true;
};
const closeConsultationLogModal = () => {
  showConsultationLogModal.value = false;
  consultationLogStart.value = "";
  consultationLogEnd.value = "";
};
  const generateConsultationLog = () => {
  if (!consultationLogStart.value || !consultationLogEnd.value) {
    alert("Please select both start and end dates.");
    return;
  }
  if (consultationLogStart.value > consultationLogEnd.value) {
    alert("Start date must be before or equal to end date.");
    return;
  }
  const base = consultationLogType.value === 'employee' ? 'consultations.employee.log.print' : 'consultations.log.print';
  const url = route(base) + `?start=${consultationLogStart.value}&end=${consultationLogEnd.value}&type=${consultationLogType.value}`;
  window.open(url, "_blank");
  closeConsultationLogModal();
};

// --- Attendance Logs Modal State ---
const showAttendanceModal = ref(false);
const attendanceStart = ref("");
const attendanceEnd = ref("");
const openAttendanceModal = () => {
  showAttendanceModal.value = true;
};
const closeAttendanceModal = () => {
  showAttendanceModal.value = false;
  attendanceStart.value = "";
  attendanceEnd.value = "";
};
const generateAttendanceReport = () => {
  if (!attendanceStart.value || !attendanceEnd.value) {
    alert('Please select both start and end dates.');
    return;
  }
  if (attendanceStart.value > attendanceEnd.value) {
    alert('Start date must be before or equal to end date.');
    return;
  }
  // Navigate to attendance index with query params
  router.get(route('hr.attendance.index'), { start: attendanceStart.value, end: attendanceEnd.value });
  closeAttendanceModal();
};

// --- Library Statistics Modal State ---
const showLibraryStatsModal = ref(false);
const libraryStatsStart = ref("");
const libraryStatsEnd = ref("");
const openLibraryStatsModal = () => {
  showLibraryStatsModal.value = true;
};
const closeLibraryStatsModal = () => {
  showLibraryStatsModal.value = false;
  libraryStatsStart.value = "";
  libraryStatsEnd.value = "";
};
const generateLibraryStats = () => {
  if (!libraryStatsStart.value || !libraryStatsEnd.value) {
    alert("Please select both start and end dates.");
    return;
  }
  if (libraryStatsStart.value > libraryStatsEnd.value) {
    alert("Start date must be before or equal to end date.");
    return;
  }
  const url = route('library.statistics.report') + `?start=${libraryStatsStart.value}&end=${libraryStatsEnd.value}`;
  window.open(url, "_blank");
  closeLibraryStatsModal();
};

// --- Health Statistics Modal ---
const showHealthStatsModal = ref(false);
const healthStatsStart = ref("");
const healthStatsEnd = ref("");
const openHealthStatsModal = () => {
  showHealthStatsModal.value = true;
};
const closeHealthStatsModal = () => {
  showHealthStatsModal.value = false;
  healthStatsStart.value = "";
  healthStatsEnd.value = "";
};
const generateHealthStats = () => {
  if (!healthStatsStart.value || !healthStatsEnd.value) {
    alert("Please select both start and end dates.");
    return;
  }
  if (healthStatsStart.value > healthStatsEnd.value) {
    alert("Start date must be before or equal to end date.");
    return;
  }
  // Open a report route if available (may be added later).
  try {
    const url = route('health.statistics.report') + `?start=${healthStatsStart.value}&end=${healthStatsEnd.value}&autoprint=1`;
    window.open(url, "_blank");
  } catch (e) {
    // If route helper is not available for this route yet, just close the modal.
    console.warn('health.statistics.report route not defined yet');
  }
  closeHealthStatsModal();
};

// --- Menu Items ---
const menuItems = [
  {
    type: "section",
    label: "Administrator",
    roles: ["Administrator"],
  },
  {
    label: "Dashboard",
    routeName: "dashboard",
    href: route("dashboard"),
    icon: HomeIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent", "DivisionChief", "OCD"],
  },
  {
    label: "Data Management",
    icon: UsersIcon,
    roles: ["Administrator"],
    children: [
      {
        label: "All Users",
        routeName: "users.index",
        href: route("users.index"),
        icon: UserGroupIcon,
        roles: ["Administrator"],
        permissions: ["users.view"],
      },
      {
        label: "Inactive Users",
        routeName: "users.inactive",
        href: route("users.inactive"),
        icon: UserGroupIcon,
        roles: ["Administrator"],
        permissions: ["hr.employees.manage"],
      },
      {
        label: "User Roles",
        routeName: "roles.index",
        href: route("roles.index"),
        icon: CursorArrowRippleIcon,
        roles: ["Administrator"],
        permissions: ["roles.assign"],
      },
      {
        label: "Roles & Permissions",
        routeName: "admin.roles",
        href: "/admin/roles",
        icon: ShieldCheckIcon,
        roles: ["Administrator"],
        permissions: ["roles.assign"],
      },
      {
        label: "Permissions",
        routeName: "admin.permissions",
        href: "/admin/permissions",
        icon: KeyIcon,
        roles: ["Administrator"],
        permissions: ["roles.assign"],
      },
      {
        label: "Assign Roles",
        routeName: "admin.assign-roles",
        href: "/admin/assign-roles",
        icon: UserGroupIcon,
        roles: ["Administrator"],
        permissions: ["roles.assign"],
      },
      {
        label: "Division",
        routeName: "roles.divisions",
        href: route("roles.divisions"),
        icon: CursorArrowRippleIcon,
        roles: ["Administrator"],
        permissions: ["roles.assign"],
      },
      {
        label: "Office/Unit",
        routeName: "offices.index",
        href: route("offices.index"),
        icon: HomeIcon,
        roles: ["Administrator"],
        permissions: ["roles.assign"],
      },
      {
        label: "Org Structure",
        routeName: "hr.org.index",
        href: route("hr.org.index"),
        icon: BuildingLibraryIcon,
        roles: ["Administrator"],
        permissions: ["org.view"],
      },
      {
        label: "Buildings",
        routeName: "buildings.index",
        href: route("buildings.index"),
        icon: HomeModernIcon,
        roles: ["Administrator"],
        permissions: ["roles.assign"],
      },
      {
        label: "Campus",
        routeName: "campuses.index",
        href: route("campuses.index"),
        icon: HomeIcon,
        roles: ["Administrator"],
        permissions: ["roles.assign"],
      },
      {
        label: "Rooms",
        routeName: "rooms.index",
        href: route("rooms.index"),
        icon: HomeIcon,
        roles: ["Administrator"],
        permissions: ["roles.assign"],
      },
      {
        label: "Vehicle",
        routeName: "vehicles.index",
        href: route("vehicles.index"),
        icon: ArchiveBoxIcon,
        roles: ["Administrator"],
        permissions: ["vehicles.manage"],
      },
      {
        label: "Facility",
        routeName: "facilities.index",
        href: route("facilities.index"),
        icon: ArchiveBoxIcon,
        roles: ["Administrator"],
        permissions: ["facilities.manage"],
      },
      
    ],
  },
  {
    label: "MIS",
    icon: ServerStackIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent", "DivisionChief", "OCD"],
    children: [
      {
        label: "IT Job Requests",
        routeName: "jobrequests.index",
        href: route("jobrequests.index"),
        icon: ComputerDesktopIcon,
        roles: ["Administrator", "Faculty", "Staff", "DivisionChief"],
        permissions: ["it.requests.view"],
      },
      {
        label: "For Approval ITJR",
        routeName: "job-requests.for-approval",
        href: route("job-requests.for-approval"),
        icon: BookOpenIcon,
        roles: ["DivisionChief"],
        permissions: ["it.requests.manage"],
      },
      {
        label: "OCD Approval ITJR",
        routeName: "job-requests.ocd-approval",
        href: route("job-requests.ocd-approval"),
        icon: BookOpenIcon,
        roles: ["OCD"],
        permissions: ["it.requests.manage"],
      },
      
      {
        label: "Equipment Inventory",
        routeName: "ict-equipments.index",
        href: route("ict-equipments.index"),
        icon: QueueListIcon,
        roles: ["Administrator", "OCD"],
        permissions: ["it.equipment.view"],
      },
      {
        label: "PMS",
        routeName: "ict-pms.index",
        href: route("ict-pms.index"),
        icon: ClockIcon,
        roles: ["Administrator", "OCD"],
        permissions: ["it.equipment.view"],
      },

    ],
  },
  {
    type: "section",
    label: "Finance & Administration",
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
  },
  
  
    {
      label: "Human Resource",
      icon: UserGroupIcon,
      roles: ["Administrator", "HR", "Faculty", "Staff", "DivisionChief", "OCD"],
      children: [
      {
        label: "My PDS",
        routeName: "pds.my",
        href: route("pds.my"),
        icon: ClipboardDocumentListIcon,
        roles: ["Faculty", "Staff", "DivisionChief", "OCD", "Administrator", "HR"],
        permissions: ["hr.pds.view"],
      },
      {
        label: "Employees",
        routeName: "hr.employees.index",
        href: route('hr.employees.index'),
        icon: UserIcon,
        roles: ["Administrator"],
        permissions: ["hr.employees.manage"],
      },
      {
        label: "201 Files",
        routeName: "hr.twoohone.index",
        href: route('hr.twoohone.index'),
        icon: ArchiveBoxIcon,
        roles: ["Administrator", "HR"],
        permissions: ["hr.employee.view"],
      },
        
      {
        label: "Work Schedules",
        routeName: "hr.schedules.index",
        href: route("hr.schedules.index"),
        icon: ClockIcon,
        roles: ["Administrator", "HR"],
        permissions: ["hr.schedule.manage"],
      },
      {
        label: "My Work Schedule",
        routeName: "hr.schedules.my",
        href: route("hr.schedules.my"),
        icon: ClockIcon,
        roles: ["Administrator", "HR", "Faculty", "Staff", "DivisionChief", "OCD", "Payroll Officer"],
      },
      
      {
        label: "Gate Pass",
        routeName: "gatepass.index",
        href: route('gatepass.index'),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "HR", "Faculty", "Staff", "DivisionChief"],
        permissions: ["hr.gatepass.view"],
      },
      {
        label: "OCD Approval - Gate Pass",
        routeName: "gatepass.ocd-approval",
        href: route('gatepass.ocd-approval'),
        icon: ClipboardDocumentListIcon,
        roles: ["OCD"],
        permissions: ["hr.gatepass.approve"],
      },
      {
        label: "Leave Applications",
        routeName: "hr.leave.index",
        href: route('hr.leave.index'),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "HR", "Faculty", "Staff", "DivisionChief", "Payroll Officer"],
        permissions: ["hr.leave.file"],
      },
      {
        label: "My Leave Credits",
        routeName: "hr.leave-credits.my",
        href: route('hr.leave-credits.my'),
        icon: CreditCardIcon,
        permissions: ["hr.leave.file"],
      },
      {
        label: "Leave Credit Ledger",
        routeName: "hr.reports.leave-credits.ledger",
        href: route('hr.reports.leave-credits.ledger'),
        icon: DocumentChartBarIcon,
        permissions: ["hr.leave.approve"],
      },
      {
        label: "Monthly Accrual Report",
        routeName: "hr.reports.leave-credits.accrual",
        href: route('hr.reports.leave-credits.accrual'),
        icon: DocumentChartBarIcon,
        permissions: ["hr.leave.approve"],
      },
      {
        label: "Leave Utilization Report",
        routeName: "hr.reports.leave-credits.utilization",
        href: route('hr.reports.leave-credits.utilization'),
        icon: ChartBarIcon,
        permissions: ["hr.leave.approve"],
      },
      {
        label: "Initialize Leave Credits",
        routeName: "hr.leave-credits.initialize",
        href: route('hr.leave-credits.initialize'),
        icon: ClipboardDocumentListIcon,
        permissions: ["hr.leave.approve"],
      },
      {
        label: "Adjust Leave Credits",
        routeName: "hr.leave-credits.adjust",
        href: route('hr.leave-credits.adjust'),
        icon: AdjustmentsHorizontalIcon,
        permissions: ["hr.leave.approve"],
      },
      {
        label: "Service Credit Approval",
        routeName: "hr.leave-credits.service-credits",
        href: route('hr.leave-credits.service-credits'),
        icon: CheckCircleIcon,
        permissions: ["hr.leave.approve"],
      },
      {
        label: "My DTR",
        routeName: "hr.my-dtr.index",
        href: route('hr.my-dtr.index'),
        icon: ClockIcon,
        roles: ["Administrator", "HR", "Faculty", "Staff", "DivisionChief", "OCD"],
        permissions: ["dtr.view_own"],
      },
      {
        label: "DTR Records",
        routeName: "hr.dtr.index",
        href: route('hr.dtr.index'),
        icon: TableCellsIcon,
        roles: ["Administrator", "HR", "Payroll Officer"],
        permissions: ["hr.dtr.view"],
      },
      {
        label: "Holidays",
        routeName: "hr.holidays.index",
        href: route('hr.holidays.index'),
        icon: StarIcon,
        roles: ["Administrator", "HR"],
        permissions: ["hr.employees.manage"],
      },
      {
        label: "Biometric Logs",
        routeName: "hr.biometric.index",
        href: route('hr.biometric.index'),
        icon: ClockIcon,
        roles: ["Administrator", "HR"],
        permissions: ["hr.biometric.manage"],
      },
      {
        label: "Work From Home",
        routeName: "hr.wfh.index",
        href: route('hr.wfh.index'),
        icon: HomeModernIcon,
        roles: ["Administrator", "HR", "Faculty", "Staff", "DivisionChief", "OCD"],
        permissions: ["wfh.view"],
      },
      {
        label: "WFH Monitoring",
        routeName: "hr.wfh.monitor.page",
        href: route('hr.wfh.monitor.page'),
        icon: ChartBarIcon,
        roles: ["Administrator", "HR", "DivisionChief", "OCD"],
        permissions: ["wfh.monitor"],
      },
    ],
  },

  {
    label: "SALN",
    icon: DocumentChartBarIcon,
    roles: [],
    permissions: ["saln.create", "saln.view_all", "saln.review"],
    children: [
      {
        label: "My SALN",
        routeName: "saln.index",
        href: route("saln.index"),
        icon: DocumentTextIcon,
        roles: [],
        permissions: ["saln.create"],
      },
      {
        label: "For Review",
        routeName: "saln.review.index",
        href: route("saln.review.index"),
        icon: ClipboardDocumentListIcon,
        roles: [],
        permissions: ["saln.review"],
      },
      {
        label: "All SALN Records",
        routeName: "saln.hr.index",
        href: route("saln.hr.index"),
        icon: TableCellsIcon,
        roles: [],
        permissions: ["saln.view_all"],
      },
      {
        label: "Annual Report",
        routeName: "saln.hr.reports.annual",
        href: route("saln.hr.reports.annual"),
        icon: ChartBarIcon,
        roles: [],
        permissions: ["saln.view_all"],
      },
    ],
  },

  {
    label: "Payroll",
    icon: BanknotesIcon,
    roles: ["Administrator", "HR", "Payroll Officer", "Faculty", "Staff", "DivisionChief"],
    children: [
      {
        label: "Payroll Runs",
        routeName: "payroll.index",
        href: route('payroll.index'),
        icon: CurrencyDollarIcon,
        roles: ["Administrator", "HR", "Payroll Officer", "Faculty", "Staff", "DivisionChief"],
        permissions: ["payroll.view"],
      },
      {
        label: "Allowance Types",
        routeName: "payroll.allowances.index",
        href: route('payroll.allowances.index'),
        icon: CurrencyDollarIcon,
        roles: ["Administrator", "HR", "Payroll Officer"],
        permissions: ["payroll.manage"],
      },
    ],
  },

  {
    label: "Performance Mngmt",
    icon: UserGroupIcon,
    roles: ["Administrator", "Faculty", "Staff", "HR", "DivisionChief", "OCD", "PMT", "PMRater"],
    children: [
      {
        label: "Agency Org Outcome",
        routeName: "outcome.index",
        href: route("outcome.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator","HR"],
        permissions: ["ipcr.view"],
      },
      {
        label: "Performance Indicators",
        routeName: "performanceindicator.index",
        href: route("performanceindicator.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator","HR","DivisionChief"],
        permissions: ["ipcr.view"],
      },
      {
        label: "Work Distribution Plan",
        routeName: "workdistribution.index",
        href: route("workdistribution.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator","HR","DivisionChief"],
        permissions: ["ipcr.view"],
      },
      {
        label: "IPCR",
        routeName: "employee-ipcr.index",
        href: route("employee-ipcr.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "HR", "DivisionChief"],
        permissions: ["ipcr.view"],
      },
      {
        label: "Committees",
        routeName: "pm-committees.index",
        href: route("pm-committees.index"),
        icon: UserGroupIcon,
        roles: ["Administrator", "DivisionChief", "OCD", "HR", "Faculty", "Staff", "PMRater"],
        permissions: ["accomplishments.view"],
      },
      {
        label: "Special Assignments",
        routeName: "pm-special-assignments.index",
        href: route("pm-special-assignments.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "DivisionChief", "OCD", "HR", "Faculty", "Staff", "PMRater"],
        permissions: ["accomplishments.view"],
      },
      {
        label: "My Accomplishments",
        routeName: "my-accomplishments.index",
        href: route("my-accomplishments.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "HR", "DivisionChief", "OCD"],
        permissions: ["accomplishments.view"],
      },
      {
        label: "My Unit",
        routeName: "my-unit-ipcr.index",
        href: route("my-unit-ipcr.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "DivisionChief", "OCD", "Faculty", "Staff"],
        permissions: ["ipcr.view"],
      },
      {
        label: "My Division",
        routeName: "division-chief-ipcr.index",
        href: route("division-chief-ipcr.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "DivisionChief", "OCD", "Faculty", "Staff"],
        permissions: ["ipcr.approve"],
      },
      {
        label: "HR IPCR Review",
        routeName: "hr-ipcr.index",
        href: route("hr-ipcr.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "HR"],
        permissions: ["ipcr.monitor"],
      },
      {
        label: "PMT Review",
        routeName: "pmt-ipcr.index",
        href: route("pmt-ipcr.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "PMT", "OCD"],
        permissions: ["ipcr.approve"],
      },
    ],
  },
  {
    label: "Recruitment",
    icon: UserGroupIcon,
    roles: ["Administrator", "HR", "OCD"],
    permissions: ["recruitment.view"],
    children: [
      {
        label: "Job Items",
        routeName: "recruitment.job-items.index",
        href: route("recruitment.job-items.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "HR"],
        permissions: ["recruitment.manage"],
      },
      {
        label: "Applicant Pool",
        routeName: "recruitment.applicants.index",
        href: route("recruitment.applicants.index"),
        icon: UsersIcon,
        roles: ["Administrator", "HR"],
        permissions: ["recruitment.manage"],
      },
      {
        label: "Applications",
        routeName: "recruitment.applications.index",
        href: route("recruitment.applications.index"),
        icon: QueueListIcon,
        roles: ["Administrator", "HR"],
        permissions: ["recruitment.evaluate"],
      },
      {
        label: "Placements & Onboarding",
        routeName: "recruitment.placements.index",
        href: route("recruitment.placements.index"),
        icon: HomeModernIcon,
        roles: ["Administrator", "HR"],
        permissions: ["recruitment.onboarding"],
      },
      {
        label: "Type Configuration",
        routeName: "recruitment.types.index",
        href: route("recruitment.types.index"),
        icon: ShieldCheckIcon,
        roles: ["Administrator"],
        permissions: ["recruitment.manage"],
      },
      {
        label: "HRMPSB Members",
        routeName: "recruitment.hrmpsb.index",
        href: route("recruitment.hrmpsb.index"),
        icon: UsersIcon,
        roles: ["Administrator"],
        permissions: ["recruitment.manage"],
      },
      {
        label: "Reports",
        routeName: "recruitment.reports.index",
        href: route("recruitment.reports.index"),
        icon: ChartBarIcon,
        roles: ["Administrator", "HR", "Recruitment Officer"],
        permissions: ["recruitment.view"],
      },
      {
        label: "Salary Grade Table",
        routeName: "salary-grades.index",
        href: route("salary-grades.index"),
        icon: TableCellsIcon,
        roles: ["Administrator", "HR", "Recruitment Officer"],
        permissions: ["recruitment.view"],
      },
    ],
  },
  {
    label: "Learning & Devt", 
    icon: BookOpenIcon,
    roles: ["Administrator", "HR"],
    permissions: ["lnd.view"],
    children: [
      {
        label: "Learning Programs",
        routeName: "lnd.programs.index",
        href: route("lnd.programs.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["lnd.view"],
      },
      {
        label: "Training Sessions",
        routeName: "lnd.sessions.index",
        href: route("lnd.sessions.index"),
        icon: QueueListIcon,
        permissions: ["lnd.view"],
      },
      {
        label: "Training Needs (TNA)",
        routeName: "lnd.tna.index",
        href: route("lnd.tna.index"),
        icon: ChartBarIcon,
        permissions: ["lnd.view"],
      },
      {
        label: "IDP",
        routeName: "lnd.idp.index",
        href: route("lnd.idp.index"),
        icon: DocumentTextIcon,
        permissions: ["lnd.view"],
      },
      {
        label: "My Trainings",
        routeName: "lnd.my-trainings",
        href: route("lnd.my-trainings"),
        icon: UserIcon,
        permissions: ["lnd.view"],
      },
      {
        label: "My IDP",
        routeName: "lnd.my-idp",
        href: route("lnd.my-idp"),
        icon: ClockIcon,
        permissions: ["lnd.view"],
      },
    ],
  },
  {
    label: "Rewards & Recog",
    icon: StarIcon,
    permissions: ["rewards.view"],
    children: [
      {
        label: "Dashboard",
        routeName: "rewards.dashboard",
        href: route("rewards.dashboard"),
        icon: ChartBarIcon,
        permissions: ["rewards.view"],
      },
      {
        label: "Nominations",
        routeName: "rewards.nominations.index",
        href: route("rewards.nominations.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["rewards.view"],
      },
      {
        label: "Evaluation Panel",
        routeName: "rewards.evaluations.panel",
        href: route("rewards.evaluations.panel"),
        icon: QueueListIcon,
        permissions: ["rewards.evaluate"],
      },
      {
        label: "Approvals",
        routeName: "rewards.approvals.index",
        href: route("rewards.approvals.index"),
        icon: DocumentTextIcon,
        permissions: ["rewards.approve"],
      },
      {
        label: "Award Types",
        routeName: "rewards.types.index",
        href: route("rewards.types.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["rewards.manage"],
      },
      {
        label: "My Recognitions",
        routeName: "rewards.my-recognitions",
        href: route("rewards.my-recognitions"),
        icon: UserIcon,
        permissions: ["rewards.view"],
      },
      {
        label: "Reports",
        routeName: "rewards.reports",
        href: route("rewards.reports"),
        icon: ChartBarIcon,
        permissions: ["rewards.view"],
      },
    ],
  },
  {
    label: "Planning",
    icon: ChartBarIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
    children: [
      {
        label: "Activity Planner",
            routeName: "activities.index",
            href: route("activities.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
      },
    ],
  },
  {
    label: "Records Management",
    icon: ArchiveBoxIcon,
    roles: ["Administrator", "Records", "Faculty", "Staff", "Student", "Parent", "GSU Head", "DivisionChief", "OCD"],
    children: [
      {
        label: "Docu Track",
        routeName: "document-tracking.index",
        href: route("document-tracking.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Records", "Faculty", "Staff", "Student", "Parent", "GSU Head", "DivisionChief"],
      },
      {
        label: "Messengerial",
        routeName: "messengerial.index",
        href: route("messengerial.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Records", "Faculty", "Staff", "Student", "Parent","GSU Head"],
      },
      {
        label: "For Approval — Messengerial",
        routeName: "messengerial.for-approval",
        href: route("messengerial.for-approval"),
        icon: ClipboardDocumentListIcon,
        roles: ["DivisionChief"],
      },
    ],
  },
  {
    label: "General Services",
    icon: WrenchScrewdriverIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent", "GSU Head", "DivisionChief","OCD"],
    children: [
      {
        label: "Vehicle Request",
        routeName: "vehicle-requests.index",
        href: route("vehicle-requests.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent", "GSU Head", "DivisionChief"],
        permissions: ["vehicles.view"],
      },
      {
        label: "Facility Request",
        routeName: "facility-requests.index",
        href: route("facility-requests.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent", "GSU Head", "DivisionChief"],
        permissions: ["facilities.view"],
      },
      {
        label: "Request for Services",
        routeName: "service-requests.index",
        href: route('service-requests.index'),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent", "GSU Head", "DivisionChief"],
        permissions: ["facilities.view"],
      },
      {
        label: "Assets",
        routeName: "assets.index",
        href: route('assets.index'),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "GSU Head"],
        permissions: ["facilities.manage"],
      },
      {
        label: "Work Request",
        routeName: "work-requests.index",
        href: route('work-requests.index'),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent", "GSU Head", "DivisionChief"],
        permissions: ["facilities.view"],
      },
      {
        label: "DC Approval — Vehicle",
        routeName: "vehicle-requests.dc-approval",
        href: route("vehicle-requests.dc-approval"),
        icon: BookOpenIcon,
        permissions: ["vehicles.dc-approve"],
      },
      {
        label: "DC Approval — Facility",
        routeName: "facility-requests.dc-approval",
        href: route("facility-requests.dc-approval"),
        icon: BookOpenIcon,
        permissions: ["facilities.dc-approve"],
      },
      {
        label: "DC Approval — Work Request",
        routeName: "work-requests.dc-approval",
        href: route("work-requests.dc-approval"),
        icon: BookOpenIcon,
        permissions: ["facilities.dc-approve"],
      },
      {
        label: "DC Approval — Services",
        routeName: "service-requests.dc-approval",
        href: route("service-requests.dc-approval"),
        icon: BookOpenIcon,
        permissions: ["facilities.dc-approve"],
      },
      {
        label: "FAD Approval — Facility",
        routeName: "facility-requests.fad-approval",
        href: route("facility-requests.fad-approval"),
        icon: BookOpenIcon,
        permissions: ["facilities.fad-approve"],
      },
      {
        label: "FAD Approval — Work Request",
        routeName: "work-requests.fad-approval",
        href: route("work-requests.fad-approval"),
        icon: BookOpenIcon,
        permissions: ["facilities.fad-approve"],
      },
      {
        label: "FAD Approval — Services",
        routeName: "service-requests.fad-approval",
        href: route("service-requests.fad-approval"),
        icon: BookOpenIcon,
        permissions: ["facilities.fad-approve"],
      },
      {
        label: "OCD Approval - Vehicle",
        routeName: "vehicle-requests.ocd-approval",
        href: route("vehicle-requests.ocd-approval"),
        icon: BookOpenIcon,
        roles: ["OCD"],
        permissions: ["vehicles.manage"],
      },
    ],
    // Only show this section for GSU Head if that's their only role
    showForGSUHeadOnly: true,
  },
  {
    label: "Procurement",
    icon: ShoppingCartIcon,
    roles: ["Administrator", "Faculty", "Staff", "GSU Head", "DivisionChief"],
    children: [
      {
        label: "PPMP",
        routeName: "ppmp.index",
        href: "/ppmp",
        icon: DocumentTextIcon,
        roles: ["Administrator", "Faculty", "Staff"],
        permissions: ["ppmp.create", "ppmp.view_all"],
      },
      {
        label: "Purchase Requests",
        routeName: "procurements.index",
        href: route("procurements.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff"],
        permissions: ["procurement.view"],
      },
    ],
  },
  {
    label: "Activity Management",
    icon: CalendarDaysIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "HR", "DivisionChief", "OCD"],
    children: [
      {
        label: "Activities",
        routeName: "ams.activities.index",
        href: route("ams.activities.index"),
        icon: ClipboardDocumentListIcon,
        roles: [],
        permissions: ["activities.manage"],
      },
      {
        label: "My Activities",
        routeName: "ams.my-activities.index",
        href: route("ams.my-activities.index"),
        icon: UserCircleIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
      },
    ],
  },
  {
    label: "Supply & Property",
    icon: ShoppingCartIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
    children: [
      {
        label: "PDS",
        routeName: null,
        href: "#",
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
      },
    ],
  },
  {
    label: "Accounting",
    icon: CreditCardIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
    children: [
      {
        label: "PDS",
        routeName: null,
        href: "#",
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
      },
    ],
  },
  {
    label: "Budget",
    icon: BanknotesIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
    children: [
      {
        label: "PDS",
        routeName: null,
        href: "#",
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
      },
    ],
  },
  {
    label: "Cashier",
    icon: CurrencyDollarIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
    children: [
      {
        label: "PDS",
        routeName: null,
        href: "#",
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
      },
    ],
  },
  {
    type: "section",
    label: "Curriculum & Instruction",
    roles: ["Administrator", "Faculty", "Staff", "DivisionChief", "OCD"],
  },

  {
    label: "Faculty Loading",
    icon: AcademicCapIcon,
    roles: [],
    permissions: [
      "faculty_loading.view_own", "faculty_loading.view",
      "faculty_loading.manage", "faculty_loading.school_year",
      "faculty_loading.approve", "faculty_loading.reports",
      "faculty_loading.setup", "faculty_loading.vacancies",
      "faculty_loading.training",
    ],
    children: [
      // ── Personal ────────────────────────────────────────────────────────────
      {
        label: "My Load",
        routeName: "faculty-loading.my-load",
        href: route("faculty-loading.my-load"),
        icon: DocumentTextIcon,
        roles: [],
        permissions: ["faculty_loading.view_own"],
      },
      // ── Overview ────────────────────────────────────────────────────────────
      {
        label: "Faculty Loads",
        routeName: "faculty-loading.index",
        href: route("faculty-loading.index"),
        icon: UserGroupIcon,
        roles: [],
        permissions: ["faculty_loading.view"],
      },
      {
        label: "Faculty List",
        routeName: "faculty-loading.faculty-list",
        href: route("faculty-loading.faculty-list"),
        icon: UsersIcon,
        roles: [],
        permissions: ["faculty_loading.manage"],
      },
      // ── Role Assignments ────────────────────────────────────────────────────
      {
        label: "Supervisory",
        routeName: "faculty-loading.supervisory.index",
        href: route("faculty-loading.supervisory.index"),
        icon: ShieldCheckIcon,
        roles: [],
        permissions: ["faculty_loading.manage"],
      },
      {
        label: "Sections",
        routeName: "faculty-loading.sections.index",
        href: route("faculty-loading.sections.index"),
        icon: TableCellsIcon,
        roles: [],
        permissions: ["faculty_loading.manage"],
      },
      // ── Load Management ─────────────────────────────────────────────────────
      {
        label: "Load Assignments",
        routeName: "faculty-loading.assignments.index",
        href: route("faculty-loading.assignments.index"),
        icon: ClipboardDocumentListIcon,
        roles: [],
        permissions: ["faculty_loading.manage"],
      },
      {
        label: "Research Advisories",
        routeName: "faculty-loading.research-advisories.index",
        href: route("faculty-loading.research-advisories.index"),
        icon: BookOpenIcon,
        roles: [],
        permissions: ["faculty_loading.manage"],
      },
      {
        label: "Committee Assignments",
        routeName: "faculty-loading.committee-assignments.index",
        href: route("faculty-loading.committee-assignments.index"),
        icon: QueueListIcon,
        roles: [],
        permissions: ["faculty_loading.manage"],
      },
      {
        label: "Schedules",
        routeName: "faculty-loading.schedules.index",
        href: route("faculty-loading.schedules.index"),
        icon: CalendarDaysIcon,
        roles: [],
        permissions: ["faculty_loading.manage"],
      },
      // ── AI Tools ────────────────────────────────────────────────────────────
      {
        label: "AI Dashboard",
        routeName: "faculty-loading.ai-dashboard",
        href: route("faculty-loading.ai-dashboard"),
        icon: CpuChipIcon,
        roles: [],
        permissions: ["faculty_loading.manage"],
      },
      {
        label: "AI Schedule Generator",
        routeName: "faculty-loading.auto-schedule.index",
        href: route("faculty-loading.auto-schedule.index"),
        icon: SparklesIcon,
        roles: [],
        permissions: ["faculty_loading.manage"],
      },
      {
        label: "Load Balancing",
        routeName: "faculty-loading.load-balance.index",
        href: route("faculty-loading.load-balance.index"),
        icon: ScaleIcon,
        roles: [],
        permissions: ["faculty_loading.manage"],
      },
      // ── Approval & Finance ──────────────────────────────────────────────────
      {
        label: "Overload Pay",
        routeName: "faculty-loading.overload-computations.index",
        href: route("faculty-loading.overload-computations.index"),
        icon: BanknotesIcon,
        roles: [],
        permissions: ["faculty_loading.approve"],
      },
      {
        label: "Salary Schedules",
        routeName: "faculty-loading.salary-schedules.index",
        href: route("faculty-loading.salary-schedules.index"),
        icon: CurrencyDollarIcon,
        roles: [],
        permissions: ["faculty_loading.approve"],
      },
      {
        label: "Reports",
        routeName: "faculty-loading.reports.loads",
        href: route("faculty-loading.reports.loads"),
        icon: ChartBarIcon,
        roles: [],
        permissions: ["faculty_loading.reports"],
      },
      // ── Catalog Setup ───────────────────────────────────────────────────────
      {
        label: "School Years",
        routeName: "faculty-loading.school-years.index",
        href: route("faculty-loading.school-years.index"),
        icon: ClockIcon,
        roles: [],
        permissions: ["faculty_loading.school_year"],
      },
      {
        label: "Academic Units",
        routeName: "faculty-loading.academic-units.index",
        href: route("faculty-loading.academic-units.index"),
        icon: BuildingLibraryIcon,
        roles: [],
        permissions: ["faculty_loading.setup"],
      },
      {
        label: "Designations",
        routeName: "faculty-loading.designations.index",
        href: route("faculty-loading.designations.index"),
        icon: IdentificationIcon,
        roles: [],
        permissions: ["faculty_loading.setup"],
      },
      {
        label: "Subjects",
        routeName: "faculty-loading.subjects.index",
        href: route("faculty-loading.subjects.index"),
        icon: BookOpenIcon,
        roles: [],
        permissions: ["faculty_loading.subjects"],
      },
      {
        label: "Classrooms",
        routeName: "faculty-loading.classrooms.index",
        href: route("faculty-loading.classrooms.index"),
        icon: TableCellsIcon,
        roles: [],
        permissions: ["faculty_loading.classrooms"],
      },
      // ── Records ─────────────────────────────────────────────────────────────
      {
        label: "Faculty Vacancies",
        routeName: "faculty-loading.vacancies.index",
        href: route("faculty-loading.vacancies.index"),
        icon: UserPlusIcon,
        roles: [],
        permissions: ["faculty_loading.vacancies"],
      },
      {
        label: "Training Records",
        routeName: "faculty-loading.training-records.index",
        href: route("faculty-loading.training-records.index"),
        icon: AcademicCapIcon,
        roles: [],
        permissions: ["faculty_loading.training"],
      },
    ],
  },

  

  {
    type: "section",
    label: "Student Services",
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
  },
  {
    label: "Registrar",
    icon: DocumentTextIcon,
        routeName: "students.index",
        href: route("students.index"),
    roles: ["Administrator", "Registrar"],
    children: [
      {
        label: "Students",
            routeName: "students.index",
            href: route("students.index"),
        icon: UserGroupIcon,
        roles: ["Administrator", "Registrar"],
      },
    ],
  },
  {
    label: "Gate Attendance",
    icon: QueueListIcon,
    roles: ["Administrator"],
    permissions: ["students.attendance.view"],
    children: [
      {
        label: "Kiosk",
        routeName: "student-attendance.kiosk",
        href: route("student-attendance.kiosk"),
        icon: ComputerDesktopIcon,
        roles: ["Administrator"],
        permissions: ["students.attendance.scan"],
      },
      {
        label: "Attendance Logs",
        routeName: "student-attendance.logs.index",
        href: route("student-attendance.logs.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator"],
        permissions: ["students.attendance.view"],
      },
      {
        label: "Parent Contacts",
        routeName: "student-attendance.parents.index",
        href: route("student-attendance.parents.index"),
        icon: UserGroupIcon,
        roles: ["Administrator"],
        permissions: ["students.attendance.view"],
      },
    ],
  },
      {
        label: "Health Services",
        icon: HeartIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent", "Clinic","Nurse"],
        children: [
          {
            label: "Consultations",
            routeName: "consultations.index",
            href: route("consultations.index"),
            icon: ChatBubbleLeftRightIcon,
            roles: ["Administrator", "Faculty", "Staff", "Student", "Parent", "Clinic","Nurse"],
            permissions: ["health.view"],
          },
          {
            label: "Consultation Logs",
            routeName: "consultations.log.print",
            href: route("consultations.log.print"),
            icon: DocumentTextIcon,
            roles: ["Administrator", "Nurse", "Clinic"],
            target: '_blank',
            permissions: ["health.view"],
          },
          {
            label: "Statistics Report",
            routeName: 'health.statistics.report',
            href: "#",
            icon: ChartBarIcon,
            roles: ["Administrator", "Faculty", "Staff", "Student", "Parent", "Clinic","Nurse"],
            permissions: ["health.view"],
          },
          {
            label: "Doctor's Schedule",
            routeName: "physician-schedule.index",
            href: route("physician-schedule.index"),
            icon: ClockIcon,
            roles: ["Administrator","Clinic","Nurse"],
            permissions: ["health.manage"],
          },
        ],
      },
  {
    label: "Guidance Services",
    icon: ChatBubbleLeftRightIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
    children: [
      {
        label: "Dashboard",
        routeName: "guidance.dashboard",
        href: "/guidance/dashboard",
        icon: ChartBarIcon,
        roles: ["Administrator", "Faculty", "Staff"],
        permissions: ["guidance.view"],
      },
      {
        label: "Consultations",
        routeName: "guidance.consultations.index",
        href: route('guidance.consultations.index'),
        icon: ClipboardDocumentListIcon,
        permissions: ["guidance.view"],
      },
      {
        label: "Refer to Guidance",
        routeName: "guidance.refer",
        href: "/guidance/refer",
        icon: ClipboardDocumentListIcon,
        permissions: ["guidance.refer"],
      },
      {
        label: "Session Reports",
        routeName: "guidance.session-reports.index",
        href: "/guidance/session-reports",
        icon: DocumentTextIcon,
        roles: ["Administrator", "Staff"],
        permissions: ["guidance.manage"],
      },
      {
        label: "Transaction Reports",
        routeName: "guidance.reports",
        href: "/guidance/reports",
        icon: DocumentChartBarIcon,
        roles: ["Administrator", "Staff"],
        permissions: ["guidance.view"],
      },
    ],
  },
  {
    label: "Library Services",
    icon: BookOpenIcon,
    roles: ["Administrator", "Librarian"],
    children: [
      {
        label: "Library Attendance",
        routeName: "library.attendance.index",
        href: route('library.attendance.index'),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Librarian"],
        permissions: ["library.manage"],
      },
      {
        label: "Collections",
        routeName: "library.collections.index",
        href: route('library.collections.index'),
        icon: ArchiveBoxIcon,
        roles: ["Administrator", "Librarian"],
        permissions: ["library.manage"],
      },
      {
        label: "Collection Categories",
        routeName: "library.collection-categories.index",
        href: route('library.collection-categories.index'),
        icon: BookOpenIcon,
        roles: ["Administrator", "Librarian"],
        permissions: ["library.manage"],
      },
      {
        label: "Borrowing",
        routeName: "library.borrowings.index",
        href: route('library.borrowings.index'),
        icon: BookOpenIcon,
        roles: ["Administrator", "Librarian"],
        permissions: ["library.view"],
      },
      {
        label: "Statistics Report",
        routeName: "library.statistics.report",
        href: '#',
        icon: ChartBarIcon,
        roles: ["Administrator", "Librarian"],
        permissions: ["library.manage"],
      },
    ],
  },
  {
    label: "Residence Hall",
    icon: HomeModernIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
    children: [
      {
        label: "PDS",
        routeName: null,
        href: "#",
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
      },
    ],
  },
  {
    type: "section",
    label: "Reports",
    roles: ["Administrator", "Faculty", "Staff"],
  },
  {
    label: "Reports",
    icon: DocumentTextIcon,
    roles: ["Administrator", "Faculty", "Staff"],
    children: [
      {
        label: "Monthly Reports",
        routeName: "reports.index",
        href: route("reports.index"),
        icon: ChartBarIcon,
        roles: ["Administrator", "Faculty", "Staff"],
        permissions: ["reports.view"],
      },
      {
        label: "Audit Logs",
        routeName: "reports.audit_logs",
        href: route("reports.audit_logs"),
        icon: DocumentTextIcon,
        roles: ["Administrator"],
        permissions: ["roles.assign"],
      },
    ],
  },
  {
    label: "Chat",
    routeName: "chat.index",
    href: "/chat",
    icon: ChatBubbleOvalLeftEllipsisIcon,
    roles: [],
    permissions: ["chat.access"],
  },
];

// --- Filter Menu by Role ---

// Support numeric role_id checks (CSV stored in user.role_id)
const roleIds = (user.role_id || "")
  .toString()
  .split(",")
  .map((s) => Number(s.trim()))
  .filter((n) => !Number.isNaN(n));


// Show Guidance Services when role_id includes 17 or 1, or by role name
const showGuidanceByRoleId = roleIds.includes(17) || roleIds.includes(1)
  || roleNames.includes('Administrator') || roleNames.includes('Guidance')
  || roleNames.includes('Faculty') || roleNames.includes('Staff');
// Show Health Statistics Report when role_id includes 16 or 1
const showHealthStatisticsByRoleId = roleIds.includes(16) || roleIds.includes(1);

const filterMenuByRole = (items, userRoleNames) =>
  items
    .filter((item) => {
      // Special-case: Guidance (numeric role_id 17 still used for legacy Guidance role)
      if (item.label === "Guidance Services") return showGuidanceByRoleId;
      // Dashboard visible to Guidance role via numeric ID
      if (item.routeName === 'dashboard' && roleIds.includes(17)) return true;
      // Health stats via numeric ID
      if (item.routeName === 'health.statistics.report') return showHealthStatisticsByRoleId;
      // GSU Head override
      if (item.showForGSUHeadOnly && userRoleNames.includes("GSU Head")) return true;

      // ── Permission-first check ───────────────────────────────────────────
      if (item.permissions?.length) {
        return hasPerm(...item.permissions);
      }

      // ── Fallback: role-name check (legacy, backward-compat) ─────────────
      return item.roles?.some((r) => userRoleNames.includes(r)) ?? true;
    })
    .map((item) =>
      item.children
        ? { ...item, children: filterMenuByRole(item.children, userRoleNames) }
        : item
    );

const filteredMenu = computed(() => filterMenuByRole(menuItems, roleNames));


// --- Expand logic ---
const toggleExpand = (label) => (expanded.value[label] = !expanded.value[label]);

filteredMenu.value.forEach((item) => {
  if (item.children?.some((c) => isActive(c.routeName))) {
    expanded.value[item.label] = true;
  }
});
</script>

<template>
  <Head :title="title" />

  <div class="min-h-screen flex bg-slate-50">
    <!-- Mobile backdrop -->
    <div
      v-if="mobileOpen"
      @click="mobileOpen = false"
      class="fixed inset-0 bg-black/50 z-30 md:hidden backdrop-blur-sm"
    />

    <!-- Sidebar -->
    <aside
      :class="[
        'bg-slate-900 transition-all duration-300 z-40 flex-shrink-0 flex flex-col',
        'fixed inset-y-0 left-0 md:static md:inset-auto',
        mobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
        collapsed ? 'w-72 md:w-[68px]' : 'w-72 md:w-60',
      ]"
    >
      <!-- Logo -->
      <div class="h-16 flex items-center gap-3 border-b border-slate-800 px-4 shrink-0">
        <img src="/images/pshslogo.png" alt="PSHS-CRC Logo" class="h-8 w-8 shrink-0 rounded-lg object-contain" />
        <div v-if="!collapsed" class="min-w-0">
          <p class="text-sm font-bold text-white leading-tight truncate">BugsayMIS</p>
          <p class="text-[10px] text-slate-500 truncate">Management Information System</p>
        </div>
        <!-- Close button (mobile only) -->
        <button
          @click="mobileOpen = false"
          class="ml-auto p-1 rounded-lg hover:bg-slate-800 md:hidden shrink-0"
          aria-label="Close sidebar"
        >
          <XMarkIcon class="h-4 w-4 text-slate-400" />
        </button>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 overflow-y-auto px-2 py-3 space-y-0.5 scrollbar-thin">
        <template v-for="item in filteredMenu" :key="item.label">

          <!-- Section label -->
          <div
            v-if="item.type === 'section' && !collapsed"
            class="px-3 pt-5 pb-1.5 text-[10px] font-bold text-slate-600 uppercase tracking-[0.12em]"
          >
            {{ item.label }}
          </div>
          <div v-else-if="item.type === 'section' && collapsed" class="my-2 mx-3 h-px bg-slate-800" />

          <!-- Single link -->
          <SidebarLink
            v-else-if="!item.children"
            :href="item.href"
            :target="item.target"
            :icon="item.icon"
            :label="item.label"
            :collapsed="collapsed"
            :active="isActive(item.routeName)"
            :badge="getBadge(item)"
          />

          <!-- Group with children -->
          <div v-else>
            <button
              @click="toggleExpand(item.label)"
              class="group relative flex w-full items-center rounded-lg px-3 py-2 text-sm font-medium transition-all duration-150 border-l-2 border-transparent"
              :class="expanded[item.label]
                ? 'bg-slate-800 text-slate-200 border-l-2 border-indigo-500'
                : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200'"
            >
              <component
                v-if="item.icon"
                :is="item.icon"
                class="h-4 w-4 shrink-0 transition-colors"
                :class="[
                  collapsed ? 'mx-auto' : 'mr-2.5',
                  expanded[item.label] ? 'text-indigo-400' : 'text-slate-500 group-hover:text-slate-300'
                ]"
              />
              <span v-if="!collapsed" class="flex-1 truncate text-left">{{ item.label }}</span>
              <span
                v-if="!collapsed && !expanded[item.label] && getGroupBadge(item) > 0"
                class="ml-1 shrink-0 inline-flex items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold leading-none bg-amber-400 text-slate-900"
              >{{ getGroupBadge(item) }}</span>
              <span
                v-else-if="collapsed && getGroupBadge(item) > 0"
                class="absolute top-1 right-1 h-1.5 w-1.5 rounded-full bg-amber-400"
              />
              <ChevronDownIcon
                v-if="!collapsed"
                class="h-3.5 w-3.5 ml-1 shrink-0 text-slate-600 transition-transform duration-200"
                :class="{ 'rotate-180 text-indigo-400': expanded[item.label] }"
              />
            </button>

            <div v-show="expanded[item.label]" class="mt-0.5 ml-4 pl-3 border-l border-slate-800 space-y-0.5">
              <template v-for="child in item.children" :key="child.label">
                <SidebarLink
                  v-if="!['consultations.log.print','consultations.employee.log.print','library.statistics.report','health.statistics.report','hr.attendance.index'].includes(child.routeName)"
                  :href="child.href"
                  :target="child.target"
                  :label="child.label"
                  :icon="child.icon"
                  :collapsed="collapsed"
                  :active="isActive(child.routeName)"
                  :badge="getBadge(child)"
                />
                <!-- Modal-trigger child buttons — styled to match SidebarLink -->
                <button
                  v-else-if="['consultations.log.print','consultations.employee.log.print'].includes(child.routeName)"
                  @click="openConsultationLogModal(child.routeName)"
                  class="group flex w-full items-center rounded-lg border-l-2 border-transparent px-3 py-2 text-sm font-medium text-slate-400 transition-all duration-150 hover:bg-slate-800 hover:text-slate-200 pl-[10px]"
                >
                  <component v-if="child.icon" :is="child.icon" class="h-4 w-4 shrink-0 mr-2.5 text-slate-500 group-hover:text-slate-300" />
                  <span v-if="!collapsed" class="truncate">{{ child.label }}</span>
                </button>
                <button
                  v-else-if="child.routeName === 'library.statistics.report'"
                  @click="openLibraryStatsModal"
                  class="group flex w-full items-center rounded-lg border-l-2 border-transparent px-3 py-2 text-sm font-medium text-slate-400 transition-all duration-150 hover:bg-slate-800 hover:text-slate-200 pl-[10px]"
                >
                  <component v-if="child.icon" :is="child.icon" class="h-4 w-4 shrink-0 mr-2.5 text-slate-500 group-hover:text-slate-300" />
                  <span v-if="!collapsed" class="truncate">{{ child.label }}</span>
                </button>
                <button
                  v-else-if="child.routeName === 'health.statistics.report'"
                  @click="openHealthStatsModal"
                  class="group flex w-full items-center rounded-lg border-l-2 border-transparent px-3 py-2 text-sm font-medium text-slate-400 transition-all duration-150 hover:bg-slate-800 hover:text-slate-200 pl-[10px]"
                >
                  <component v-if="child.icon" :is="child.icon" class="h-4 w-4 shrink-0 mr-2.5 text-slate-500 group-hover:text-slate-300" />
                  <span v-if="!collapsed" class="truncate">{{ child.label }}</span>
                </button>
                <button
                  v-else-if="child.routeName === 'hr.attendance.index'"
                  @click="openAttendanceModal"
                  class="group flex w-full items-center rounded-lg border-l-2 border-transparent px-3 py-2 text-sm font-medium text-slate-400 transition-all duration-150 hover:bg-slate-800 hover:text-slate-200 pl-[10px]"
                >
                  <component v-if="child.icon" :is="child.icon" class="h-4 w-4 shrink-0 mr-2.5 text-slate-500 group-hover:text-slate-300" />
                  <span v-if="!collapsed" class="truncate">{{ child.label }}</span>
                </button>
              </template>
            </div>
          </div>
        </template>
      </nav>

      <!-- Version footer -->
      <div class="shrink-0 border-t border-slate-800 px-3 py-3">
        <button
          @click="showVersionModal = true"
          class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-xs text-slate-600 hover:bg-slate-800 hover:text-slate-400 transition-all duration-150"
          :class="collapsed ? 'justify-center' : 'justify-between'"
        >
          <span class="font-mono" :class="collapsed ? 'font-bold text-slate-500' : 'text-slate-500'">
            v{{ appVersion.current }}
          </span>
          <span v-if="!collapsed" class="text-slate-700">Changelog →</span>
        </button>
      </div>
    </aside>

    <!-- Version History Modal -->
    <Teleport to="body">
      <div v-if="showVersionModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="fixed inset-0 bg-black/40" @click="showVersionModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 max-h-[80vh] flex flex-col">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <div>
              <h2 class="text-lg font-bold text-gray-800">Version History</h2>
              <p class="text-xs text-gray-400">Current version: v{{ appVersion.current }}</p>
            </div>
            <button @click="showVersionModal = false" class="text-gray-400 hover:text-gray-600">
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>
          <div class="overflow-y-auto px-6 py-4 space-y-4">
            <div
              v-for="entry in appVersion.history"
              :key="entry.version"
              class="flex gap-4"
            >
              <div class="flex flex-col items-center">
                <div class="w-2.5 h-2.5 rounded-full mt-1.5"
                  :class="entry.version === appVersion.current ? 'bg-blue-500' : 'bg-gray-300'">
                </div>
                <div class="w-px flex-1 bg-gray-200 mt-1"></div>
              </div>
              <div class="pb-4 flex-1">
                <div class="flex items-center gap-2 mb-1">
                  <span class="font-semibold text-sm text-gray-800">v{{ entry.version }}</span>
                  <span v-if="entry.version === appVersion.current"
                    class="text-xs bg-blue-100 text-blue-600 px-2 py-0.5 rounded-full">Latest</span>
                  <span class="text-xs text-gray-400 ml-auto">{{ entry.date }}</span>
                </div>
                <p class="text-sm text-gray-600">{{ entry.remarks }}</p>
              </div>
            </div>
            <p v-if="!appVersion.history.length" class="text-sm text-gray-400 text-center py-4">No history yet.</p>
          </div>
          <!-- Admin footer -->
          <div v-if="isAdmin" class="px-6 py-3 border-t flex justify-end">
            <button
              @click="openAddVersionModal"
              class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded-lg hover:bg-blue-700"
            >
              + Add New Version
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Add Version Modal -->
    <Teleport to="body">
      <div v-if="showAddVersionModal" class="fixed inset-0 z-[60] flex items-center justify-center">
        <div class="fixed inset-0 bg-black/50" @click="showAddVersionModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h2 class="text-lg font-bold text-gray-800">Add New Version</h2>
            <button @click="showAddVersionModal = false" class="text-gray-400 hover:text-gray-600">
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>
          <form @submit.prevent="submitVersion" class="px-6 py-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Version <span class="text-red-500">*</span></label>
              <input
                v-model="versionForm.version"
                type="text"
                placeholder="e.g. 1.2.0"
                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                required
              />
              <p v-if="versionForm.errors.version" class="mt-1 text-xs text-red-500">{{ versionForm.errors.version }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Release Date <span class="text-red-500">*</span></label>
              <input
                v-model="versionForm.date"
                type="date"
                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                required
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Remarks / Changelog <span class="text-red-500">*</span></label>
              <textarea
                v-model="versionForm.remarks"
                rows="4"
                placeholder="Describe what changed in this version…"
                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                required
              ></textarea>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
              <input v-model="versionForm.is_current" type="checkbox" class="rounded border-gray-300 text-blue-600" />
              Set as current version
            </label>
            <div class="flex justify-end gap-3 pt-2">
              <button type="button" @click="showAddVersionModal = false" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 text-sm">Cancel</button>
              <button
                type="submit"
                :disabled="versionForm.processing"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm disabled:opacity-60 disabled:cursor-not-allowed min-w-[80px]"
              >
                <span v-if="versionForm.processing">Saving…</span>
                <span v-else>Save</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Main -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Navbar -->
      <header class="h-14 bg-white border-b border-gray-100 flex items-center justify-between px-4 md:px-6">
        <!-- Left: hamburger + page title -->
        <div class="flex items-center gap-3">
          <!-- Mobile hamburger -->
          <button
            @click="mobileOpen = !mobileOpen"
            class="p-1.5 rounded-md hover:bg-gray-100 md:hidden"
            aria-label="Open sidebar"
          >
            <Bars3Icon class="h-5 w-5 text-gray-500" />
          </button>
          <!-- Desktop hamburger -->
          <button
            @click="collapsed = !collapsed"
            class="hidden md:block p-1.5 rounded-md hover:bg-gray-100"
            aria-label="Toggle sidebar"
          >
            <Bars3Icon class="h-5 w-5 text-gray-500" />
          </button>
          <span v-if="title" class="hidden md:block text-sm font-medium text-gray-700">{{ title }}</span>
        </div>

        <!-- Right: chat + profile -->
        <div class="flex items-center gap-2">

        <!-- Chat Icon -->
        <a
          :href="route('chat.index')"
          class="relative p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
          aria-label="Messenger"
        >
          <ChatBubbleLeftRightIcon class="h-5 w-5 text-gray-500" />
          <span
            v-if="chatUnreadCount > 0"
            class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white"
          >{{ chatUnreadCount > 99 ? '99+' : chatUnreadCount }}</span>
        </a>

        <!-- Profile Dropdown -->
        <div class="relative">
          <button
            @click="toggleDropdown"
            class="flex items-center gap-2.5 px-2 py-1.5 rounded-lg hover:bg-gray-50 transition-colors"
          >
            <img
              :src="user.profile_picture ? ('/storage/' + user.profile_picture) : 'https://i.pravatar.cc/40'"
              alt="User Avatar"
              class="w-7 h-7 rounded-full object-cover ring-2 ring-gray-200"
            />
            <div class="hidden md:block text-left">
              <p class="text-sm font-medium text-gray-800 leading-none">{{ user.name }}</p>
              <p class="text-[11px] text-gray-500 leading-none mt-0.5">{{ roleName }}</p>
            </div>
            <ChevronDownIcon class="h-4 w-4 text-gray-400" />
          </button>

          <div
            v-if="showDropdown"
            class="absolute right-0 mt-1.5 w-44 bg-white rounded-lg shadow-lg border border-gray-100 z-50 py-1"
          >
            <button
              type="button"
              @click.prevent="openProfileModal"
              class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
            >
              Profile
            </button>
            <div class="my-1 border-t border-gray-100"></div>
            <button
              @click="logout"
              class="w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-red-50"
            >
              Logout
            </button>
          </div>
        </div>

        </div><!-- end right group -->
      </header>

      <!-- Page Content -->
      <main class="p-4 md:p-6 flex-1 min-w-0">
        <slot />
      </main>
    </div>
    <!-- Profile Edit Modal -->
    <ProfileEditModal :show="showProfileModal" :user="user" @close="closeProfileModal" />
  <!-- Consultation Log Date Range Modal -->
  <div v-if="showConsultationLogModal" class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="fixed inset-0 bg-black opacity-30 z-40" @click="closeConsultationLogModal"></div>
    <div @click.stop class="bg-white rounded-lg shadow-lg z-50 w-full max-w-md mx-4">
      <div class="px-6 py-4 border-b">
        <h3 class="text-lg font-semibold">Consultation Log Generation</h3>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Report Type</label>
          <div class="mt-2 flex items-center gap-4">
            <label class="flex items-center space-x-2">
              <input type="radio" value="student" v-model="consultationLogType" class="form-radio" />
              <span class="text-sm">Student</span>
            </label>
            <label class="flex items-center space-x-2">
              <input type="radio" value="employee" v-model="consultationLogType" class="form-radio" />
              <span class="text-sm">Employee</span>
            </label>
            <label class="flex items-center space-x-2">
              <input type="radio" value="both" v-model="consultationLogType" class="form-radio" />
              <span class="text-sm">Both</span>
            </label>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Start Date</label>
          <input type="date" v-model="consultationLogStart" class="mt-1 block w-full border rounded px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">End Date</label>
          <input type="date" v-model="consultationLogEnd" class="mt-1 block w-full border rounded px-3 py-2" />
        </div>
      </div>
      <div class="px-6 py-4 border-t flex justify-end gap-2">
        <button @click="closeConsultationLogModal" class="px-4 py-2 rounded bg-gray-200">Cancel</button>
        <button @click="generateConsultationLog" class="px-4 py-2 rounded bg-blue-600 text-white">Generate Graph</button>
      </div>
    </div>
  </div>

  <!-- Library Statistic Report Date Range Modal -->
  <div v-if="showLibraryStatsModal" class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="fixed inset-0 bg-black opacity-30 z-40" @click="closeLibraryStatsModal"></div>
    <div @click.stop class="bg-white rounded-lg shadow-lg z-50 w-full max-w-md mx-4">
      <div class="px-6 py-4 border-b">
        <h3 class="text-lg font-semibold">Library Statistic Report</h3>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Start Date</label>
          <input type="date" v-model="libraryStatsStart" class="mt-1 block w-full border rounded px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">End Date</label>
          <input type="date" v-model="libraryStatsEnd" class="mt-1 block w-full border rounded px-3 py-2" />
        </div>
      </div>
      <div class="px-6 py-4 border-t flex justify-end gap-2">
        <button @click="closeLibraryStatsModal" class="px-4 py-2 rounded bg-gray-200">Cancel</button>
        <button @click="generateLibraryStats" class="px-4 py-2 rounded bg-blue-600 text-white">Generate</button>
      </div>
    </div>
  </div>

  <!-- Health Statistics Date Range Modal -->
  <div v-if="showHealthStatsModal" class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="fixed inset-0 bg-black opacity-30 z-40" @click="closeHealthStatsModal"></div>
    <div @click.stop class="bg-white rounded-lg shadow-lg z-50 w-full max-w-md mx-4">
      <div class="px-6 py-4 border-b">
        <h3 class="text-lg font-semibold">Statistics Report</h3>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Start Date</label>
          <input type="date" v-model="healthStatsStart" class="mt-1 block w-full border rounded px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">End Date</label>
          <input type="date" v-model="healthStatsEnd" class="mt-1 block w-full border rounded px-3 py-2" />
        </div>
      </div>
      <div class="px-6 py-4 border-t flex justify-end gap-2">
        <button @click="closeHealthStatsModal" class="px-4 py-2 rounded bg-gray-200">Cancel</button>
        <button @click="generateHealthStats" class="px-4 py-2 rounded bg-blue-600 text-white">Generate</button>
      </div>
    </div>
  </div>

  <!-- Attendance Logs Date Range Modal -->
  <div v-if="showAttendanceModal" class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="fixed inset-0 bg-black opacity-30 z-40" @click="closeAttendanceModal"></div>
    <div @click.stop class="bg-white rounded-lg shadow-lg z-50 w-full max-w-md mx-4">
      <div class="px-6 py-4 border-b">
        <h3 class="text-lg font-semibold">Attendance Logs</h3>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Start Date</label>
          <input type="date" v-model="attendanceStart" class="mt-1 block w-full border rounded px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">End Date</label>
          <input type="date" v-model="attendanceEnd" class="mt-1 block w-full border rounded px-3 py-2" />
        </div>
      </div>
      <div class="px-6 py-4 border-t flex justify-end gap-2">
        <button @click="closeAttendanceModal" class="px-4 py-2 rounded bg-gray-200">Cancel</button>
        <button @click="generateAttendanceReport" class="px-4 py-2 rounded bg-blue-600 text-white">View</button>
      </div>
    </div>
  </div>

</div>
</template>





