const OFFICE_EMAIL_LABELS = {
  ocd: 'OCD',
  cid: 'CID',
  fad: 'FAD',
  mis: 'MIS',
  hr: 'HR',
  records: 'Records',
  registrar: 'Registrar',
  library: 'Library',
  clinic: 'Clinic',
  guidance: 'Guidance',
  supply: 'Supply',
  property: 'Property',
  cashier: 'Cashier',
  budget: 'Budget',
  accounting: 'Accounting',
}

function normalizedName(user) {
  return (user?.name ?? '').trim().toLowerCase()
}

export function userAccountLabel(user) {
  const localPart = (user?.email ?? '').split('@')[0].toLowerCase()

  for (const [token, label] of Object.entries(OFFICE_EMAIL_LABELS)) {
    if (localPart.includes(token)) return label
  }

  return 'Personal'
}

export function userDisplayName(user, users = []) {
  const name = user?.name ?? ''
  if (!name) return ''

  const key = normalizedName(user)
  const duplicateCount = (users ?? []).filter(u => normalizedName(u) === key).length
  if (duplicateCount <= 1) return name

  return `${name} (${userAccountLabel(user)})`
}
