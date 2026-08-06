const STORAGE_KEY = 'player_token';

export function useAuthToken() {
    function get(): string | null {
        return localStorage.getItem(STORAGE_KEY);
    }

    function set(token: string): void {
        localStorage.setItem(STORAGE_KEY, token);
    }

    function clear(): void {
        localStorage.removeItem(STORAGE_KEY);
    }

    function authHeaders(): Record<string, string> {
        const token = get();

        return token
            ? { Authorization: `Bearer ${token}`, Accept: 'application/json' }
            : { Accept: 'application/json' };
    }

    /**
     * Same as fetch(), but automatically attaches the auth token, and if the
     * response comes back 401 (token missing/expired/invalid), clears the
     * stale token and sends the browser to the login page instead of letting
     * the page render a confusing "unauthenticated" error state.
     */
    async function authFetch(input: string, init: RequestInit = {}): Promise<Response> {
        const response = await fetch(input, {
            ...init,
            headers: { ...authHeaders(), ...(init.headers ?? {}) },
        });

        if (response.status === 401) {
            clear();
            window.location.href = '/api/login';
            // Never resolves — we're navigating away, so nothing downstream should try to read this response.
            return new Promise(() => {});
        }

        return response;
    }

    return { get, set, clear, authHeaders, authFetch };
}
