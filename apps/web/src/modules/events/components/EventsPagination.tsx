import { ChevronLeft, ChevronRight } from 'lucide-react';

import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationLink,
} from '@/components/ui/pagination';

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

interface EventsPaginationProps {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
  isFetching?: boolean;
  onPageChange: (page: number) => void;
}

export function EventsPagination({
  currentPage,
  lastPage,
  perPage,
  total,
  isFetching = false,
  onPageChange,
}: EventsPaginationProps) {
  if (lastPage <= 1) {
    return null;
  }

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
    <div className="flex flex-col gap-4 border-t border-border/60 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
      <div className="text-sm text-muted-foreground">
        {start}-{end} de {total} eventos
      </div>

      <Pagination className="mx-0 w-auto justify-start sm:justify-end">
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
              <span>Próxima</span>
              <ChevronRight className="h-4 w-4" />
            </PaginationLink>
          </PaginationItem>
        </PaginationContent>
      </Pagination>
    </div>
  );
}
