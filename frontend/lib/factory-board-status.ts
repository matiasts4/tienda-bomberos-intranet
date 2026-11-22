/** Normalize historical server aliases at the API boundary; packages keep their own status contract. */
export function factoryBoardStatus(status: string): string {
  return ({ 'in-cutting': 'cutting', 'in-assembly': 'cutting-finished', 'in-quality-control': 'quality-control',
    completed: 'finished', delivered: 'transit' } as Record<string, string>)[status] ?? status
}

export function cuttingOrderDeadline(createdAt?: string, estimatedDays?: number): string | undefined {
  if (!createdAt) return undefined
  const start = new Date(createdAt).getTime()
  const days = Number(estimatedDays ?? 7)
  if (!Number.isFinite(start) || !Number.isFinite(days) || days < 1) return undefined
  return new Date(start + days * 86400000).toISOString()
}
