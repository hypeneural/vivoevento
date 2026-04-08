import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { Button } from '@/components/ui/button';

import { WallCommandToolbar } from './WallCommandToolbar';

describe('WallCommandToolbar', () => {
  it('navega entre os controles com as setas horizontais', () => {
    render(
      <WallCommandToolbar ariaLabel="Comandos do telao">
        <Button type="button">Voltar</Button>
        <Button type="button">Abrir telao</Button>
        <Button type="button">Pausar</Button>
      </WallCommandToolbar>,
    );

    const voltar = screen.getByRole('button', { name: /Voltar/i });
    const abrir = screen.getByRole('button', { name: /Abrir telao/i });
    const pausar = screen.getByRole('button', { name: /Pausar/i });

    voltar.focus();
    fireEvent.keyDown(voltar, { key: 'ArrowRight' });
    expect(abrir).toHaveFocus();

    fireEvent.keyDown(abrir, { key: 'ArrowRight' });
    expect(pausar).toHaveFocus();

    fireEvent.keyDown(pausar, { key: 'ArrowLeft' });
    expect(abrir).toHaveFocus();
  });

  it('respeita Home e End para ir ao primeiro e ao ultimo controle', () => {
    render(
      <WallCommandToolbar ariaLabel="Comandos do telao">
        <Button type="button">Voltar</Button>
        <Button type="button">Abrir telao</Button>
        <Button type="button">Pausar</Button>
      </WallCommandToolbar>,
    );

    const voltar = screen.getByRole('button', { name: /Voltar/i });
    const abrir = screen.getByRole('button', { name: /Abrir telao/i });
    const pausar = screen.getByRole('button', { name: /Pausar/i });

    abrir.focus();
    fireEvent.keyDown(abrir, { key: 'End' });
    expect(pausar).toHaveFocus();

    fireEvent.keyDown(pausar, { key: 'Home' });
    expect(voltar).toHaveFocus();
  });
});
