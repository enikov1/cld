import { autocompletion, type Completion, type CompletionContext } from '@codemirror/autocomplete'
import { css } from '@codemirror/lang-css'
import { html } from '@codemirror/lang-html'
import { javascript } from '@codemirror/lang-javascript'
import { oneDark } from '@codemirror/theme-one-dark'
import { Decoration, EditorView, ViewPlugin, closeHoverTooltips, hoverTooltip } from '@codemirror/view'
import CodeMirror, { type ReactCodeMirrorRef } from '@uiw/react-codemirror'
import { forwardRef, useEffect, useImperativeHandle, useMemo, useRef } from 'react'
import type { TplHint } from '../types/tplDocs'
import {
  buildHintIndex,
  buildInsert,
  completionOptions,
  findTplTokenAt,
  lookupHint,
  type TplEditorHandle,
} from './tplEditorUtils'

type TemplateCodeEditorProps = {
  value?: string
  onChange?: (value: string) => void
  filePath: string | null
  hints?: TplHint[]
  contexts?: string[]
  hintsPrefiltered?: boolean
  isDark?: boolean
  readOnly?: boolean
  height?: string
}

const blockDeco = Decoration.mark({ class: 'cm-tpl-block' })
const metaDeco = Decoration.mark({ class: 'cm-tpl-meta' })
const varDeco = Decoration.mark({ class: 'cm-tpl-variable' })

const tplBlockRe = /\[(?:meta-[a-z-]+|not-[A-Za-z0-9_.]+|loop\s+[A-Za-z0-9_.]+|\/[A-Za-z0-9_.-]+|[A-Za-z0-9_.]+)\]/g
const tplVarRe = /\{[A-Za-z0-9_.|]+\}/g

function scanTplHighlights(view: EditorView) {
  const ranges: ReturnType<typeof blockDeco.range>[] = []

  for (const { from, to } of view.visibleRanges) {
    const text = view.state.doc.sliceString(from, to)

    tplBlockRe.lastIndex = 0
    let match = tplBlockRe.exec(text)
    while (match) {
      const start = from + match.index
      const end = start + match[0].length
      const deco = match[0].startsWith('[meta-') || match[0].startsWith('[/meta-') ? metaDeco : blockDeco
      ranges.push(deco.range(start, end))
      match = tplBlockRe.exec(text)
    }

    tplVarRe.lastIndex = 0
    match = tplVarRe.exec(text)
    while (match) {
      const start = from + match.index
      ranges.push(varDeco.range(start, start + match[0].length))
      match = tplVarRe.exec(text)
    }
  }

  return Decoration.set(ranges, true)
}

const tplHighlightPlugin = ViewPlugin.fromClass(
  class {
    decorations: ReturnType<typeof scanTplHighlights>

    constructor(view: EditorView) {
      this.decorations = scanTplHighlights(view)
    }

    update(update: { docChanged: boolean; viewportChanged: boolean; view: EditorView }) {
      if (update.docChanged || update.viewportChanged) {
        this.decorations = scanTplHighlights(update.view)
      }
    }
  },
  {
    decorations: (value) => value.decorations,
  },
)

function renderHintTooltip(hint: TplHint): HTMLElement {
  const dom = document.createElement('div')
  dom.className = 'cm-tpl-hover'

  const title = document.createElement('div')
  title.className = 'cm-tpl-hover__label'
  title.textContent = hint.label
  dom.appendChild(title)

  if (hint.detail) {
    const detail = document.createElement('div')
    detail.className = 'cm-tpl-hover__detail'
    detail.textContent = hint.detail
    dom.appendChild(detail)
  }

  if (hint.sample) {
    const sample = document.createElement('div')
    sample.className = 'cm-tpl-hover__sample'
    sample.textContent = hint.sample
    dom.appendChild(sample)
  }

  return dom
}

function tplHoverHints(hints: TplHint[]) {
  const index = buildHintIndex(hints)

  return hoverTooltip(
    (view, pos) => {
      const token = findTplTokenAt(view.state.doc, pos)
      if (!token) return null

      const hint = lookupHint(index, token.insert)
      if (!hint) return null

      return {
        pos: token.from,
        end: token.to,
        above: true,
        create() {
          return { dom: renderHintTooltip(hint) }
        },
      }
    },
    { hoverTime: 250 },
  )
}

function tplCursorHintPlugin(hints: TplHint[]) {
  const index = buildHintIndex(hints)

  return ViewPlugin.fromClass(
    class {
      tooltip: HTMLDivElement | null = null
      view: EditorView

      constructor(view: EditorView) {
        this.view = view
        this.sync()
      }

      update(update: { docChanged: boolean; selectionSet: boolean; view: EditorView }) {
        if (update.docChanged || update.selectionSet) {
          this.sync()
        }
      }

      destroy() {
        this.tooltip?.remove()
        this.tooltip = null
      }

      private sync() {
        const pos = this.view.state.selection.main.head
        if (!this.view.state.selection.main.empty) {
          this.hide()
          return
        }

        const token = findTplTokenAt(this.view.state.doc, pos)
        if (!token) {
          this.hide()
          return
        }

        const hint = lookupHint(index, token.insert)
        if (!hint) {
          this.hide()
          return
        }

        this.show(hint, pos)
      }

      private hide() {
        if (this.tooltip) {
          this.tooltip.remove()
          this.tooltip = null
        }
      }

      private show(hint: TplHint, pos: number) {
        if (!this.tooltip) {
          this.tooltip = document.createElement('div')
          this.tooltip.className = 'cm-tpl-hover cm-tpl-cursor-hint'
          this.view.dom.appendChild(this.tooltip)
        }

        this.tooltip.replaceChildren(...Array.from(renderHintTooltip(hint).childNodes))

        const coords = this.view.coordsAtPos(pos)
        if (!coords) {
          this.hide()
          return
        }

        const editorRect = this.view.dom.getBoundingClientRect()
        this.tooltip.style.display = 'block'
        this.tooltip.style.left = `${coords.left - editorRect.left}px`
        this.tooltip.style.top = `${coords.bottom - editorRect.top + 6}px`
      }
    },
  )
}

