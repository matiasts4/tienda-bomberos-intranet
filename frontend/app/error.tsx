"use client" // Error components must be Client Components

import { useEffect } from "react"
import { Button } from "@/components/ui/button"
import { AlertTriangle, RefreshCw, LogOut } from "lucide-react"

export default function Error({
    error,
    reset,
}: {
    error: Error & { digest?: string }
    reset: () => void
}) {
    useEffect(() => {
        console.error("Global Error Caught:", error)

        // --- MECANISMO ANTI-BUCLE (Circuit Breaker) ---
        if (typeof window !== "undefined" && typeof sessionStorage !== "undefined") {
            const now = Date.now()
            const lastErrorTime = Number(sessionStorage.getItem("last_error_at") || 0)
            const errorCount = Number(sessionStorage.getItem("error_loop_count") || 0)

            if (now - lastErrorTime < 10000) {
                const newCount = errorCount + 1
                sessionStorage.setItem("error_loop_count", String(newCount))

                if (newCount >= 3) {
                    console.warn("Bucle de error detectado. Limpiando caché y cerrando sesión...")
                    localStorage.clear()
                    sessionStorage.clear()
                    window.location.href = "/"
                    return
                }
            } else {
                sessionStorage.setItem("error_loop_count", "1")
            }
            sessionStorage.setItem("last_error_at", String(now))
        }

        // ChunkLoadError = mismatch entre build viejo y build nuevo
        const isChunkLoadError =
            error.name === "ChunkLoadError" ||
            error.message.includes("Failed to load chunk") ||
            error.message.includes("ChunkLoadError") ||
            error.message.includes("dynamically imported module") ||
            error.message.includes("Loading chunk")

        if (isChunkLoadError) {
            // Limpiar service workers si existen
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.getRegistrations().then(registrations => {
                    registrations.forEach(r => r.unregister())
                })
            }
            // Forzar recarga con timestamp para bypassear cualquier caché del navegador
            const url = new URL(window.location.href)
            url.searchParams.set('_reload', Date.now().toString())
            window.location.replace(url.toString())
        }
    }, [error])

    const handleHardReset = () => {
        if (typeof window !== "undefined") {
            localStorage.clear()
            sessionStorage.clear()
            window.location.href = "/"
        }
    }

    return (
        <div className="flex flex-col items-center justify-center min-h-screen bg-slate-950 p-4">
            <div className="flex flex-col items-center gap-6 text-center max-w-md p-8 bg-slate-900 rounded-xl shadow-2xl border border-red-900/30 ring-1 ring-white/10">
                <div className="bg-red-500/20 p-4 rounded-full">
                    <AlertTriangle className="h-10 w-10 text-red-500" />
                </div>

                <div className="space-y-2">
                    <h2 className="text-2xl font-bold tracking-tight text-white">Algo salió mal</h2>
                    <p className="text-slate-400 text-sm">
                        La aplicación encontró un error inesperado. Esto puede ocurrir al actualizarse el sitio.
                    </p>
                    {error?.message && (
                        <div className="bg-black/40 p-2 rounded text-[10px] font-mono text-red-400 mt-2 break-all">
                            {error.message}
                        </div>
                    )}
                </div>

                <div className="grid grid-cols-1 gap-3 w-full mt-2">
                    <Button
                        onClick={() => {
                            localStorage.clear()
                            sessionStorage.clear()
                            window.location.replace(window.location.pathname + '?_reload=' + Date.now())
                        }}
                        className="bg-slate-800 hover:bg-slate-700 text-white"
                    >
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Recargar Página
                    </Button>

                    <div className="grid grid-cols-2 gap-2">
                        <Button
                            variant="outline"
                            onClick={() => reset()}
                            className="border-slate-800 text-slate-300 hover:bg-slate-800"
                        >
                            Reintentar
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleHardReset}
                            className="bg-red-900/50 hover:bg-red-800 text-red-200"
                        >
                            <LogOut className="mr-2 h-4 w-4" />
                            Cerrar Sesión
                        </Button>
                    </div>
                </div>

                <p className="text-[10px] text-slate-600 italic">
                    Si el problema persiste automáticamente, el sistema cerrará tu sesión para limpiar la caché.
                </p>
            </div>
        </div>
    )
}
