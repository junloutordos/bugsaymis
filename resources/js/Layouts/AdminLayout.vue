<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
const props = defineProps({ title: { type: String, default: '' } });
const title = props.title;
import { Head, usePage, router } from "@inertiajs/vue3";
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
  HomeModernIcon,
  UserIcon,
  CursorArrowRippleIcon,
  ClockIcon,
  XMarkIcon,
} from "@heroicons/vue/24/outline";

// (menu insertion removed here; menu items are defined later in `menuItems`)
// --- State ---
const collapsed = ref(false);
const mobileOpen = ref(false);
const showDropdown = ref(false);
const expanded = ref({});

// Close mobile sidebar on Inertia navigation
let removeNavListener;
onMounted(() => {
  removeNavListener = router.on('navigate', () => { mobileOpen.value = false; });
});
onUnmounted(() => {
  if (removeNavListener) removeNavListener();
});

// --- Page + Auth ---
const page = usePage();
const user = page.props.auth?.user || { role: { name: "Guest" }, name: "Guest" };
const roleName = user.role?.name || "Guest";
// Support multiple roles: array of role name strings
const roleNames = user.roleNames?.length ? user.roleNames : (roleName !== "Guest" ? [roleName] : []);


// --- Helpers ---
const toggleDropdown = () => (showDropdown.value = !showDropdown.value);
const logout = () => router.post(route("logout"));
const isActive = (name) => name && route().current(name); // ✅ check via routeName

// Return numeric badge from shared Inertia props based on child routeName
const getBadge = (child) => {
  // Suppress badges only if the user has no roles beyond Staff/Faculty
  if (roleNames.length > 0 && roleNames.every(r => r === 'Staff' || r === 'Faculty')) return 0;
  const rn = child?.routeName || null;
  if (!page || !page.props) return 0;
  switch (rn) {
    case 'consultations.index':
      return page.props.consultationsNotificationCount || 0;
    case 'jobrequests.index':
      return page.props.itJobRequestsNotificationCount || 0;
    case 'vehicle-requests.index':
      return page.props.vehicleRequestsNotificationCount || 0;
    case 'gatepass.index':
      return page.props.gatepassNotificationCount || 0;
    case 'messengerial.index':
      return page.props.messengerialRequestsNotificationCount || 0;
    case 'facility-requests.index':
      return page.props.facilityRequestsNotificationCount || 0;
    case 'service-requests.index':
      return page.props.serviceRequestsNotificationCount || 0;
    case 'work-requests.index':
      return page.props.workRequestsNotificationCount || 0;
    case 'library.borrowings.index':
      return page.props.borrowingsOverdueCount || 0;
    case 'document-tracking.index':
      return page.props.documentTrackingNotificationCount || 0;
    default:
      return 0;
  }
};

