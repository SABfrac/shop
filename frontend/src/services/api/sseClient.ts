export class SseClient {
    private eventSource: EventSource | null = null;
    private reconnectAttempts = 0;
    private readonly maxReconnectAttempts = 5;
    private readonly reconnectDelay = 3000; // 3 секунды

    /**
     * Подключается к SSE потоку.
     * @param url - URL для подключения (например, /vendor/feed/report-status-stream/123)
     * @param onMessage - Callback, который будет вызван при получении события 'statusUpdate'
     * @param onError - Callback для обработки ошибок соединения
     */
    public connect(
        url: string,
        onMessage: (data: any) => void,
        onError: (error: Event) => void
    ): void {
        this.disconnect(); // Закрываем предыдущее соединение, если оно было

        const fullUrl = `${url}`; // URL уже содержит ID

        this.eventSource = new EventSource(fullUrl, {
            withCredentials: true, // Ключевой момент для отправки HttpOnly cookie
        });

        this.eventSource.onopen = () => {
            console.log('SSE connection established.');
            this.reconnectAttempts = 0; // Сбрасываем счетчик при успешном подключении
        };

        // Обработчик для кастомного события 'statusUpdate'
        this.eventSource.addEventListener('statusUpdate', (event) => {
            try {
                const data = JSON.parse(event.data);
                onMessage(data);
            } catch (e) {
                console.error('Failed to parse SSE data:', e);
            }
        });


        this.eventSource.addEventListener('heartbeat', (event) => {
            // console.log('Heartbeat received:', event.data);
        });


        this.eventSource.onerror = (error) => {
            // 👈 Проверяем readyState перед вызовом onError
            if (this.eventSource?.readyState === EventSource.CLOSED) {
                console.log('SSE connection closed by server.');
                // Не вызываем onError если это нормальное закрытие
                return;
            }

            console.error('SSE error:', error);
            onError(error);

            // Логика реконнекта (если нужно)
            if (this.reconnectAttempts < this.maxReconnectAttempts) {
                this.reconnectAttempts++;
                console.log(`Reconnect attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts}`);
            }
        };
    }

    /**
     * Разрывает соединение.
     */
    public disconnect(): void {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
            console.log('SSE connection closed by client.');
        }
    }
}