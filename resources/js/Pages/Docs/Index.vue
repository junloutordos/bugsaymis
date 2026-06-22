<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  MagnifyingGlassIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  QuestionMarkCircleIcon,
} from '@heroicons/vue/24/outline'

const search = ref('')

const sections = [
  {
    id: 'dashboard',
    title: 'Dashboard',
    icon: '🏠',
    articles: [
      {
        q: 'What does the Dashboard show?',
        a: 'The Dashboard gives you a quick overview of your pending tasks, recent activity, and key metrics relevant to your role. Approvers see pending approval counts; HR sees attendance summaries; faculty see their loading status.',
      },
      {
        q: 'Why are some widgets not visible to me?',
        a: 'Dashboard widgets are role-based. Only the information relevant to your assigned role is displayed. Contact your Administrator if you believe a widget is missing.',
      },
    ],
  },
  {
    id: 'approvals',
    title: 'Approvals Inbox',
    icon: '✅',
    articles: [
      {
        q: 'What is the Approvals Inbox?',
        a: 'The Approvals Inbox is a unified page where approvers (Division Chief, FAD Chief, GSU Head, OCD, HR Officer) can see all pending requests that need their action — across all modules — in one place, organized by request type.',
      },
      {
        q: 'Who can see the Approvals Inbox?',
        a: 'Division Chiefs, FAD Chiefs, GSU Heads, OCD users, HR Officers, and Administrators. The sidebar "Approvals" link shows a badge count of your total pending items.',
      },
      {
        q: 'How do I approve or decline a request?',
        a: 'Click the "Review" button on any row to open the request details modal. Review all the information, then click "Approve" (with a confirmation prompt) or "Decline" (which requires a reason). The item is removed from your inbox once acted upon.',
      },
      {
        q: 'Why is a request not appearing in my inbox?',
        a: 'The inbox only shows requests currently waiting for your specific role\'s action. If a request is at a different approval stage (e.g., waiting for OCD when you are a Division Chief), it will not appear until it reaches your stage.',
      },
      {
        q: 'Can I still use the individual module approval pages?',
        a: 'Yes. The individual per-module approval pages are still accessible. The Approvals Inbox is an additional convenience — both work independently.',
      },
    ],
  },
  {
    id: 'hr-leave',
    title: 'HR — Leave Applications',
    icon: '📋',
    articles: [
      {
        q: 'How do I file a leave application?',
        a: 'Go to HR → Leave Applications → File Leave. Select your leave type, choose your dates, provide a reason, and submit. Your application will go through three stages: HR Officer certification → Division Chief recommendation → Campus Director approval.',
      },
      {
        q: 'What are the leave approval stages?',
        a: '1. HR Officer certifies your available leave credits (status: hr_verified). 2. Division Chief recommends approval (status: forwarded). 3. Campus Director gives final approval (status: approved). You will be notified at each stage.',
      },
      {
        q: 'Why was my leave rejected?',
        a: 'Your leave may have been rejected due to insufficient leave credits, scheduling conflicts, or at the discretion of your approver. Check the rejection remarks in your leave application for the specific reason.',
      },
      {
        q: 'What leave types are available?',
        a: 'Leave types follow CSC Form No. 6 (Revised 2020) and include Vacation Leave, Sick Leave, Maternity/Paternity Leave, Special Leave Benefits, and others. Available types depend on your employment category.',
      },
      {
        q: 'Can I cancel a leave application?',
        a: 'Yes, you can cancel a pending or approved leave application before the leave date. Go to your leave application and click "Cancel Application." Credits will be restored if the leave was already approved.',
      },
      {
        q: 'How are leave credits computed?',
        a: 'Leave credits are accrued monthly based on your employment category (Teaching or Non-Teaching). You can view your current balance under HR → My Leave Credits.',
      },
    ],
  },
  {
    id: 'hr-dtr',
    title: 'HR — Daily Time Record (DTR)',
    icon: '🕐',
    articles: [
      {
        q: 'How is my DTR generated?',
        a: 'DTR records are automatically generated daily from your biometric logs. The system pairs your time-in and time-out punches and computes hours worked, late minutes, undertime, and overtime based on your assigned schedule.',
      },
      {
        q: 'Why is my DTR showing "Absent" when I was present?',
        a: 'This usually means your biometric logs were not imported or resolved yet. Check with your HR officer to ensure your badge ID is correctly linked to your account and that the biometric device logs were imported.',
      },
      {
        q: 'What is a "penned entry"?',
        a: 'A penned entry is a manually entered time that fills an empty biometric slot (e.g., if you forgot to tap in). Penned entries are submitted by the employee and reviewed by HR. They appear in amber in the DTR table.',
      },
      {
        q: 'How do I submit a penned entry?',
        a: 'Go to My DTR, find the date with the missing punch, and click the edit icon. Enter the correct time in the empty slot and provide a reason. Submit for HR review.',
      },
      {
        q: 'What does "Late" mean in my DTR?',
        a: 'Late minutes are computed when your actual time-in exceeds your scheduled time-in plus the grace period (default: 15 minutes). For example, if your schedule is 08:00 and you arrive at 08:20, you have 5 minutes late (20 - 15 grace = 5).',
      },
      {
        q: 'What is undertime?',
        a: 'Undertime is when you leave before your scheduled end time. It is computed as the difference between your actual time-out and your scheduled time-out.',
      },
      {
        q: 'Does the system support graveyard/night shifts?',
        a: 'Yes. If your schedule has a time-out earlier than your time-in (e.g., 22:00 in, 06:00 out), the system automatically detects it as an overnight shift and correctly pairs your punches across calendar days.',
      },
      {
        q: 'Why is my DTR locked?',
        a: 'DTR records are locked after payroll finalization. Locked records cannot be edited. Contact your HR officer if a correction is needed on a locked record.',
      },
    ],
  },
  {
    id: 'hr-schedule',
    title: 'HR — Work Schedules',
    icon: '📅',
    articles: [
      {
        q: 'How do I set my work schedule?',
        a: 'Go to HR → My Work Schedule. You can select a preset schedule or customize your daily time-in and time-out per day of the week. Submit the schedule for HR approval. It takes effect on the effective date you specify.',
      },
      {
        q: 'How do I set a graveyard/night shift schedule?',
        a: 'In the schedule form, simply set your time-in to the evening (e.g., 22:00) and time-out to the next morning (e.g., 06:00). The system automatically detects that the shift crosses midnight and handles DTR generation accordingly.',
      },
      {
        q: 'Can HR assign a schedule for me?',
        a: 'Yes. HR can bulk-assign schedule presets to employees. When HR assigns a schedule, it takes effect on the specified date and your existing unlocked DTR records are automatically recomputed.',
      },
      {
        q: 'What is a schedule preset?',
        a: 'A schedule preset is a reusable schedule template created by HR (e.g., "Regular Office Hours 08:00–17:00"). You can load a preset when submitting your schedule to pre-fill the times.',
      },
    ],
  },
  {
    id: 'hr-gatepass',
    title: 'HR — Gate Pass',
    icon: '🚪',
    articles: [
      {
        q: 'How do I request a gate pass?',
        a: 'Go to HR → Gate Pass → New Gate Pass. Fill in the purpose, destination, and date. Your request goes to your Division Chief for approval, then to OCD for final approval.',
      },
      {
        q: 'What are the gate pass types?',
        a: 'Gate passes can be for Official Business (OB), Undertime (UT), or Overtime (OT). The type affects how the time outside is recorded in your DTR.',
      },
      {
        q: 'How does a gate pass affect my DTR?',
        a: 'Once your actual time out and time in are recorded on an approved gate pass, the system deducts the time spent outside from your hours worked. Undertime-type gate passes also add to your undertime minutes.',
      },
    ],
  },
  {
    id: 'payroll',
    title: 'Payroll',
    icon: '💰',
    articles: [
      {
        q: 'How is payroll computed?',
        a: 'Payroll is computed based on your salary grade, step, and DTR records for the pay period. Deductions for late, undertime, and absences are applied automatically. Allowances and other benefits are added based on your employment category.',
      },
      {
        q: 'Where can I view my payslip?',
        a: 'Go to Payroll → My Payslips. You can view and print your payslip for any completed payroll run.',
      },
      {
        q: 'Why is my salary different this month?',
        a: 'Salary differences are usually due to late/undertime deductions, absences, or changes in your salary grade/step. Check your DTR for the pay period and compare with your payslip deductions.',
      },
      {
        q: 'What is the salary schedule based on?',
        a: 'Salaries follow the Salary Standardization Law (SSL) schedule. Your monthly rate is determined by your salary grade and step as recorded in the system.',
      },
    ],
  },
  {
    id: 'ipcr',
    title: 'IPCR / Performance Management',
    icon: '📊',
    articles: [
      {
        q: 'What is IPCR?',
        a: 'IPCR (Individual Performance Commitment and Review) is the performance management system for PSHS employees. It tracks your performance targets, actual accomplishments, and ratings for each semester.',
      },
      {
        q: 'How do I submit my IPCR?',
        a: 'Go to IPCR → My IPCR. Fill in your performance targets at the start of the rating period, then update your actual accomplishments before the deadline. Submit for your supervisor\'s review.',
      },
      {
        q: 'Who rates my IPCR?',
        a: 'Your immediate supervisor rates your IPCR. Division Chiefs rate their staff; the Campus Director rates Division Chiefs.',
      },
    ],
  },
  {
    id: 'faculty-loading',
    title: 'Faculty Loading',
    icon: '🎓',
    articles: [
      {
        q: 'What is Faculty Loading?',
        a: 'Faculty Loading manages the assignment of teaching loads, committee work, and designations to faculty members for each academic term.',
      },
      {
        q: 'How is overload computed?',
        a: 'Overload is computed based on the total units assigned beyond the standard teaching load. The system automatically flags overload and routes it for approval.',
      },
      {
        q: 'Can the system auto-assign schedules?',
        a: 'Yes. The AI-assisted auto-assignment feature can suggest optimal class schedules based on faculty availability, room capacity, and subject requirements.',
      },
    ],
  },
  {
    id: 'recruitment',
    title: 'Recruitment',
    icon: '👥',
    articles: [
      {
        q: 'How does the recruitment process work?',
        a: 'Recruitment follows this flow: Job Posting → Application Submission → Screening → Interview → Panel Evaluation → Placement. HR manages the process and applicants are notified at each stage.',
      },
      {
        q: 'Where can I view job postings?',
        a: 'Active job postings are visible under Recruitment → Job Postings. External applicants can apply through the public-facing application form.',
      },
    ],
  },
  {
    id: 'saln',
    title: 'SALN',
    icon: '📝',
    articles: [
      {
        q: 'What is SALN?',
        a: 'SALN (Statement of Assets, Liabilities, and Net Worth) is a mandatory annual declaration required of all government employees under Republic Act 6713.',
      },
      {
        q: 'How do I file my SALN?',
        a: 'Go to SALN → My SALN. Fill in your assets, liabilities, and net worth as of December 31 of the filing year. Submit before the deadline set by HR.',
      },
      {
        q: 'Who reviews my SALN?',
        a: 'SALN submissions are reviewed by the designated SALN reviewer, then approved by the authorized official, and filed by the records officer.',
      },
    ],
  },
  {
    id: 'it-requests',
    title: 'IT Job Requests',
    icon: '💻',
    articles: [
      {
        q: 'How do I submit an IT job request?',
        a: 'Go to IT Job Requests → New Request. Select the category (e.g., Hardware Repair, Software Installation, Technical Assistance), fill in the details, and assign your Division Chief. Your request will be approved by your Division Chief, then by OCD before MIS personnel act on it.',
      },
      {
        q: 'What are the IT request categories?',
        a: 'Categories include: Hardware Repair/Replacement, Software Installation/Configuration, Network/Connectivity Issues, Technical Assistance on Events, and others.',
      },
      {
        q: 'How will I know when my request is resolved?',
        a: 'You will receive email notifications at each status change. You can also track the status in IT Job Requests → My Requests.',
      },
      {
        q: 'Can I rate the service after resolution?',
        a: 'Yes. Once your request is marked as completed, you can submit a satisfaction rating and remarks.',
      },
    ],
  },
  {
    id: 'vehicle-requests',
    title: 'Vehicle Requests',
    icon: '🚗',
    articles: [
      {
        q: 'How do I request a vehicle?',
        a: 'Go to General Services → Vehicle Requests → New Request. Fill in the purpose, destination, date needed, and number of passengers. Your Division Chief approves first, then GSU Head assigns a driver, and OCD gives final approval.',
      },
      {
        q: 'What is the approval flow for vehicle requests?',
        a: '1. Submitted → 2. Division Chief approves → 3. GSU Head assigns driver → 4. OCD final approval → 5. Approved.',
      },
      {
        q: 'Can I request a vehicle for multiple dates?',
        a: 'Yes. You can specify multiple dates in the request form for recurring trips.',
      },
    ],
  },
  {
    id: 'facility-requests',
    title: 'Facility Requests',
    icon: '🏢',
    articles: [
      {
        q: 'How do I reserve a facility?',
        a: 'Go to General Services → Facility Requests → New Request. Select the venue, activity details, dates, and equipment needed. Requests must be filed at least 3 days before the event date.',
      },
      {
        q: 'What is the approval flow for facility requests?',
        a: '1. Submitted (Pending) → 2. Division Chief approves → 3. FAD Chief gives final approval → 4. Approved.',
      },
      {
        q: 'What venues are available?',
        a: 'Available venues include the Gymnasium, Audio Visual Room, Conference Room, Multi-Purpose Hall, Academic Building 1 Lobby, and Student Activity Center.',
      },
      {
        q: 'What if my preferred venue is already booked?',
        a: 'The system checks for scheduling conflicts when you submit. If your selected venue is already booked for the same date and time, you will be notified and asked to choose a different venue or time.',
      },
    ],
  },
  {
    id: 'work-requests',
    title: 'Work Requests (General Services)',
    icon: '🔧',
    articles: [
      {
        q: 'How do I submit a work/maintenance request?',
        a: 'Go to General Services → Work Requests → New Request. Describe the issue, select the category and priority, and specify the location. GSU Head will assign skilled personnel to address it.',
      },
      {
        q: 'What is the approval flow for work requests?',
        a: '1. Submitted (Pending) → 2. GSU Head approves and assigns staff → 3. FAD Chief approves → 4. Work is completed.',
      },
      {
        q: 'How do I know when my work request is completed?',
        a: 'You will receive an email notification when the work is marked as completed by GSU Head, including the action taken and date completed.',
      },
    ],
  },
  {
    id: 'service-requests',
    title: 'Request for Services',
    icon: '📄',
    articles: [
      {
        q: 'What is a Request for Services?',
        a: 'A Request for Services covers reproduction/printing, photocopying, and other administrative services provided by the General Services Unit.',
      },
      {
        q: 'How do I submit a service request?',
        a: 'Go to General Services → Request for Services → New Request. Select the service type, specify the date needed, and provide details. Your Division Chief approves first, then FAD Chief gives final approval.',
      },
    ],
  },
  {
    id: 'messengerial',
    title: 'Messengerial Requests',
    icon: '📦',
    articles: [
      {
        q: 'How do I request a messengerial service?',
        a: 'Go to Messengerial → New Request. Fill in the purpose, destination, consignee details, and delivery method (Hand-Carry or Courier). Your Division Chief approves the request, then Records processes it.',
      },
      {
        q: 'How is the reference number generated?',
        a: 'Reference numbers are auto-generated based on your division acronym, year, month, and sequence (e.g., FAD-2026-05-0001).',
      },
      {
        q: 'How do I know when my package was delivered?',
        a: 'Records will upload proof of delivery and mark the request as Completed. You will receive an email notification with the delivery details.',
      },
    ],
  },
  {
    id: 'document-tracking',
    title: 'Document Tracking',
    icon: '📂',
    articles: [
      {
        q: 'How does document tracking work?',
        a: 'Documents are routed between offices/personnel through the Document Tracking module. Each routing step is recorded with timestamps, creating a full audit trail of where a document has been.',
      },
      {
        q: 'How do I receive a document?',
        a: 'When a document is routed to you, you will see a notification badge on the Document Tracking sidebar link. Open the module, find the document, and mark it as received.',
      },
      {
        q: 'Can I track where my document currently is?',
        a: 'Yes. Open the document in Document Tracking to see the full routing history — who sent it, who received it, and the current holder.',
      },
    ],
  },
  {
    id: 'library',
    title: 'Library',
    icon: '📚',
    articles: [
      {
        q: 'How do I borrow a book?',
        a: 'Go to Library → Collections to browse available materials. Present your ID to the librarian to check out a book. The system records the borrowing and due date.',
      },
      {
        q: 'What happens if I return a book late?',
        a: 'Overdue borrowings are flagged in the system. The librarian will see an overdue count badge. Contact the library to settle any overdue items.',
      },
    ],
  },
  {
    id: 'chat',
    title: 'Chat / Messenger',
    icon: '💬',
    articles: [
      {
        q: 'How do I send a message?',
        a: 'Click the chat icon in the top navbar or go to Chat in the sidebar. Select a contact or group and type your message. Messages are delivered in real time.',
      },
      {
        q: 'Why am I not receiving message notifications?',
        a: 'Make sure you have allowed browser notifications when prompted. If the issue persists, check your internet connection — the real-time messaging requires a stable connection to the server.',
      },
      {
        q: 'Who can I message?',
        a: 'You can message any active user in the system. Group chats are also supported.',
      },
    ],
  },
  {
    id: 'pds',
    title: 'Personal Data Sheet (PDS)',
    icon: '🪪',
    articles: [
      {
        q: 'What is the PDS?',
        a: 'The Personal Data Sheet (PDS) is the official CSC form that captures your personal, educational, civil service, work experience, and other background information.',
      },
      {
        q: 'How do I update my PDS?',
        a: 'Go to PDS → My PDS. You can update each section (Personal Information, Family Background, Educational Background, etc.) and save changes. HR reviews and approves significant changes.',
      },
    ],
  },
  {
    id: 'health',
    title: 'Health / Clinic',
    icon: '🏥',
    articles: [
      {
        q: 'How do I request a consultation?',
        a: 'Go to Health Services → Request Consultation. Fill in your symptoms or concern and submit. The clinic staff will attend to your request.',
      },
      {
        q: 'Can I view my medical history?',
        a: 'Yes. Your consultation records are stored in the system and can be viewed under Health Services → My Consultations.',
      },
    ],
  },
  {
    id: 'esignature',
    title: 'Electronic Signature',
    icon: '✍️',
    articles: [
      {
        q: 'Will my e-signature only be used for requests I personally submit or approve?',
        a: 'Yes. Your electronic signature is rendered on printed documents only for the specific role you played in that transaction. For example, if you submitted a Vehicle Request, your signature appears in the "Requested by" field of that request\'s printed slip. If you are a Division Chief, your signature appears in the "Approved by" field only on requests you approved. The system links your signature to a document only when your user account is directly associated with that record as a requestor, approver, or signatory — never for records you have no involvement in.',
      },
      {
        q: 'Where is my e-signature stored and who can access it?',
        a: 'Your signature image is stored as a file in the server\'s secure storage directory (storage/signatures/). The file path is saved in your user profile. Only authenticated users can access their own profile data. Administrators can view user records for management purposes but cannot download or export signature files in bulk. The storage directory is not publicly browsable — files are served only through the application when generating official printed documents.',
      },
      {
        q: 'What safeguards prevent unauthorized use of my signature?',
        a: 'The system enforces the following safeguards: (1) Your signature is only embedded in a printed document when your user ID is directly linked to that record as a participant. (2) No API endpoint or export feature exposes raw signature files to other users. (3) The application uses server-side authorization checks before rendering any signature on a document. (4) All document generation is logged with timestamps and the identity of the user who triggered it. (5) Access to the server storage directory is restricted at the infrastructure level.',
      },
      {
        q: 'What is the retention policy for my e-signature?',
        a: 'When you upload a new signature, the system automatically deletes your previous signature file and replaces it with the new one. Your signature remains on file as long as your account is active in the system. If your account is deactivated, the signature file is retained for audit trail purposes — historical printed documents that already bear your signature remain valid records. You may request deletion of your signature by contacting your system administrator, subject to applicable records retention requirements under Philippine law.',
      },
      {
        q: 'Can I remove or update my e-signature?',
        a: 'Yes. Go to your Profile (click your name/avatar in the top-right corner) and navigate to the Electronic Signature section. You can upload a new signature image (PNG format, max 1200×600 px) at any time. Uploading a new signature automatically removes the old one. If you want your signature removed entirely without replacement, contact your system administrator.',
      },
      {
        q: 'On which documents does my signature appear?',
        a: 'Your signature currently appears on the following printed documents where you are a named participant: IT Job Request PDF (as requestor, Division Chief, assigned MIS personnel, or OCD); Vehicle Request print slip (as requestor, Division Chief, or OCD); Facility Request print slip (as requestor, Division Chief, or FAD Chief); Messengerial Request print slip (as requestor); and Library Statistics Report (for Librarian and Clerk roles). Your signature is never placed on a document for a request you did not participate in.',
      },
    ],
  },
  {
    id: 'admin',
    title: 'Administration',
    icon: '⚙️',
    articles: [
      {
        q: 'How do I add a new user?',
        a: 'Go to Data Management → All Users → Add User. Fill in the employee details, assign a role, and set the initial password. The user can change their password on first login.',
      },
      {
        q: 'How do I assign roles and permissions?',
        a: 'Go to Data Management → Assign Roles. Select the user and assign the appropriate role. Roles control what modules and actions a user can access.',
      },
      {
        q: 'What roles are available?',
        a: 'Roles include: Administrator, HR, DivisionChief, FAD Chief, GSU Head, OCD, MIS, Faculty, Staff, Records, Payroll Officer, Librarian, Clinic, Nurse, Guidance, and others.',
      },
      {
        q: 'How do I manage divisions and offices?',
        a: 'Go to Data Management → Division to manage divisions and assign Division Chiefs. Go to Data Management → Office/Unit to manage offices under each division.',
      },
    ],
  },
]

