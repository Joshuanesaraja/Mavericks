import api from '../api/axios';

let csrfToken = null;

export async function getCsrfToken() {
  if (csrfToken) {
    return csrfToken;
  }

  const response = await api.get('/csrf-token');

  csrfToken = response.data.data.csrf_token;

  return csrfToken;
}

export function setCsrfToken(token) {
  csrfToken = token;
}

export function clearCsrfToken() {
  csrfToken = null;
}