import { useState } from 'react';
import { motion } from 'framer-motion';
import { Gamepad2, Puzzle, Trophy, Settings, Grid3X3, Zap } from 'lucide-react';
import { PageHeader } from '@/shared/components/PageHeader';
import { StatsCard } from '@/shared/components/StatsCard';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { mockMedia } from '@/shared/mock/data';

export default function PlayPage() {
  const published = mockMedia.filter(m => m.status === 'published' || m.status === 'approved');

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader title="Play / Minigames" description="Configure os jogos interativos do evento" />

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatsCard title="Total de Partidas" value={156} icon={Gamepad2} change="+34 hoje" changeType="positive" />
        <StatsCard title="Tempo Médio" value="2m 15s" icon={Zap} />
        <StatsCard title="Taxa de Conclusão" value="78%" icon={Trophy} />
        <StatsCard title="Jogos Ativos" value={2} icon={Settings} />
      </div>

      <Tabs defaultValue="overview">
        <TabsList className="bg-muted/50">
          <TabsTrigger value="overview">Visão Geral</TabsTrigger>
          <TabsTrigger value="memory">Jogo da Memória</TabsTrigger>
          <TabsTrigger value="puzzle">Puzzle</TabsTrigger>
          <TabsTrigger value="ranking">Ranking</TabsTrigger>
        </TabsList>

        <TabsContent value="overview" className="mt-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {[
              { name: 'Jogo da Memória', icon: Grid3X3, active: true, plays: 98, desc: 'Encontre os pares usando fotos do evento' },
              { name: 'Puzzle', icon: Puzzle, active: true, plays: 58, desc: 'Monte o quebra-cabeça com a foto do evento' },
            ].map(g => (
              <div key={g.name} className="glass rounded-xl p-5 card-hover">
                <div className="flex items-center gap-3 mb-3">
                  <div className="rounded-lg bg-primary/10 p-2.5"><g.icon className="h-6 w-6 text-primary" /></div>
                  <div className="flex-1">
                    <p className="font-semibold">{g.name}</p>
                    <p className="text-xs text-muted-foreground">{g.desc}</p>
                  </div>
                  <Switch checked={g.active} />
                </div>
                <div className="flex gap-4 text-sm text-muted-foreground">
                  <span>{g.plays} partidas</span>
                  <span>•</span>
                  <span className={g.active ? 'text-success' : 'text-muted-foreground'}>{g.active ? 'Ativo' : 'Inativo'}</span>
                </div>
                <div className="mt-3 grid grid-cols-4 gap-1">
                  {published.slice(0, 4).map(m => (
                    <img key={m.id} src={m.thumbnailUrl} alt="" className="h-14 rounded-md object-cover" />
                  ))}
                </div>
                <p className="text-[10px] text-muted-foreground mt-2">Usa as fotos do evento · Atualização automática</p>
              </div>
            ))}
          </div>
        </TabsContent>

        <TabsContent value="memory" className="mt-6">
          <div className="glass rounded-xl p-6 space-y-4 max-w-xl">
            <h3 className="font-semibold">Configurar Jogo da Memória</h3>
            <div className="space-y-3">
              <div className="flex items-center justify-between"><Label>Ativo</Label><Switch defaultChecked /></div>
              <div>
                <Label>Quantidade de Pares</Label>
                <Select defaultValue="6"><SelectTrigger className="mt-1.5"><SelectValue /></SelectTrigger>
                  <SelectContent><SelectItem value="4">4 pares</SelectItem><SelectItem value="6">6 pares</SelectItem><SelectItem value="8">8 pares</SelectItem></SelectContent>
                </Select>
              </div>
              <div>
                <Label>Dificuldade</Label>
                <Select defaultValue="medium"><SelectTrigger className="mt-1.5"><SelectValue /></SelectTrigger>
                  <SelectContent><SelectItem value="easy">Fácil</SelectItem><SelectItem value="medium">Médio</SelectItem><SelectItem value="hard">Difícil</SelectItem></SelectContent>
                </Select>
              </div>
            </div>
            <div className="grid grid-cols-4 gap-2 mt-4">
              {Array.from({ length: 8 }).map((_, i) => (
                <div key={i} className="h-16 rounded-lg bg-primary/10 flex items-center justify-center">
                  <span className="text-xs text-primary font-medium">?</span>
                </div>
              ))}
            </div>
          </div>
        </TabsContent>

        <TabsContent value="puzzle" className="mt-6">
          <div className="glass rounded-xl p-6 space-y-4 max-w-xl">
            <h3 className="font-semibold">Configurar Puzzle</h3>
            <div className="space-y-3">
              <div className="flex items-center justify-between"><Label>Ativo</Label><Switch defaultChecked /></div>
              <div>
                <Label>Número de Peças</Label>
                <Select defaultValue="9"><SelectTrigger className="mt-1.5"><SelectValue /></SelectTrigger>
                  <SelectContent><SelectItem value="4">4 peças (2×2)</SelectItem><SelectItem value="9">9 peças (3×3)</SelectItem><SelectItem value="16">16 peças (4×4)</SelectItem></SelectContent>
                </Select>
              </div>
            </div>
            {published[0] && (
              <div className="grid grid-cols-3 gap-1 mt-4">
                {Array.from({ length: 9 }).map((_, i) => (
                  <div key={i} className="h-20 rounded-md overflow-hidden border border-border">
                    <img src={published[0].thumbnailUrl} alt="" className="w-full h-full object-cover" style={{ objectPosition: `${(i % 3) * 50}% ${Math.floor(i / 3) * 50}%` }} />
                  </div>
                ))}
              </div>
            )}
          </div>
        </TabsContent>

        <TabsContent value="ranking" className="mt-6">
          <div className="glass rounded-xl p-5">
            <h3 className="font-semibold mb-4">Ranking de Jogadores</h3>
            <div className="space-y-2">
              {['Maria S.', 'João P.', 'Ana L.', 'Carlos R.', 'Fernanda M.'].map((name, i) => (
                <div key={name} className="flex items-center gap-3 p-3 rounded-lg bg-muted/30">
                  <span className={`text-sm font-bold w-6 ${i < 3 ? 'text-warning' : 'text-muted-foreground'}`}>#{i + 1}</span>
                  <div className="h-8 w-8 rounded-full gradient-primary flex items-center justify-center text-xs font-bold text-primary-foreground">{name.slice(0, 2)}</div>
                  <span className="flex-1 text-sm font-medium">{name}</span>
                  <span className="text-sm text-muted-foreground">{Math.floor(Math.random() * 60 + 30)}s</span>
                  <Trophy className={`h-4 w-4 ${i < 3 ? 'text-warning' : 'text-muted-foreground'}`} />
                </div>
              ))}
            </div>
          </div>
        </TabsContent>
      </Tabs>
    </motion.div>
  );
}
