import axios from 'axios';

import API_BASE_URL from '../config/api';
import { encryptPayload, decryptPayload } from '../security/aes';
import {
    getCsrfToken,
    clearCsrfToken,
} from '../security/csrf';

const api = axios.create({
    baseURL: API_BASE_URL,
    withCredentials: true,
    headers: {
        'Content-Type': 'application/json',
    },
});

// Encrypt protected request payloads
api.interceptors.request.use(
    async (config) => {
        const method = config.method?.toUpperCase();

        if (
            ['POST', 'PUT', 'DELETE'].includes(method) &&
            !config.skipEncryption
        ) {
            const csrfToken = await getCsrfToken();

            config.data = {
                csrf_token: csrfToken,
                payload: encryptPayload(config.data || {}),
            };
        }

        return config;
    },
    (error) => Promise.reject(error)
);

// Decrypt encrypted responses
api.interceptors.response.use(
    (response) => {
        if (response.data?.payload) {
            response.data = decryptPayload(response.data.payload);
        }

        return response;
    },

    async (error) => {
        const originalRequest = error.config;

        if (!originalRequest) {
            return Promise.reject(error);
        }

        const status = error.response?.status;

        // Access token expired
        if (
            status === 401 &&
            !originalRequest._retry &&
            originalRequest.url !== '/refresh'
        ) {
            originalRequest._retry = true;

            try {
                clearCsrfToken();

                // Axios request interceptor will automatically:
                // 1. get the current CSRF token
                // 2. encrypt the refresh request
                // 3. send it with HttpOnly cookies
                await api.post('/refresh', {});

                // Refresh regenerates the CSRF token.
                clearCsrfToken();

                // Get the new CSRF token before retrying the original request.
                await getCsrfToken();

                return api(originalRequest);
            } catch (refreshError) {
                clearCsrfToken();

                return Promise.reject(refreshError);
            }
        }

        return Promise.reject(error);
    }
);

export default api;