// --- Profile Modal State ---
const showProfileModal = ref(false);
const openProfileModal = () => {
  console.log('AdminLayout: openProfileModal called');
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
      },
      {
        label: "Inactive Users",
        routeName: "users.inactive",
        href: route("users.inactive"),
        icon: UserGroupIcon,
        roles: ["Administrator"],
      },
      {
        label: 'DTR Upload',
        routeName: 'data.dtr.upload',
        href: route('data.dtr.upload'),
        icon: DocumentTextIcon,
        roles: ['Administrator'],
      },
      {
        label: "User Roles",
        routeName: "roles.index",
        href: route("roles.index"),
        icon: CursorArrowRippleIcon,
        roles: ["Administrator"],
      },
      {
        label: "Division",
        routeName: "roles.divisions",
        href: route("roles.divisions"),
        icon: CursorArrowRippleIcon,
        roles: ["Administrator"],
      },
      {
        label: "Office/Unit",
        routeName: "offices.index",
        href: route("offices.index"),
        icon: HomeIcon,
        roles: ["Administrator"],
      },
      {
        label: "Buildings",
        routeName: "buildings.index",
        href: route("buildings.index"),
        icon: HomeModernIcon,
        roles: ["Administrator"],
      },
      {
        label: "Rooms",
        routeName: "rooms.index",
        href: route("rooms.index"),
        icon: HomeIcon,
        roles: ["Administrator"],
      },
      {
        label: "Vehicle",
        routeName: "vehicles.index",
        href: route("vehicles.index"),
        icon: ArchiveBoxIcon,
        roles: ["Administrator"],
      },
          {
            label: "Facility",
            routeName: "facilities.index",
            href: route("facilities.index"),
            icon: ArchiveBoxIcon,
            roles: ["Administrator"],
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
      },
      {
        label: "For Approval ITJR",
        routeName: "job-requests.for-approval",
        href: route("job-requests.for-approval"),
        icon: BookOpenIcon,
        roles: ["DivisionChief"],
      },
      {
        label: "OCD Approval ITJR",
        routeName: "job-requests.ocd-approval",
        href: route("job-requests.ocd-approval"),
        icon: BookOpenIcon,
        roles: ["OCD"],
      },
      {
        label: "Equipment Inventory",
        routeName: "ict-equipments.index",
        href: route("ict-equipments.index"),
        icon: QueueListIcon,
        roles: ["Administrator", "OCD"],
      },
      {
        label: "PMS",
        routeName: "ict-pms.index",
        href: route("ict-pms.index"),
        icon: ClockIcon,
        roles: ["Administrator", "OCD"],
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
      roles: ["Administrator", "HR", "Faculty", "Staff", "DivisionChief"],
      children: [
      {
        label: "My PDS",
        routeName: "pds.my",
        href: route("pds.my"),
        icon: ClipboardDocumentListIcon,
        roles: ["Faculty", "Staff", "DivisionChief", "OCD", "Administrator", "HR"],
      },
      {
        label: "Employees",
        routeName: "hr.employees.index",
        href: route('hr.employees.index'),
        icon: UserIcon,
        roles: ["Administrator"],
      },
      {
        label: "Attendance Logs",
        routeName: "hr.attendance.index",
        href: route('hr.attendance.index'),
        icon: ClockIcon,
        roles: ["Administrator", "HR", "Faculty", "Staff"],
      },
      {
        label: "Schedule",
        routeName: "schedules.index",
        href: route("schedules.index"),
        icon: DocumentTextIcon,
        roles: ["Administrator", "HR", "Faculty", "Staff"],
      },
      {
        label: "Date Parameters",
        routeName: "hr.date-parameters.index",
        href: route('hr.date-parameters.index'),
        icon: ClockIcon,
        roles: ["Administrator", "HR"],
      },
      {
        label: "Gate Pass",
        routeName: "gatepass.index",
        href: route('gatepass.index'),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "HR", "Faculty", "Staff", "DivisionChief"],
      },
    ],
  },

  {
    label: "Performance Mngmt",
    icon: UserGroupIcon,
    roles: ["Administrator", "Faculty", "Staff", "HR", "DivisionChief"],
    children: [
      {
        label: "Agency Org Outcome",
        routeName: "outcome.index",
        href: route("outcome.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator","HR"],
      },
      {
        label: "Performance Indicators",
        routeName: "performanceindicator.index",
        href: route("performanceindicator.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator","HR","DivisionChief"],
      },
      {
        label: "Work Distribution Plan",
        routeName: "workdistribution.index",
        href: route("workdistribution.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator","HR","DivisionChief"],
      },
      {
        label: "IPCR",
        routeName: "employee-ipcr.index",
        href: route("employee-ipcr.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "HR", "DivisionChief"],
      },

      {
        label: "My Division",
        routeName: "division-chief-ipcr.index",
        href: route("division-chief-ipcr.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "DivisionChief", "OCD"],
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
    roles: ["Administrator", "Records", "Faculty", "Staff", "Student", "Parent","GSU Head"],
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
    ],
  },
  {
    label: "General Services",
    icon: WrenchScrewdriverIcon,
    roles: ["Administrator", "Faculty", "Staff", "Student", "Parent", "GSU Head", "DivisionChief"],
    children: [
      {
        label: "Vehicle Request",
        routeName: "vehicle-requests.index",
        href: route("vehicle-requests.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff", "Student", "Parent", "GSU Head", "DivisionChief"],
      },
          {
            label: "Facility Request",
            routeName: "facility-requests.index",
            href: route("facility-requests.index"),
            icon: ClipboardDocumentListIcon,
            roles: ["Administrator", "Faculty", "Staff", "Student", "Parent", "GSU Head", "DivisionChief"],
          },
          {
            label: "Request for Services",
            routeName: "service-requests.index",
            href: route('service-requests.index'),
            icon: ClipboardDocumentListIcon,
            roles: ["Administrator", "Faculty", "Staff", "Student", "Parent", "GSU Head", "DivisionChief"],
          },
          {
            label: "Assets",
            routeName: "assets.index",
            href: route('assets.index'),
            icon: ClipboardDocumentListIcon,
            roles: ["Administrator", "GSU Head"],
          },
          {
            label: "Work Request",
            routeName: "work-requests.index",
            href: route('work-requests.index'),
            icon: ClipboardDocumentListIcon,
            roles: ["Administrator", "Faculty", "Staff", "Student", "Parent", "GSU Head", "DivisionChief"],
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
        label: "Purchase Requests",
        routeName: "procurements.index",
        href: route("procurements.index"),
        icon: ClipboardDocumentListIcon,
        roles: ["Administrator", "Faculty", "Staff"],
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
          },
          {
            label: "Consultation Logs",
            routeName: "consultations.log.print",
            href: route("consultations.log.print"),
            icon: DocumentTextIcon,
            roles: ["Administrator", "Nurse", "Clinic","Nurse"],
            target: '_blank',
          },
          {
            label: "Statistics Report",
            routeName: 'health.statistics.report',
            href: "#",
            icon: ChartBarIcon,
            roles: ["Administrator", "Faculty", "Staff", "Student", "Parent", "Clinic","Nurse"],
          },
          {
            label: "Doctor's Schedule",
            routeName: "physician-schedule.index",
            href: route("physician-schedule.index"),
            icon: ClockIcon,
            roles: ["Administrator","Clinic","Nurse"],
          },
        ],
      },
  {
    label: "Guidance Services",
    icon: ChatBubbleLeftRightIcon,
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
      },
      {
        label: "Collections",
        routeName: "library.collections.index",
        href: route('library.collections.index'),
        icon: ArchiveBoxIcon,
        roles: ["Administrator", "Librarian"],
      },
          {
            label: "Collection Categories",
            routeName: "library.collection-categories.index",
            href: route('library.collection-categories.index'),
            icon: BookOpenIcon,
            roles: ["Administrator", "Librarian"],
          },
          {
            label: "Borrowing",
            routeName: "library.borrowings.index",
            href: route('library.borrowings.index'),
            icon: BookOpenIcon,
            roles: ["Administrator", "Librarian"],
          },
          {
            label: "Statistics Report",
            routeName: "library.statistics.report",
            href: '#',
            icon: ChartBarIcon,
            roles: ["Administrator", "Librarian"],
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
      },
          {
            label: "Audit Logs",
            routeName: "reports.audit_logs",
            href: route("reports.audit_logs"),
            icon: DocumentTextIcon,
            roles: ["Administrator"],
          },
    ],
  },
];

