"use client"

import { AppHeader } from "@/components/app-header"
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@/components/ui/breadcrumb"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Flame, Building2, Briefcase, Users } from "lucide-react"
import { CuerposTab } from "@/components/comercial/gestion/CuerposTab"
import { CompaniasTab } from "@/components/comercial/gestion/CompaniasTab"
import { EmpresasTab } from "@/components/comercial/gestion/EmpresasTab"
import { ContactosTab } from "@/components/comercial/gestion/ContactosTab"

export default function GestionClientesPage() {
  return (
    <div className="flex min-h-screen flex-col">
      <AppHeader />

      <main className="flex-1 p-4 md:p-6">
        <div className="mb-6">
          <Breadcrumb>
            <BreadcrumbList>
              <BreadcrumbItem>
                <BreadcrumbLink href="/comercial/cotizaciones">Comercial</BreadcrumbLink>
              </BreadcrumbItem>
              <BreadcrumbSeparator />
              <BreadcrumbItem>
                <BreadcrumbPage>Clientes</BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        </div>

        <div className="mb-4">
          <h1 className="text-2xl font-bold">Gestión de Clientes</h1>
          <p className="text-sm text-muted-foreground">
            Administra la Red de Bomberos (Cuerpos y Compañías), las Empresas/Particulares y sus Contactos.
          </p>
        </div>

        <Tabs defaultValue="cuerpos">
          <TabsList>
            <TabsTrigger value="cuerpos" className="gap-1.5">
              <Flame className="h-4 w-4" /> Cuerpos
            </TabsTrigger>
            <TabsTrigger value="companias" className="gap-1.5">
              <Building2 className="h-4 w-4" /> Compañías
            </TabsTrigger>
            <TabsTrigger value="empresas" className="gap-1.5">
              <Briefcase className="h-4 w-4" /> Empresas y Particulares
            </TabsTrigger>
            <TabsTrigger value="contactos" className="gap-1.5">
              <Users className="h-4 w-4" /> Contactos
            </TabsTrigger>
          </TabsList>

          <TabsContent value="cuerpos" className="mt-4">
            <CuerposTab />
          </TabsContent>
          <TabsContent value="companias" className="mt-4">
            <CompaniasTab />
          </TabsContent>
          <TabsContent value="empresas" className="mt-4">
            <EmpresasTab />
          </TabsContent>
          <TabsContent value="contactos" className="mt-4">
            <ContactosTab />
          </TabsContent>
        </Tabs>
      </main>
    </div>
  )
}
