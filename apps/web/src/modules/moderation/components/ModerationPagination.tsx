import { ChevronLeft, ChevronRight } from 'lucide-react';

import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationLink,
} from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

function buildPages(currentPage: number, lastPage: number) {
  if (lastPage <= 7) {
    return Array.from({ length: lastPage }, (_, index) => index + 1);
  }

  if (currentPage <= 3) {
    return [1, 2, 3, 4, 'ellipsis', lastPage] as const;
  }

  if (currentPage >= lastPage - 2) {
    return [1, 'ellipsis', lastPage - 3, lastPage - 2, lastPage - 1, lastPage] as const;
  }

  return [1, 'ellipsis', currentPage - 1, currentPage, currentPage + 1, 'ellipsis', lastPage] as const;
}

interface ModerationPaginationProps {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
  isFetching?: boolean;
  pageSizeOptions: readonly number[];
  onPageChange: (page: number) => void;
  onPerPageChange: (perPage: number) => void;
}

export function ModerationPagination({
  currentPage,
  lastPage,
  perPage,
  total,
  isFetching = false,
  pageSizeOptions,
  onPageChange,
  onPerPageChange,
}: ModerationPaginationProps) {
  const start = total === 0 ? 0 : ((currentPage - 1) * perPage) + 1;
  const end = Math.min(currentPage * perPage, total);
  const pages = buildPages(currentPage, lastPage);

  const goToPage = (nextPage: number) => {
    if (nextPage < 1 || nextPage > lastPage || nextPage === currentPage || isFetching) {
      return;
    }

    onPageChange(nextPage);
  };

  return (
    <div className="flex flex-col gap-4 border-t border-border/60 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div className="text-sm text-muted-foreground">
          {start}-{end} de {total} itens
        </div>

        <div className="flex items-center gap-2">
          <span className="text-xs font-medium uppercase tracking-[0.18em] text-muted-foreground">Por pagina</span>
          <Select value={String(perPage)} onValueChange={(value) => onPerPageChange(Number(value))}>
            <SelectTrigger className="h-9 w-[92px]">
              <SelectValue placeholder="Itens" />
            </SelectTrigger>
            <SelectContent>
              {pageSizeOptions.map((option) => (
                <SelectItem key={option} value={String(option)}>
                  {option}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      {lastPage > 1 ? (
        <Pagination className="mx-0 w-auto justify-start lg:justify-end">
          <PaginationContent>
            <PaginationItem>
              <PaginationLink
                href="#"
                size="default"
                className={currentPage === 1 ? 'pointer-events-none opacity-50' : undefined}
                onClick={(event) => {
                  event.preventDefault();
                  goToPage(currentPage - 1);
                }}
              >
                <ChevronLeft className="h-4 w-4" />
                <span>Anterior</span>
              </PaginationLink>
            </PaginationItem>

            {pages.map((page, index) => (
              <PaginationItem key={`${page}-${index}`}>
                {page === 'ellipsis' ? (
                  <PaginationEllipsis />
                ) : (
                  <PaginationLink
                    href="#"
                    isActive={page === currentPage}
                    onClick={(event) => {
                      event.preventDefault();
                      goToPage(page);
                    }}
                  >
                    {page}
                  </PaginationLink>
                )}
              </PaginationItem>
            ))}

            <PaginationItem>
              <PaginationLink
                href="#"
                size="default"
                className={currentPage === lastPage ? 'pointer-events-none opacity-50' : undefined}
                onClick={(event) => {
                  event.preventDefault();
                  goToPage(currentPage + 1);
                }}
              >
                <span>Proxima</span>
                <ChevronRight className="h-4 w-4" />
              </PaginationLink>
            </PaginationItem>
          </PaginationContent>
        </Pagination>
      ) : null}
    </div>
  );
}
