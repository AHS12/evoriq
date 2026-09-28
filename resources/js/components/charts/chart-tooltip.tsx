import { ChartTooltipContent, type ChartConfig } from '@/components/ui/chart';
import type { ChartValueFormatter } from '@/components/charts/types';

type Props = React.ComponentProps<typeof ChartTooltipContent> & {
    config: ChartConfig;
    valueFormatter?: ChartValueFormatter;
};

/**
 * Recharts tooltip content that routes numeric values through the caller's
 * formatter (typically `lib/format.ts`). Without a formatter it falls back to
 * the shadcn default rendering.
 */
export function ChartValueTooltip({ config, valueFormatter, ...props }: Props) {
    return (
        <ChartTooltipContent
            {...props}
            formatter={
                valueFormatter
                    ? (value, name) => (
                          <div className="flex w-full items-center justify-between gap-3">
                              <span className="text-muted-foreground">
                                  {config[String(name)]?.label ?? name}
                              </span>
                              <span className="font-mono font-medium tabular-nums">
                                  {valueFormatter(Number(value), String(name))}
                              </span>
                          </div>
                      )
                    : undefined
            }
        />
    );
}
