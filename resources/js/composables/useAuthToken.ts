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

    return { get, set, clear, authHeaders };
}
