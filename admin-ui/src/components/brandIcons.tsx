import Icon from '@ant-design/icons'
import type { ComponentProps } from 'react'

type BrandIconProps = Omit<ComponentProps<typeof Icon>, 'component'>

function RutubeSvg() {
  return (
    <svg viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" aria-hidden>
      <path d="M5 4.75A2.75 2.75 0 0 1 7.75 2h8.5A2.75 2.75 0 0 1 19 4.75v14.5A2.75 2.75 0 0 1 16.25 22h-8.5A2.75 2.75 0 0 1 5 19.25V4.75Zm5.1 3.55v7.4L15.7 12l-5.6-3.7Z" />
    </svg>
  )
}

function AllohaSvg() {
  return (
    <svg viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" aria-hidden>
      <path d="M12 3 4 20.2h3.05l1.45-3.3h7l1.45 3.3H20L12 3Zm0 5.4 2.55 5.85h-5.1L12 8.4Z" />
    </svg>
  )
}

function KinopoiskSvg() {
  return (
    <svg viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" aria-hidden>
      <path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5v-13ZM6.2 6.2v2.1h1.8V6.2H6.2Zm0 4.7v2.2h1.8v-2.2H6.2Zm0 4.8v2.1h1.8v-2.1H6.2ZM16 6.2v2.1h1.8V6.2H16Zm0 4.7v2.2h1.8v-2.2H16Zm0 4.8v2.1h1.8v-2.1H16Z" />
      <path d="m12 8.1 1.05 2.2 2.4.3-1.8 1.65.5 2.35L12 13.45l-2.15 1.15.5-2.35-1.8-1.65 2.4-.3L12 8.1Z" />
    </svg>
  )
}

function TmdbSvg() {
  return (
    <svg viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" aria-hidden>
      <path d="M3.5 5.5A2.5 2.5 0 0 1 6 3h12a2.5 2.5 0 0 1 2.5 2.5v13A2.5 2.5 0 0 1 18 21H6a2.5 2.5 0 0 1-2.5-2.5v-13ZM7.2 7.1v9.8h2.1V14.2h2.35c2.35 0 3.85-1.25 3.85-3.35 0-2.05-1.5-3.75-3.9-3.75H7.2Zm2.1 1.85h2.05c1.2 0 1.9.65 1.9 1.7s-.7 1.7-1.9 1.7H9.3V8.95Z" />
    </svg>
  )
}

export function RutubeIcon(props: BrandIconProps) {
  return <Icon component={RutubeSvg} {...props} />
}

export function AllohaIcon(props: BrandIconProps) {
  return <Icon component={AllohaSvg} {...props} />
}

export function KinopoiskIcon(props: BrandIconProps) {
  return <Icon component={KinopoiskSvg} {...props} />
}

export function TmdbIcon(props: BrandIconProps) {
  return <Icon component={TmdbSvg} {...props} />
}
