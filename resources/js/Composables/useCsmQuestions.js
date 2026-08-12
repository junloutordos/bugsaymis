// Shared CSC-compliant Client Satisfaction Measurement (CSM) question data.
// Used by both the transactional CsmForm.vue (module-triggered) and the
// anonymous PublicOfficeSurvey.vue (QR-code-triggered, per office/unit).
export function useCsmQuestions() {
  const clientTypes = [
    { value: 'citizen', label: 'Citizen' },
    { value: 'business', label: 'Business' },
    { value: 'government', label: 'Government (Employee or another agency)' },
  ]

  // Generalized service-availed options for the office-wide public survey.
  // Module-triggered flows (IT Job Request, Facility Request, etc.) pass their
  // own specific service key via CsmForm's `serviceKey`/`serviceOtherLabel` props.
  const generalServiceOptions = [
    { value: 'school_facilities', label: 'Availment of school facilities' },
    { value: 'school_credentials', label: "Processing of requests for school credentials (Students of the current school year)" },
    { value: 'personnel_documents', label: 'Processing of requests for personnel documents' },
    { value: 'office_transaction', label: 'General transaction with this office/unit' },
  ]

  const cc1Options = [
    { value: 1, label: '1. I know what a CC is and I saw this office\'s CC.' },
    { value: 2, label: '2. I know what a CC but I did NOT see this office\'s CC.' },
    { value: 3, label: '3. I learned of the CC only when I saw this office\'s CC.' },
    { value: 4, label: '4. I do not know what a CC is and I did not see one in this office. (Answer "NA" on CC2 and CC3)' },
  ]

  const cc2Options = [
    { value: 1, label: '1. Easy to see' },
    { value: 2, label: '2. Somewhat easy to see' },
    { value: 3, label: '3. Difficult to see' },
    { value: 4, label: '4. Not visible at all' },
    { value: 5, label: '5. N/A' },
  ]

  const cc3Options = [
    { value: 1, label: '1. Helped very much' },
    { value: 2, label: '2. Somewhat helped' },
    { value: 3, label: '3. Did not help' },
    { value: 4, label: '4. N/A' },
  ]

  const sqdColumns = [
    { value: 1, label: 'Strongly\nDisagree' },
    { value: 2, label: 'Disagree' },
    { value: 3, label: 'Neither\nAgree nor\nDisagree' },
    { value: 4, label: 'Agree' },
    { value: 5, label: 'Strongly\nAgree' },
    { value: 6, label: 'N/A\nNot\nApplicable' },
  ]

  const sqdItems = [
    { idx: 0, key: 'sqd0', label: 'I am satisfied with the services that I availed.' },
    { idx: 1, key: 'sqd1', label: 'I spent reasonable amount of time for my transaction.' },
    { idx: 2, key: 'sqd2', label: 'The office followed the transaction\'s requirements and steps based on the information provided.' },
    { idx: 3, key: 'sqd3', label: 'The steps (including payment) I needed to do for my transaction were easy and simple.' },
    { idx: 4, key: 'sqd4', label: 'I easily found information about my transaction from the office\'s website.' },
    { idx: 5, key: 'sqd5', label: 'I paid a reasonable amount of fees for my transaction. (If service was free, mark the "N/A" column)' },
    { idx: 6, key: 'sqd6', label: 'I am confident my transaction was secure.' },
    { idx: 7, key: 'sqd7', label: 'The office\'s support was available, and (if asked questions) support was quick to respond.' },
    { idx: 8, key: 'sqd8', label: 'I got what I needed from the government office, or (if denied) denial of request was sufficiently explained to me.' },
  ]

  return {
    clientTypes,
    generalServiceOptions,
    cc1Options,
    cc2Options,
    cc3Options,
    sqdColumns,
    sqdItems,
  }
}
