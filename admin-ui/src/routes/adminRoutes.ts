import type { AdminPageKey } from '../types'

export const ADMIN_ROUTES: Record<AdminPageKey, string> = {
  dashboard: '/',
  'nav-menu': '/nav-menu',
  'home-sections': '/home-sections',
  reactions: '/reactions',
  taxonomy: '/taxonomy',
  series: '/series',
  media: '/media',
  collections: '/collections',
  studios: '/studios',
  comments: '/comments',
  reviews: '/reviews',
  'player-reports': '/player-reports',
  'cron-runs': '/cron-runs',
  users: '/users',
  'search-stats': '/search-stats',
  'views-stats': '/views-stats',
  redirects: '/redirects',
  settings: '/settings',
  templates: '/templates',
  'tpl-docs': '/tpl-docs',
  sync: '/sync',
  'alloha-sync': '/alloha-sync',
  'rutube-sync': '/rutube-sync',
  backup: '/backup',
  'admin-access': '/admin-access',
  'audit-log': '/audit-log',
}

export const pageMeta: Record<AdminPageKey, { title: string; subtitle?: string }> = {
  dashboard: { title: 'Обзор', subtitle: 'Статистика и быстрые действия' },
  'nav-menu': { title: 'Меню', subtitle: 'Конструктор навигации и mega-menu' },
  'home-sections': { title: 'Секции главной', subtitle: 'Конструктор блоков с фильтрами под студиями' },
  reactions: { title: 'Реакции', subtitle: 'Виджет оценок под плеером и статистика голосов' },
  taxonomy: { title: 'Справочники', subtitle: 'Жанры, страны, актёры, озвучки, блоки на главной' },
  series: { title: 'Сериалы', subtitle: 'Карточки контента и плеер на сайте' },
  media: { title: 'Медиатека', subtitle: 'Постеры и брендинг — загрузка и повторное использование' },
  collections: { title: 'Подборки', subtitle: 'Тематические списки сериалов' },
  studios: { title: 'Студии', subtitle: 'Студии и их каталоги сериалов' },
  comments: { title: 'Комментарии', subtitle: 'Модерация пользовательских отзывов' },
  reviews: { title: 'Рецензии', subtitle: 'Редакционные и пользовательские рецензии с оценкой' },
  'player-reports': { title: 'Жалобы на плеер', subtitle: 'Сообщения о проблемах со страницы сериала' },
  'cron-runs': { title: 'История задач', subtitle: 'Лог автоматических и ручных синхронизаций' },
  users: { title: 'Пользователи', subtitle: 'Аккаунты, роли, IP и блокировка' },
  'search-stats': { title: 'Поиск', subtitle: 'Статистика поисковых запросов' },
  'views-stats': { title: 'Просмотры', subtitle: 'Динамика и топ сериалов по дням, неделям и месяцам' },
  redirects: { title: 'Редиректы', subtitle: 'Перенаправления URL и страниц сериалов' },
  settings: { title: 'Настройки', subtitle: 'Брендинг, авторизация, комментарии, SEO и шаблон' },
  templates: { title: 'Шаблоны', subtitle: 'Редактор .tpl, справка по тегам и подсказки' },
  'tpl-docs': {
    title: 'TPL-DOC',
    subtitle: 'Полная справка по шаблонам для верстальщика — с поиском и скачиванием',
  },
  sync: { title: 'KinoPoisk', subtitle: 'Импорт сериалов с прогрессом и паузой' },
  'alloha-sync': { title: 'Alloha', subtitle: 'Автообновление, latest и синхронизация' },
  'rutube-sync': { title: 'Rutube', subtitle: 'Массовая простановка трейлеров' },
  backup: { title: 'Бэкапы', subtitle: 'Готовые архивы и настройки резервного копирования' },
  'admin-access': { title: 'Токены доступа', subtitle: 'Создание, права по разделам, перевыпуск и отзыв' },
  'audit-log': { title: 'Журнал аудита', subtitle: 'История действий администраторов' },
}

export function pageKeyFromPath(pathname: string): AdminPageKey {
  const path = pathname.replace(/\/+$/, '') || '/'

  if (path === '/') {
    return 'dashboard'
  }

  if (path === '/tpl-docs' || path.startsWith('/tpl-docs/')) {
    return 'tpl-docs'
  }

  const entry = Object.entries(ADMIN_ROUTES).find(([, route]) => route !== '/' && path === route)
  return (entry?.[0] as AdminPageKey | undefined) ?? 'dashboard'
}
