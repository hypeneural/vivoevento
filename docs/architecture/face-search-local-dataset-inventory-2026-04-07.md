# FaceSearch Local Dataset Inventory

## Escopo

Este documento consolida a auditoria local de `C:\Users\Usuario\Desktop\model` feita em `2026-04-07`.

Objetivo:

- identificar o que ja esta baixado;
- separar o que esta realmente utilizavel agora;
- marcar o que esta parcial, bloqueado por senha ou sem valor operacional;
- padronizar os caminhos locais para os proximos loaders e sweeps.

## Diretorio auditado

- raiz:
  - `C:\Users\Usuario\Desktop\model`
- extraidos:
  - `C:\Users\Usuario\Desktop\model\extracted`

## Resumo executivo

Pronto para trabalhar agora:

- `COFW`
- `Caltech WebFaces`
- `WIDER FACE` via cache local do `TFDS`
- `XQLFW`
- `CALFW`
- `CFP-FP`
- `RMFD/RMFVD` parcial, mas com artefatos locais reais

Parcial ou bloqueado:

- `CelebA`
  - apenas metadata local
  - faltam os arquivos de imagens
- `AgeDB`
  - zip local criptografado
  - extracao requer senha
- `VGGFace2`
  - o arquivo local e de source code/samples, nao o dataset completo

## Inventario local consolidado

| dataset | artefatos locais encontrados | extraido localmente | status operacional | caminho util principal | leitura pratica |
|---|---|---|---|---|---|
| COFW | `COFW.zip`, `COFW_color.zip`, `documentation.zip` | sim | pronto | `C:\Users\Usuario\Desktop\model\extracted\cofw_color` | ja integrado ao app; bom para oclusao/pose |
| Caltech WebFaces | `Caltech_WebFaces.tar`, `WebFaces_GroundThruth.txt` | sim | pronto | `C:\Users\Usuario\Desktop\model\extracted\caltech_webfaces` | agora integrado ao pipeline de `manifest + probe`; base forte para face pequena e densidade |
| WIDER FACE | cache local em `tfds-wider-face` | sim | pronto | `C:\Users\Usuario\Desktop\model\tfds-wider-face` | ja integrado como lane duro de densidade/face pequena |
| LFW | `lfw.tgz` | sim | pronto | `C:\Users\Usuario\Desktop\model\extracted\lfw\lfw` | agora e o lane principal de identidade em escala local |
| XQLFW | `xqlfw.zip`, `xqlfw_aligned_112.zip`, `xqlfw_pairs.txt`, `xqlfw_scores.txt` | sim | pronto | `C:\Users\Usuario\Desktop\model\extracted\xqlfw` | melhor candidato imediato para ampliar lane de identidade com holdout de qualidade |
| CALFW | `calfw.zip` | sim | pronto | `C:\Users\Usuario\Desktop\model\extracted\calfw\calfw\aligned images` | bom holdout de envelhecimento; estrutura local esta utilizavel |
| CFP-FP | `cfp-dataset.zip` | sim | pronto | `C:\Users\Usuario\Desktop\model\extracted\cfp_fp\cfp-dataset\Data\Images` | bom holdout de pose frontal-perfil |
| CelebA | `identity_CelebA.txt`, `list_eval_partition.txt` | metadata only | parcial | `C:\Users\Usuario\Desktop\model\extracted\celeba_metadata` | da para preparar adapter de labels, mas ainda nao da para rodar identity lane sem imagens |
| AgeDB | `AgeDB.zip` | extracao falhou | bloqueado | `C:\Users\Usuario\Desktop\model\AgeDB.zip` | zip contem imagens criptografadas; precisa senha |
| RMFD / RMFVD | `Real-World-Masked-Face-Dataset-master.zip` | parcialmente inspecionado | parcial utilizavel | `C:\Users\Usuario\Desktop\model\Real-World-Masked-Face-Dataset-master.zip` | lane de mascara existe localmente, mas ainda precisa consolidacao |
| VGGFace2 | `vgg_face2 source code.zip` | nao necessario | nao usar como lane principal | `C:\Users\Usuario\Desktop\model\vgg_face2 source code.zip` | e source code + samples, nao o dataset completo |

## Estruturas validadas localmente

### XQLFW

Arquivos crus:

- `C:\Users\Usuario\Desktop\model\xqlfw.zip`
- `C:\Users\Usuario\Desktop\model\xqlfw_aligned_112.zip`
- `C:\Users\Usuario\Desktop\model\xqlfw_pairs.txt`
- `C:\Users\Usuario\Desktop\model\xqlfw_scores.txt`

