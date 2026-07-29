import type { AdminPageKey } from '../types'

export const ADMIN_ROUTES: Record<AdminPageKey, string> = {
  dashboard: '/',
  'nav-menu': '/nav-menu',
  'home-sections': '/home-sections',
  reactions: '/reactions',
  taxonomy: '/taxonomy',
  series: '/series',
  collections: '/collections',
  studios: '/studios',
  comments: '/comments',
  'player-reports': '/player-reports',
  'cron-runs': '/cron-runs',
  users: '/users',
  'search-stats': '/search-stats',
  settings: '/settings',
  templates: '/templates',
  sync: '/sync',
  'alloha-sync': '/alloha-sync',
  backup: '/backup',
}

export const pageMeta: Record<AdminPageKey, { title: string; subtitle?: string }> = {
  dashboard: { title: 'Обзор', subtitle: 'Статистика и быстрые действия' },
  'nav-menu': { title: 'Меню', subtitle: 'Конструктор навигации и mega-menu' },
  'home-sections': { title: 'Секции главной', subtitle: 'Конструктор блоков с фильтрами под студиями' },
  reactions: { title: 'Реакции', subtitle: 'Виджет оценок под плеером на странице сериала' },
  taxonomy: { title: 'Справочники', subtitle: 'Жанры, страны, актёры, блоки на главной' },
  series: { title: 'Сериалы', subtitle: 'Карточки контента и плеер на сайте' },
  collections: { title: 'Подборки', subtitle: 'Тематические списки сериалов' },
  studios: { title: 'Студии', subtitle: 'Студии и их каталоги сериалов' },
  comments: { title: 'Комментарии', subtitle: 'Модерация пользовательских отзывов' },
  'player-reports': { title: 'Жалобы на плеер', subtitle: 'Сообщения о проблемах со страницы сериала' },
  'cron-runs': { title: 'История задач', subtitle: 'Лог автоматических и ручных синхронизаций' },
  users: { title: 'Пользователи', subtitle: 'Аккаунты, роли, IP и блокировка' },
  'search-stats': { title: 'Поиск', subtitle: 'Статистика поисковых запросов' },
  settings: { title: 'Настройки', subtitle: 'Брендинг, авторизация, комментарии, SEO и шаблон' },
  templates: { title: 'Шаблоны', subtitle: 'Редактор .tpl, справка по тегам и подсказки' },
  sync: { title: 'KinoPoisk', subtitle: 'Импорт сериалов через kp:sync' },
  'alloha-sync': { title: 'Alloha', subtitle: 'Автообновление, latest и синхронизация' },
  backup: { title: 'Бэкапы', subtitle: 'Автоматическое резервное копирование БД и файлов' },
}

export function pageKeyFromPath(pathname: string): AdminPageKey {
  const path = pathname.replace(/\/+$/, '') || '/'

  if (path === '/') {
    return 'dashboard'
  }

  const entry = Object.entries(ADMIN_ROUTES).find(([, route]) => route !== '/' && path === route)
  return (entry?.[0] as AdminPageKey | undefined) ?? 'dashboard'
}
