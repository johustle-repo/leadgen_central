import { Check } from 'lucide-react';
import { ACCENTS, useAccent } from '@/hooks/use-accent';
import { cn } from '@/lib/utils';

export function AccentPicker() {
    const { accent, updateAccent } = useAccent();

    return (
        <div
            role="radiogroup"
            aria-label="Accent colour"
            className="grid grid-cols-4 gap-2 sm:grid-cols-7"
        >
            {ACCENTS.map((option) => {
                const selected = accent === option.value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        onClick={() => updateAccent(option.value)}
                        className={cn(
                            'flex flex-col items-center gap-2 rounded-xl border p-3 text-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            selected
                                ? 'border-primary bg-primary/10 font-semibold text-foreground'
                                : 'text-muted-foreground hover:border-foreground/30 hover:text-foreground',
                        )}
                    >
                        <span
                            className="flex size-8 items-center justify-center rounded-full text-white shadow-sm ring-2 ring-background"
                            style={{ background: option.swatch }}
                        >
                            {selected && <Check className="size-4" />}
                        </span>
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}
