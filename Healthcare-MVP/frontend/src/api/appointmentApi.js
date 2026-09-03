import api from './axios';

export async function getAppointments(params = {}) {
    const response = await api.get('/appointments', {
        params,
    });

    return response.data;
}

export async function getUpcomingAppointments(params = {}) {
    const response = await api.get('/appointments/upcoming', {
        params,
    });

    return response.data;
}

export async function getAppointment(id) {
    const response = await api.get('/appointments/detail', {
        params: { id },
    });

    return response.data;
}

export async function createAppointment(appointmentData) {
    const response = await api.post(
        '/appointments/create',
        appointmentData
    );

    return response.data;
}

export async function updateAppointment(id, appointmentData) {
    const response = await api.put(
        '/appointments/update',
        {
            id,
            ...appointmentData,
        }
    );

    return response.data;
}

export async function cancelAppointment(id, reason = '') {
    const response = await api.put(
        '/appointments/cancel',
        {
            id,
            reason,
        }
    );

    return response.data;
}

export async function updateAppointmentStatus(id, status) {
    const response = await api.put(
        '/appointments/status',
        {
            id,
            status,
        }
    );

    return response.data;
}