Extraidos:

- `C:\Users\Usuario\Desktop\model\extracted\xqlfw\lfw_original_imgs_min_qual0.85variant11`
- `C:\Users\Usuario\Desktop\model\extracted\xqlfw_aligned_112\xqlfw_aligned_112`
- `C:\Users\Usuario\Desktop\model\extracted\xqlfw_metadata`

Contagens observadas:

- `xqlfw` original:
  - `9843` arquivos de imagem
  - `4203` diretorios
  - `1209` identidades com `2+` imagens
- `xqlfw_aligned_112`:
  - `9653` arquivos de imagem

Leitura:

- essa e a aquisicao de identidade mais madura hoje dentro da pasta local;
- ja temos imagens, pares e scores;
- se a proxima automacao for identidade, `XQLFW` e o melhor proximo alvo.

### CALFW

Arquivo cru:

- `C:\Users\Usuario\Desktop\model\calfw.zip`

Extraido:

- `C:\Users\Usuario\Desktop\model\extracted\calfw\calfw\aligned images`

Contagens observadas:

- `12174` imagens
- `4025` identidades distintas por nome-base
- `4024` identidades com `2+` imagens

Leitura:

- o pacote local ficou extraido com sucesso;
- o path esta um nivel mais profundo por causa da estrutura do zip;
- vale como holdout de idade, nao como primeiro lane de sweep principal.

### CFP-FP

Arquivo cru:

- `C:\Users\Usuario\Desktop\model\cfp-dataset.zip`

Extraido:

- `C:\Users\Usuario\Desktop\model\extracted\cfp_fp\cfp-dataset\Data\Images`
- `C:\Users\Usuario\Desktop\model\extracted\cfp_fp\cfp-dataset\Data\Fiducials`

Contagens observadas no pacote local:

- `334` diretorios de sujeito
- `3340` imagens frontais
- `1335` imagens de perfil
- `4675` imagens totais

Leitura:

- o pacote local esta consistente para uso como holdout de pose;
- os arquivos de fiduciais tambem estao presentes;
- eu nao trataria este conjunto como lane principal de threshold antes de criar um adapter de galeria/probe.

### CelebA metadata

Arquivos locais:

- `C:\Users\Usuario\Desktop\model\identity_CelebA.txt`
- `C:\Users\Usuario\Desktop\model\list_eval_partition.txt`

Organizados em:

- `C:\Users\Usuario\Desktop\model\extracted\celeba_metadata`

Leitura:

- os labels principais ja estao na maquina;
- ainda faltam os zips de imagens, entao hoje esse lane esta incompleto;
- vale preparar conformidade/licenca antes de puxar `img_align_celeba.zip`.

### AgeDB

Arquivo local:

- `C:\Users\Usuario\Desktop\model\AgeDB.zip`

Diagnostico tecnico:

- o indice do zip abre normalmente;
- os arquivos de imagem dentro do zip estao marcados como `encrypted=True`;
- leitura direta do conteudo falha com `password required for extraction`.

Leitura:

- o problema nao e corrupcao simples;
- o arquivo local realmente exige senha;
- este lane fica bloqueado ate obter a senha correta da fonte.

### VGGFace2

Arquivo local:

- `C:\Users\Usuario\Desktop\model\vgg_face2 source code.zip`

Inspecao local:

- o zip contem `vgg_face2-master/README.md`
- `attributes/`
- `samples (test set)/`

Leitura:

- esse zip nao e o dataset completo do `VGGFace2`;
- serve no maximo como referencia de codigo/metadados/samples;
- nao entra no plano operacional do FaceSearch.

## Duplicidades e organizacao atual

Duplicados encontrados na raiz:

- `Caltech_WebFaces.tar` e `Caltech_WebFaces (1).tar`
- `WebFaces_GroundThruth.txt` e `WebFaces_GroundThruth (1).txt`
- `matlab.zip` e `matlab (1).zip`
- `ReadMe.txt` e `ReadMe (1).txt`

Leitura:

- nao removi nada para evitar apagar artefatos do usuario;
- para uso do app, o que importa ja esta consolidado em `extracted/`;
- esses duplicados podem ser limpos depois sem impacto no pipeline, desde que se preserve uma copia de cada artefato.

## Confirmacoes em fontes oficiais

### CelebA

Confirmado na pagina oficial:

- `10,177` identidades
- `202,599` imagens
- uso `non-commercial research only`
- identidades `released upon request`

Fonte oficial:

- `https://mmlab.ie.cuhk.edu.hk/projects/CelebA.html`

### LFW