const openSections = ref(new Set(sections.map(s => s.id)))

function toggle(id) {
  if (openSections.value.has(id)) {
    openSections.value.delete(id)
  } else {
    openSections.value.add(id)
  }
}

const filteredSections = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return sections
  return sections
    .map(section => ({
      ...section,
      articles: section.articles.filter(
        a => a.q.toLowerCase().includes(q) || a.a.toLowerCase().includes(q)
      ),
    }))
    .filter(section =>
      section.title.toLowerCase().includes(q) || section.articles.length > 0
    )
})

const totalArticles = computed(() =>
  sections.reduce((sum, s) => sum + s.articles.length, 0)
)
</script>

<template>
  <Head title="Help & Documentation" />
  <AdminLayout title="Help & Documentation">
    <div class="max-w-4xl mx-auto space-y-6">

      <!-- Header -->
      <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-2xl p-8 text-white">
        <div class="flex items-center gap-3 mb-3">
          <QuestionMarkCircleIcon class="w-8 h-8 text-indigo-200" />
          <h1 class="font-heading text-2xl font-bold">Atlas Help Center</h1>
        </div>
        <p class="text-indigo-200 text-sm mb-6">
          Find answers to common questions about all modules in the PSHS Caraga Region Campus Management Information System.
        </p>
        <!-- Search -->
        <div class="relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-indigo-300" />
          <input
            v-model="search"
            type="text"
            placeholder="Search for help topics, questions, or keywords…"
            class="w-full pl-10 pr-4 py-3 rounded-xl bg-white/10 border border-white/20 text-white placeholder-indigo-300 focus:outline-none focus:ring-2 focus:ring-white/40 text-sm"
          />
        </div>
        <p class="text-indigo-300 text-xs mt-3">
          {{ totalArticles }} articles across {{ sections.length }} modules
        </p>
      </div>

      <!-- Empty state -->
      <div v-if="filteredSections.length === 0" class="bg-white rounded-xl border border-slate-100 shadow-sm py-16 text-center">
        <QuestionMarkCircleIcon class="w-10 h-10 text-slate-300 mx-auto mb-3" />
        <p class="text-slate-500 font-medium">No results found for "{{ search }}"</p>
        <p class="text-slate-400 text-sm mt-1">Try a different keyword or browse the sections below.</p>
        <button @click="search = ''" class="mt-4 text-sm text-indigo-600 hover:text-indigo-800 font-medium">Clear search</button>
      </div>

      <!-- Sections -->
      <div v-for="section in filteredSections" :key="section.id"
        class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">

        <!-- Section header -->
        <button
          @click="toggle(section.id)"
          class="w-full flex items-center justify-between px-5 py-4 hover:bg-slate-50 transition-colors text-left"
        >
          <div class="flex items-center gap-3">
            <span class="text-xl">{{ section.icon }}</span>
            <div>
              <h2 class="text-sm font-semibold text-slate-800">{{ section.title }}</h2>
              <p class="text-xs text-slate-400 mt-0.5">{{ section.articles.length }} article{{ section.articles.length !== 1 ? 's' : '' }}</p>
            </div>
          </div>
          <ChevronUpIcon v-if="openSections.has(section.id)" class="w-4 h-4 text-slate-400 shrink-0" />
          <ChevronDownIcon v-else class="w-4 h-4 text-slate-400 shrink-0" />
        </button>

        <!-- Articles -->
        <div v-if="openSections.has(section.id)" class="border-t border-slate-100 divide-y divide-slate-50">
          <div v-for="(article, idx) in section.articles" :key="idx" class="px-5 py-4">
            <p class="text-sm font-medium text-slate-800 mb-1.5 flex items-start gap-2">
              <span class="text-indigo-500 font-bold shrink-0 mt-0.5">Q</span>
              <span>{{ article.q }}</span>
            </p>
            <p class="text-sm text-slate-600 leading-relaxed flex items-start gap-2">
              <span class="text-emerald-500 font-bold shrink-0 mt-0.5">A</span>
              <span>{{ article.a }}</span>
            </p>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="bg-slate-50 rounded-xl border border-slate-100 p-5 text-center">
        <p class="text-sm text-slate-500">
          Can't find what you're looking for? Contact your system administrator or reach out via
          <a href="/chat" class="text-indigo-600 hover:text-indigo-800 font-medium">Chat</a>.
        </p>
        <p class="text-xs text-slate-400 mt-1">Atlas — PSHS Caraga Region Campus Digital Management Platform</p>
      </div>

    </div>
  </AdminLayout>
</template>
