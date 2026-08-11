import { Alert, Button, Modal, Segmented, Slider, Space, Spin, Typography, message } from 'antd'
import { useCallback, useEffect, useMemo, useState } from 'react'
import Cropper, { type Area, type Point } from 'react-easy-crop'
import { getCroppedImageFile } from '../utils/cropImage'

export type BrandImageEditorResult = {
  file: File
  width: number
  height: number
}

type AspectPreset = {
  key: string
  label: string
  /** null = freeform via height ratio slider */
  aspect: number | null
}

const ASPECT_PRESETS: AspectPreset[] = [
  { key: '3:1', label: 'Баннер 3:1', aspect: 3 },
  { key: '2:1', label: 'Широкий 2:1', aspect: 2 },
  { key: '16:9', label: '16:9', aspect: 16 / 9 },
  { key: '21:9', label: '21:9', aspect: 21 / 9 },
  { key: 'custom', label: 'Своя высота', aspect: null },
]

function isValidArea(area: Area | null): area is Area {
  return Boolean(
    area
    && Number.isFinite(area.width)
    && Number.isFinite(area.height)
    && area.width > 1
    && area.height > 1,
  )
}

type Props = {
  open: boolean
  imageSrc: string | null
  fileName?: string
  title?: string
  confirmLabel?: string
  onCancel: () => void
  onConfirm: (result: BrandImageEditorResult) => void | Promise<void>
}

