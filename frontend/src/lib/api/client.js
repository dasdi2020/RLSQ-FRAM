/**
 * Client HTTP pour l'API RLSQ-FRAM.
 * Gère automatiquement le JWT et le refresh.
 */

const BASE_URL = '';

function getToken() {
    return localStorage.getItem('access_token');
}

function getRefreshToken() {
    return localStorage.getItem('refresh_token');
}

export function setTokens(access, refresh) {
    localStorage.setItem('access_token', access);
    if (refresh) localStorage.setItem('refresh_token', refresh);
}

export function clearTokens() {
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
    localStorage.removeItem('user');
}

export function getStoredUser() {
    const u = localStorage.getItem('user');
    return u ? JSON.parse(u) : null;
}

export function setStoredUser(user) {
    localStorage.setItem('user', JSON.stringify(user));
}

async function refreshAccessToken() {
    const refresh = getRefreshToken();
    if (!refresh) return false;

    try {
        const res = await fetch(`${BASE_URL}/api/auth/refresh`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ refresh_token: refresh }),
        });

        if (!res.ok) return false;

        const data = await res.json();
        setTokens(data.access_token, null);
        return true;
    } catch {
        return false;
    }
}

/**
 * Requête API avec gestion automatique du JWT.
 */
export async function api(path, options = {}) {
    const token = getToken();
    const headers = {
        'Content-Type': 'application/json',
        ...options.headers,
    };

    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    let res = await fetch(`${BASE_URL}${path}`, { ...options, headers });

    // Si 401 et qu'on a un refresh token, tenter le refresh
    if (res.status === 401 && getRefreshToken()) {
        const refreshed = await refreshAccessToken();
        if (refreshed) {
            headers['Authorization'] = `Bearer ${getToken()}`;
            res = await fetch(`${BASE_URL}${path}`, { ...options, headers });
        }
    }

    return res;
}

/**
 * Raccourcis
 */
export const get = (path) => api(path).then(r => r.json());
export const post = (path, data) => api(path, { method: 'POST', body: JSON.stringify(data) }).then(r => r.json());
export const put = (path, data) => api(path, { method: 'PUT', body: JSON.stringify(data) }).then(r => r.json());
export const del = (path) => api(path, { method: 'DELETE' }).then(r => r.json());
