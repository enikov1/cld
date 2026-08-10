import { Modal } from 'antd'
import MediaLibraryPage, { type MediaItem } from '../pages/MediaLibraryPage'

type MediaPickerModalProps = {
  open: boolean
  onClose: () => void
  onSelect: (url: string, item: MediaItem) => void
  title?: string
  typeFilter?: 'poster' | 'branding' | 'all'
}

export default function MediaPickerModal({
  open,
  onClose,
  onSelect,
  title = 'Выбрать из медиатеки',
  typeFilter = 'poster',
}: MediaPickerModalProps) {
  return (
    <Modal
      open={open}
      onCancel={onClose}
      footer={null}
      title={title}
      width={920}
      destroyOnHidden
      styles={{ body: { maxHeight: '70vh', overflow: 'auto', paddingTop: 8 } }}
    >
      <MediaLibraryPage
        picker
        typeFilter={typeFilter}
        onPick={(item) => {
          onSelect(item.url, item)
          onClose()
        }}
      />
    </Modal>
  )
}

export type { MediaItem }
