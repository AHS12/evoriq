import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import type { JobError } from '@/types';

type Props = {
    errors: JobError[];
};

export function JobIssueTable({ errors }: Props) {
    const { t } = useTranslation();

    return (
        <div className="overflow-hidden rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-16">{t('Row')}</TableHead>
                        <TableHead className="w-28">{t('Type')}</TableHead>
                        <TableHead>{t('Message')}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {errors.map((error, index) => (
                        <TableRow key={`${error.row}-${index}`}>
                            <TableCell className="text-muted-foreground">
                                {error.row > 0 ? error.row : '—'}
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline">{error.type}</Badge>
                            </TableCell>
                            <TableCell className="text-sm">
                                {error.message}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