Confirmado na documentacao oficial do `TensorFlow Datasets`:

- homepage oficial `http://vis-www.cs.umass.edu/lfw`
- `download size=172.20 MiB`
- split `train=13,233`

Fonte oficial:

- `https://www.tensorflow.org/datasets/catalog/lfw`

Leitura:

- o lane `LFW` continua valido oficialmente;
- a fonte oficial mantida hoje pelo `TFDS` aponta para um espelho `figshare`, nao depende mais do host antigo da `UMass`;
- o dataset agora ja esta presente localmente em:
  - `C:\Users\Usuario\Desktop\model\lfw.tgz`
  - `C:\Users\Usuario\Desktop\model\extracted\lfw\lfw`

### XQLFW

Os nomes dos artefatos locais batem com a release oficial usada para distribuicao:

- `xqlfw.zip`
- `xqlfw_aligned_112.zip`
- `xqlfw_pairs.txt`
- `xqlfw_scores.txt`

Fonte oficial:

- `https://github.com/Martlgap/xqlfw/releases/tag/1.0`

### VGGFace2

Confirmado na pagina oficial:

- os links de download do dataset nao estao mais disponiveis

Fonte oficial:

- `https://www.robots.ox.ac.uk/~vgg/data/vgg_face2/`

## Leitura operacional para o FaceSearch

Hoje, olhando apenas para o que ja esta em disco e realmente pode ser usado sem nova aquisicao:

1. `XQLFW` e o lane de identidade mais pronto para automacao local.
2. `CALFW` e `CFP-FP` devem entrar como holdouts de idade e pose.
3. `CelebA` ainda esta incompleto porque faltam as imagens.
4. `AgeDB` depende de senha, entao nao deve bloquear a proxima iteracao.
5. `VGGFace2` nao vale esforco agora.

## Atualizacao apos automacao do `XQLFW`

Comando adicionado ao modulo:

- `php artisan face-search:load-xqlfw-local`

Validacao real executada:

- export:
  - `apps/api/storage/app/face-search-datasets/xqlfw/20260408-030724-xqlfw-original/manifest.json`
- smoke dry-run:
  - `apps/api/storage/app/face-search-smoke/20260408-030803-compreface-dry-run.json`
- smoke real:
  - `apps/api/storage/app/face-search-smoke/20260408-031022-compreface-real-run.json`
- benchmark:
  - `apps/api/storage/app/face-search-benchmark/20260408-031044-face-search-benchmark.json`

Resumo objetivo:

- `32` entradas exportadas
- `8` identidades
- `4` imagens por identidade
- pessoas selecionadas:
  - `Colin_Powell`
  - `Donald_Rumsfeld`
  - `George_W_Bush`
  - `Gerhard_Schroeder`
  - `John_Ashcroft`
  - `Junichiro_Koizumi`
  - `Tony_Blair`
  - `Vladimir_Putin`

Leitura:

- o `XQLFW` ja saiu do estado de inventario e virou lane executavel no app;
- o manifesto gerado e compativel com `smoke` e `benchmark`;
- isso cria a primeira base local mais dura de identidade alem do dataset consentido pequeno.

## Proxima ordem recomendada

1. ampliar o slice do `XQLFW` ou criar um segundo slice com outra estrategia de selecao
2. adicionar holdouts secundarios para `CALFW` e `CFP-FP`
3. baixar `LFW` se quiser um baseline identity mais padrao antes de `CelebA`
4. so puxar imagens do `CelebA` depois de fechar a nota de licenca/politica

## Atualizacao apos segundo slice do `XQLFW` e holdouts reais

### `XQLFW` segundo slice

Comando usado:

- `php artisan face-search:load-xqlfw-local --variant=original --selection=official_pairs --image-selection=score_spread --offset=8 --people=8 --images-per-person=4`

Artefatos reais:

- manifesto:
  - `apps/api/storage/app/face-search-datasets/xqlfw/20260408-032451-xqlfw-original/manifest.json`
- smoke:
  - `apps/api/storage/app/face-search-smoke/xqlfw-second-slice/20260408-033107-411605-compreface-real-run.json`
- benchmark:
  - `apps/api/storage/app/face-search-benchmark/xqlfw-second-slice/20260408-033156-face-search-benchmark.json`

Leitura:

- o segundo slice ficou executavel no app sem nenhuma aquisicao nova;
- houve `1` falha de deteccao em `32` entradas, mas o smoke agora fecha como `degraded` e nao perde o restante do lote;
- o benchmark repetiu o mesmo padrao do primeiro slice:
  - `good` forte
  - `mixed` e `low_quality` fracos

