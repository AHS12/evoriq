import { ExternalLink } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { DeveloperTool } from '@/types';

type Props = {
    tool: DeveloperTool;
};

export function ToolCard({ tool }: Props) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center justify-between gap-2">
                    {tool.title}
                    <Badge variant={tool.available ? 'default' : 'outline'}>
                        {tool.available ? 'Available' : 'Unavailable'}
                    </Badge>
                </CardTitle>
                <CardDescription>{tool.description}</CardDescription>
            </CardHeader>
            <CardContent>
                <Button asChild variant="outline">
                    <a href={tool.href} target="_blank" rel="noreferrer">
                        Open
                        <ExternalLink className="size-4" />
                    </a>
                </Button>
            </CardContent>
        </Card>
    );
}
