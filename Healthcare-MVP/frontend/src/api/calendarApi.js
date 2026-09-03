import api from './axios';

export async function getCalendarByDate(date) {
    const response = await api.get('/calendar/date', {
        params: { date },
    });

    return response.data;
}

export async function getCalendarByRange(startDate, endDate) {
    const response = await api.get('/calendar/range', {
        params: {
            start_date: startDate,
            end_date: endDate,
        },
    });

    return response.data;
}

export async function getCalendar(params = {}) {
    const response = await api.get('/calendar', {
        params,
    });

    return response.data;
}