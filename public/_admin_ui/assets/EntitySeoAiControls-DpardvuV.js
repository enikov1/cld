import{r as e}from"./rolldown-runtime-QTnfLwEv.js";import{Yr as t,n,t as r}from"./jsx-runtime-BMNAXVJB.js";import{n as i,r as a,t as o}from"./typography-Chd1CYZB.js";import{t as s}from"./message-CAcdUTeI.js";import{t as c}from"./modal-BWmTODO_.js";import{a as l,t as u}from"./space-PlkkO4Yh.js";import{i as d,r as f}from"./AdminDocumentMeta-keep_iEf.js";import{t as p}from"./ImportOutlined-D3ophq6b.js";import{D as m}from"./index-D-1SFrvE.js";var h=e(t(),1),g=`studio_seo_ai_prompt`,_=`collection_seo_ai_prompt`,v=`Ты — SEO-редактор каталога зарубежных сериалов.

Задача: для страницы студии «{name}» подготовь meta_title, description, meta_description и SEO-блок (HTML) для сайта зарубежных сериалов.

Контекст страницы:
- Студия: {name}
- URL: {url}

Цель: тексты должны быть полезными для людей и дружелюбными к Яндексу/Google — естественные формулировки, коммерческий интент «смотреть онлайн», без переспама и воды.

ПРАВИЛА meta_title (до ~70 символов):
- Включи название студии и акцент на зарубежные сериалы
- Естественно встрой 1–2 фразы: смотреть онлайн, в HD качестве, бесплатно
- Пример: «Сериалы USA Network смотреть онлайн в HD качестве бесплатно»

ПРАВИЛА description (2–4 предложения, обычный текст без HTML):
- Живое описание студии и её зарубежных сериалов
- Без обязательных SEO-штампов в каждом предложении
- Без markdown и кавычек-ёлочек

ПРАВИЛА meta_description (120–160 символов):
- 1–2 предложения: что пользователь найдёт на странице студии
- Упомяни зарубежные сериалы и смотреть онлайн / бесплатно / HD без набивки ключей
- Без клише вроде «лучший сайт»

ПРАВИЛА seo_html:
- 2–4 абзаца валидного HTML: только <p>, при необходимости <h2> и <ul><li>
- Полезный текст про зарубежные сериалы студии «{name}»
- SEO-фразы («смотреть онлайн», «бесплатно», «HD») используй естественно 2–4 раза на весь блок
- Без markdown, inline-стилей, скриптов и внешних ссылок
- В JSON экранируй кавычки внутри HTML как \\"

ФОРМАТ ОТВЕТА (JSON):
Ответ верни СТРОГО в формате JSON без markdown, без пояснений до или после JSON.
{
  "meta_title": "Сериалы USA Network смотреть онлайн в HD качестве бесплатно",
  "description": "USA Network — студия с узнаваемыми зарубежными сериалами: динамичные сюжеты, сильные герои и сезоны, которые удобно смотреть подряд.",
  "meta_description": "Зарубежные сериалы USA Network — смотреть онлайн бесплатно в хорошем HD качестве. Новые серии и популярные тайтлы студии.",
  "seo_html": "<p>На странице собраны зарубежные сериалы студии USA Network — смотреть онлайн бесплатно в HD.</p><p>Выбирайте тайтлы студии и следите за новыми сериями.</p>"
}`,y=`Ты — SEO-редактор каталога зарубежных сериалов.

Задача: для страницы подборки «{name}» подготовь meta_title, description, meta_description и SEO-блок (HTML) для сайта зарубежных сериалов.

Контекст страницы:
- Подборка: {name}
- URL: {url}

Цель: тексты должны быть полезными для людей и дружелюбными к Яндексу/Google — естественные формулировки, коммерческий интент «смотреть онлайн», без переспама и воды.

ПРАВИЛА meta_title (до ~70 символов):
- Включи тему подборки и акцент на зарубежные сериалы
- Естественно встрой 1–2 фразы: смотреть онлайн, в HD качестве, бесплатно
- Пример: «Сериалы про вампиров — смотреть онлайн в HD качестве бесплатно»

ПРАВИЛА description (2–4 предложения, обычный текст без HTML):
- Живое тематическое описание подборки зарубежных сериалов
- Без обязательных SEO-штампов в каждом предложении
- Без markdown и кавычек-ёлочек

ПРАВИЛА meta_description (120–160 символов):
- 1–2 предложения: что пользователь найдёт в подборке
- Упомяни зарубежные сериалы и смотреть онлайн / бесплатно / HD без набивки ключей
- Без клише вроде «лучший сайт»

ПРАВИЛА seo_html:
- 2–4 абзаца валидного HTML: только <p>, при необходимости <h2> и <ul><li>
- Полезный текст про зарубежные сериалы по теме «{name}»
- SEO-фразы («смотреть онлайн», «бесплатно», «HD») используй естественно 2–4 раза на весь блок
- Без markdown, inline-стилей, скриптов и внешних ссылок
- В JSON экранируй кавычки внутри HTML как \\"

ФОРМАТ ОТВЕТА (JSON):
Ответ верни СТРОГО в формате JSON без markdown, без пояснений до или после JSON.
{
  "meta_title": "Сериалы про вампиров — смотреть онлайн в HD качестве бесплатно",
  "description": "Подборка зарубежных сериалов про вампиров: мрачная атмосфера, сильные характеры и истории, в которых магия соседствует с повседневностью.",
  "meta_description": "Подборка зарубежных сериалов про вампиров — смотреть онлайн бесплатно в хорошем HD качестве. Атмосферные истории и новые серии.",
  "seo_html": "<p>В подборке собраны зарубежные сериалы про вампиров — смотреть онлайн бесплатно в HD.</p><p>Выбирайте тайтлы по настроению и следите за новыми сериями.</p>"
}`;function b(e,t){return e.replaceAll(`{name}`,t.name).replaceAll(`{slug}`,t.slug).replaceAll(`{url}`,t.url).trim()}function x(e){return typeof e==`string`?e.trim():``}function S(e){try{let t=JSON.parse(e);return t&&typeof t==`object`&&!Array.isArray(t)?t:null}catch{return null}}function C(e){let t=e.trim();if(!t)return null;let n=[],r=t.match(/```(?:json)?\s*([\s\S]*?)```/i);r?.[1]&&n.push(r[1].trim()),n.push(t);let i=t.indexOf(`{`),a=t.lastIndexOf(`}`);i!==-1&&a>i&&n.push(t.slice(i,a+1));for(let e of n){let t=S(e);if(!t)continue;let n={meta_title:x(t.meta_title),description:x(t.description),meta_description:x(t.meta_description),seo_html:x(t.seo_html)};if(n.meta_title||n.description||n.meta_description||n.seo_html)return n}return null}async function w(e,t,n){return(await e(`/api/admin/settings`)).items.find(e=>e.key===t)?.value?.trim()||n}var T=r();function E({form:e,settingKey:t,defaultTemplate:r,entityLabel:g,buildVars:_}){let[v,y]=(0,h.useState)(!1),[x,S]=(0,h.useState)(!1),[E,D]=(0,h.useState)(``),[O,k]=(0,h.useState)(!1),[A,j]=(0,h.useState)(!1),[M,N]=(0,h.useState)(!1),[P,F]=(0,h.useState)(``),[I,L]=(0,h.useState)(!1),[R,z]=(0,h.useState)(``),[B,V]=(0,h.useState)(null),[H,U]=(0,h.useState)(``);d(I?`Импорт SEO ${g} из ИИ`:O?`Шаблон промпта для SEO ${g}`:v?`Промпт для ИИ — SEO ${g}`:null),f(x||A||M);async function W(){let e=_();if(!e?.name){s.warning(`Сначала укажите название`);return}y(!0),S(!0),D(``);try{let i=await w(n,t,r);D(b(i,e))}catch(e){s.error(String(e.message)),y(!1)}finally{S(!1)}}async function G(){if(E.trim())try{await navigator.clipboard.writeText(E),s.success(`Промпт скопирован в буфер обмена`)}catch{s.error(`Не удалось скопировать`)}}async function K(){k(!0),j(!0);try{F(await w(n,t,r))}catch(e){s.error(String(e.message)),k(!1)}finally{j(!1)}}async function q(){N(!0);try{await n(`/api/admin/settings`,{method:`POST`,body:JSON.stringify({settings:[{key:t,value:P}]})}),s.success(`Шаблон промпта сохранён`),k(!1)}catch(e){s.error(String(e.message))}finally{N(!1)}}function J(){L(!0),z(``),V(null),U(``)}function Y(){let e=R.trim();if(!e){s.warning(`Вставьте JSON-ответ от ИИ`);return}let t=C(e);if(!t){V(null),U(`Не удалось распознать JSON. Вставьте ответ ИИ целиком или только JSON-блок.`),s.error(`Не удалось распознать JSON`);return}U(``),V(t),s.success(`JSON распознан — проверьте предпросмотр`)}function X(){if(!B){s.warning(`Сначала вставьте JSON и нажмите «Проверить»`);return}B.meta_title&&e.setFieldValue(`meta_title`,B.meta_title),B.description&&e.setFieldValue(`description`,B.description),B.meta_description&&e.setFieldValue(`meta_description`,B.meta_description),B.seo_html&&e.setFieldValue(`seo_html`,B.seo_html),s.success(`SEO-поля заполнены из ответа ИИ`),L(!1),z(``),V(null),U(``)}return(0,T.jsxs)(T.Fragment,{children:[(0,T.jsxs)(u,{wrap:!0,style:{marginBottom:12},children:[(0,T.jsx)(l,{icon:(0,T.jsx)(i,{}),onClick:()=>void W(),children:`Промпт для ИИ`}),(0,T.jsx)(l,{icon:(0,T.jsx)(p,{}),onClick:J,children:`Импорт из ИИ`}),(0,T.jsx)(l,{icon:(0,T.jsx)(a,{}),onClick:()=>void K(),children:`Шаблон промпта`})]}),(0,T.jsxs)(c,{title:`Промпт для ИИ — SEO ${g}`,open:v,onCancel:()=>y(!1),footer:[(0,T.jsx)(l,{onClick:()=>void K(),children:`Шаблон`},`template`),(0,T.jsx)(l,{type:`primary`,icon:(0,T.jsx)(i,{}),onClick:()=>void G(),disabled:!E.trim(),children:`Скопировать`},`copy`),(0,T.jsx)(l,{onClick:()=>y(!1),children:`Закрыть`},`close`)],width:820,destroyOnHidden:!0,children:[(0,T.jsx)(o.Paragraph,{type:`secondary`,children:`Скопируйте промпт в ChatGPT/Claude, затем вставьте JSON-ответ через «Импорт из ИИ».`}),x?(0,T.jsx)(o.Text,{type:`secondary`,children:`Загрузка…`}):(0,T.jsx)(m.TextArea,{value:E,readOnly:!0,rows:18,style:{fontFamily:`monospace`,fontSize:12}})]}),(0,T.jsxs)(c,{title:`Шаблон промпта для SEO ${g}`,open:O,onCancel:()=>k(!1),onOk:()=>void q(),okText:`Сохранить`,cancelText:`Отмена`,confirmLoading:M,width:820,destroyOnHidden:!0,children:[(0,T.jsxs)(o.Paragraph,{type:`secondary`,children:[`Плейсхолдеры: `,(0,T.jsx)(o.Text,{code:!0,children:`{name}`}),`,`,` `,(0,T.jsx)(o.Text,{code:!0,children:`{slug}`}),`,`,` `,(0,T.jsx)(o.Text,{code:!0,children:`{url}`}),`. Также редактируется в Настройки → SEO → Промпты для ИИ.`]}),(0,T.jsx)(u,{style:{marginBottom:12},children:(0,T.jsx)(l,{disabled:A,onClick:()=>F(r),children:`Сбросить к умолчанию`})}),A?(0,T.jsx)(o.Text,{type:`secondary`,children:`Загрузка…`}):(0,T.jsx)(m.TextArea,{value:P,onChange:e=>F(e.target.value),rows:18,style:{fontFamily:`monospace`,fontSize:12}})]}),(0,T.jsxs)(c,{title:`Импорт SEO ${g} из ИИ`,open:I,onCancel:()=>L(!1),width:820,footer:(0,T.jsxs)(u,{children:[(0,T.jsx)(l,{onClick:Y,children:`Проверить`}),(0,T.jsx)(l,{type:`primary`,onClick:X,disabled:!B,children:`Заполнить поля`}),(0,T.jsx)(l,{onClick:()=>L(!1),children:`Отмена`})]}),destroyOnHidden:!0,styles:{body:{maxHeight:`75vh`,overflowY:`auto`}},children:[(0,T.jsx)(o.Paragraph,{type:`secondary`,children:"Вставьте JSON-ответ от ИИ (можно с markdown-блоком ```json). Сначала нажмите «Проверить», затем «Заполнить поля»."}),(0,T.jsx)(m.TextArea,{value:R,onChange:e=>{z(e.target.value),V(null),U(``)},rows:10,placeholder:`{
  "meta_title": "...",
  "description": "...",
  "meta_description": "...",
  "seo_html": "<p>...</p>"
}`,style:{fontFamily:`monospace`,fontSize:12,marginBottom:16}}),H?(0,T.jsx)(o.Paragraph,{type:`danger`,style:{marginBottom:12},children:H}):null,B?(0,T.jsxs)(u,{direction:`vertical`,size:12,style:{width:`100%`},children:[(0,T.jsxs)(`div`,{children:[(0,T.jsx)(o.Text,{strong:!0,children:`Meta title`}),(0,T.jsx)(o.Paragraph,{style:{marginBottom:0},children:B.meta_title||(0,T.jsx)(o.Text,{type:`secondary`,children:`пусто`})})]}),(0,T.jsxs)(`div`,{children:[(0,T.jsx)(o.Text,{strong:!0,children:`Описание`}),(0,T.jsx)(o.Paragraph,{style:{marginBottom:0},children:B.description||(0,T.jsx)(o.Text,{type:`secondary`,children:`пусто`})})]}),(0,T.jsxs)(`div`,{children:[(0,T.jsx)(o.Text,{strong:!0,children:`Meta description`}),(0,T.jsx)(o.Paragraph,{style:{marginBottom:0},children:B.meta_description||(0,T.jsx)(o.Text,{type:`secondary`,children:`пусто`})})]}),(0,T.jsxs)(`div`,{children:[(0,T.jsx)(o.Text,{strong:!0,children:`SEO-блок (HTML)`}),(0,T.jsx)(m.TextArea,{value:B.seo_html,readOnly:!0,rows:6,style:{fontFamily:`monospace`,fontSize:12,marginTop:4}})]})]}):null]})]})}export{g as a,v as i,_ as n,w as o,y as r,E as t};