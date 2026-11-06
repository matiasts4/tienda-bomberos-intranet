const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000";

// Esta función es vital para Sanctum
async function getCsrfCookie() {
  try {
    const response = await fetch(`${API_URL}/sanctum/csrf-cookie`, {
      method: "GET",
      credentials: "include", // Importante para que guarde la cookie
      headers: {
        Accept: "application/json",
      },
    });

    if (!response.ok) {
      console.error("Error getting CSRF cookie:", response.status, response.statusText);
      throw new Error(`Failed to get CSRF cookie: ${response.status}`);
    }

    console.log("CSRF cookie obtained successfully");
  } catch (error) {
    console.error("Network error getting CSRF cookie:", error);
    throw new Error("Network error: Unable to connect to server");
  }
}

export async function login(email: string, password: string) {
  try {
    console.log("Starting login process...");

    // 1. PRIMERO: Pedimos la cookie de seguridad
    await getCsrfCookie();

    // 2. SEGUNDO: Hacemos el login
    console.log("Making login request to:", `${API_URL}/api/auth/login`);

    const res = await fetch(`${API_URL}/api/auth/login`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      credentials: "include", // Envía la cookie que acabamos de pedir
      body: JSON.stringify({ email, password }),
    });

    console.log("Login response status:", res.status);

    if (!res.ok) {
      const data = await res.json().catch(() => null);
      console.error("Login failed:", res.status, data);
      throw new Error(data?.message || `Login failed with status ${res.status}`);
    }

    const result = await res.json();
    // Store the Bearer token in a cookie on the frontend domain
    if (result.token && typeof document !== 'undefined') {
      document.cookie = `auth_token=${result.token}; path=/; secure; samesite=none; max-age=604800`; // 7 days
      document.cookie = 'auth=true; path=/; secure; samesite=none; max-age=604800';
    }
    console.log("Login successful");
    return result;
  } catch (error) {
    console.error("Login error:", error);
    if (error instanceof TypeError && error.message.includes("fetch")) {
      throw new Error("Network error: Unable to connect to server. Please check if the backend is running.");
    }
    throw error;
  }
}

export async function logout() {
  // Read Bearer token from cookie to send in logout request
  const getCookie = (name: string) => {
    if (typeof document === 'undefined') return null;
    const match = document.cookie.match(new RegExp('(^|; )' + name + '=([^;]*)'));
    return match ? match[2] : null;
  };
  const token = getCookie('auth_token');

  const res = await fetch(`${API_URL}/api/auth/logout`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    credentials: "include",
  });

  // Clear cookies regardless of server response
  if (typeof document !== 'undefined') {
    document.cookie = 'auth_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; secure; samesite=none';
    document.cookie = 'auth=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; secure; samesite=none';
  }
}

export async function getMe() {
  // Read Bearer token from cookie
  const getCookie = (name: string) => {
    if (typeof document === 'undefined') return null;
    const match = document.cookie.match(new RegExp('(^|; )' + name + '=([^;]*)'));
    return match ? match[2] : null;
  };
  const token = getCookie('auth_token');

  const headers: Record<string, string> = { Accept: "application/json" };
  if (token) headers["Authorization"] = `Bearer ${token}`;

  const res = await fetch(`${API_URL}/api/me`, {
    method: "GET",
    headers,
    credentials: "include",
  });

  if (res.status === 401) return null;
  if (!res.ok) throw new Error("Error al obtener usuario");

  return res.json();
}