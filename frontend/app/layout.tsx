import type React from "react"
import type { Metadata } from "next"
import { Geist, Geist_Mono } from "next/font/google"
import "./globals.css"
import 'goey-toast/styles.css'

// 👇 Lo importante: Seguimos usando AppProviders (pero ahora él ya no bloquea el login)
import { AppProviders } from "@/components/AppProviders"
import { Toaster } from "@/components/ui/toaster"

const geist = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
})

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
})

export const metadata: Metadata = {
  title: "Tienda Bomberos - Sistema de Gestión",
  description: "Plataforma interna para gestionar pedidos de ropa y equipamiento para bomberos",
  generator: "v0.app",
  icons: {
    icon: [
      { url: "/icon-light-32x32.png", media: "(prefers-color-scheme: light)" },
      { url: "/icon-dark-32x32.png", media: "(prefers-color-scheme: dark)" },
      { url: "/icon.svg", type: "image/svg+xml" },
    ],
    apple: "/apple-icon.png",
  },
}

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode
}>) {
  return (
    <html lang="es">
      <body
        className={`${geist.variable} ${geistMono.variable} font-sans antialiased`}
      >
        {/* Aquí AppProviders ya NO muestra spinner de carga global.
           Renderiza 'children' inmediatamente (Login rápido).
           Pero mantiene el CatalogoProvider vivo en memoria.
        */}
        <AppProviders>
          {children}
        </AppProviders>

        <Toaster />
      </body>
    </html>
  )
}