### `CALFW` holdout de idade

Comando usado:

- `php artisan face-search:load-calfw-local --selection=largest_identities --image-selection=spread --people=8 --images-per-person=4`

Artefatos reais:

- manifesto:
  - `apps/api/storage/app/face-search-datasets/calfw/20260408-032451-calfw/manifest.json`
- smoke:
  - `apps/api/storage/app/face-search-smoke/calfw-holdout/20260408-033107-504836-compreface-real-run.json`
- benchmark:
  - `apps/api/storage/app/face-search-benchmark/calfw-holdout/20260408-033156-face-search-benchmark.json`

Leitura:

- `CALFW` entrou como holdout real e ficou totalmente utilizavel no app;
- no slice alinhado local, idade nao abriu gap relevante de matching;
- isso faz dele um bom lane de regressao, nao o melhor lane principal para expandir threshold.

### `CFP-FP` holdout de pose

Comando usado:

- `php artisan face-search:load-cfp-fp-local --image-selection=spread --people=8 --frontal-per-person=2 --profile-per-person=2`

Artefatos reais:

- manifesto:
  - `apps/api/storage/app/face-search-datasets/cfp-fp/20260408-032451-cfp-fp/manifest.json`
- smoke:
  - `apps/api/storage/app/face-search-smoke/cfp-fp-holdout/20260408-033110-030062-compreface-real-run.json`
- benchmark:
  - `apps/api/storage/app/face-search-benchmark/cfp-fp-holdout/20260408-033156-face-search-benchmark.json`

Leitura:

- `CFP-FP` entrou como holdout real de pose e perfil extremo;
- houve `1` falha de deteccao em `32` entradas, novamente sem abortar o smoke;
- frontal ficou forte, mas perfil extremo caiu para `top_1=0.8`, entao pose continua sendo um dos gaps mais claros do pipeline.

## Leitura operacional atualizada

1. `XQLFW` continua sendo o melhor lane local para ampliar identidade.
2. `CALFW` deve ficar como holdout fixo de idade.
3. `CFP-FP` deve ficar como holdout fixo de pose.
4. O proximo dataset de escala mais util para identidade segue sendo `LFW`, nao um novo sweep imediato de `search_threshold`.

## Atualizacao apos integracao real do `LFW`

Aquisição local feita a partir da rota oficial usada hoje pelo builder do `TensorFlow Datasets`, com artefato salvo em:

- `C:\Users\Usuario\Desktop\model\lfw.tgz`
- extraido em:
  - `C:\Users\Usuario\Desktop\model\extracted\lfw\lfw`

Comando adicionado ao modulo:

- `php artisan face-search:load-lfw-local`

Validacao real executada:

- manifesto:
  - `apps/api/storage/app/face-search-datasets/lfw/20260408-035458-lfw/manifest.json`
- smoke:
  - `apps/api/storage/app/face-search-smoke/lfw-identity-lane/20260408-035540-441914-compreface-real-run.json`
- benchmark:
  - `apps/api/storage/app/face-search-benchmark/lfw-identity-lane/20260408-035609-face-search-benchmark.json`

Resumo objetivo:

- `72` entradas exportadas
- `12` identidades
- `6` imagens por identidade
- `72/72` deteccoes validas no smoke
- `exact.top_1_hit_rate=1.0`
- `exact.top_5_hit_rate=1.0`
- `exact.false_positive_top_1_rate=0.0`
- `ann.top_1_hit_rate=1.0`
- `ann.top_5_hit_rate=1.0`
- `ann.false_positive_top_1_rate=0.0`

Leitura:

- o `LFW` ja saiu da fila de aquisicao e virou lane principal de identidade executavel no app;
- o sweep real de threshold confirmou `0.5` como melhor compromisso global quando `LFW` e confrontado com `XQLFW`, `CALFW` e `CFP-FP`;
- `CALFW` e `CFP-FP` continuam melhores como holdouts fixos de regressao;
- o proximo sweep de `search_threshold` agora pode ser refeito sobre um lane mais confiavel, mas nao era necessario nesta rodada.

## Leitura operacional atualizada

1. `LFW` agora e o melhor lane local principal para identidade em escala.
2. `XQLFW` continua valioso como lane complementar de qualidade mais dura.
3. `CALFW` deve permanecer como holdout fixo de idade.
4. `CFP-FP` deve permanecer como holdout fixo de pose.
5. `search_threshold=0.5` continua sendo o default global correto depois do sweep principal + regressao.
6. `CelebA` continua opcional e dependente de politica/licenca.
