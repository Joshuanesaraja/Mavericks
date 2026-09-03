import api from './axios';

export async function getPatients(params = {}) {
    const response = await api.get('/patients', {
        params,
    });

    return response.data;
}

export async function getPatient(id) {
    const response = await api.get('/patients/detail', {
        params: { id },
    });

    return response.data;
}

export async function createPatient(patientData) {
    const response = await api.post('/patients/create', patientData);

    return response.data;
}

export async function updatePatient(id, patientData) {
    const response = await api.put('/patients/update', {
        id,
        ...patientData,
    });

    return response.data;
}

export async function deletePatient(id) {
    const response = await api.post('/patients/delete', {
        id,
    });

    return response.data;
}