// --- Filter Menu by Role ---

const filterMenuByRole = (items, userRoleNames) =>
  items
    .filter((item) => {
      if (item.showForGSUHeadOnly && userRoleNames.includes('GSU Head')) return true;
      return item.roles.some(r => userRoleNames.includes(r));
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

  <div class="min-h-screen flex bg-gray-100">
    <!-- Mobile backdrop -->
    <div
      v-if="mobileOpen"
      @click="mobileOpen = false"
      class="fixed inset-0 bg-black/40 z-30 md:hidden"
    />

    <!-- Sidebar -->
    <aside
      :class="[
        'bg-white shadow-lg transition-all duration-300 z-40 flex-shrink-0 flex flex-col',
        'fixed inset-y-0 left-0 md:static md:inset-auto',
        mobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
        collapsed ? 'w-72 md:w-20' : 'w-72 md:w-64',
      ]"
    >
      <!-- Logo -->
      <div class="h-16 flex items-center border-b px-4">
        <img src="/images/pshslogo.png" alt="PSHS-CRC Logo" class="h-10 shrink-0" />
        <span v-if="!collapsed" class="ml-3 text-xl font-bold text-gray-800 truncate">
          BugsayMIS
        </span>
        <!-- Close button (mobile only) -->
        <button
          @click="mobileOpen = false"
          class="ml-auto p-1 rounded hover:bg-gray-100 md:hidden shrink-0"
          aria-label="Close sidebar"
        >
          <XMarkIcon class="h-5 w-5 text-gray-500" />
        </button>
      </div>

      <!-- Navigation -->
      <nav class="mt-2 px-2 space-y-1 overflow-y-auto flex-1 pb-6">
        <template v-for="item in filteredMenu" :key="item.label">
          <!-- Section label -->
          <div
            v-if="item.type === 'section' && !collapsed"
            class="px-3 pt-4 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider"
          >
            {{ item.label }}
          </div>

          <!-- Single link -->
          <SidebarLink
            v-else-if="!item.children"
            :href="item.href"
            :target="item.target"
            :icon="item.icon"
            :label="item.label"
            :collapsed="collapsed"
            :active="isActive(item.routeName)"
          />

          <!-- With children -->
          <div v-else>
            <button
              @click="toggleExpand(item.label)"
              class="flex items-center w-full px-3 py-2 rounded-md transition
                text-gray-700 hover:bg-gray-100"
              :class="{ 'bg-gray-200 font-semibold': expanded[item.label] }"
            >
              <component v-if="item.icon" :is="item.icon" class="h-5 w-5 mr-2" />
              <span v-if="!collapsed">{{ item.label }}</span>
              <ChevronDownIcon
                class="h-4 w-4 ml-auto transform transition-transform"
                :class="{ 'rotate-180': expanded[item.label] }"
              />
            </button>

            <div v-show="expanded[item.label]" class="ml-6 mt-1 space-y-1">
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
                <button
                  v-else-if="['consultations.log.print','consultations.employee.log.print'].includes(child.routeName)"
                  @click="openConsultationLogModal(child.routeName)"
                  class="flex items-center px-3 py-2 rounded-md transition text-gray-700 hover:bg-gray-100 w-full text-left"
                >
                  <component v-if="child.icon" :is="child.icon" class="h-5 w-5 mr-2" />
                  <span v-if="!collapsed" class="flex items-center w-full">
                    <span>{{ child.label }}</span>
                  </span>
                  <span v-else class="mx-auto"></span>
                </button>
                <button
                  v-else-if="child.routeName === 'library.statistics.report'"
                  @click="openLibraryStatsModal"
                  class="flex items-center px-3 py-2 rounded-md transition text-gray-700 hover:bg-gray-100 w-full text-left"
                >
                  <component v-if="child.icon" :is="child.icon" class="h-5 w-5 mr-2" />
                  <span v-if="!collapsed" class="flex items-center w-full">
                    <span>{{ child.label }}</span>
                  </span>
                  <span v-else class="mx-auto"></span>
                </button>
                <button
                  v-else-if="child.routeName === 'health.statistics.report'"
                  @click="openHealthStatsModal"
                  class="flex items-center px-3 py-2 rounded-md transition text-gray-700 hover:bg-gray-100 w-full text-left"
                >
                  <component v-if="child.icon" :is="child.icon" class="h-5 w-5 mr-2" />
                  <span v-if="!collapsed" class="flex items-center w-full">
                    <span>{{ child.label }}</span>
                  </span>
                  <span v-else class="mx-auto"></span>
                </button>
                <button
                  v-else-if="child.routeName === 'hr.attendance.index'"
                  @click="openAttendanceModal"
                  class="flex items-center px-3 py-2 rounded-md transition text-gray-700 hover:bg-gray-100 w-full text-left"
                >
                  <component v-if="child.icon" :is="child.icon" class="h-5 w-5 mr-2" />
                  <span v-if="!collapsed" class="flex items-center w-full">
                    <span>{{ child.label }}</span>
                  </span>
                  <span v-else class="mx-auto"></span>
                </button>
              </template>
            </div>
          </div>
        </template>
      </nav>
    </aside>

    <!-- Main -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Navbar -->
      <header class="h-16 bg-white shadow flex items-center justify-between px-4 md:px-6">
        <!-- Mobile hamburger -->
        <button
          @click="mobileOpen = !mobileOpen"
          class="p-2 rounded-md hover:bg-gray-100 md:hidden"
          aria-label="Open sidebar"
        >
          <Bars3Icon class="h-6 w-6 text-gray-600" />
        </button>
        <!-- Desktop hamburger -->
        <button
          @click="collapsed = !collapsed"
          class="hidden md:block p-2 rounded-md hover:bg-gray-100"
          aria-label="Toggle sidebar"
        >
          <Bars3Icon class="h-6 w-6 text-gray-600" />
        </button>

        <!-- Profile Dropdown -->
        <div class="relative">
          <button
            @click="toggleDropdown"
            class="flex items-center space-x-2 p-2 rounded-md hover:bg-gray-100"
          >
            <img
              :src="user.profile_picture ? ('/storage/' + user.profile_picture) : 'https://i.pravatar.cc/40'"
              alt="User Avatar"
              class="w-10 h-10 rounded-full border"
            />
            <span class="hidden md:inline text-gray-700">{{ user.name }}</span>
            <ChevronDownIcon class="h-5 w-5 text-gray-600" />
          </button>


          <div
            v-if="showDropdown"
            class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg border z-50"
          >
            <button
              type="button"
              @click.prevent="openProfileModal"
              class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
            >
              Profile
            </button>
            <button
              @click="logout"
              class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
            >
              Logout
            </button>
          </div>
        </div>
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