export default function BrandImageEditorModal({
  open,
  imageSrc,
  fileName = 'brand.jpg',
  title = 'Подгонка фона (бренда)',
  confirmLabel = 'Применить и загрузить',
  onCancel,
  onConfirm,
}: Props) {
  const [crop, setCrop] = useState<Point>({ x: 0, y: 0 })
  const [zoom, setZoom] = useState(1)
  const [presetKey, setPresetKey] = useState<string>('3:1')
  /** Relative banner height for custom mode: higher = taller crop (smaller aspect). */
  const [heightRatio, setHeightRatio] = useState(35)
  const [croppedAreaPixels, setCroppedAreaPixels] = useState<Area | null>(null)
  const [saving, setSaving] = useState(false)
  /** Wait until Modal animation finishes — Cropper needs a real-sized container. */
  const [stageReady, setStageReady] = useState(false)
  const [imageStatus, setImageStatus] = useState<'idle' | 'loading' | 'ready' | 'error'>('idle')
  const [imageError, setImageError] = useState('')

  useEffect(() => {
    if (!open) {
      setStageReady(false)
      setImageStatus('idle')
      setImageError('')
      return
    }
    setCrop({ x: 0, y: 0 })
    setZoom(1)
    setPresetKey('3:1')
    setHeightRatio(35)
    setCroppedAreaPixels(null)
  }, [open, imageSrc])

  useEffect(() => {
    if (!open || !imageSrc) {
      setImageStatus('idle')
      return
    }

    let cancelled = false
    setImageStatus('loading')
    setImageError('')

    const img = new Image()
    // Same-origin (/storage via proxy) must NOT use crossOrigin — otherwise
    // a missing CORS header makes the preload fail while <img> would work.
    const isExternal = /^https?:\/\//i.test(imageSrc)
    if (isExternal) {
      img.crossOrigin = 'anonymous'
    }
    img.onload = () => {
      if (cancelled) return
      if (!img.naturalWidth || !img.naturalHeight) {
        setImageStatus('error')
        setImageError('Изображение загрузилось без размеров')
        return
      }
      setImageStatus('ready')
    }
    img.onerror = () => {
      if (cancelled) return
      // Retry once without CORS for odd CDNs that block anonymous but allow display.
      if (isExternal && img.crossOrigin) {
        const retry = new Image()
        retry.onload = () => {
          if (cancelled) return
          setImageStatus('ready')
        }
        retry.onerror = () => {
          if (cancelled) return
          setImageStatus('error')
          setImageError(
            'Не удалось загрузить изображение. Если это внешний URL — сначала скачайте его на сервер, затем откройте «Подогнать кадр».',
          )
        }
        retry.src = imageSrc
        return
      }
      setImageStatus('error')
      setImageError(
        'Не удалось загрузить изображение. Если это внешний URL — сначала скачайте его на сервер, затем откройте «Подогнать кадр».',
      )
    }
    img.src = imageSrc

    return () => {
      cancelled = true
    }
  }, [open, imageSrc])

  const aspect = useMemo(() => {
    const preset = ASPECT_PRESETS.find((p) => p.key === presetKey)
    if (!preset || preset.aspect != null) {
      return preset?.aspect ?? 3
    }
    const h = Math.max(20, Math.min(70, heightRatio))
    return 100 / h
  }, [presetKey, heightRatio])

  const showCropper = open && stageReady && imageStatus === 'ready' && Boolean(imageSrc)

  const onCropComplete = useCallback((_area: Area, pixels: Area) => {
    setCroppedAreaPixels(isValidArea(pixels) ? pixels : null)
  }, [])

  async function handleOk() {
    if (!imageSrc || !isValidArea(croppedAreaPixels)) {
      message.warning('Дождитесь загрузки картинки и подгоните кадр')
      return
    }
    setSaving(true)
    try {
      const file = await getCroppedImageFile(imageSrc, croppedAreaPixels, fileName, 1920, 0.9)
      await onConfirm({
        file,
        width: Math.round(croppedAreaPixels.width),
        height: Math.round(croppedAreaPixels.height),
      })
    } catch (e) {
      message.error(String((e as Error).message || e))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal
      open={open}
      title={title}
      onCancel={saving ? undefined : onCancel}
      width={920}
      destroyOnHidden
      afterOpenChange={(visible) => {
        if (visible) {
          // Double rAF: layout after Ant Design modal transition.
          requestAnimationFrame(() => {
            requestAnimationFrame(() => setStageReady(true))
          })
        } else {
          setStageReady(false)
        }
      }}
      footer={
        <Space>
          <Button disabled={saving} onClick={onCancel}>
            Отмена
          </Button>
          <Button
            type="primary"
            loading={saving}
            disabled={!showCropper || !isValidArea(croppedAreaPixels)}
            onClick={() => void handleOk()}
          >
            {confirmLabel}
          </Button>
        </Space>
      }
    >
      <Typography.Paragraph type="secondary" style={{ marginTop: 0 }}>
        Обрежьте кадр под фон сайта: перетаскивайте картинку, меняйте зум и соотношение. На сервере к бренду
        дополнительно применится мягкое затухание краёв.
      </Typography.Paragraph>

      <div className="brand-image-editor__presets">
        <Segmented
          value={presetKey}
          onChange={(v) => setPresetKey(String(v))}
          options={ASPECT_PRESETS.map((p) => ({ value: p.key, label: p.label }))}
        />
      </div>

      {presetKey === 'custom' ? (
        <div className="brand-image-editor__control">
          <Typography.Text type="secondary">Высота кадра</Typography.Text>
          <Slider
            min={20}
            max={70}
            value={heightRatio}
            onChange={setHeightRatio}
            tooltip={{ formatter: (v) => `${v}%` }}
          />
        </div>
      ) : null}

      <div className="brand-image-editor__stage">
        {imageStatus === 'error' ? (
          <div className="brand-image-editor__status">
            <Alert type="error" showIcon message={imageError || 'Ошибка загрузки изображения'} />
          </div>
        ) : null}
        {imageStatus === 'loading' || (imageStatus === 'ready' && !stageReady) ? (
          <div className="brand-image-editor__status">
            <Spin tip="Загрузка изображения…" />
          </div>
        ) : null}
        {showCropper ? (
          <Cropper
            key={`${imageSrc}-${aspect.toFixed(4)}`}
            image={imageSrc!}
            crop={crop}
            zoom={zoom}
            minZoom={1}
            maxZoom={3}
            aspect={aspect}
            onCropChange={setCrop}
            onZoomChange={setZoom}
            onCropComplete={onCropComplete}
            onMediaLoaded={() => {
              setZoom(1)
              setCrop({ x: 0, y: 0 })
            }}
            showGrid
            objectFit="horizontal-cover"
          />
        ) : null}
      </div>

      <div className="brand-image-editor__control">
        <Typography.Text type="secondary">Масштаб</Typography.Text>
        <Slider
          min={1}
          max={3}
          step={0.05}
          value={zoom}
          disabled={!showCropper}
          onChange={setZoom}
        />
      </div>

      {isValidArea(croppedAreaPixels) ? (
        <Typography.Text type="secondary">
          Область: {Math.round(croppedAreaPixels.width)} × {Math.round(croppedAreaPixels.height)} px (на сервер —
          до 1920 по длинной стороне)
        </Typography.Text>
      ) : (
        <Typography.Text type="secondary">Область обрезки появится после загрузки кадра</Typography.Text>
      )}
    </Modal>
  )
}
