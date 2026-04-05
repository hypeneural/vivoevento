import { Archive, Copy, Edit3, ExternalLink, Eye, Globe, MoreHorizontal, Radio, Send } from 'lucide-react';
import { Link } from 'react-router-dom';

import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

import type { EventListItem } from '../types';

interface EventActionsMenuProps {
  event: EventListItem;
  isBusy?: boolean;
  onCopyLink: (label: string, url?: string | null) => void;
  onRequestPublish: (event: EventListItem) => void;
  onRequestArchive: (event: EventListItem) => void;
}

const PUBLISHABLE_STATUSES = new Set(['draft', 'scheduled', 'paused']);

export function EventActionsMenu({
  event,
  isBusy = false,
  onCopyLink,
  onRequestPublish,
  onRequestArchive,
}: EventActionsMenuProps) {
  const canPublish = PUBLISHABLE_STATUSES.has(event.status);
  const canArchive = event.status !== 'archived';

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" disabled={isBusy}>
          <MoreHorizontal className="h-4 w-4" />
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end" className="w-56">
        <DropdownMenuItem asChild>
          <Link to={`/events/${event.id}`}>
            <Eye className="mr-2 h-4 w-4" />
            Ver detalhes
          </Link>
        </DropdownMenuItem>

        <DropdownMenuItem asChild>
          <Link to={`/events/${event.id}/edit`}>
            <Edit3 className="mr-2 h-4 w-4" />
            Editar evento
          </Link>
        </DropdownMenuItem>

        {event.public_url ? (
          <DropdownMenuItem asChild>
            <a href={event.public_url} target="_blank" rel="noreferrer">
              <Globe className="mr-2 h-4 w-4" />
              Abrir hub público
            </a>
          </DropdownMenuItem>
        ) : null}

        {event.upload_url ? (
          <DropdownMenuItem asChild>
            <a href={event.upload_url} target="_blank" rel="noreferrer">
              <Send className="mr-2 h-4 w-4" />
              Abrir link de envio
            </a>
          </DropdownMenuItem>
        ) : null}

        <DropdownMenuSeparator />

        <DropdownMenuItem onClick={() => onCopyLink('Hub público', event.public_url)}>
          <Copy className="mr-2 h-4 w-4" />
          Copiar link do hub
        </DropdownMenuItem>

        <DropdownMenuItem onClick={() => onCopyLink('Envio público', event.upload_url)}>
          <ExternalLink className="mr-2 h-4 w-4" />
          Copiar link de envio
        </DropdownMenuItem>

        <DropdownMenuSeparator />

        {canPublish ? (
          <DropdownMenuItem disabled={isBusy} onClick={() => onRequestPublish(event)}>
            <Radio className="mr-2 h-4 w-4" />
            Publicar evento
          </DropdownMenuItem>
        ) : null}

        {canArchive ? (
          <DropdownMenuItem
            disabled={isBusy}
            className="text-destructive focus:text-destructive"
            onClick={() => onRequestArchive(event)}
          >
            <Archive className="mr-2 h-4 w-4" />
            Arquivar evento
          </DropdownMenuItem>
        ) : null}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
