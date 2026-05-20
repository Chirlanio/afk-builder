## Visão geral

Os **atributos** (stats) dos heróis em AFK Arena se dividem em três grandes blocos:

1. **Básicos** — Vida, Ataque e Defesa que todo herói tem.
2. **Combate avançado** — Taxa de crítico, perfurações, esquiva, supressões e similares; aparecem em equipamentos, móveis, artefatos e inscrições.
3. **Especiais** — Dano dracônico, bônus de Arena, controles de debuff e perda de vida; aparecem em modos específicos ou em heróis de facções avançadas.

A maioria desses atributos funciona em **par atacante × defensor**: a diferença entre os dois é o que define o resultado final. Entender as fórmulas ajuda a investir nos atributos certos pro modo de jogo que você prioriza.

## Atributos básicos

### Vida (PV)
Determina a quantidade de dano que um herói pode sofrer antes de cair.

### Ataque (ATQ)
Determina a quantidade de dano que um herói pode causar.

### Defesa (DEF)
Reduz a quantidade de dano mágico e físico sofrido pelo herói.

## Crítico

### Taxa de crítico (CRIT)
Determina a frequência com que um herói consegue um acerto crítico ao atacar. Por padrão, acertos críticos causam o **dobro de dano** — essa quantidade pode aumentar ou diminuir com base na Amplificação de Dano Crítico (ADC) do atacante e na Resistência a Dano Crítico (RDC) do defensor.

### Amplificação de Dano Crítico (ADC) × Resistência a Dano Crítico (RDC)
A quantidade de dano adicional causada por um acerto crítico é afetada pela **diferença entre ADC do atacante e RDC do defensor**:

- Diferença de **30 ou menos** → cada ponto altera o dano de acerto crítico em **1%**.
- Diferença **maior que 30** → cada ponto adicional altera o dano em **2%**.
- O dano de acerto crítico pode ser alterado em **até 90%** (cap).

### Taxa de Bloqueio Crítico (TBC)
Cada ponto de TBC reduz em **1%** a chance de o herói ser atingido por um acerto crítico.

## Físico

### Precisão (PREC)
Determina quão precisos são os ataques físicos do herói. Precisão alta aumenta a chance de acertar o golpe.

### Esquiva (ESQUIVA)
Determina quão bem o herói se esquiva de ataques físicos. Quanto maior a esquiva, maior a chance de evitar o ataque.

### Perfuração Física (PF) × Resistência Física (RF)
- Cada ponto de PF aumenta em **1%** o dano físico causado pelo atacante.
- Se o alvo tem RF, **cada ponto de PF anula 1 ponto de RF**.
- Cada ponto de RF restante reduz o dano físico sofrido em **1%**.

## Mágico

### Eficiência Mágica (EM) × Supressão Mágica (SM)
Quando um herói sofre dano mágico, ele tem chance de **reduzir o dano em 50%**. Essa chance depende da diferença entre **EM do atacante** e **SM do alvo** — quanto maior a EM do atacante em relação à SM, **menor** a chance de redução.

### Perfuração Mágica (PM) × Resistência Mágica (RM)
- Cada ponto de PM aumenta em **1%** o dano mágico causado.
- Se o alvo tem RM, **cada ponto de PM anula 1 ponto de RM**.
- Cada ponto de RM restante reduz o dano mágico sofrido em **1%**.

## Controle de efeitos (debuff)

### Percepção (PER) × Tenacidade (TC)
Afetam a **taxa de sucesso** de efeitos de controle (atordoamento, silêncio, etc):

- Quando um herói tenta aplicar controle, **cada ponto de TC** do defensor **acima da PER** do atacante dá **2% de chance de resistir**.
- Reciprocamente, **cada ponto de PER** acima da TC dá ao atacante mais chance de aplicar o efeito.

### Competência (COM) × Dissolver (DISS)
Afetam a **duração** dos debuffs aplicados:

- Cada ponto de diferença altera a duração em **1%**.
- Range: até **50% menor** (se DISS > COM) ou **100% maior** (se COM > DISS).

## Cura e sobrevivência

### Sanguessuga (SS)
Determina quanta vida o herói recupera ao causar dano (lifesteal).

### Recuperação por Segundo (RPS)
Determina quanta vida o herói recupera por segundo (regeneração passiva).

### Cura (CURA)
Quando o herói **cura outro personagem**, cada ponto de CURA aumenta a vida curada em **1%** (potência da cura aplicada).

