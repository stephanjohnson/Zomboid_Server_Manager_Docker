import { useEffect, useRef, useState } from 'react';

export function usePing(url: string, intervalMs = 10000): number | null {
    const [ping, setPing] = useState<number | null>(null);
    const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

    useEffect(() => {
        const measure = async () => {
            try {
                const start = performance.now();
                await fetch(url, { method: 'HEAD', cache: 'no-store' });
                const elapsed = Math.round(performance.now() - start);
                setPing(elapsed);
            } catch {
                setPing(null);
            }
        };

        measure();
        timerRef.current = setInterval(measure, intervalMs);

        return () => {
            if (timerRef.current) {
                clearInterval(timerRef.current);
            }
        };
    }, [url, intervalMs]);

    return ping;
}
