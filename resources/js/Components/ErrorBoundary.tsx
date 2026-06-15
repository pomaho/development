import { Component, ErrorInfo, ReactNode } from 'react';

interface Props {
    children: ReactNode;
    fallback?: ReactNode;
}

interface State {
    hasError: boolean;
    error: Error | null;
}

export default class ErrorBoundary extends Component<Props, State> {
    constructor(props: Props) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error: Error): State {
        return { hasError: true, error };
    }

    componentDidCatch(error: Error, info: ErrorInfo): void {
        console.error('[ErrorBoundary]', error, info.componentStack);
    }

    render(): ReactNode {
        if (this.state.hasError) {
            if (this.props.fallback) {
                return this.props.fallback;
            }

            return (
                <div className="flex min-h-[200px] flex-col items-center justify-center gap-3 rounded-lg border border-red-200 bg-red-50 p-6 text-center">
                    <p className="text-sm font-medium text-red-700">Что-то пошло не так</p>
                    {this.state.error && (
                        <p className="max-w-md text-xs text-red-500">{this.state.error.message}</p>
                    )}
                    <button
                        type="button"
                        className="mt-1 rounded px-3 py-1.5 text-xs font-medium text-red-700 ring-1 ring-red-300 hover:bg-red-100"
                        onClick={() => this.setState({ hasError: false, error: null })}
                    >
                        Попробовать снова
                    </button>
                </div>
            );
        }

        return this.props.children;
    }
}
