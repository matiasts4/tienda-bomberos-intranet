"use client"

import React, { useState, useRef, KeyboardEvent, ClipboardEvent } from 'react'
import { X, Mail, AlertCircle, Plus } from 'lucide-react'

interface MultiEmailInputProps {
  id?: string
  value?: string
  onChange: (value: string) => void
  placeholder?: string
  disabled?: boolean
  className?: string
  helperText?: string
}

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

export function MultiEmailInput({
  id,
  value = "",
  onChange,
  placeholder = "Escribe un correo y presiona Enter o coma...",
  disabled = false,
  className = "",
  helperText,
}: MultiEmailInputProps) {
  const [inputValue, setInputValue] = useState("")
  const inputRef = useRef<HTMLInputElement>(null)

  // Parsear la cadena comma/semicolon-separated a arreglo
  const emails = value
    ? value
        .split(/[,;\n]+/)
        .map(e => e.trim())
        .filter(Boolean)
    : []

  const updateEmails = (newEmails: string[]) => {
    // Normalizar a string separado por comas
    const cleanList = Array.from(new Set(newEmails.map(e => e.trim()).filter(Boolean)))
    onChange(cleanList.join(", "))
  }

  const addEmailsFromText = (text: string) => {
    if (!text.trim()) return
    const incoming = text
      .split(/[,;\s\n]+/)
      .map(e => e.trim())
      .filter(Boolean)
    
    if (incoming.length > 0) {
      updateEmails([...emails, ...incoming])
      setInputValue("")
    }
  }

  const handleKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
    if (disabled) return

    if (e.key === "Enter" || e.key === "," || e.key === ";" || e.key === "Tab") {
      if (inputValue.trim()) {
        e.preventDefault()
        addEmailsFromText(inputValue)
      }
    } else if (e.key === "Backspace" && !inputValue && emails.length > 0) {
      // Borrar el último tag si el input está vacío
      e.preventDefault()
      const newEmails = [...emails]
      newEmails.pop()
      updateEmails(newEmails)
    }
  }

  const handleBlur = () => {
    if (inputValue.trim()) {
      addEmailsFromText(inputValue)
    }
  }

  const handlePaste = (e: ClipboardEvent<HTMLInputElement>) => {
    e.preventDefault()
    const pasted = e.clipboardData.getData("text")
    if (pasted) {
      addEmailsFromText(pasted)
    }
  }

  const removeEmail = (indexToRemove: number) => {
    if (disabled) return
    const newEmails = emails.filter((_, idx) => idx !== indexToRemove)
    updateEmails(newEmails)
  }

  return (
    <div className={`space-y-1.5 ${className}`}>
      <div 
        onClick={() => inputRef.current?.focus()}
        className={`min-h-[42px] w-full rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs transition-colors cursor-text flex flex-wrap items-center gap-1.5 focus-within:border-slate-400 focus-within:ring-2 focus-within:ring-slate-400/20 ${
          disabled ? 'opacity-60 cursor-not-allowed bg-slate-50' : ''
        }`}
      >
        {emails.map((email, idx) => {
          const isValid = EMAIL_REGEX.test(email)
          return (
            <span
              key={`${email}-${idx}`}
              className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium border transition-all ${
                isValid
                  ? 'bg-slate-100/90 text-slate-800 border-slate-200/80 hover:bg-slate-200/80'
                  : 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100'
              }`}
            >
              {isValid ? (
                <Mail className="w-3 h-3 text-slate-400 shrink-0" />
              ) : (
                <AlertCircle className="w-3 h-3 text-red-500 shrink-0" />
              )}
              <span className="max-w-[220px] truncate" title={email}>{email}</span>
              {!disabled && (
                <button
                  type="button"
                  onClick={(e) => {
                    e.stopPropagation()
                    removeEmail(idx)
                  }}
                  className="p-0.5 rounded-full hover:bg-black/10 text-slate-400 hover:text-slate-700 transition-colors ml-0.5"
                  title="Eliminar correo"
                >
                  <X className="w-3 h-3" />
                </button>
              )}
            </span>
          )
        })}

        <input
          ref={inputRef}
          id={id}
          type="text"
          value={inputValue}
          onChange={(e) => setInputValue(e.target.value)}
          onKeyDown={handleKeyDown}
          onBlur={handleBlur}
          onPaste={handlePaste}
          disabled={disabled}
          placeholder={emails.length === 0 ? placeholder : "Agregar otro correo..."}
          className="flex-1 min-w-[140px] bg-transparent border-none outline-none text-xs text-slate-800 placeholder:text-slate-400 disabled:cursor-not-allowed h-7"
        />
      </div>

      <div className="flex items-center justify-between text-[10px] text-slate-400 px-0.5">
        <span>{helperText || "Separa con coma (,), punto y coma (;) o presiona Enter."}</span>
        {emails.length > 0 && (
          <span className="font-semibold text-slate-500">
            {emails.length} {emails.length === 1 ? 'correo' : 'correos'}
          </span>
        )}
      </div>
    </div>
  )
}
