import api from '../api/axios';

export async function register(userData) {
    const response = await api.post('/register', userData);

    return response.data;
}

export async function login(credentials) {
    const response = await api.post('/login', credentials);

    return response.data;
}

export async function refreshToken() {
    const response = await api.post('/refresh', {});

    return response.data;
}

export async function logout() {
    const response = await api.post('/logout', {});

    return response.data;
}

export async function getProfile() {
    const response = await api.get('/profile');

    return response.data;
}

export async function changePassword(passwordData) {
    const response = await api.post(
        '/change-password',
        passwordData
    );

    return response.data;
}