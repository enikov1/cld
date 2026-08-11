import type { Area } from 'react-easy-crop'

function createImage(url: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const tryLoad = (withCors: boolean) => {
      const image = new Image()
      if (withCors && !url.startsWith('blob:') && !url.startsWith('data:')) {
        image.crossOrigin = 'anonymous'
      }
      image.onload = () => resolve(image)
      image.onerror = () => {
        if (withCors && /^https?:\/\//i.test(url)) {
          // Last resort for export — may taint canvas; prefer same-origin URLs.
          tryLoad(false)
          return
        }
        reject(new Error('Не удалось загрузить изображение для обрезки'))
      }
      image.src = url
    }
    // Relative /storage is same-origin — no CORS needed.
    tryLoad(/^https?:\/\//i.test(url))
  })
}

function toJpegFile(blob: Blob, fileName: string): File {
  const base = fileName.replace(/\.[^.]+$/, '') || 'brand'
  return new File([blob], `${base}.jpg`, {
    type: 'image/jpeg',
    lastModified: Date.now(),
  })
}

/**
 * Crops imageSrc to pixelCrop and returns a JPEG File.
 * Caps longest side at maxDimension to keep uploads reasonable.
 */
export async function getCroppedImageFile(
  imageSrc: string,
  pixelCrop: Area,
  fileName = 'brand.jpg',
  maxDimension = 1920,
  quality = 0.9,
): Promise<File> {
  const image = await createImage(imageSrc)
  const sourceCanvas = document.createElement('canvas')
  sourceCanvas.width = Math.max(1, Math.round(pixelCrop.width))
  sourceCanvas.height = Math.max(1, Math.round(pixelCrop.height))
  const ctx = sourceCanvas.getContext('2d')
  if (!ctx) {
    throw new Error('Canvas недоступен')
  }

  ctx.imageSmoothingEnabled = true
  ctx.imageSmoothingQuality = 'high'
  ctx.drawImage(
    image,
    pixelCrop.x,
    pixelCrop.y,
    pixelCrop.width,
    pixelCrop.height,
    0,
    0,
    sourceCanvas.width,
    sourceCanvas.height,
  )

  let outW = sourceCanvas.width
  let outH = sourceCanvas.height
  const longest = Math.max(outW, outH)
  if (longest > maxDimension) {
    const scale = maxDimension / longest
    outW = Math.max(1, Math.round(outW * scale))
    outH = Math.max(1, Math.round(outH * scale))
  }

  const outCanvas = document.createElement('canvas')
  outCanvas.width = outW
  outCanvas.height = outH
  const outCtx = outCanvas.getContext('2d')
  if (!outCtx) {
    throw new Error('Canvas недоступен')
  }
  outCtx.imageSmoothingEnabled = true
  outCtx.imageSmoothingQuality = 'high'
  outCtx.drawImage(sourceCanvas, 0, 0, outW, outH)

  const blob = await new Promise<Blob>((resolve, reject) => {
    outCanvas.toBlob(
      (b) => {
        if (!b) {
          reject(new Error('Не удалось сохранить обрезанное изображение'))
          return
        }
        resolve(b)
      },
      'image/jpeg',
      quality,
    )
  })

  return toJpegFile(blob, fileName)
}
