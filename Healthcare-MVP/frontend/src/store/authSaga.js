import { call, put, takeLatest } from 'redux-saga/effects';
import { clearCsrfToken } from '../security/csrf';

import {
    loginRequest,
    loginSuccess,
    loginFailure,
    logoutRequest,
    logoutSuccess,
    logoutFailure,
    setUser,
    clearAuth,
    bootstrapStart,
    bootstrapSuccess,
    bootstrapFailure,
} from './authSlice';

import {
    login,
    logout,
    getProfile,
} from '../auth/authService';

function* handleLogin(action) {
    try {
        const response = yield call(login, action.payload);

        if (!response.success) {
            throw new Error(response.message || 'Login failed');
        }

        yield put(loginSuccess(response.data));
    } catch (error) {
        yield put(
            loginFailure(
                error.response?.data?.message ||
                error.message ||
                'Login failed'
            )
        );
    }
}

function* handleLogout() {
    try {
        const response = yield call(logout);

        clearCsrfToken();

        if (!response.success) {
            throw new Error(response.message || 'Logout failed');
        }

        yield put(logoutSuccess());
    } catch (error) {
        clearCsrfToken();

        yield put(logoutSuccess());

        yield put(
            logoutFailure(
                error.response?.data?.message ||
                error.message ||
                'Logout failed'
            )
        );
    }
}

function* handleAuthBootstrap() {
    try {
        yield put(bootstrapStart());

        const response = yield call(getProfile);

        if (response.success && response.data) {
            yield put(setUser(response.data));
            yield put(bootstrapSuccess());
        } else {
            yield put(clearAuth());
            yield put(bootstrapFailure());
        }
    } catch (error) {
        yield put(clearAuth());
        yield put(bootstrapFailure());
    }
}

export default function* authSaga() {
    yield takeLatest(loginRequest.type, handleLogin);
    yield takeLatest(logoutRequest.type, handleLogout);

    // Restore the authenticated user after page refresh.
    yield takeLatest('auth/bootstrap', handleAuthBootstrap);
}