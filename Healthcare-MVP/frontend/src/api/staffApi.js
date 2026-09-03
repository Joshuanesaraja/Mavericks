import api from './axios';

export async function getStaff(params = {}) {
    const response = await api.get('/staff', {
        params,
    });

    return response.data;
}

export async function getStaffMember(id) {
    const response = await api.get('/staff/detail', {
        params: { id },
    });

    return response.data;
}

export async function createStaff(staffData) {
    const response = await api.post('/staff/create', staffData);

    return response.data;
}

export async function updateStaff(id, staffData) {
    const response = await api.put('/staff/update', {
        id,
        ...staffData,
    });

    return response.data;
}

export async function deleteStaff(id) {
    const response = await api.post('/staff/delete', {
        id,
    });

    return response.data;
}