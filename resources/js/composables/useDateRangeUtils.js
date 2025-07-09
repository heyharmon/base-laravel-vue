import moment from 'moment'

export function useDateRangeUtils() {
  const getDateRangeForTimeframe = (timeframe) => {
    const now = moment()

    const ranges = {
      today: {
        startDate: now.format('YYYY-MM-DD'),
        endDate: now.format('YYYY-MM-DD')
      },
      yesterday: {
        startDate: now.clone().subtract(1, 'day').format('YYYY-MM-DD'),
        endDate: now.clone().subtract(1, 'day').format('YYYY-MM-DD')
      },
      last_7_days: {
        startDate: now.clone().subtract(7, 'days').format('YYYY-MM-DD'),
        endDate: now.format('YYYY-MM-DD')
      },
      last_30_days: {
        startDate: now.clone().subtract(30, 'days').format('YYYY-MM-DD'),
        endDate: now.format('YYYY-MM-DD')
      },
      this_week: {
        startDate: now.clone().startOf('week').format('YYYY-MM-DD'),
        endDate: now.format('YYYY-MM-DD')
      },
      this_month: {
        startDate: now.clone().startOf('month').format('YYYY-MM-DD'),
        endDate: now.format('YYYY-MM-DD')
      },
      this_year: {
        startDate: now.clone().startOf('year').format('YYYY-MM-DD'),
        endDate: now.format('YYYY-MM-DD')
      },
      last_week: {
        startDate: now.clone().subtract(1, 'week').startOf('week').format('YYYY-MM-DD'),
        endDate: now.clone().subtract(1, 'week').endOf('week').format('YYYY-MM-DD')
      },
      last_month: {
        startDate: now.clone().subtract(1, 'month').startOf('month').format('YYYY-MM-DD'),
        endDate: now.clone().subtract(1, 'month').endOf('month').format('YYYY-MM-DD')
      },
      last_year: {
        startDate: now.clone().subtract(1, 'year').startOf('year').format('YYYY-MM-DD'),
        endDate: now.clone().subtract(1, 'year').endOf('year').format('YYYY-MM-DD')
      },
      all_time: {
        startDate: null,
        endDate: null
      }
    }

    return ranges[timeframe] || { startDate: null, endDate: null }
  }

  const detectTimeframe = (startDate, endDate, availableTimeframes) => {
    for (const timeframe of availableTimeframes) {
      const range = getDateRangeForTimeframe(timeframe)
      if (range.startDate === startDate && range.endDate === endDate) {
        return timeframe
      }
    }
    return 'custom'
  }

  const formatDateRange = (startDate, endDate, format = 'MMM D, YYYY') => {
    if (!startDate || !endDate) return ''
    
    const start = moment(startDate)
    const end = moment(endDate)
    
    if (start.isSame(end, 'day')) {
      return start.format(format)
    }
    
    if (start.year() === end.year()) {
      return `${start.format('MMM D')} - ${end.format(format)}`
    }
    
    return `${start.format(format)} - ${end.format(format)}`
  }

  const isValidDateRange = (startDate, endDate) => {
    if (!startDate || !endDate) return false
    
    const start = moment(startDate)
    const end = moment(endDate)
    
    return start.isValid() && end.isValid() && start.isSameOrBefore(end)
  }

  const getDefaultDateRange = () => {
    return getDateRangeForTimeframe('last_30_days')
  }

  return {
    getDateRangeForTimeframe,
    detectTimeframe,
    formatDateRange,
    isValidDateRange,
    getDefaultDateRange
  }
}