function tplAutocomplete(hints: TplHint[], contexts: string[], prefiltered: boolean) {
  return autocompletion({
    activateOnTyping: true,
    maxRenderedOptions: 16,
    override: [
      (context: CompletionContext) => {
        const variableMatch = context.matchBefore(/\{[a-zA-Z0-9_.|]*$/)
        if (variableMatch) {
          const query = variableMatch.text.slice(1)
          const options = completionOptions(hints, contexts, query, 'variable', prefiltered).map(toCompletion)
          return options.length
            ? { from: variableMatch.from + 1, options, validFor: /^[a-zA-Z0-9_.|]*$/ }
            : null
        }

        const tagMatch = context.matchBefore(/\[[a-zA-Z0-9_.\-]*$/)
        if (tagMatch) {
          const query = tagMatch.text.slice(1)
          const options = completionOptions(hints, contexts, query, 'tag', prefiltered).map(toCompletion)
          return options.length
            ? { from: tagMatch.from + 1, options, validFor: /^[a-zA-Z0-9_.\-]*$/ }
            : null
        }

        if (context.explicit) {
          const token = findTplTokenAt(context.state.doc, context.pos)
          if (token?.kind === 'variable') {
            const query = token.insert.slice(1).replace(/\|raw$/, '')
            const options = completionOptions(hints, contexts, query, 'variable', prefiltered).map(toCompletion)
            return options.length
              ? { from: token.from + 1, to: token.to - (token.insert.endsWith('|raw}') ? 4 : 1), options }
              : null
          }

          if (token?.kind === 'tag') {
            const query = token.insert.slice(1).replace(/^\//, '')
            const options = completionOptions(hints, contexts, query, 'tag', prefiltered).map(toCompletion)
            return options.length ? { from: token.from + 1, to: token.to - 1, options } : null
          }
        }

        return null
      },
    ],
  })
}

function toCompletion(hint: TplHint): Completion {
  return {
    label: hint.label,
    detail: hint.detail,
    type: hint.kind === 'variable' ? 'variable' : 'keyword',
    apply: buildInsert(hint),
  }
}

function languageExtensions(
  filePath: string | null,
  hints: TplHint[],
  contexts: string[],
  prefiltered: boolean,
) {
  const lower = (filePath ?? '').toLowerCase()
  if (lower.endsWith('.css')) return [css()]
  if (lower.endsWith('.js')) return [javascript()]
  if (lower.endsWith('.html') || lower.endsWith('.htm')) return [html()]
  if (lower.endsWith('.tpl') || lower.endsWith('.svg')) {
    return [
      html(),
      tplHighlightPlugin,
      tplAutocomplete(hints, contexts, prefiltered),
      tplHoverHints(hints),
      tplCursorHintPlugin(hints),
    ]
  }
  return []
}

const TemplateCodeEditor = forwardRef<TplEditorHandle, TemplateCodeEditorProps>(function TemplateCodeEditor(
  {
    value = '',
    onChange,
    filePath,
    hints = [],
    contexts = [],
    hintsPrefiltered = false,
    isDark = false,
    readOnly = false,
    height = '420px',
  },
  ref,
) {
  const editorRef = useRef<ReactCodeMirrorRef | null>(null)
  const rootRef = useRef<HTMLDivElement | null>(null)

  const extensions = useMemo(
    () => languageExtensions(filePath, hints, contexts, hintsPrefiltered),
    [contexts, filePath, hints, hintsPrefiltered],
  )

  useEffect(() => {
    return () => {
      const view = editorRef.current?.view
      if (view) {
        view.dispatch({ effects: closeHoverTooltips })
      }
      rootRef.current?.querySelectorAll('.cm-tpl-cursor-hint').forEach((el) => el.remove())
      document.querySelectorAll('.cm-tooltip-hover').forEach((el) => el.remove())
    }
  }, [filePath])

  useImperativeHandle(ref, () => ({
    insertAtCursor(text: string) {
      const view = editorRef.current?.view
      if (!view) {
        if (onChange) onChange(value + text)
        return
      }
      const { from, to } = view.state.selection.main
      view.dispatch({
        changes: { from, to, insert: text },
        selection: { anchor: from + text.length },
      })
      view.focus()
    },
  }))

  return (
    <div className="template-codemirror" ref={rootRef}>
      <CodeMirror
        key={filePath ?? 'empty'}
        ref={editorRef}
        value={value}
        height={height}
        theme={isDark ? oneDark : 'light'}
        extensions={extensions}
        onChange={(next) => onChange?.(next)}
        readOnly={readOnly}
        basicSetup={{
          lineNumbers: true,
          foldGutter: true,
          highlightActiveLine: true,
          autocompletion: false,
          bracketMatching: true,
          closeBrackets: true,
          indentOnInput: true,
        }}
      />
    </div>
  )
})

export default TemplateCodeEditor
