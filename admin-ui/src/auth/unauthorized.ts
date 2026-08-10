type UnauthorizedHandler = (requestEpoch?: number) => void

let handler: UnauthorizedHandler | null = null

export function setUnauthorizedHandler(fn: UnauthorizedHandler | null): void {
  handler = fn
}

export function notifyUnauthorized(requestEpoch?: number): void {
  handler?.(requestEpoch)
}
