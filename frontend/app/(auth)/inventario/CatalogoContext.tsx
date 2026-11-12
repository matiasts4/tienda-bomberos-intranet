"use client"

import React, { createContext, useContext, useState, useRef, useCallback } from "react"
import { api } from "@/lib/api"

type CatalogoItem = { id: number; nombre: string; familia_id?: number }
const catalogKeys = ["unidades", "origenes", "grupos", "familias", "subfamilias", "tipos", "formatos", "generos", "colores", "tallas"] as const
type Catalogos = Record<typeof catalogKeys[number], CatalogoItem[]>

type CatalogoContextType = {
  catalogos: Catalogos
  loading: boolean
  error: string | null
  isLoaded: boolean
  initCatalogos: () => Promise<Catalogos>
}

const CatalogoContext = createContext<CatalogoContextType | undefined>(undefined)

export function CatalogoProvider({ children }: { children: React.ReactNode }) {
  const [catalogos, setCatalogos] = useState<Catalogos>({
    unidades: [], origenes: [], grupos: [], familias: [], subfamilias: [],
    tipos: [], formatos: [], generos: [], colores: [], tallas: [],
  })

  const [loading, setLoading] = useState(false)
  const [isLoaded, setIsLoaded] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const loadedRef = useRef<Catalogos | null>(null)
  const requestRef = useRef<Promise<Catalogos> | null>(null)

  // Cada consumidor espera la misma petición, nunca datos vacíos provisionales.
  const initCatalogos = useCallback((): Promise<Catalogos> => {
    if (loadedRef.current) return Promise.resolve(loadedRef.current)
    if (requestRef.current) return requestRef.current
    setLoading(true)
    setError(null)
    const request = Promise.resolve().then(() => api.get<Catalogos>("/api/catalogos/todos"))
      .then(response => {
        if (!response || catalogKeys.some(key => !Array.isArray(response[key]))) {
          throw new Error("Respuesta de catálogos incompleta")
        }
        loadedRef.current = response
        setCatalogos(response)
        setIsLoaded(true)
        return response
      })
      .catch(error => {
        setError("No pudimos cargar los catálogos de productos. Revisa tu conexión y vuelve a intentarlo.")
        throw error
      })
      .finally(() => {
        setLoading(false)
        requestRef.current = null
      })
    requestRef.current = request
    return request
  }, [])

  return (
    <CatalogoContext.Provider value={{ catalogos, loading, error, isLoaded, initCatalogos }}>
      {children}
    </CatalogoContext.Provider>
  )
}

export function useCatalogos() {
  const context = useContext(CatalogoContext)
  if (!context) throw new Error("useCatalogos debe usarse dentro de CatalogoProvider")
  return context
}
