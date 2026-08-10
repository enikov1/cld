import { Alert, Button, Result } from 'antd'
import { Component, type ErrorInfo, type ReactNode } from 'react'

type Props = {
  children: ReactNode
}

type State = {
  error: Error | null
  resetKey: number
}

export default class AdminErrorBoundary extends Component<Props, State> {
  state: State = { error: null, resetKey: 0 }

  static getDerivedStateFromError(error: Error): Partial<State> {
    return { error }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    console.error('Admin UI error:', error, info.componentStack)
  }

  render() {
    if (!this.state.error) {
      return <div key={this.state.resetKey}>{this.props.children}</div>
    }

    const isDev = import.meta.env.DEV

    return (
      <Result
        status="error"
        title="Ошибка в интерфейсе админки"
        subTitle={this.state.error.message || 'Неизвестная ошибка'}
        extra={
          <Button
            type="primary"
            onClick={() => this.setState((s) => ({ error: null, resetKey: s.resetKey + 1 }))}
          >
            Попробовать снова
          </Button>
        }
      >
        {isDev ? (
          <Alert type="error" showIcon message={String(this.state.error.stack || this.state.error)} />
        ) : null}
      </Result>
    )
  }
}
