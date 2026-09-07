// A Core/Support Function tagged to N Work Distribution Plans materializes
// into N items sharing the same employee_function_id. This groups
// consecutive items sharing that id so a table can render the Function
// label/placeholder cells once per function (rowspan'd) instead of once
// per item — while everything else (Success Indicator, Target,
// Accomplishment, ratings, Remarks) stays rendered per item.
export function groupConsecutiveByFunction(items) {
  const rows = []
  let i = 0

  while (i < items.length) {
    const functionId = items[i].employee_function_id
    let groupSize = 1

    while (
      functionId != null &&
      i + groupSize < items.length &&
      items[i + groupSize].employee_function_id === functionId
    ) {
      groupSize++
    }

    for (let j = 0; j < groupSize; j++) {
      rows.push({ item: items[i + j], isFirst: j === 0, groupSize })
    }

    i += groupSize
  }

  return rows
}
