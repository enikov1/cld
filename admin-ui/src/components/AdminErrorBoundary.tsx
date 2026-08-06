import { Alert, Button, Result } from 'antd'
import { Component, type ErrorInfo, type ReactNode } from 'react'

type Props = {
  children: ReactNode
}

type State = {
  error: Error | null
}

export default class AdminErrorBoundary extends Component<Props, State> {
  state: State = { error: null }

  static getDerivedStateFromError(error: Error): State {
    return { error }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    console.error('Admin UI error:', error, info.componentStack)
  }

  render() {
    if (!this.state.error) {
      return this.props.children
    }

    return (
      <Result
        status="error"
        title="Ошибка в интерфейсе админки"
        subTitle={this.state.error.message || 'Неизвестная ошибка'}
        extra={
          <Button type="primary" onClick={() => this.setState({ error: null })}>
            Попробовать снова
          </Button>
        }
      >
        <Alert type="error" showIcon message={String(this.state.error.stack || this.state.error)} />
      </Result>
    )
  }
}
