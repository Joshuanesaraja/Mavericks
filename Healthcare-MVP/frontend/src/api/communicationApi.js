import api from './axios';

// Appointment notes

export async function createNote(noteData) {
    const response = await api.post(
        '/notes/create',
        noteData
    );

    return response.data;
}

export async function getNotes(params = {}) {
    const response = await api.get('/notes', {
        params,
    });

    return response.data;
}

// Messages

export async function sendMessage(messageData) {
    const response = await api.post(
        '/messages/send',
        messageData
    );

    return response.data;
}

export async function getMessages(params = {}) {
    const response = await api.get('/messages', {
        params,
    });

    return response.data;
}

export async function getMessageHistory(params = {}) {
    const response = await api.get(
        '/messages/history',
        {
            params,
        }
    );

    return response.data;
}