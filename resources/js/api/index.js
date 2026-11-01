let csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

export function setCsrfToken(token) {
    csrfToken = token || csrfToken;
}

function request(path, options) {
    return fetch(`/api${path}`, {
        ...options,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            ...(options.headers || {}),
        },
    });
}

async function refreshCsrfToken() {
    const response = await fetch('/api/csrf-token', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.csrf_token) {
        throw new Error(data.message || 'Could not refresh the security token. Reload the page and try again.');
    }

    setCsrfToken(data.csrf_token);
}

export async function api(path, options = {}) {
    let response = await request(path, options);
    if (response.status === 419 && !['GET', 'HEAD', 'OPTIONS'].includes((options.method || 'GET').toUpperCase())) {
        await refreshCsrfToken();
        response = await request(path, options);
    }

    const data = response.status === 204 ? {} : await response.json().catch(() => ({}));
    if (!response.ok) {
        const message = data.message || Object.values(data.errors || {})[0]?.[0] || `Request failed (${response.status}).`;
        throw new Error(message);
    }

    return data;
}
