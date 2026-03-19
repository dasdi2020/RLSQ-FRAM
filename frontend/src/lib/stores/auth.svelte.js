import { post, get, setTokens, clearTokens, getStoredUser, setStoredUser } from '$lib/api/client.js';

/**
 * Store d'authentification (Svelte 5 runes)
 */

let user = $state(getStoredUser());
let isAuthenticated = $derived(user !== null);
let isLoading = $state(false);
let error = $state(null);
let requires2FA = $state(false);
let pendingUserId = $state(null);

export function getAuth() {
    return {
        get user() { return user; },
        get isAuthenticated() { return isAuthenticated; },
        get isLoading() { return isLoading; },
        get error() { return error; },
        get requires2FA() { return requires2FA; },

        /**
         * Étape 1 : Login avec email/password → demande 2FA
         */
        async login(email, password) {
            isLoading = true;
            error = null;
            requires2FA = false;

            try {
                const data = await post('/api/auth/login', { email, password });

                if (data.error) {
                    error = data.error;
                    return false;
                }

                if (data.requires_2fa) {
                    requires2FA = true;
                    pendingUserId = data.user_id;
                    return true;
                }

                return false;
            } catch (e) {
                error = 'Erreur de connexion.';
                return false;
            } finally {
                isLoading = false;
            }
        },

        /**
         * Étape 2 : Vérifier le code 2FA → JWT
         */
        async verify2FA(code) {
            isLoading = true;
            error = null;

            try {
                const data = await post('/api/auth/verify-2fa', {
                    user_id: pendingUserId,
                    code,
                });

                if (data.error) {
                    error = data.error;
                    return false;
                }

                setTokens(data.access_token, data.refresh_token);
                user = data.user;
                setStoredUser(data.user);
                requires2FA = false;
                pendingUserId = null;

                return true;
            } catch (e) {
                error = 'Erreur de vérification.';
                return false;
            } finally {
                isLoading = false;
            }
        },

        /**
         * Charger le profil depuis /api/auth/me
         */
        async fetchProfile() {
            try {
                const data = await get('/api/auth/me');
                if (data.user_id) {
                    user = data;
                    setStoredUser(data);
                }
            } catch {
                this.logout();
            }
        },

        /**
         * Déconnexion
         */
        logout() {
            user = null;
            requires2FA = false;
            pendingUserId = null;
            error = null;
            clearTokens();
        },

        hasRole(role) {
            return user?.roles?.includes(role) ?? false;
        },
    };
}
