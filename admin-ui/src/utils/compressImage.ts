const HARD_MAX_BYTES = 2 * 1024 * 1024
/** Soft ceiling (~iLoveIMG-style result for ~1 MB source). */
const TARGET_CEILING_BYTES = 130 * 1024
const TARGET_FLOOR_BYTES = 64 * 1024
/** Keep ~11% of original size (~89% reduction). */
const TARGET_KEEP_RATIO = 0.11
const DEFAULT_MAX_DIMENSION = 1600
const MIN_QUALITY = 0.28
const MAX_QUALITY = 0.82
const MIN_DIMENSION = 640

const SKIP_MIME = new Set([
  'image/svg+xml',
  'image/gif',
  'image/x-icon',
  'image/vnd.microsoft.icon',
])

type CompressOptions = {
  targetBytes?: number
  maxBytes?: number
  maxDimension?: number
}

function extensionOf(name: string): string {
  return (name.split('.').pop() ?? '').toLowerCase()
}

export function isCompressibleImage(file: File): boolean {
  const ext = extensionOf(file.name)
  if (['svg', 'gif', 'ico'].includes(ext)) {
    return false
  }
  if (file.type && SKIP_MIME.has(file.type)) {
    return false
  }
  if (file.type.startsWith('image/')) {
    return true
  }
  // Some browsers leave type empty — trust extension.
  return ['jpg', 'jpeg', 'png', 'webp', 'bmp'].includes(ext)
}

function loadImage(file: File): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file)
    const img = new Image()
    img.onload = () => {
      URL.revokeObjectURL(url)
      resolve(img)
    }
    img.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('Не удалось прочитать изображение'))
    }
    img.src = url
  })
}

function canvasToBlob(canvas: HTMLCanvasElement, type: string, quality: number): Promise<Blob> {
  return new Promise((resolve, reject) => {
    canvas.toBlob(
      (blob) => {
        if (!blob) {
          reject(new Error('Не удалось сжать изображение'))
          return
        }
        resolve(blob)
      },
      type,
      quality,
    )
  })
}

function scaledSize(width: number, height: number, maxDimension: number): { width: number; height: number } {
  const longest = Math.max(width, height)
  if (longest <= maxDimension) {
    return { width, height }
  }
  const scale = maxDimension / longest
  return {
    width: Math.max(1, Math.round(width * scale)),
    height: Math.max(1, Math.round(height * scale)),
  }
}

function drawToCanvas(img: HTMLImageElement, width: number, height: number): HTMLCanvasElement {
  const canvas = document.createElement('canvas')
  canvas.width = width
  canvas.height = height
  const ctx = canvas.getContext('2d')
  if (!ctx) {
    throw new Error('Canvas недоступен')
  }
  ctx.fillStyle = '#111111'
  ctx.fillRect(0, 0, width, height)
  ctx.imageSmoothingEnabled = true
  ctx.imageSmoothingQuality = 'high'
  ctx.drawImage(img, 0, 0, width, height)
  return canvas
}

function replaceExtension(name: string, ext: string): string {
  const base = name.replace(/\.[^.]+$/, '') || 'image'
  return `${base}.${ext}`
}

function toJpegFile(blob: Blob, originalName: string): File {
  return new File([blob], replaceExtension(originalName, 'jpg'), {
    type: 'image/jpeg',
    lastModified: Date.now(),
  })
}

function resolveTargetBytes(fileSize: number, override?: number): number {
  if (typeof override === 'number' && override > 0) {
    return override
  }
  const byRatio = Math.round(fileSize * TARGET_KEEP_RATIO)
  return Math.min(TARGET_CEILING_BYTES, Math.max(TARGET_FLOOR_BYTES, byRatio))
}

/**
 * Aggressively compresses PNG/JPG/WebP (iLoveIMG-like size reduction).
 * Always re-encodes as JPEG; aims for ~89% size cut, never exceeds 2 MB.
 */
export async function compressImageForUpload(file: File, options: CompressOptions = {}): Promise<File> {
  if (!isCompressibleImage(file)) {
    return file
  }

  const targetBytes = resolveTargetBytes(file.size, options.targetBytes)
  const maxBytes = options.maxBytes ?? HARD_MAX_BYTES
  const maxDimension = options.maxDimension ?? DEFAULT_MAX_DIMENSION

  const img = await loadImage(file)
  let { width, height } = scaledSize(img.naturalWidth || img.width, img.naturalHeight || img.height, maxDimension)

  let bestUnderTarget: Blob | null = null
  let bestUnderMax: Blob | null = null
  let smallestOverall: Blob | null = null

  for (let pass = 0; pass < 10; pass += 1) {
    const canvas = drawToCanvas(img, width, height)
    let lo = MIN_QUALITY
    let hi = MAX_QUALITY

    for (let i = 0; i < 10; i += 1) {
      const quality = (lo + hi) / 2
      const blob = await canvasToBlob(canvas, 'image/jpeg', quality)

      if (!smallestOverall || blob.size < smallestOverall.size) {
        smallestOverall = blob
      }
      if (blob.size <= maxBytes && (!bestUnderMax || blob.size > bestUnderMax.size)) {
        bestUnderMax = blob
      }
      if (blob.size <= targetBytes) {
        if (!bestUnderTarget || blob.size > bestUnderTarget.size) {
          bestUnderTarget = blob
        }
        lo = quality
      } else {
        hi = quality
      }
    }

    const lowBlob = await canvasToBlob(canvas, 'image/jpeg', MIN_QUALITY)
    if (!smallestOverall || lowBlob.size < smallestOverall.size) {
      smallestOverall = lowBlob
    }
    if (lowBlob.size <= maxBytes && (!bestUnderMax || lowBlob.size > bestUnderMax.size)) {
      bestUnderMax = lowBlob
    }
    if (lowBlob.size <= targetBytes && (!bestUnderTarget || lowBlob.size > bestUnderTarget.size)) {
      bestUnderTarget = lowBlob
    }

    if (bestUnderTarget) {
      break
    }

    const nextLongest = Math.max(width, height) * 0.78
    if (nextLongest < MIN_DIMENSION) {
      break
    }
    width = Math.max(1, Math.round(width * 0.78))
    height = Math.max(1, Math.round(height * 0.78))
  }

  const chosen =
    bestUnderTarget ??
    (smallestOverall && smallestOverall.size <= maxBytes ? smallestOverall : null) ??
    (bestUnderMax && bestUnderMax.size <= maxBytes ? bestUnderMax : null)

  if (!chosen || chosen.size > maxBytes) {
    throw new Error('Не удалось сжать изображение до 2 МБ. Выберите файл меньшего размера.')
  }

  return toJpegFile(chosen, file.name)
}

/** Compress every compressible image field in a FormData payload. */
export async function compressFormDataImages(formData: FormData): Promise<FormData> {
  const next = new FormData()
  for (const [key, value] of formData.entries()) {
    if (value instanceof File && isCompressibleImage(value)) {
      const compressed = await compressImageForUpload(value)
      next.append(key, compressed, compressed.name)
    } else {
      next.append(key, value)
    }
  }
  return next
}
