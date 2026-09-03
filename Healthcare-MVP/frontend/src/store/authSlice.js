import { createSlice } from '@reduxjs/toolkit';

const initialState = {
    user: null,
    roles: [],
    isAuthenticated: false,
    initializing: true,
    loading: false,
    error: null,
};

const authSlice = createSlice({
    name: 'auth',
    initialState,

    reducers: {
        loginRequest: (state) => {
            state.loading = true;
            state.error = null;
        },

        loginSuccess: (state, action) => {
            state.loading = false;
            state.user = action.payload;
            state.roles = action.payload.roles || [];
            state.isAuthenticated = true;
            state.error = null;
        },

        loginFailure: (state, action) => {
            state.loading = false;
            state.error = action.payload;
            state.isAuthenticated = false;
        },

        logoutRequest: (state) => {
            state.loading = true;
        },

        logoutSuccess: (state) => {
            state.user = null;
            state.roles = [];
            state.isAuthenticated = false;
            state.loading = false;
            state.error = null;
        },

        logoutFailure: (state, action) => {
            state.loading = false;
            state.error = action.payload;
        },

        setUser: (state, action) => {
            state.user = action.payload;
            state.roles = action.payload.roles || [];
            state.isAuthenticated = true;
            state.initializing = false;
        },

        clearAuth: (state) => {
            state.user = null;
            state.roles = [];
            state.isAuthenticated = false;
            state.initializing = false;
            state.loading = false;
            state.error = null;
        },

        bootstrapStart: (state) => {
            state.initializing = true;
        },

        bootstrapSuccess: (state) => {
            state.initializing = false;
        },

        bootstrapFailure: (state) => {
            state.initializing = false;
        },
    },
});

export const {
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
} = authSlice.actions;

export default authSlice.reducer;