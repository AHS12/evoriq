import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { JobError } from '@/types';

type Props = {
    errors: JobError[];
};

export function JobIssueTable({ errors }: Props) {
    return (
        <div className="overflow-hidden rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-16">Row</TableHead>
                        <TableHead className="w-28">Type</TableHead>
                        <TableHead>Message</TableHead>
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
