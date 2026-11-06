// frontend/services/api/marketplacePortal.ts
"use client"

import { MarketplaceLoginResponse } from "../../types/marketplace";

const BASE_URL = (process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000").replace(/\/$/, "");

function buildUrl(path: string) {
  if (path.startsWith("http")) return path;
  return `${BASE_URL}${path.startsWith("/") ? "" : "/"}${path}`;
}

export function getMpToken(slug: string): string | null {
  if (typeof window === "undefined") return null;
  // 1. Token específico de este marketplace (bombero/cliente)
  const mpToken = localStorage.getItem(`mp_token_${slug}`);
  if (mpToken) return mpToken;

  // 2. Fallback: Si el usuario tiene sesión activa como Administrador/Staff en el ERP interno
  const adminToken = localStorage.getItem("auth_token");
  if (adminToken) return adminToken;

  return null;
}

export function setMpToken(slug: string, token: string) {
  if (typeof window === "undefined") return;
  localStorage.setItem(`mp_token_${slug}`, token);
}

export function removeMpToken(slug: string) {
  if (typeof window === "undefined") return;
  localStorage.removeItem(`mp_token_${slug}`);
}

type RequestOptions = {
  headers?: Record<string, string>;
  signal?: AbortSignal;
  timeoutMs?: number;
};

export async function mpRequest<T>(
  slug: string,
  path: string,
  method: string,
  body?: any,
  options: RequestOptions = {}
): Promise<T> {
  const url = buildUrl(path);

  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Marketplace-Slug": slug,
    ...options.headers,
  };

  const hasBody = body !== undefined && body !== null;
  if (hasBody && !(body instanceof FormData)) {
    headers["Content-Type"] = headers["Content-Type"] || "application/json";
  }

  const token = getMpToken(slug);
  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  // Timeout logic (30 seconds default for network/SMTP operations)
  const controller = new AbortController();
  const timeoutMs = options.timeoutMs || 30000;
  const timeoutId = setTimeout(() => controller.abort(), timeoutMs);
  const signal = options.signal || controller.signal;

  try {
    const res = await fetch(url, {
      method,
      headers,
      signal,
      body: hasBody
        ? body instanceof FormData
          ? body
          : headers["Content-Type"]?.includes("application/json")
          ? JSON.stringify(body)
          : body
        : undefined,
    });

    clearTimeout(timeoutId);

    if (res.status === 204) return undefined as T;

    const contentType = res.headers.get("content-type") || "";
    const data = contentType.includes("application/json")
      ? await res.json().catch(() => null)
      : await res.text().catch(() => null);

    if (!res.ok) {
      if (res.status === 401) {
        if (typeof window !== "undefined") {
          removeMpToken(slug);
          if (window.location.pathname !== `/m/${slug}` && !path.includes("/login")) {
            window.location.href = `/m/${slug}`;
          }
        }
      }

      // Format 422 Validation Errors
      let errorMessage = `HTTP ${res.status}`;
      if (data && typeof data === "object") {
        if (res.status === 422 && data.errors) {
          errorMessage = Object.values(data.errors).flat().join(", ");
        } else {
          errorMessage = data.message || data.error || errorMessage;
        }
      } else if (typeof data === "string" && data) {
        errorMessage = data;
      }

      const err = new Error(errorMessage) as Error & { status?: number; data?: any };
      err.status = res.status;
      err.data = data;
      throw err;
    }

    return data as T;
  } catch (error: any) {
    clearTimeout(timeoutId);
    if (error.name === 'AbortError') {
      const err = new Error("La solicitud ha tardado demasiado (Timeout).") as Error & { status?: number };
      err.status = 408;
      throw err;
    }
    throw error;
  }
}

export const mpApi = (slug: string) => ({
  get: <T = any>(path: string, options?: RequestOptions) => mpRequest<T>(slug, path, "GET", undefined, options),
  post: <T = any>(path: string, body?: any, options?: RequestOptions) => mpRequest<T>(slug, path, "POST", body, options),
  put: <T = any>(path: string, body?: any, options?: RequestOptions) => mpRequest<T>(slug, path, "PUT", body, options),
  patch: <T = any>(path: string, body?: any, options?: RequestOptions) => mpRequest<T>(slug, path, "PATCH", body, options),
  del: <T = any>(path: string, options?: RequestOptions) => mpRequest<T>(slug, path, "DELETE", undefined, options),
});
