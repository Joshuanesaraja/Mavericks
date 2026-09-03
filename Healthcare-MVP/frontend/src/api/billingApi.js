import api from './axios';

export async function getInvoices(params = {}) {
    const response = await api.get('/invoices', {
        params,
    });

    return response.data;
}

export async function getInvoice(id) {
    const response = await api.get('/invoices/detail', {
        params: { id },
    });

    return response.data;
}

export async function getPayments(params = {}) {
    const response = await api.get('/payments', {
        params,
    });

    return response.data;
}

export async function getBillingSummary(params = {}) {
    const response = await api.get('/billing/summary', {
        params,
    });

    return response.data;
}