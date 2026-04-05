import { useState, useRef, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { Search, CalendarDays, Image, Users, Loader2, X } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { useGlobalSearch } from '@/shared/hooks/useGlobalSearch';
import type { SearchResult } from '@/shared/hooks/useGlobalSearch';

const TYPE_CONFIG: Record<string, { icon: typeof CalendarDays; label: string; color: string }> = {
  event:  { icon: CalendarDays, label: 'Evento',  color: 'text-violet-500' },
  media:  { icon: Image,        label: 'Mídia',   color: 'text-blue-500'   },
  client: { icon: Users,        label: 'Cliente', color: 'text-emerald-500' },
};

interface GlobalSearchProps {
  className?: string;
}

export function GlobalSearch({ className }: GlobalSearchProps) {
  const [query, setQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const [isOpen, setIsOpen] = useState(false);
  const [highlightIndex, setHighlightIndex] = useState(-1);
  const containerRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const navigate = useNavigate();

  // Debounce 300ms
  useEffect(() => {
    const timer = setTimeout(() => setDebouncedQuery(query.trim()), 300);
    return () => clearTimeout(timer);
  }, [query]);

  const { data, isLoading, isFetching } = useGlobalSearch(debouncedQuery);
  const results = data?.results ?? [];

  // Open/close
  useEffect(() => {
    setIsOpen(debouncedQuery.length >= 2);
    setHighlightIndex(-1);
  }, [debouncedQuery]);

  // Click outside
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  const handleSelect = useCallback((result: SearchResult) => {
    setQuery('');
    setIsOpen(false);
    navigate(result.url);
  }, [navigate]);

  // Keyboard navigation
  const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
    if (!isOpen || results.length === 0) return;

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setHighlightIndex(prev => (prev + 1) % results.length);
        break;
      case 'ArrowUp':
        e.preventDefault();
        setHighlightIndex(prev => (prev - 1 + results.length) % results.length);
        break;
      case 'Enter':
        e.preventDefault();
        if (highlightIndex >= 0 && results[highlightIndex]) {
          handleSelect(results[highlightIndex]);
        }
        break;
      case 'Escape':
        setIsOpen(false);
        inputRef.current?.blur();
        break;
    }
  }, [isOpen, results, highlightIndex, handleSelect]);

  const showSpinner = isFetching && debouncedQuery.length >= 2;

  return (
    <div ref={containerRef} className={`relative ${className ?? ''}`}>
      <div className="relative">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
        <Input
          ref={inputRef}
          value={query}
          onChange={e => setQuery(e.target.value)}
          onFocus={() => debouncedQuery.length >= 2 && setIsOpen(true)}
          onKeyDown={handleKeyDown}
          placeholder="Buscar eventos, mídias, clientes..."
          className="pl-9 pr-8 h-9 sm:h-10 bg-muted/50 border-border/50 focus:bg-background text-sm"
        />
        {showSpinner && (
          <Loader2 className="absolute right-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground animate-spin" />
        )}
        {query && !showSpinner && (
          <button
            onClick={() => { setQuery(''); setIsOpen(false); }}
            className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
          >
            <X className="h-3.5 w-3.5" />
          </button>
        )}
      </div>

      {/* Dropdown */}
      {isOpen && (
        <div className="absolute top-full left-0 right-0 mt-1.5 bg-popover border border-border rounded-xl shadow-lg overflow-hidden z-50 animate-in fade-in slide-in-from-top-1 duration-150">
          {isLoading && results.length === 0 ? (
            <div className="flex items-center justify-center gap-2 py-6 text-sm text-muted-foreground">
              <Loader2 className="h-4 w-4 animate-spin" />
              Buscando...
            </div>
          ) : results.length === 0 ? (
            <div className="flex flex-col items-center py-6 text-muted-foreground">
              <Search className="h-5 w-5 mb-1.5 opacity-50" />
              <p className="text-sm">Nenhum resultado para "{debouncedQuery}"</p>
            </div>
          ) : (
            <>
              <div className="px-3 py-1.5 border-b border-border/50">
                <span className="text-[11px] text-muted-foreground font-medium">
                  {data?.total ?? results.length} resultado{results.length !== 1 ? 's' : ''}
                </span>
              </div>
              <div className="max-h-[320px] overflow-y-auto overscroll-contain">
                {results.map((result, i) => {
                  const config = TYPE_CONFIG[result.type] ?? TYPE_CONFIG.event;
                  const Icon = config.icon;
                  const isHighlighted = i === highlightIndex;

                  return (
                    <button
                      key={`${result.type}-${result.id}`}
                      onClick={() => handleSelect(result)}
                      onMouseEnter={() => setHighlightIndex(i)}
                      className={`w-full flex items-center gap-2.5 px-3 py-2 text-left transition-colors ${
                        isHighlighted ? 'bg-muted' : 'hover:bg-muted/50'
                      }`}
                    >
                      {result.image ? (
                        <img src={result.image} alt="" className="h-8 w-10 rounded-md object-cover shrink-0 ring-1 ring-border/30" />
                      ) : (
                        <div className={`h-8 w-10 rounded-md bg-muted flex items-center justify-center shrink-0`}>
                          <Icon className={`h-3.5 w-3.5 ${config.color}`} />
                        </div>
                      )}
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium truncate">{result.title}</p>
                        <p className="text-[11px] text-muted-foreground truncate">{result.subtitle}</p>
                      </div>
                      <span className={`text-[10px] font-medium px-1.5 py-0.5 rounded ${config.color} bg-current/5 shrink-0`}>
                        {config.label}
                      </span>
                    </button>
                  );
                })}
              </div>
            </>
          )}
        </div>
      )}
    </div>
  );
}