### Vitalidade (VIT)
Quando o herói **é curado** por outro personagem, cada ponto de VIT aumenta a vida recebida em **1%** (eficiência da cura recebida).

## Velocidade e ritmo

### Pressa (PRESSA)
Determina a rapidez e a frequência com que o herói pode atacar e usar habilidades. Pressa alta aumenta a velocidade da animação dos ataques/habilidades e permite usá-los com mais frequência.

### Velocidade de Ataque (VA)
Cada ponto de VA aumenta em **1%** a velocidade e a frequência de animação dos **ataques normais** (não habilidades).

### Taxa de Recuperação de Energia
Cada ponto aumenta em **1%** a taxa com que o herói gera energia para usar habilidades.

## Perda de vida e mitigação

### Erosão × Amparo
Atributos que controlam efeitos de "perda de vida" (life loss, mecânica distinta de dano direto):

- Cada ponto de diferença entre Erosão (atacante) e Amparo (defensor) altera o efeito em **1%**.
- O efeito de perda de vida pode ser reduzido em **até 90%**.

### Resistência a Dano
Cada ponto reduz em **1%** todos os tipos de **dano direto** recebido, **até 90%** (cap).

### Armadura
Cada ponto aumenta em **1%** o valor do escudo fornecido pelo personagem.

## Dano dracônico

### Amplificação de Verbo Dracônico (ADV) × Resistência a Verbo Dracônico (RDV)
- Cada ponto de ADV aumenta em **1%** o dano de Verbo Dracônico causado.
- Se o alvo tem RDV, cada ponto de ADV **anula 1 ponto** de RDV.
- Cada ponto de RDV restante reduz o dano de Verbo Dracônico sofrido.

Verbo Dracônico é uma camada de dano exclusiva de heróis da facção Draconis e mecânicas pós-dragão.

## Modos específicos

### Reforço de Dano na Arena
Aumento percentual ao dano causado em modos competitivos: **Arena dos Heróis**, **Torneio Desafio das Lendas**, **Campeonato das Lendas**, **Heróis de Esperia**, **Corrida ao Tesouro** e **Fronteira Dourada**.

## Tabela de abreviações

| Sigla   | Atributo                          |
|---------|-----------------------------------|
| PV      | Vida                              |
| ATQ     | Ataque                            |
| DEF     | Defesa                            |
| CRIT    | Taxa de Crítico                   |
| ADC     | Amplificação de Dano Crítico      |
| RDC     | Resistência a Dano Crítico        |
| TBC     | Taxa de Bloqueio Crítico          |
| PREC    | Precisão                          |
| ESQUIVA | Esquiva                           |
| PF      | Perfuração Física                 |
| RF      | Resistência Física                |
| PM      | Perfuração Mágica                 |
| RM      | Resistência Mágica                |
| EM      | Eficiência Mágica                 |
| SM      | Supressão Mágica                  |
| PER     | Percepção                         |
| TC      | Tenacidade                        |
| COM     | Competência                       |
| DISS    | Dissolver                         |
| SS      | Sanguessuga                       |
| RPS     | Recuperação por Segundo           |
| CURA    | Cura                              |
| VIT     | Vitalidade                        |
| PRESSA  | Pressa                            |
| VA      | Velocidade de Ataque              |
| ADV     | Amplificação de Verbo Dracônico   |
| RDV     | Resistência a Verbo Dracônico     |

## Dicas práticas

- **Atacantes físicos**: priorize PF antes de aumentar ATQ no late-game — anula RF do alvo e dá mais valor por ponto investido.
- **Atacantes mágicos**: PM tem o mesmo papel contra RM. Construções híbridas (físico + mágico) precisam de ambos.
- **Tanks**: RF, RM, Resistência a Dano e VIT (pra absorver cura recebida) escalam melhor que PV pura quando você passa do dano básico.
- **Supports de cura**: invista em CURA pra escalar healing aplicado, mas lembre que VIT do alvo é o que multiplica de fato — heróis frontais com VIT aproveitam mais.
- **Crítico**: CRIT sozinho não é suficiente. Sem ADC, o crit causa só o dobro base. ADC alta é o multiplicador real.
- **Controle**: PER vs TC é um par chave em PvP. Heróis de controle precisam de PER alta pra que seus efeitos não sejam resistidos.

## Fontes

Conteúdo compilado da descrição oficial in-game (PT-BR) — versão 2026. Confronte com a [Fandom Wiki (EN)](https://afk-arena.fandom.com/wiki/Attributes) pra detalhes que possam variar entre patches.
