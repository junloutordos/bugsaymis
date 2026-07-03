<script setup>
import { ref, computed, markRaw, onMounted, onUnmounted, watch } from "vue";
import { sessionExpired } from "@/Composables/useSession.js";
const props = defineProps({ title: { type: String, default: '' } });
const title = props.title;
import { Head, usePage, router } from "@inertiajs/vue3";
import SidebarLink from "@/Components/SidebarLink.vue";
import ProfileEditModal from '@/Components/ProfileEditModal.vue';
import AdminTopbar from '@/Components/Layout/AdminTopbar.vue';
import ReportDateRangeModal from '@/Components/Layout/ReportDateRangeModal.vue';
import SessionExpiredOverlay from '@/Components/Layout/SessionExpiredOverlay.vue';
import VersionHistoryModal from '@/Components/Layout/VersionHistoryModal.vue';
import LighthouseIcon from '@/Components/Icons/LighthouseIcon.vue';
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
  InboxIcon,
  QuestionMarkCircleIcon,
  ArrowUpCircleIcon,
  BugAntIcon,

} from "@heroicons/vue/24/outline";
import ErrorReportModal from '@/Components/ErrorReportModal.vue'
import PageSkeleton from '@/Components/PageSkeleton.vue';

// (menu insertion removed here; menu items are defined later in `menuItems`)
// --- State ---
const collapsed = ref(false);
const mobileOpen = ref(false);

const expanded = ref({});
const showVersionModal = ref(false);
const showErrorReportModal = ref(false);

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

// ─── Skeleton loading ─────────────────────────────────────────────────────────
const isNavigating = ref(false);
let navTimer = null;

