# Documentação dos Fluxos e Passos - WhatsApp

Este documento descreve os fluxos e passos configurados no sistema de WhatsApp (conforme `database/migrations/2025_10_16_140711_create_whatsapp_flow_steps_table.php`). Cada passo lista o prompt enviado ao usuário, o tipo de entrada esperada e a condição que determina o próximo passo.

> Observação: os placeholders como `{{primeironome}}` e `{{NomeBanco}}` serão substituídos dinamicamente pelo código.

## Fluxo Inicial (flow_id = 1)

- Step 1
  - step_number: 1
  - prompt: "Seja bem-vindo(a) ao nosso canal digital! Sou a assistente da Neocob em nome da {{NomeBanco}}."
  - expected_input: null
  - next_step_condition: `verifica_horario`
  - Descrição: mensagem de boas-vindas enviada ao iniciar a conversa. A condição `verifica_horario` deve avaliar se é horário útil e direcionar para o próximo passo apropriado.

- Step 2
  - step_number: 2
  - prompt: "Hoje é dia útil ? (07:00 às 22:00; Sábados: 07:00 às 14:00)\n\nSelecione: Sim ou Não"
  - expected_input: `botao`
  - next_step_condition: `verifica_horario`
  - Descrição: pergunta rápida de disponibilidade do serviço. Espera um botão/quick-reply com resposta "Sim" ou "Não". A condição `verifica_horario` decide se avança para solicitar CPF ou informa que está fora do horário.

- Step 3
  - step_number: 3
  - prompt: "Para localizar suas informações, por favor informe seu *CPF/CNPJ* (apenas números).\nDigite apenas os números conforme o exemplo abaixo:\n01010109120"
  - expected_input: `cpf`
  - next_step_condition: `api_valida_cpf`
  - Descrição: solicita o CPF/CNPJ do cliente. O próximo passo valida o documento e pesquisa por contratos.

- Step 4
  - step_number: 4
  - prompt: "Estou buscando as informações necessárias para seguirmos por aqui, só um instante."
  - expected_input: null
  - next_step_condition: `fluxo_negociar`
  - Descrição: mensagem informativa enviada enquanto o sistema processa a busca de contratos. Em seguida, a sessão transita para o `Fluxo Negociar`.

- Step 5
  - step_number: 5
  - prompt: "Não localizei cadastro com esse documento. Por favor digite novamente o CPF/CNPJ (apenas números)."
  - expected_input: `cpf`
  - next_step_condition: `api_valida_cpf`
  - Descrição: mensagem enviada quando a busca não retornou resultados. Solicita reentrada do CPF.


## Fluxo Negociar (flow_id = 2)

- Step 1
  - step_number: 1
  - prompt: "@primeironome! Identifiquei 1 débito em atraso a *5* dias.\n\nAguarde enquanto localizo a melhor proposta para você..."
  - expected_input: null
  - next_step_condition: `repetir_pergunta`
  - Descrição: mensagem inicial do fluxo de negociação quando existe um débito. Informa o usuário e pode acionar lógica adicional para proposta.

- Step 2
  - step_number: 2
  - prompt: "{{Nome}}, você não possui contrato(s) ativo(s) em nossa assessoria.\n\n\nPodemos ajudar em algo mais?\nSelecione uma opção abaixo:"
  - expected_input: `botao`
  - next_step_condition: `processar_opcao`
  - Descrição: se nenhum contrato for encontrado, apresenta menu de opções (Negociar, 2ª via de boleto, Enviar comprovante, Atendimento, Encerrar conversa).

- Step 3
  - step_number: 3
  - prompt: "@primeironome, localizei *8* contratos em aberto.\n\nSelecione o botão abaixo para conferir:"
  - expected_input: `botao`
  - next_step_condition: `processar_opcao`
  - Descrição: quando múltiplos contratos são encontrados, lista/resume e pede seleção por botão.

- Step 4
  - step_number: 4
  - prompt: "@primeironome, este contrato possui acordo vigente? Por favor selecione: Sim ou Não"
  - expected_input: `botao`
  - next_step_condition: `verifica_acordo`
  - Descrição: após o cliente selecionar um contrato, pergunta se existe acordo vigente para aquele contrato e direciona para visualizar acordos ou para verificar débitos.

- Step 5
  - step_number: 5
  - prompt: "@primeironome, localizei *{{qtdAcordos}}* acordo(s) vigente(s). Deseja visualizar?"
  - expected_input: `botao`
  - next_step_condition: `fluxo_acordos`
  - Descrição: quando existem acordos vigentes, apresenta a quantidade encontrada e pergunta se o cliente deseja visualizar os acordos.

