import { AlertCircleIcon } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

export default function AlertSuccess({
    message,
    title,
}: {
    message: string;
    title?: string;
}) {
    return (
        <Alert variant="default">
            <AlertCircleIcon />
            <AlertTitle>{title || '成功'}</AlertTitle>
            {/* <AlertDescription>
                <ul className="list-inside list-disc text-sm">
                    {Array.from(new Set([message])).map((error, index) => (
                        <li key={index}>{error}</li>
                    ))}
                </ul>
            </AlertDescription> */}
        </Alert>
    );
}
