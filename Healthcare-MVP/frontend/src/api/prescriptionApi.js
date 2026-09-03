import api from './axios';

export async function getPrescriptions(params = {}) {
    const response = await api.get('/prescriptions', {
        params,
    });

    return response.data;
}

export async function getPrescription(id) {
    const response = await api.get('/prescriptions/detail', {
        params: { id },
    });

    return response.data;
}

export async function createPrescription(prescriptionData) {
    const response = await api.post(
        '/prescriptions/create',
        prescriptionData
    );

    return response.data;
}

export async function updatePrescription(id, prescriptionData) {
    const response = await api.put(
        '/prescriptions/update',
        {
            id,
            ...prescriptionData,
        }
    );

    return response.data;
}

export async function verifyPrescription(id) {
    const response = await api.put(
        '/prescriptions/verify',
        {
            id,
        }
    );

    return response.data;
}

export async function updatePrescriptionStatus(id, status) {
    const response = await api.put(
        '/prescriptions/status',
        {
            id,
            status,
        }
    );

    return response.data;
}