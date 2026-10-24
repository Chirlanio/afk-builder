# AFK Builder

Ferramenta para jogadores de AFK Arena planejarem formações, acompanharem o investimento nos heróis e receberem sugestões de equipes do otimizador.

## Jogador e contas

**Usuário**:
Quem faz login no AFK Builder. Pode ter várias Contas de jogo.
_Evitar_: jogador (quando se refere ao login), conta

**Conta de jogo**:
Uma conta do AFK Arena cadastrada por um Usuário; dona do Roster e das Formações.
_Evitar_: perfil, servidor

**Roster**:
Os heróis que uma Conta de jogo possui, com o investimento e os Atributos de tela de cada um.
_Evitar_: coleção (é outro conceito do jogo), inventário

## Heróis

**Herói**:
Uma ficha do jogo com habilidades e ratings próprios. A versão base e a Desperta são Heróis distintos.

**Personagem**:
A identidade compartilhada entre a versão base de um Herói e a sua versão Desperta (ex.: Shemira e Shemira Desperta são um Personagem).
_Evitar_: herói (quando se quer dizer a identidade)

**Atributos base**:
Os atributos de um Herói no nível 1, vindos do catálogo; iguais para todos os jogadores.
_Evitar_: stats, atributos do catálogo

**Atributos de tela**:
Os atributos que o jogador lê no jogo para um herói do seu Roster; já incluem equipamento e todos os bônus.
_Evitar_: stats reais, atributos reais

**Poder estimado**:
O número que o AFK Builder calcula para um herói a partir do investimento e/ou dos atributos. Não é o poder exibido pelo jogo.
_Evitar_: poder, power, CP

**Coleção**:
Um item do catálogo da Galeria Tenebrosa, com os bônus de atributo que concede.
_Evitar_: coleção (para se referir ao Roster), artefato

**Coleção possuída**:
Um exemplar de Coleção que uma Conta de jogo tem; pode estar equipado em no máximo um herói do Roster.

## Desafios

**Tipo de desafio**:
Um conteúdo do jogo para o qual se monta uma Formação (Campanha, Torre do Rei, Arena dos Heróis, Provas da Forja Dracônica...).
_Evitar_: modo de jogo, evento, modo

**Contexto**:
A família de desafios em que um Herói é avaliado (PvE, PvP, Reino dos Sonhos, Expedição Abissal, Labirinto, Forja Dracônica); sempre derivado do Tipo de desafio. Ratings e tier rating são lidos por Contexto.
_Evitar_: modo, fonte de ratings

**Chefe**:
Um foco da análise de capacidades: luta contra um inimigo único, em que controle não tem efeito. Não é um Contexto.

**Relíquia**:
Um item do catálogo da Expedição Abissal que fortalece os heróis durante uma temporada.

**Relíquias da temporada**:
As Relíquias que uma Conta de jogo tem na temporada atual da Expedição Abissal; substituídas a cada nova temporada.

**Objetivo**:
O que o jogador quer alcançar e pede ao app: um Tipo de desafio (com fase, quando houver) e a meta nele, ou uma meta sem desafio (ex.: "em quem investir a seguir"). Nem todo Objetivo gera uma Formação.
_Evitar_: meta, missão

## Formações

**Formação**:
O plano de uma Conta de jogo para um Tipo de desafio, com 1 a 5 Equipes.
_Evitar_: time, comp

**Equipe**:
Até 5 heróis posicionados dentro de uma Formação.
_Evitar_: grupo, time

**Planejamento**:
Como uma Formação é montada: **real** (só heróis do Roster, no investimento atual) ou **alvo** (qualquer herói do jogo, no Investimento máximo).
_Evitar_: modo

**Investimento máximo**:
O teto de investimento usado no Planejamento alvo: Ascendido 5★, nível 240, F9, item de assinatura e gravação no limite da facção.
_Evitar_: investimento-alvo

**Equipe inimiga**:
Até 5 heróis com posição, informados pelo jogador como adversários; não pertence a uma Formação e não tem investimento conhecido.
_Evitar_: equipe (sozinho), time adversário

**Comp recomendado**:
Uma composição publicada pela comunidade, com uma ou mais Equipes; serve de referência e nunca pertence a uma Conta de jogo.
_Evitar_: formação recomendada, meta comp

## Otimizador

**Pontuação**:
A nota que o otimizador dá a uma Equipe: a soma ponderada dos Componentes da pontuação.
_Evitar_: score, poder do time

**Componente da pontuação**:
Uma das parcelas da Pontuação (facção, tier rating, sinergias, classes, destaques, mobília, Poder estimado...).

**Counter**:
Relação curada e direcional "Herói X é forte contra Herói Y". Capacidades podem explicá-la, mas não a definem.
_Evitar_: contra-ataque, anti

## Comunidade

**Global Tier List**:
Ranking de heróis agregado, de forma anônima, a partir das Contas de jogo que optaram por participar.
_Evitar_: tier list da comunidade, meta
