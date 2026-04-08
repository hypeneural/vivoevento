import type { KeyboardEvent } from 'react';
import { Settings } from 'lucide-react';

import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';

import { WallManagerSection } from '../../WallManagerSection';

interface WallInspectorTabsProps {
  activeTab: 'queue' | 'appearance' | 'ads';
  onTabChange: (tab: 'queue' | 'appearance' | 'ads') => void;
}

export function WallInspectorTabs({
  activeTab,
  onTabChange,
}: WallInspectorTabsProps) {
  function handleTabKeyDown(tab: 'queue' | 'appearance' | 'ads') {
    return (event: KeyboardEvent<HTMLButtonElement>) => {
      if (event.key !== 'Enter' && event.key !== ' ') {
        return;
      }

      event.preventDefault();
      onTabChange(tab);
    };
  }

  return (
    <WallManagerSection
      title={(
        <span className="flex items-center gap-2">
          <Settings className="h-4 w-4" />
          Configuracoes do telao
        </span>
      )}
      description="Troque o grupo de ajustes sem perder o palco de vista. A fila usa ativacao manual para evitar mudancas acidentais enquanto voce navega pelo teclado."
    >
      <Tabs
        value={activeTab}
        onValueChange={(value) => onTabChange(value as 'queue' | 'appearance' | 'ads')}
        activationMode="manual"
      >
        <TabsList aria-label="Abas de configuracao do telao" className="grid h-auto w-full grid-cols-3">
          <TabsTrigger value="queue" onClick={() => onTabChange('queue')} onKeyDown={handleTabKeyDown('queue')}>
            Fila
          </TabsTrigger>
          <TabsTrigger value="appearance" onClick={() => onTabChange('appearance')} onKeyDown={handleTabKeyDown('appearance')}>
            Aparencia
          </TabsTrigger>
          <TabsTrigger value="ads" onClick={() => onTabChange('ads')} onKeyDown={handleTabKeyDown('ads')}>
            Anuncios
          </TabsTrigger>
        </TabsList>
      </Tabs>
    </WallManagerSection>
  );
}
