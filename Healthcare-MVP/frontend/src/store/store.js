import { configureStore } from '@reduxjs/toolkit';
import createSagaMiddleware from 'redux-saga';

import authReducer from './authSlice';
import authSaga from './authSaga';


// this starts our authentication saga
const sagaMiddleware = createSagaMiddleware();

const store = configureStore({
    reducer: {
        auth: authReducer,
    },

    middleware: (getDefaultMiddleware) =>
        getDefaultMiddleware({
            thunk: false,
            serializableCheck: false,
        }).concat(sagaMiddleware),
});

sagaMiddleware.run(authSaga);

export default store;