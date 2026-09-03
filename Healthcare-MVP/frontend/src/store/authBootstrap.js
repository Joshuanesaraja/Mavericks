import { call, put } from 'redux-saga/effects';

import { setUser, clearAuth } from './authSlice';
import { getProfile } from '../auth/authService';

export function* handleAuthBootstrap() {
    try {
        const response = yield call(getProfile);

        if (response.success && response.data) {
            yield put(setUser(response.data));
        } else {
            yield put(clearAuth());
        }
    } catch (error) {
        yield put(clearAuth());
    }
}