import { Download } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { template } from '@/routes/activity';
import type { JobEntityOption } from '@/types';

type Props = {
    entity: JobEntityOption | null;
};

export function ImportTemplateDownload({ entity }: Props) {
    const [format, setFormat] = useState<'csv' | 'xlsx'>('csv');

    if (!entity) {
        return null;
    }

    const help = Object.entries(entity.import_column_help);

    return (
        <div className="space-y-2">
            <div className="flex items-center gap-2">
                <Select
                    value={format}
                    onValueChange={(value) =>
                        setFormat(value === 'xlsx' ? 'xlsx' : 'csv')
                    }
                >
                    <SelectTrigger className="w-28">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="csv">CSV</SelectItem>
                        <SelectItem value="xlsx">Excel</SelectItem>
                    </SelectContent>
                </Select>

                <Button asChild variant="outline" className="flex-1">
                    <a
                        href={template.url(
                            { entity: entity.value },
                            { query: { format } },
                        )}
                    >
                        <Download className="size-4" />
                        Download template
                    </a>
                </Button>
            </div>

            {help.length > 0 && (
                <ul className="space-y-0.5 text-xs text-muted-foreground">
                    {help.map(([column, description]) => (
                        <li key={column}>
                            <span className="font-medium text-foreground">
                                {column}
                            </span>{' '}
                            — {description}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