// Close mobile sidebar on Inertia navigation
let removeNavListener;
let removeStartListener;
let removeFinishListener;
onMounted(() => {
  // Only show skeleton for GET navigations (page changes), not POST/PUT/PATCH/DELETE (saves)
  removeStartListener = router.on('start', (event) => {
    if (event.detail.visit.method === 'get') {
      navTimer = setTimeout(() => { isNavigating.value = true; }, 150);
    }
  });

  // 'finish' fires for EVERY request (success, error, cancel) — definitive reset
  removeFinishListener = router.on('finish', () => {
    clearTimeout(navTimer);
    isNavigating.value = false;
  });

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
  if (removeStartListener) removeStartListener();
  if (removeFinishListener) removeFinishListener();
  if (removeNavListener) removeNavListener();
  clearTimeout(navTimer);
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
const roleNames = [
  ...baseRoleNames,
  ...(page.props.isPMRater ? ['PMRater'] : []),
  ...(page.props.isAUH    ? ['AUH']     : []),
];

// Permission set — populated by HandleInertiaRequests via shared Inertia props
// Using a Set for O(1) lookups on every sidebar render
const userPermissions = new Set(user.permissions ?? []);
const hasPerm = (...perms) => perms.some(p => userPermissions.has(p));

// Also expose hasPerm for use in template (e.g. version modal button)
const isAdmin = hasPerm('roles.assign');


// --- Helpers ---
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
    case 'approvals.inbox':
      return toBadgeInt(page.props.approvalInboxCount);
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

const showProfileModal = ref(false);

// Consultation Log modal state
const showConsultationLogModal = ref(false);
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
};
const generateConsultationLog = ({ start, end, type }) => {
  const base = type === 'employee' ? 'consultations.employee.log.print' : 'consultations.log.print';
  const url = route(base) + `?start=${start}&end=${end}&type=${type}`;
  window.open(url, "_blank");
  closeConsultationLogModal();
};

// --- Attendance Logs Modal State ---
const showAttendanceModal = ref(false);
const openAttendanceModal = () => {
  showAttendanceModal.value = true;
};
const closeAttendanceModal = () => {
  showAttendanceModal.value = false;
};
const generateAttendanceReport = ({ start, end }) => {
  // Navigate to attendance index with query params
  router.get(route('hr.attendance.index'), { start, end });
  closeAttendanceModal();
};

// --- Library Statistics Modal State ---
const showLibraryStatsModal = ref(false);
const openLibraryStatsModal = () => {
  showLibraryStatsModal.value = true;
};
const closeLibraryStatsModal = () => {
  showLibraryStatsModal.value = false;
};
const generateLibraryStats = ({ start, end }) => {
  const url = route('library.statistics.report') + `?start=${start}&end=${end}`;
  window.open(url, "_blank");
  closeLibraryStatsModal();
};

// --- Health Statistics Modal ---
const showHealthStatsModal = ref(false);
const openHealthStatsModal = () => {
  showHealthStatsModal.value = true;
};
const closeHealthStatsModal = () => {
  showHealthStatsModal.value = false;
};
const generateHealthStats = ({ start, end }) => {
  // Open a report route if available (may be added later).
  try {
    const url = route('health.statistics.report') + `?start=${start}&end=${end}&autoprint=1`;
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
  },
  {
    label: "Approvals",
    routeName: "approvals.inbox",
    href: route("approvals.inbox"),
    icon: InboxIcon,
    roles: ["Administrator", "DivisionChief", "OCD", "GSU Head", "FAD Chief", "HR"],
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
        permissions: ["users.view"],
      },
      {
        label: "Inactive Users",
        routeName: "users.inactive",
        href: route("users.inactive"),
        icon: UserGroupIcon,
        permissions: ["hr.employees.manage"],
      },
      {
        label: "User Roles",
        routeName: "roles.index",
        href: route("roles.index"),
        icon: CursorArrowRippleIcon,
        permissions: ["roles.assign"],
      },
      {
        label: "Roles & Permissions",
        routeName: "admin.roles",
        href: "/admin/roles",
        icon: ShieldCheckIcon,
        permissions: ["roles.assign"],
      },
      {
        label: "Permissions",
        routeName: "admin.permissions",
        href: "/admin/permissions",
        icon: KeyIcon,
        permissions: ["roles.assign"],
      },
      {
        label: "Assign Roles",
        routeName: "admin.assign-roles",
        href: "/admin/assign-roles",
        icon: UserGroupIcon,
        permissions: ["roles.assign"],
      },
      {
        label: "Division",
        routeName: "roles.divisions",
        href: route("roles.divisions"),
        icon: CursorArrowRippleIcon,
        permissions: ["roles.assign"],
      },
      {
        label: "Office/Unit",
        routeName: "offices.index",
        href: route("offices.index"),
        icon: HomeIcon,
        permissions: ["roles.assign"],
      },
      {
        label: "Org Structure",
        routeName: "hr.org.index",
        href: route("hr.org.index"),
        icon: BuildingLibraryIcon,
        permissions: ["org.view"],
      },
      {
        label: "Buildings",
        routeName: "buildings.index",
        href: route("buildings.index"),
        icon: HomeModernIcon,
        permissions: ["roles.assign"],
      },
      {
        label: "Campus",
        routeName: "campuses.index",
        href: route("campuses.index"),
        icon: HomeIcon,
        permissions: ["roles.assign"],
      },
      {
        label: "Rooms",
        routeName: "rooms.index",
        href: route("rooms.index"),
        icon: HomeIcon,
        permissions: ["roles.assign"],
      },
      {
        label: "Vehicle",
        routeName: "vehicles.index",
        href: route("vehicles.index"),
        icon: ArchiveBoxIcon,
        permissions: ["vehicles.manage"],
      },
      {
        label: "Facility",
        routeName: "facilities.index",
        href: route("facilities.index"),
        icon: ArchiveBoxIcon,
        permissions: ["facilities.manage"],
      },
      
    ],
  },
  {
    label: "MIS",
    icon: ServerStackIcon,
    roles: ["Administrator", "Faculty", "Staff", "DivisionChief", "OCD"],
    children: [
      {
        label: "MIS Dashboard",
        routeName: "mis.dashboard",
        href: route("mis.dashboard"),
        icon: ChartBarIcon,
        permissions: ["it.requests.manage"],
      },
      {
        label: "CSM Feedback",
        routeName: "csm.dashboard",
        href: route("csm.dashboard"),
        icon: StarIcon,
        permissions: ["it.requests.manage"],
      },
      {
        label: "Error Reports",
        routeName: "error-reports.index",
        href: route("error-reports.index"),
        icon: BugAntIcon,
        permissions: ["error-reports.manage"],
      },
      {
        label: "My Error Reports",
        routeName: "error-reports.my",
        href: route("error-reports.my"),
        icon: BugAntIcon,
      },
      {
        label: "IT Job Requests",
        routeName: "jobrequests.index",
        href: route("jobrequests.index"),
        icon: ComputerDesktopIcon,
        permissions: ["it.requests.view"],
      },
      {
        label: "Equipment Inventory",
        routeName: "ict-equipments.index",
        href: route("ict-equipments.index"),
        icon: QueueListIcon,
        permissions: ["it.equipment.view"],
      },
      {
        label: "Computer Laboratories",
        routeName: "computer-labs.index",
        href: route("computer-labs.index"),
        icon: ComputerDesktopIcon,
        permissions: ["it.equipment.view"],
      },
      {
        label: "Sentinel",
        routeName: "atlas-sentinel.health-dashboard",
        href: route("atlas-sentinel.health-dashboard"),
        icon: ChartBarIcon,
        permissions: ["it.equipment.view"],
      },
      {
        label: "Module Monitor",
        routeName: "atlas.modules.index",
        href: route("atlas.modules.index"),
        icon: CpuChipIcon,
        permissions: ["atlas.modules.view"],
      },
      {
        label: "WatchTower",
        routeName: "atlas.watchtower.index",
        href: route("atlas.watchtower.index"),
        icon: LighthouseIcon,
        permissions: ["atlas.watchtower.view"],
      },
      {
        label: "PMS",
        routeName: "ict-pms.index",
        href: route("ict-pms.index"),
        icon: ClockIcon,
        permissions: ["it.equipment.view"],
      },

    ],
  },
  {
    label: "Administration",
    icon: DocumentTextIcon,
    permissions: ["issuances.manage", "km.view"],
    children: [
      {
        label: "Issuances",
        routeName: "issuances.index",
        href: route("issuances.index"),
        icon: DocumentTextIcon,
        permissions: ["issuances.manage"],
      },
      {
        label: "OED Issuances",
        routeName: "km.index",
        href: route("km.index"),
        icon: BookOpenIcon,
        permissions: ["km.view"],
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
        label: "HR Dashboard",
        routeName: "hr.dashboard",
        href: route("hr.dashboard"),
        icon: ChartBarIcon,
        permissions: ["hr.dashboard.view"],
      },
      {
        label: "My PDS",
        routeName: "pds.my",
        href: route("pds.my"),
        icon: ClipboardDocumentListIcon,
        permissions: ["hr.pds.view"],
      },
      {
        label: "Employees",
        routeName: "hr.employees.index",
        href: route('hr.employees.index'),
        icon: UserIcon,
        permissions: ["hr.employees.manage"],
      },
      {
        label: "201 Files",
        routeName: "hr.twoohone.index",
        href: route('hr.twoohone.index'),
        icon: ArchiveBoxIcon,
        permissions: ["hr.employee.view"],
      },
      {
        label: "Work Schedules",
        routeName: "hr.schedules.index",
        href: route("hr.schedules.index"),
        icon: ClockIcon,
        permissions: ["hr.schedule.manage"],
      },
      {
        label: "My Work Schedule",
        routeName: "hr.schedules.my",
        href: route("hr.schedules.my"),
        icon: ClockIcon,
      },
      
      {
        label: "Gate Pass",
        routeName: "gatepass.index",
        href: route('gatepass.index'),
        icon: ClipboardDocumentListIcon,
        permissions: ["hr.gatepass.view"],
      },
      {
        label: "Leave Applications",
        routeName: "hr.leave.index",
        href: route('hr.leave.index'),
        icon: ClipboardDocumentListIcon,
        permissions: ["hr.leave.file", "hr.leave.view"],
      },
      {
        label: "My Leave Credits",
        routeName: "hr.leave-credits.my",
        href: route('hr.leave-credits.my'),
        icon: CreditCardIcon,
        permissions: ["hr.leave.file", "hr.leave.credits.view"],
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
        permissions: ["dtr.view_own"],
      },
      {
        label: "DTR Records",
        routeName: "hr.dtr.index",
        href: route('hr.dtr.index'),
        icon: TableCellsIcon,
        permissions: ["hr.dtr.view"],
      },
      {
        label: "Holidays",
        routeName: "hr.holidays.index",
        href: route('hr.holidays.index'),
        icon: StarIcon,
        permissions: ["hr.employees.manage"],
      },
      {
        label: "Biometric Logs",
        routeName: "hr.biometric.index",
        href: route('hr.biometric.index'),
        icon: ClockIcon,
        permissions: ["hr.biometric.manage"],
      },
      {
        label: "Work From Home",
        routeName: "hr.wfh.index",
        href: route('hr.wfh.index'),
        icon: HomeModernIcon,
        permissions: ["wfh.view"],
      },
      {
        label: "WFH Monitoring",
        routeName: "hr.wfh.monitor.page",
        href: route('hr.wfh.monitor.page'),
        icon: ChartBarIcon,
        permissions: ["wfh.monitor"],
      },
      {
        label: "Online Time Punches",
        routeName: "hr.online-punch.index",
        href: route('hr.online-punch.index'),
        icon: IdentificationIcon,
        permissions: ["hr.online-punch.record"],
      },
      {
        label: "Online Punch Monitoring",
        routeName: "hr.online-punch.monitor.page",
        href: route('hr.online-punch.monitor.page'),
        icon: ChartBarIcon,
        permissions: ["hr.online-punch.monitor"],
      },
      {
        label: "Face Enrollment",
        routeName: "hr.face-enrollment.self",
        href: route('hr.face-enrollment.self'),
        icon: UserIcon,
        permissions: ["hr.face-enrollment.self"],
      },
      {
        label: "Face Enrollment Review",
        routeName: "hr.face-enrollment.index",
        href: route('hr.face-enrollment.index'),
        icon: ShieldCheckIcon,
        permissions: ["hr.face-enrollment.manage"],
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
        permissions: ["payroll.view"],
      },
      {
        label: "Allowance Types",
        routeName: "payroll.allowances.index",
        href: route('payroll.allowances.index'),
        icon: CurrencyDollarIcon,
        permissions: ["payroll.manage"],
      },
      {
        label: "Payroll Upload",
        routeName: "payroll.cashier.index",
        href: route('payroll.cashier.index'),
        icon: DocumentChartBarIcon,
        permissions: ["payroll.upload", "payroll.view_all"],
      },
      {
        label: "My Payslips",
        routeName: "payroll.my-payslips.index",
        href: route('payroll.my-payslips.index'),
        icon: DocumentTextIcon,
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
        permissions: ["ipcr.view"],
      },
      {
        label: "Performance Indicators",
        routeName: "performanceindicator.index",
        href: route("performanceindicator.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["ipcr.view"],
      },
      {
        label: "Work Distribution Plan",
        routeName: "workdistribution.index",
        href: route("workdistribution.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["ipcr.view"],
      },
      {
        label: "IPCR",
        routeName: "employee-ipcr.index",
        href: route("employee-ipcr.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["ipcr.view"],
      },
      {
        label: "Committees",
        routeName: "pm-committees.index",
        href: route("pm-committees.index"),
        icon: UserGroupIcon,
        permissions: ["accomplishments.view"],
      },
      {
        label: "Special Assignments",
        routeName: "pm-special-assignments.index",
        href: route("pm-special-assignments.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["accomplishments.view"],
      },
      {
        label: "My Accomplishments",
        routeName: "my-accomplishments.index",
        href: route("my-accomplishments.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["accomplishments.view"],
      },
      {
        label: "My Unit",
        routeName: "my-unit-ipcr.index",
        href: route("my-unit-ipcr.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["ipcr.view"],
      },
      {
        label: "My Division",
        routeName: "division-chief-ipcr.index",
        href: route("division-chief-ipcr.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["ipcr.approve"],
      },
      {
        label: "HR IPCR Review",
        routeName: "hr-ipcr.index",
        href: route("hr-ipcr.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["ipcr.monitor"],
      },
      {
        label: "PMT Review",
        routeName: "pmt-ipcr.index",
        href: route("pmt-ipcr.index"),
        icon: ClipboardDocumentListIcon,
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
        permissions: ["recruitment.manage"],
      },
      {
        label: "Applicant Pool",
        routeName: "recruitment.applicants.index",
        href: route("recruitment.applicants.index"),
        icon: UsersIcon,
        permissions: ["recruitment.manage"],
      },
      {
        label: "Applications",
        routeName: "recruitment.applications.index",
        href: route("recruitment.applications.index"),
        icon: QueueListIcon,
        permissions: ["recruitment.evaluate"],
      },
      {
        label: "Placements & Onboarding",
        routeName: "recruitment.placements.index",
        href: route("recruitment.placements.index"),
        icon: HomeModernIcon,
        permissions: ["recruitment.onboarding"],
      },
      {
        label: "Type Configuration",
        routeName: "recruitment.types.index",
        href: route("recruitment.types.index"),
        icon: ShieldCheckIcon,
        permissions: ["recruitment.manage"],
      },
      {
        label: "HRMPSB Members",
        routeName: "recruitment.hrmpsb.index",
        href: route("recruitment.hrmpsb.index"),
        icon: UsersIcon,
        permissions: ["recruitment.manage"],
      },
      {
        label: "Reports",
        routeName: "recruitment.reports.index",
        href: route("recruitment.reports.index"),
        icon: ChartBarIcon,
        permissions: ["recruitment.view"],
      },
      {
        label: "Salary Grade Table",
        routeName: "salary-grades.index",
        href: route("salary-grades.index"),
        icon: TableCellsIcon,
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
    label: "Records Management",
    icon: ArchiveBoxIcon,
    roles: ["Administrator", "Records", "Faculty", "Staff", "GSU Head", "DivisionChief", "OCD"],
    children: [
      {
        label: "Issuances",
        routeName: "issuances.index",
        href: route("issuances.index"),
        icon: DocumentTextIcon,
        permissions: ["issuances.view"],
      },
      {
        label: "Document Tracking",
        routeName: "document-tracking.index",
        href: route("document-tracking.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["documents.view"],
      },
      {
        label: "Messengerial",
        routeName: "messengerial.index",
        href: route("messengerial.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["messengerial.view"],
      },
    ],
  },
  {
    label: "Travel",
    icon: CalendarDaysIcon,
    roles: ["Administrator", "Faculty", "Staff", "DivisionChief", "OCD", "FAD Chief", "Budget Officer", "Bookkeeper", "Accountant", "Cashier"],
    children: [
      {
        label: "Dashboard",
        routeName: "travel.dashboard",
        href: route("travel.dashboard"),
        icon: ChartBarIcon,
        permissions: ["travel.view"],
      },
      {
        label: "Travel Requests",
        routeName: "travel.index",
        href: route("travel.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["travel.view"],
      },
    ],
  },
  {
    label: "General Services",
    icon: WrenchScrewdriverIcon,
    roles: ["Administrator", "Faculty", "Staff", "GSU Head", "DivisionChief", "OCD", "FAD Chief"],
    children: [
      {
        label: "Dashboard",
        routeName: "general-services.dashboard",
        href: route("general-services.dashboard"),
        icon: ChartBarIcon,
        roles: ["Administrator", "GSU Head", "DivisionChief", "OCD", "FAD Chief"],
      },
      {
        label: "Vehicle Request",
        routeName: "vehicle-requests.index",
        href: route("vehicle-requests.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["vehicles.view"],
      },
      {
        label: "Facility Request",
        routeName: "facility-requests.index",
        href: route("facility-requests.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["facilities.view"],
      },
      {
        label: "Request for Services",
        routeName: "service-requests.index",
        href: route('service-requests.index'),
        icon: ClipboardDocumentListIcon,
        permissions: ["facilities.view"],
      },
      {
        label: "Assets",
        routeName: "assets.index",
        href: route('assets.index'),
        icon: ClipboardDocumentListIcon,
        permissions: ["facilities.manage"],
      },
      {
        label: "Work Request",
        routeName: "work-requests.index",
        href: route('work-requests.index'),
        icon: ClipboardDocumentListIcon,
        permissions: ["facilities.view"],
      },
    ],
  },
  {
    label: "Procurement",
    icon: ShoppingCartIcon,
    roles: ["Administrator", "Faculty", "Staff", "GSU Head", "DivisionChief", "OCD", "Budget Officer", "Bookkeeper", "Accountant", "Procurement Officer", "FAD Chief"],
    children: [
      {
        label: "PPMP",
        routeName: "ppmp.index",
        href: "/ppmp",
        icon: DocumentTextIcon,
        permissions: ["ppmp.create", "ppmp.view_all"],
      },
      {
        label: "PPMP Dashboard",
        routeName: "ppmp.dashboard",
        href: "/ppmp/dashboard",
        icon: DocumentTextIcon,
        permissions: ["ppmp.view_all"],
      },
      {
        label: "PS-DBM Catalogue",
        routeName: "ppmp.catalogue.index",
        href: "/ppmp/catalogue",
        icon: DocumentTextIcon,
        permissions: ["ppmp.consolidate"],
      },
      {
        label: "Annual Procurement Plan",
        routeName: "ppmp.app.index",
        href: "/ppmp/app",
        icon: DocumentTextIcon,
        permissions: ["ppmp.consolidate"],
      },
      {
        label: "Purchase Requests",
        routeName: "procurements.index",
        href: route("procurements.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["procurement.pr.view", "procurement.pr.create", "procurement.view"],
      },
      {
        label: "RFQ",
        routeName: "rfq.index",
        href: route("rfq.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["procurement.rfq.view", "procurement.rfq.create", "procurement.rfq.evaluate", "procurement.rfq.award"],
      },
      {
        label: "Purchase Orders",
        routeName: "po.index",
        href: route("po.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["procurement.po.view", "procurement.po.create", "procurement.po.review", "procurement.po.sign"],
      },
      {
        label: "ORS",
        routeName: "ors.index",
        href: route("ors.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["procurement.ors.view", "procurement.ors.create"],
      },
      {
        label: "Disbursement Vouchers",
        routeName: "dv.index",
        href: route("dv.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["procurement.dv.view", "procurement.dv.create"],
      },
    ],
  },
  {
    label: "Supply & Property",
    icon: ArchiveBoxIcon,
    permissions: ["supply.view", "supply.receive", "supply.issue", "supply.manage", "property.view", "property.manage", "property.transfer", "property.reports", "property.dispose", "work-orders.view", "work-orders.manage"],
    children: [
      {
        label: "Item Catalog",
        routeName: "supply.items.index",
        href: route("supply.items.index"),
        icon: ArchiveBoxIcon,
        permissions: ["supply.view", "supply.manage"],
      },
      {
        label: "IAR",
        routeName: "supply.iar.index",
        href: route("supply.iar.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["supply.receive", "supply.manage"],
      },
      {
        label: "RIS",
        routeName: "supply.ris.index",
        href: route("supply.ris.index"),
        icon: QueueListIcon,
        permissions: ["supply.view", "supply.issue", "supply.manage"],
      },
      {
        label: "Stock Card",
        routeName: "supply.stock-card.index",
        href: route("supply.stock-card.index"),
        icon: ArchiveBoxIcon,
        permissions: ["supply.view", "supply.manage"],
      },
      {
        label: "Property Items",
        routeName: "property.items.index",
        href: route("property.items.index"),
        icon: ArchiveBoxIcon,
        permissions: ["property.view", "property.manage"],
      },
      {
        label: "ICS",
        routeName: "property.ics.index",
        href: route("property.ics.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["property.view", "property.manage"],
      },
      {
        label: "PAR",
        routeName: "property.par.index",
        href: route("property.par.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["property.view", "property.manage"],
      },
      {
        label: "Transfers",
        routeName: "property.transfers.index",
        href: route("property.transfers.index"),
        icon: QueueListIcon,
        permissions: ["property.view", "property.transfer", "property.manage"],
      },
      {
        label: "Reports",
        routeName: "property.reports.index",
        href: route("property.reports.index"),
        icon: TableCellsIcon,
        permissions: ["property.reports"],
      },
      {
        label: "Work Orders",
        routeName: "property.work-orders.index",
        href: route("property.work-orders.index"),
        icon: WrenchScrewdriverIcon,
        permissions: ["work-orders.view", "work-orders.manage"],
      },
      {
        label: "Disposal",
        routeName: "property.disposal.index",
        href: route("property.disposal.index"),
        icon: ArchiveBoxIcon,
        permissions: ["property.dispose"],
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
        permissions: ["activities.manage", "activities.view_all"],
      },
      {
        label: "My Activities",
        routeName: "ams.my-activities.index",
        href: route("ams.my-activities.index"),
        icon: UserCircleIcon,
      },
    ],
  },
  // {
  //   label: "Supply & Property",
  //   icon: ShoppingCartIcon,
  //   roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
  //   children: [
  //     {
  //       label: "PDS",
  //       routeName: null,
  //       href: "#",
  //       icon: ClipboardDocumentListIcon,
  //       roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
  //     },
  //   ],
  // },
  // {
  //   label: "Accounting",
  //   icon: CreditCardIcon,
  //   roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
  //   children: [
  //     {
  //       label: "PDS",
  //       routeName: null,
  //       href: "#",
  //       icon: ClipboardDocumentListIcon,
  //       roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
  //     },
  //   ],
  // },
  // {
  //   label: "Budget",
  //   icon: BanknotesIcon,
  //   roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
  //   children: [
  //     {
  //       label: "PDS",
  //       routeName: null,
  //       href: "#",
  //       icon: ClipboardDocumentListIcon,
  //       roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
  //     },
  //   ],
  // },
  // {
  //   label: "Cashier",
  //   icon: CurrencyDollarIcon,
  //   roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
  //   children: [
  //     {
  //       label: "PDS",
  //       routeName: null,
  //       href: "#",
  //       icon: ClipboardDocumentListIcon,
  //       roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
  //     },
  //   ],
  // },
  {
    type: "section",
    label: "Curriculum & Instruction",
    roles: ["Administrator", "Faculty", "Staff", "DivisionChief", "OCD"],
  },

  {
    label: "CID Dashboard",
    routeName: "cid.dashboard.index",
    href: route("cid.dashboard.index"),
    icon: ChartBarIcon,
    permissions: ["cid.dashboard"],
  },

  {
    label: "Class Records",
    icon: TableCellsIcon,
    roles: ["Administrator", "Faculty", "CID Chief", "OCD"],
    children: [
      {
        label: "My Class Records",
        routeName: "class-records.page.index",
        href: route("class-records.page.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "CID Chief", "OCD"],
      },
    ],
  },

  {
    label: "Teacher Attendance",
    icon: ClockIcon,
    roles: ["Administrator", "CID Chief", "AUH"],
    children: [
      {
        label: "Attendance Monitor",
        routeName: "teacher-attendance.index",
        href: route("teacher-attendance.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "CID Chief", "AUH"],
      },
    ],
  },

  {
    label: "Faculty Loading",
    icon: AcademicCapIcon,
    roles: ["Administrator", "Faculty", "CID Chief", "OCD"],
    permissions: [
      "faculty_loading.view_own", "faculty_loading.view",
      "faculty_loading.manage", "faculty_loading.school_year",
      "faculty_loading.approve", "faculty_loading.reports",
      "faculty_loading.setup", "faculty_loading.vacancies",
      "faculty_loading.training",
    ],
    children: [
      // ── Setup ───────────────────────────────────────────────────────────────
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
      // ── Faculty & Sections ──────────────────────────────────────────────────
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
      {
        label: "Faculty List",
        routeName: "faculty-loading.faculty-list",
        href: route("faculty-loading.faculty-list"),
        icon: UsersIcon,
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
      // ── Overview & Records ──────────────────────────────────────────────────
      {
        label: "Faculty Loads",
        routeName: "faculty-loading.index",
        href: route("faculty-loading.index"),
        icon: UserGroupIcon,
        roles: [],
        permissions: ["faculty_loading.view"],
      },
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
      // ── Finance & Reports ───────────────────────────────────────────────────
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
      // ── Personal ────────────────────────────────────────────────────────────
      {
        label: "My Load",
        routeName: "faculty-loading.my-load",
        href: route("faculty-loading.my-load"),
        icon: DocumentTextIcon,
        roles: [],
        permissions: ["faculty_loading.view_own"],
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
    routeName: "registrar.enrollment.index",
    href: route("registrar.enrollment.index"),
    roles: ["Administrator", "Registrar"],
    permissions: ["students.enrollment.view"],
    children: [
      {
        label: "Students",
        routeName: "students.index",
        href: route("students.index"),
        icon: UserGroupIcon,
        roles: ["Administrator", "Registrar"],
      },
      {
        label: "Enrollment",
        routeName: "registrar.enrollment.index",
        href: route("registrar.enrollment.index"),
        icon: AcademicCapIcon,
        permissions: ["students.enrollment.view"],
      },
      {
        label: "Enrollment Periods",
        routeName: "registrar.enrollment-periods.index",
        href: route("registrar.enrollment-periods.index"),
        icon: CalendarDaysIcon,
        permissions: ["students.enrollment.manage"],
      },
      {
        label: "Enrollment Applications",
        routeName: "registrar.enrollment-applications.index",
        href: route("registrar.enrollment-applications.index"),
        icon: TableCellsIcon,
        permissions: ["students.enrollment.manage"],
      },
      {
        label: "Academic Policies",
        routeName: "registrar.academic-policies.index",
        href: route("registrar.academic-policies.index"),
        icon: ScaleIcon,
        permissions: ["students.policies.manage"],
      },
      {
        label: "Transcripts",
        routeName: "registrar.transcript.index",
        href: route("registrar.transcript.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["students.transcript.view"],
      },
      {
        label: "Promotion",
        routeName: "registrar.promotion.index",
        href: route("registrar.promotion.index"),
        icon: ArrowUpCircleIcon,
        permissions: ["students.promotion.process"],
      },
      {
        label: "Analytics",
        routeName: "registrar.analytics.index",
        href: route("registrar.analytics.index"),
        icon: ChartBarIcon,
        permissions: ["students.analytics.view"],
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
        permissions: ["students.attendance.scan"],
      },
      {
        label: "Attendance Logs",
        routeName: "student-attendance.logs.index",
        href: route("student-attendance.logs.index"),
        icon: ClipboardDocumentListIcon,
        permissions: ["students.attendance.view"],
      },
      {
        label: "Parent Contacts",
        routeName: "student-attendance.parents.index",
        href: route("student-attendance.parents.index"),
        icon: UserGroupIcon,
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
            permissions: ["health.view"],
          },
          {
            label: "Consultation Logs",
            routeName: "consultations.log.print",
            href: route("consultations.log.print"),
            icon: DocumentTextIcon,
            target: '_blank',
            permissions: ["health.view"],
          },
          {
            label: "Statistics Report",
            routeName: 'health.statistics.report',
            href: "#",
            icon: ChartBarIcon,
            permissions: ["health.view"],
          },
          {
            label: "Doctor's Schedule",
            routeName: "physician-schedule.index",
            href: route("physician-schedule.index"),
            icon: ClockIcon,
            permissions: ["health.manage"],
          },
          {
            label: "Student Medical Records",
            routeName: "students.health.index",
            href: route('students.health.index'),
            icon: UserIcon,
            permissions: ["students.health.view"],
          },
        ],
      },
  {
    label: "Guidance Services",
    icon: ChatBubbleLeftRightIcon,
    permissions: ["guidance.view", "guidance.refer", "guidance.manage", "guidance.cumulative.view", "guidance.cumulative.manage"],
    children: [
      {
        label: "Dashboard",
        routeName: "guidance.dashboard",
        href: "/guidance/dashboard",
        icon: ChartBarIcon,
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
        permissions: ["guidance.manage"],
      },
      {
        label: "Transaction Reports",
        routeName: "guidance.reports",
        href: "/guidance/reports",
        icon: DocumentChartBarIcon,
        permissions: ["guidance.view"],
      },
      {
        label: "Cumulative Records",
        routeName: "guidance.cumulative.index",
        href: route('guidance.cumulative.index'),
        icon: UserIcon,
        permissions: ["guidance.cumulative.view"],
      },
    ],
  },
  {
    label: "Student Discipline",
    icon: ShieldCheckIcon,
    permissions: ["discipline.file", "discipline.view", "discipline.manage"],
    children: [
      {
        label: "File a Report",
        routeName: "discipline.cases.create",
        href: "/discipline/cases/create",
        icon: ClipboardDocumentListIcon,
        permissions: ["discipline.file"],
      },
      {
        label: "Cases",
        routeName: "discipline.cases.index",
        href: "/discipline/cases",
        icon: DocumentTextIcon,
        permissions: ["discipline.view", "discipline.manage"],
      },
      {
        label: "Offense Catalog",
        routeName: "discipline.offenses.index",
        href: "/discipline/offenses",
        icon: BookOpenIcon,
        permissions: ["discipline.manage"],
      },
      {
        label: "Confiscated Items",
        routeName: "discipline.confiscated.index",
        href: "/discipline/confiscated",
        icon: ArchiveBoxIcon,
        permissions: ["discipline.manage"],
      },
    ],
  },
  {
    label: "Residence Hall",
    icon: HomeModernIcon,
    permissions: [
      "rh.dashboard.view", "rh.applications.view", "rh.applications.evaluate",
      "rh.applications.approve", "rh.interns.view", "rh.interns.manage",
      "rh.rooms.manage", "rh.leave-passes.view", "rh.leave-passes.approve",
      "rh.leave-passes.guard", "rh.housekeeping.manage", "rh.incidents.manage",
      "rh.fees.manage",
    ],
    children: [
      {
        label: "Dashboard",
        routeName: "rh.dashboard",
        href: "/rh/dashboard",
        icon: HomeModernIcon,
        permissions: ["rh.dashboard.view"],
      },
      {
        label: "Applications",
        routeName: "rh.applications.index",
        href: "/rh/applications",
        icon: DocumentTextIcon,
        permissions: ["rh.applications.view", "rh.applications.evaluate", "rh.applications.approve"],
      },
      {
        label: "Dormers",
        routeName: "rh.interns.index",
        href: "/rh/interns",
        icon: UserGroupIcon,
        permissions: ["rh.interns.view", "rh.interns.manage"],
      },
      {
        label: "Leave Passes",
        routeName: "rh.leave-passes.index",
        href: "/rh/leave-passes",
        icon: ClockIcon,
        permissions: ["rh.leave-passes.view", "rh.leave-passes.approve", "rh.leave-passes.guard"],
      },
      {
        label: "Housekeeping",
        routeName: "rh.housekeeping.index",
        href: "/rh/housekeeping",
        icon: ClipboardDocumentListIcon,
        permissions: ["rh.housekeeping.manage"],
      },
      {
        label: "Incidents",
        routeName: "rh.incidents.index",
        href: "/rh/incidents",
        icon: HeartIcon,
        permissions: ["rh.incidents.manage"],
      },
      {
        label: "Fee Ledger",
        routeName: "rh.fees.index",
        href: "/rh/fees",
        icon: BanknotesIcon,
        permissions: ["rh.fees.manage"],
      },
      {
        label: "Rooms",
        routeName: "rh.rooms.index",
        href: "/rh/rooms",
        icon: HomeModernIcon,
        permissions: ["rh.rooms.manage"],
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
        permissions: ["library.manage"],
      },
      {
        label: "Collections",
        routeName: "library.collections.index",
        href: route('library.collections.index'),
        icon: ArchiveBoxIcon,
        permissions: ["library.manage"],
      },
      {
        label: "Collection Categories",
        routeName: "library.collection-categories.index",
        href: route('library.collection-categories.index'),
        icon: BookOpenIcon,
        permissions: ["library.manage"],
      },
      {
        label: "Borrowing",
        routeName: "library.borrowings.index",
        href: route('library.borrowings.index'),
        icon: BookOpenIcon,
        permissions: ["library.view"],
      },
      {
        label: "Statistics Report",
        routeName: "library.statistics.report",
        href: '#',
        icon: ChartBarIcon,
        permissions: ["library.manage"],
      },
    ],
  },
  // {
  //   label: "Residence Hall",
  //   icon: HomeModernIcon,
  //   roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
  //   children: [
  //     {
  //       label: "PDS",
  //       routeName: null,
  //       href: "#",
  //       icon: ClipboardDocumentListIcon,
  //       roles: ["Administrator", "Faculty", "Staff", "Student", "Parent"],
  //     },
  //   ],
  // },
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
        permissions: ["reports.view"],
      },
      {
        label: "Audit Logs",
        routeName: "reports.audit_logs",
        href: route("reports.audit_logs"),
        icon: DocumentTextIcon,
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


const filterMenuByRole = (items, userRoleNames) =>
  items
    .filter((item) => {
      if (item.permissions?.length) {
        return hasPerm(...item.permissions);
      }
      return item.roles?.some((r) => userRoleNames.includes(r)) ?? true;
    })
    .map((item) =>
      item.children
        ? { ...item, icon: item.icon ? markRaw(item.icon) : item.icon, children: filterMenuByRole(item.children, userRoleNames) }
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
        'transition-all duration-300 z-40 flex-shrink-0 flex flex-col bg-white shadow-xl ring-1 ring-slate-200/70 md:border-r md:border-slate-200 md:shadow-sm md:ring-0',
        'fixed inset-y-0 left-0 md:static md:inset-auto',
        mobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
        collapsed ? 'w-72 md:w-[68px]' : 'w-72 md:w-60',
      ]"
    >
      <!-- Logo -->
      <div class="min-h-16 flex items-center gap-3 border-b border-slate-100 px-4 py-3 shrink-0">
        <img v-if="collapsed" src="/images/atlas-mark.png" alt="Atlas" class="h-8 w-8 shrink-0 object-contain mx-auto" />
        <div v-else class="flex-1 flex flex-col items-center text-center min-w-0">
          <img src="/images/atlas-logo-full.png" alt="Atlas" class="h-7 w-auto object-contain" />
          <p class="text-[10px] text-slate-500 leading-tight mt-1">Centralized Management Information System</p>
        </div>
        <!-- Close button (mobile only) -->
        <button
          @click="mobileOpen = false"
          class="ml-auto p-1 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 md:hidden shrink-0"
          aria-label="Close sidebar"
        >
          <XMarkIcon class="h-4 w-4" />
        </button>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 overflow-y-auto px-2 py-3 space-y-0.5 scrollbar-thin">
        <template v-for="item in filteredMenu" :key="item.label">

          <!-- Section label -->
          <div
            v-if="item.type === 'section' && !collapsed"
            class="px-3 pt-5 pb-1.5 text-[10px] font-bold text-slate-400 uppercase tracking-[0.12em]"
          >
            {{ item.label }}
          </div>
          <div v-else-if="item.type === 'section' && collapsed" class="my-2 mx-3 h-px bg-slate-200" />

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
                ? 'bg-blue-50 text-slate-900 border-l-2 border-blue-600'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
              <component
                v-if="item.icon"
                :is="item.icon"
                class="h-4 w-4 shrink-0 transition-colors"
                :class="[
                  collapsed ? 'mx-auto' : 'mr-2.5',
                  expanded[item.label] ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-600'
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
                class="h-3.5 w-3.5 ml-1 shrink-0 text-slate-400 transition-transform duration-200"
                :class="{ 'rotate-180 text-blue-600': expanded[item.label] }"
              />
            </button>

            <div v-show="expanded[item.label]" class="mt-0.5 ml-4 pl-3 border-l border-slate-200 space-y-0.5">
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
                  class="group flex w-full items-center rounded-lg border-l-2 border-transparent px-3 py-2 text-sm font-medium text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900 pl-[10px]"
                >
                  <component v-if="child.icon" :is="child.icon" class="h-4 w-4 shrink-0 mr-2.5 text-slate-400 group-hover:text-slate-600" />
                  <span v-if="!collapsed" class="truncate">{{ child.label }}</span>
                </button>
                <button
                  v-else-if="child.routeName === 'library.statistics.report'"
                  @click="openLibraryStatsModal"
                  class="group flex w-full items-center rounded-lg border-l-2 border-transparent px-3 py-2 text-sm font-medium text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900 pl-[10px]"
                >
                  <component v-if="child.icon" :is="child.icon" class="h-4 w-4 shrink-0 mr-2.5 text-slate-400 group-hover:text-slate-600" />
                  <span v-if="!collapsed" class="truncate">{{ child.label }}</span>
                </button>
                <button
                  v-else-if="child.routeName === 'health.statistics.report'"
                  @click="openHealthStatsModal"
                  class="group flex w-full items-center rounded-lg border-l-2 border-transparent px-3 py-2 text-sm font-medium text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900 pl-[10px]"
                >
                  <component v-if="child.icon" :is="child.icon" class="h-4 w-4 shrink-0 mr-2.5 text-slate-400 group-hover:text-slate-600" />
                  <span v-if="!collapsed" class="truncate">{{ child.label }}</span>
                </button>
                <button
                  v-else-if="child.routeName === 'hr.attendance.index'"
                  @click="openAttendanceModal"
                  class="group flex w-full items-center rounded-lg border-l-2 border-transparent px-3 py-2 text-sm font-medium text-slate-600 transition-all duration-150 hover:bg-slate-100 hover:text-slate-900 pl-[10px]"
                >
                  <component v-if="child.icon" :is="child.icon" class="h-4 w-4 shrink-0 mr-2.5 text-slate-400 group-hover:text-slate-600" />
                  <span v-if="!collapsed" class="truncate">{{ child.label }}</span>
                </button>
              </template>
            </div>
          </div>
        </template>
      </nav>

      <!-- Version footer -->
      <div class="shrink-0 border-t border-slate-100 px-3 py-3">
        <button
          @click="showVersionModal = true"
          class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-xs text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-all duration-150"
          :class="collapsed ? 'justify-center' : 'justify-between'"
        >
          <span class="font-mono" :class="collapsed ? 'font-bold text-slate-500' : 'text-slate-500'">
            v{{ appVersion.current }}
          </span>
          <span v-if="!collapsed" class="text-slate-400">Changelog →</span>
        </button>
      </div>
    </aside>

    <VersionHistoryModal
      :show="showVersionModal"
      :app-version="appVersion"
      :is-admin="isAdmin"
      @close="showVersionModal = false"
    />

    <!-- Main -->
    <div class="flex-1 flex flex-col min-w-0">
      <AdminTopbar
        :title="title"
        :user="user"
        :role-name="roleName"
        :chat-unread-count="chatUnreadCount"
        @toggle-mobile="mobileOpen = !mobileOpen"
        @toggle-collapse="collapsed = !collapsed"
        @report-error="showErrorReportModal = true"
        @open-profile="showProfileModal = true"
      />

      <!-- Page Content -->
      <main class="p-4 md:p-6 flex-1 min-w-0">
        <Transition name="page-fade" mode="out-in">
          <PageSkeleton v-if="isNavigating" key="skeleton" />
          <div v-else key="content"><slot /></div>
        </Transition>
      </main>
    </div>
  <ProfileEditModal :show="showProfileModal" @close="showProfileModal = false" />
  <ErrorReportModal :open="showErrorReportModal" @close="showErrorReportModal = false" />
  <ReportDateRangeModal
    :show="showConsultationLogModal"
    title="Consultation Log Generation"
    action="Generate Graph"
    :with-type="true"
    :type-value="consultationLogType"
    @close="closeConsultationLogModal"
    @submit="generateConsultationLog"
  />
  <ReportDateRangeModal
    :show="showLibraryStatsModal"
    title="Library Statistic Report"
    @close="closeLibraryStatsModal"
    @submit="generateLibraryStats"
  />
  <ReportDateRangeModal
    :show="showHealthStatsModal"
    title="Statistics Report"
    @close="closeHealthStatsModal"
    @submit="generateHealthStats"
  />
  <ReportDateRangeModal
    :show="showAttendanceModal"
    title="Attendance Logs"
    action="View"
    @close="closeAttendanceModal"
    @submit="generateAttendanceReport"
  />

</div>

  <SessionExpiredOverlay :show="sessionExpired" />

</template>

<style scoped>
.page-fade-enter-active,
.page-fade-leave-active {
  transition: opacity 120ms ease;
}
.page-fade-enter-from,
.page-fade-leave-to {
  opacity: 0;
}
</style>
