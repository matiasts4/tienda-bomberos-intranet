// frontend/src/lib/api.ts
"use client"

type ApiError = Error & { status?: number; data?: any }

type RequestOptions = {
  headers?: Record<string, string>
  signal?: AbortSignal
}

const BASE_URL = (process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000").replace(/\/$/, "")

function buildUrl(path: string) {
  if (path.startsWith("http")) return path
  return `${BASE_URL}${path.startsWith("/") ? "" : "/"}${path}`
}

function getCookie(name: string): string | null {
  if (typeof document === "undefined") return null
  const match = document.cookie.match(
    new RegExp("(^|; )" + name.replace(/([.$?*|{}()[\]\\/+^])/g, "\\$1") + "=([^;]*)")
  )
  return match ? match[2] : null
}

// Laravel guarda XSRF-TOKEN URL-encoded
function getXsrfToken(): string | null {
  const raw = getCookie("XSRF-TOKEN")
  if (!raw) return null
  try {
    return decodeURIComponent(raw)
  } catch {
    return raw
  }
}

// Get Bearer token stored after login
function getAuthToken(): string | null {
  if (typeof window === 'undefined') return null
  return localStorage.getItem('auth_token')
}

function isUnsafeMethod(method: string) {
  return ["POST", "PUT", "PATCH", "DELETE"].includes(method.toUpperCase())
}

async function fetchCsrfCookie(signal?: AbortSignal) {
  await fetch(buildUrl("/sanctum/csrf-cookie"), {
    method: "GET",
    credentials: "include",
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    signal,
  })
}

async function request<T>(
  path: string,
  method: string,
  body?: any,
  options: RequestOptions = {},
  retried = false
): Promise<T> {
  const url = buildUrl(path)

  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
    ...(options.headers || {}),
  }

  // Attach Bearer token if available (cross-domain auth)
  const authToken = getAuthToken()
  if (authToken) {
    headers["Authorization"] = `Bearer ${authToken}`
  }

  const hasBody = body !== undefined && body !== null

  if (hasBody && !(body instanceof FormData)) {
    headers["Content-Type"] = headers["Content-Type"] || "application/json"
  }

  // Si es método inseguro, asegura cookie XSRF y header X-XSRF-TOKEN
  if (isUnsafeMethod(method)) {
    let xsrf = getXsrfToken()

    // Si no hay XSRF-TOKEN, pide /sanctum/csrf-cookie y vuelve a leer
    if (!xsrf && !retried) {
      await fetchCsrfCookie(options.signal)
      xsrf = getXsrfToken()
    }

    if (xsrf) headers["X-XSRF-TOKEN"] = xsrf
  }

  const res = await fetch(url, {
    method,
    credentials: "include",
    headers,
    signal: options.signal,
    body: hasBody
      ? body instanceof FormData
        ? body
        : headers["Content-Type"]?.includes("application/json")
          ? JSON.stringify(body)
          : body
      : undefined,
  })

  // Reintento automático 1 vez si 419 (CSRF)
  if (res.status === 419 && isUnsafeMethod(method) && !retried) {
    await fetchCsrfCookie(options.signal)
    return request<T>(path, method, body, options, true)
  }

  if (res.status === 204) {
    notifyAgendaChange(path, method)
    return undefined as T
  }

  const contentType = res.headers.get("content-type") || ""
  const data = contentType.includes("application/json")
    ? await res.json().catch(() => null)
    : await res.text().catch(() => null)

  if (!res.ok) {
    if (res.status === 401) {
      if (
        typeof window !== "undefined" &&
        window.location.pathname !== "/login" &&
        window.location.pathname !== "/" &&
        !window.location.pathname.startsWith("/encuesta") &&
        !window.location.pathname.startsWith("/m/") &&
        window.location.pathname !== "/m"
      ) {
        localStorage.removeItem("auth_token")
        localStorage.removeItem("user_data")
        window.location.href = "/login"
      }
    }

    const err: ApiError = new Error(
      (data && typeof data === "object" && (data.message || data.error)) ||
      (typeof data === "string" && data) ||
      `HTTP ${res.status}`
    )
    err.status = res.status
    err.data = data
    throw err
  }

  notifyAgendaChange(path, method)

  return data as T
}

function notifyAgendaChange(path: string, method: string) {
  if (typeof window !== 'undefined' && isUnsafeMethod(method) && /^\/api\/(eventos|cotizaciones|oportunidades|prospeccion|contactos|despachos)(\/|$)/.test(path)) {
    window.dispatchEvent(new Event('agenda:changed'))
  }
}

export async function downloadPdf(url: string, options?: { filename?: string; throwOnError?: boolean; signal?: AbortSignal; body?: unknown }) {
  try {
    const token = typeof window !== 'undefined' ? localStorage.getItem('auth_token') : null;
    const response = await fetch(buildUrl(url), {
      method: options?.body === undefined ? 'GET' : 'POST',
      body: options?.body === undefined ? undefined : JSON.stringify(options.body),
      credentials: 'include',
      signal: options?.signal,
      headers: { Accept: 'application/pdf, application/json', ...(options?.body === undefined ? {} : { 'Content-Type': 'application/json' }), ...(token ? { 'Authorization': `Bearer ${token}` } : {}) }
    });
    if (!response.ok) {
      const error = await response.json().catch(() => null);
      throw new Error(error?.message || error?.error || "No se pudo generar el PDF. Reintenta la descarga.");
    }
    if (!response.headers.get('content-type')?.includes('application/pdf')) {
      throw new Error("El servidor no entregó un PDF. Revisa tu sesión y reintenta.");
    }
    const blob = await response.blob();
    const blobUrl = window.URL.createObjectURL(blob);
    if (options?.filename) {
      const link = document.createElement('a');
      link.href = blobUrl;
      link.download = options.filename;
      document.body.appendChild(link);
      link.click();
      link.remove();
    } else {
      window.open(blobUrl, '_blank');
    }
    setTimeout(() => window.URL.revokeObjectURL(blobUrl), 60000);
  } catch (error) {
    console.error("PDF generation failed:", error);
    if (options?.throwOnError) throw error;
    alert("Error al generar el documento PDF.");
  }
}

export const api = {
  get: <T = any>(path: string, options?: RequestOptions) => request<T>(path, "GET", undefined, options),
  post: <T = any>(path: string, body?: any, options?: RequestOptions) => request<T>(path, "POST", body, options),
  put: <T = any>(path: string, body?: any, options?: RequestOptions) => request<T>(path, "PUT", body, options),
  patch: <T = any>(path: string, body?: any, options?: RequestOptions) => request<T>(path, "PATCH", body, options),
  delete: <T = any>(path: string, body?: any, options?: RequestOptions) => request<T>(path, "DELETE", body, options),
  del: <T = any>(path: string, body?: any, options?: RequestOptions) => request<T>(path, "DELETE", body, options),
  downloadPdf,
}