- Step 6
  - step_number: 6
  - prompt: "Para este contrato, selecione uma opção abaixo:"
  - expected_input: `botao`
  - next_step_condition: `processar_opcao`
  - Descrição: menu de ações por contrato (Negociar / 2ª via de boleto / Enviar comprovante / Atendimento / Encerrar conversa).


## Fluxo Proposta (flow_id = 3)

- Step 1
  - step_number: 1
  - prompt: "A melhor oferta para pagamento é de R$ *{{valorTotal}}* com vencimento em *{{data}}*.\n\n*Podemos enviar o boleto?*\nSelecione uma opção abaixo:"
  - expected_input: `botao`
  - next_step_condition: `processar_opcao`
  - Descrição: apresenta a proposta principal ao cliente e solicita escolha via botão (ex.: Gerar Acordo, Mais Opções, Ver outro Contrato).

- Step 2
  - step_number: 2
  - prompt: "Opções:\n- Gerar Acordo\n- Mais Opções\n- Ver outro Contrato"
  - expected_input: `botao`
  - next_step_condition: `processar_opcao`
  - Descrição: botões rápidos associados à proposta — cada botão mapeia para uma ação tratada por `processar_opcao`.

- Step 3
  - step_number: 3
  - prompt: "Opções adicionais:\n- Alterar Vencimento\n- Parcelar Pagamento\n- Ver outro contrato\n- Falar com Especialista\n- Encerrar Atendimento"
  - expected_input: `botao`
  - next_step_condition: `processar_opcao`
  - Descrição: lista de alternativas (mais opções) que o cliente pode escolher para ajustar a proposta ou pedir atendimento.


## Fluxo Acordos (flow_id = 4)

- Step 1
  - step_number: 1
  - prompt: "@primeironome, localizei *{{qtdAcordos}}* acordos formalizados. Deseja visualizar?"
  - expected_input: `botao`
  - next_step_condition: `fluxo_envia_codigo_barras`
  - Descrição: pergunta sobre visualizar acordos formalizados.


## Fluxo Confirma Acordo (flow_id = 5)

- Step 1
  - step_number: 1
  - prompt: "Resumo do acordo: Valor R$ *{{valorTotal}}*, Vencimento *{{dataVencimento}}*. Confirmar formalização?"
  - expected_input: `sim_nao`
  - next_step_condition: `fluxo_envia_codigo_barras`
  - Descrição: confirma a formalização do acordo.


## Fluxo Envia Código de Barras (flow_id = 6)

- Step 1
  - step_number: 1
  - prompt: "Estou te enviando o código de barras para pagamento: {{codigoBarras}}"
  - expected_input: null
  - next_step_condition: `fluxo_algo_mais`
  - Descrição: envia o código de barras para pagamento.


## Fluxo Erros (flow_id = 7)

- Step 1
  - step_number: 1
  - prompt: "Esta não é uma resposta válida. Por favor, responda conforme solicitado."
  - expected_input: null
  - next_step_condition: `repetir_pergunta`
  - Descrição: mensagem genérica de erro quando a entrada não é válida.


## Fluxo Administrativo (flow_id = 8)

- Step 1
  - step_number: 1
  - prompt: "Configuração de feriados e expediente: informe as novas datas e horários."
  - expected_input: `texto`
  - next_step_condition: `salvar_configuracao`
  - Descrição: área administrativa para configurar feriados/expediente.


## Fluxo Avaliação Atendente (flow_id = 9)

- Step 1
  - step_number: 1
  - prompt: "@primeironome, avalie seu atendimento com uma nota de 0 a 10:"
  - expected_input: `numero`
  - next_step_condition: `avaliacao_finalizada`
  - Descrição: coleta a avaliação do usuário.


## Notas e próximos passos
- O passo `verifica_horario` precisa ser implementado no `processCondition` do controller para interpretar respostas "Sim"/"Não" na pergunta de horário e retornar o passo correto.
- Se quiser, posso:
  - Implementar `processCondition('verifica_horario')` no controller e adicionar a mensagem de fora de horário como um novo passo (ex.: Flow Inicial step 6), ou
  - Gerar um migration idempotente para ajustar os passos diretamente no banco (já criei outra migration idempotente, mas você pediu para manter esta file como fonte única — posso alterar conforme preferir).

---
Gerado automaticamente a partir do conteúdo de `database/migrations/2025_10_16_140711_create_whatsapp_flow_steps_table.php`.
