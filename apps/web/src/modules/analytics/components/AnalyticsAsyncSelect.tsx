import { useMemo, useState } from 'react';
import { Check, ChevronsUpDown, Loader2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

import type { AnalyticsOption } from '../types';

interface AnalyticsAsyncSelectProps {
  value: AnalyticsOption | null;
  options: AnalyticsOption[];
  search: string;
  onSearchChange: (value: string) => void;
  onSelect: (option: AnalyticsOption | null) => void;
  placeholder: string;
  emptyMessage: string;
  loading?: boolean;
  disabled?: boolean;
  className?: string;
  clearLabel?: string;
}

export function AnalyticsAsyncSelect({
  value,
  options,
  search,
  onSearchChange,
  onSelect,
  placeholder,
  emptyMessage,
  loading = false,
  disabled = false,
  className,
  clearLabel = 'Limpar selecao',
}: AnalyticsAsyncSelectProps) {
  const [open, setOpen] = useState(false);

  const items = useMemo(() => {
    if (!value) {
      return options;
    }

    return options.some((option) => option.id === value.id)
      ? options
      : [value, ...options];
  }, [options, value]);

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          type="button"
          variant="outline"
          role="combobox"
          aria-expanded={open}
          disabled={disabled}
          className={cn('w-full justify-between font-normal', className)}
        >
          <span className="truncate text-left">
            {value?.label || placeholder}
          </span>
          <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent align="start" className="w-[320px] p-0">
        <Command shouldFilter={false}>
          <CommandInput
            placeholder={placeholder}
            value={search}
            onValueChange={onSearchChange}
          />
          <CommandList>
            {value ? (
              <CommandGroup heading="Atual">
                <CommandItem
                  value={`clear-${value.id}`}
                  onSelect={() => {
                    onSelect(null);
                    onSearchChange('');
                    setOpen(false);
                  }}
                >
                  {clearLabel}
                </CommandItem>
              </CommandGroup>
            ) : null}

            <CommandEmpty>
              {loading ? (
                <div className="flex items-center justify-center gap-2 text-sm text-muted-foreground">
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Buscando...
                </div>
              ) : emptyMessage}
            </CommandEmpty>

            <CommandGroup heading="Resultados">
              {items.map((option) => (
                <CommandItem
                  key={option.id}
                  value={`${option.id}-${option.label}`}
                  onSelect={() => {
                    onSelect(option);
                    onSearchChange(option.label);
                    setOpen(false);
                  }}
                >
                  <Check
                    className={cn(
                      'mr-2 h-4 w-4',
                      value?.id === option.id ? 'opacity-100' : 'opacity-0',
                    )}
                  />
                  <div className="min-w-0">
                    <div className="truncate">{option.label}</div>
                    {option.description ? (
                      <div className="truncate text-xs text-muted-foreground">
                        {option.description}
                      </div>
                    ) : null}
                  </div>
                </CommandItem>
              ))}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}
