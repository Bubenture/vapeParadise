function mainFilter() {
  try {
    filterData0()
  } catch (e) {
    SpreadsheetApp.getUi().alert('Ошибка: ' + e.message)
    console.error(e)
  }
}

function filterData0() {
  const config = {
    sourceSheetName: 'Кол-во',
    targetSheetName: 'Сводка',
    dateRange: 'C2:D2',
    outputRange: { row: 2, col: 6, cols: 9 },
    branchCell: null,
    nextFunction: filterData,
  }

  processFilter(config)
}

function filterData1() {
  const config = {
    sourceSheetName: 'Кол-во',
    targetSheetName: 'Сводка',
    dateRange: 'C2:D2',
    outputRange: { row: 62, col: 26, cols: 9 },
    branchCell: 'Z1',
    nextFunction: filterData1,
  }

  processFilter(config)
}

function filterData2() {
  const config = {
    sourceSheetName: 'Кол-во',
    targetSheetName: 'Сводка',
    dateRange: 'C2:D2',
    outputRange: { row: 62, col: 37, cols: 9 },
    branchCell: 'AK1',
    nextFunction: filterData2,
  }

  processFilter(config)
}

function filterData3() {
  const config = {
    sourceSheetName: 'Кол-во',
    targetSheetName: 'Сводка',
    dateRange: 'C2:D2',
    outputRange: { row: 62, col: 48, cols: 9 },
    branchCell: 'AV1',
    nextFunction: filterData3,
  }

  processFilter(config)
}

function filterData4() {
  const config = {
    sourceSheetName: 'Кол-во',
    targetSheetName: 'Сводка',
    dateRange: 'C2:D2',
    outputRange: { row: 62, col: 59, cols: 9 },
    branchCell: 'BG1',
    nextFunction: null,
  }

  processFilter(config)
}

function processFilter(config) {
  const ss = SpreadsheetApp.getActiveSpreadsheet()
  const sourceSheet = ss.getSheetByName(config.sourceSheetName)
  const targetSheet = ss.getSheetByName(config.targetSheetName)

  if (!sourceSheet || !targetSheet) {
    throw new Error('Лист с указанным именем не найден.')
  }

  const dateRange = targetSheet.getRange(config.dateRange).getValues()[0]
  const startDate = new Date(dateRange[0])
  const endDate = new Date(dateRange[1])

  if (!startDate || !endDate) {
    throw new Error('Пожалуйста, введите корректные даты.')
  }

  let branchFilter = null
  if (config.branchCell) {
    branchFilter = targetSheet.getRange(config.branchCell).getValue().trim()
    if (!branchFilter) {
      throw new Error(
        'Пожалуйста, введите значение для фильтрации филиала в ячейке ' +
          config.branchCell
      )
    }
  }

  const sourceData = sourceSheet.getDataRange().getValues()
  const headers = sourceData[0]
  const result = [headers]

  for (let i = 1; i < sourceData.length; i++) {
    const row = sourceData[i]
    const date = new Date(row[0])

    if (date >= startDate && date <= endDate) {
      if (!branchFilter || row[1] === branchFilter) {
        result.push(row)
      }
    }
  }

  const outputRange = targetSheet
    .getRange(
      config.outputRange.row,
      config.outputRange.col,
      Math.max(targetSheet.getLastRow() - config.outputRange.row + 1, 1),
      config.outputRange.cols
    )
    .clearContent()

  if (result.length > 1) {
    targetSheet
      .getRange(
        config.outputRange.row,
        config.outputRange.col,
        result.length,
        result[0].length
      )
      .setValues(result)
  }

  if (config.nextFunction) {
    config.nextFunction()
  }
}
