import { Button } from "@/components/ui/button"
import { RefreshCcw as RefreshIcon, Save, Loader2 } from "lucide-react"

interface BulkEditFloatingPanelProps {
    changesCount: number
    onSave: () => Promise<void>
    onCancel: () => void
    isSaving: boolean
}

export function BulkEditFloatingPanel({
    changesCount,
    onSave,
    onCancel,
    isSaving
}: BulkEditFloatingPanelProps) {
    if (changesCount === 0) return null

    return (
        <div className="fixed bottom-6 right-6 z-[100] animate-in slide-in-from-bottom-5 fade-in duration-300">
            <div className="bg-gray-900 text-white rounded-lg shadow-2xl p-4 flex items-center gap-4 border border-gray-700">
                <div className="flex flex-col">
                    <span className="font-bold text-sm flex items-center gap-2">
                        <RefreshIcon className="h-4 w-4 text-blue-400 animate-pulse" />
                        Cambios pendientes
                    </span>
                    <span className="text-xs text-gray-400">
                        {changesCount} productos modificados
                    </span>
                </div>
                <div className="h-8 w-[1px] bg-gray-700 mx-2"></div>
                <div className="flex gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={onCancel}
                        className="text-gray-300 hover:text-white hover:bg-gray-800 h-9"
                    >
                        Descartar
                    </Button>
                    <Button
                        onClick={onSave}
                        disabled={isSaving}
                        className="bg-blue-600 hover:bg-blue-700 text-white font-bold h-9 shadow-lg shadow-blue-900/20"
                    >
                        {isSaving ? (
                            <Loader2 className="animate-spin h-4 w-4 mr-2" />
                        ) : (
                            <Save className="h-4 w-4 mr-2" />
                        )}
                        GUARDAR CAMBIOS
                    </Button>
                </div>
            </div>
        </div>
    )
}
