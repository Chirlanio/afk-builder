# Só sinais validados por benchmark entram na Pontuação

Um sinal novo só vira Componente da pontuação (ou ganha peso diferente de zero) se o `optimizer:benchmark` mostrar que ele aproxima os Comps recomendados do topo, em comparação com times aleatórios. Antes disso, o sinal fica informativo: aparece na UI e no detalhamento, mas não decide a sugestão. Já aconteceu três vezes: o perfil de capacidades (Fase 62) ficou abaixo do acaso (percentil 20–41); os efeitos de mobília (Fase 66) só melhoravam o resultado pela cobertura dos dados e derrubavam o total de 88,3 para 74,0; e os Counters começam informativos porque ainda não existe benchmark de PvP.

## Consequences

Uma feature pode parecer "desligada" do otimizador mesmo estando pronta (Counters não mudam a Equipe sugerida). Para ligá-la, primeiro é preciso um benchmark que meça o ganho, e só depois mexer no peso.
