# Criação de Checklists

## Status

Implementado

## Contexto

Precisamos permitir que cada instância crie checklists configuráveis para orientar o andamento dos negócios. Um checklist possui itens ordenados e pode ser associado a um ou mais funis.

Opcionalmente, o checklist pode ter condições de exibição. Por exemplo, um checklist de averbação pode aparecer no funil de consignado quando o produto for Portabilidade e a etapa atual for Documentação ou qualquer etapa posterior.

O checklist deve ser isolado por instância. Usuários master e admin gerenciam checklists; os demais usuários podem consultar os checklists aplicáveis à instância e marcar seus itens como concluídos no contexto de um negócio, conforme suas permissões atuais de acesso ao CRM.

## Objetivos

- [ ] Criar a entidade `Checklist`, relacionada a uma instância e a um ou mais funis.
- [ ] Criar a entidade `ChecklistItem`, com relação 1:N com checklist.
- [ ] Permitir criar, listar, consultar, atualizar e remover checklists.
- [ ] Permitir consultar somente os checklists aplicáveis a um funil, produto e etapa.
- [ ] Permitir somente 1 checklist por produto.
- [ ] Permitir ordenar os itens dentro de um checklist.
- [ ] Persistir o estado de conclusão de cada item por negócio, sem associá-lo a um usuário específico.
- [x] Criar automaticamente conclusões pendentes quando um negócio novo for criado ou mudar de etapa.
- [ ] Registrar no histórico qual usuário marcou ou desmarcou cada item e quando isso ocorreu.
- [ ] Garantir que somente usuários master ou admin possam criar, editar e remover checklists e itens.
- [ ] Garantir isolamento completo entre instâncias.

## Fora de Escopo

- Layout ou visual da página de gestão dos checklists.
- Histórico de alterações dos checklists.
- Regras com operadores diferentes de etapa mínima.

## Conceitos e Regras de Domínio

- `Checklist` pertence a exatamente uma `Instance`.
- Um checklist pertence a um ou mais funis.
- Um produto pode nenhum ou somente um checklist.
- Um checklist possui zero ou mais `ChecklistItem`.
- Cada item possui uma posição única dentro do checklist.
- Um checklist pode estar ativo ou inativo. O padrão é ativo.
- A configuração de um item não guarda o estado de conclusão.
- A conclusão é única por combinação de item e negócio; o estado atual é compartilhado pelos usuários que podem acessar o negócio.
- Ao criar um negócio, ou quando seu `stage_id` mudar, os checklists aplicáveis devem criar uma conclusão `done = false` para cada item que ainda não possua conclusão naquele negócio.
- A criação automática de uma conclusão pendente não registra histórico, pois não representa uma ação de usuário.
- O usuário que marcou ou desmarcou o item e o momento da ação são registrados na tabela `histories`.
- Checklists ativos podem ser retornados pela consulta de checklists aplicáveis.
- Checklists inativos continuam disponíveis na listagem administrativa, mas não aparecem na consulta de checklists aplicáveis.
- O título deve ser único dentro da mesma instância.

### Condições de Exibição

As condições são opcionais.

- Sem condições, o checklist aplica-se a todos os produtos e etapas dos funis associados.
- Cada condição pertence a um funil associado ao checklist.
- `product_ids` vazio ou nulo significa todos os produtos daquele funil.
- `min_stage_id` nulo significa todas as etapas daquele funil.
- Quando `min_stage_id` é informado, o checklist aplica-se à etapa indicada e às etapas posteriores, comparando o campo `stages.position`, nunca o ID.
- Uma condição é satisfeita quando o funil corresponde, o produto corresponde — ou não há restrição de produto — e a etapa atual atende à etapa mínima — ou não há restrição de etapa.
- Quando houver várias condições, basta uma condição ser satisfeita (`OR`).
- Produtos e etapas referenciados devem pertencer à mesma instância e ao funil da condição.

## Contrato de API

As rotas devem ficar agrupadas sob o recurso plural `checklists`.

### Contexto da Instância

- Usuário admin: `instance_id` deve ser derivado da instância do usuário; qualquer `instance_id` enviado pelo cliente deve ser ignorado ou rejeitado.
- Usuário master com instância selecionada: usar o `instance_id` informado no contexto atual, seguindo `InstanceContext`.
- Usuário master sem instância selecionada: retornar `422` com `INSTANCE_REQUIRED`.
- Nenhuma relação de outro tenant pode ser aceita, mesmo quando os IDs forem válidos.

### Criação

`POST /api/checklists`

Usuário master ou admin envia:

```json
{
  "instance_id": 1,
  "title": "Checklist de averbação",
  "description": "Documentos necessários para averbação",
  "active": true,
  "funnel_ids": [1, 3],
  "items": [
    {
      "label": "Documento de identidade",
      "position": 1,
      "required": true
    },
    {
      "label": "Comprovante de residência",
      "position": 2,
      "required": false
    }
  ],
  "conditions": [
    {
      "funnel_id": 1,
      "product_ids": [10],
      "min_stage_id": 4
    },
    {
      "funnel_id": 3,
      "product_ids": [],
      "min_stage_id": 8
    }
  ]
}
```

Regras de entrada:

- `title` é obrigatório, textual e único dentro da instância.
- `description` é opcional.
- `active` é opcional e assume `true`.
- `funnel_ids` é obrigatório, deve conter ao menos um funil e não pode conter IDs repetidos.
- `items` é opcional; quando informado, cada item deve possuir `label`, `position` e `required`.
- `position` deve ser inteiro positivo e único dentro do checklist.
- `conditions` é opcional; quando informado, cada condição deve referenciar um funil de `funnel_ids`.
- `product_ids` é opcional e não pode conter IDs repetidos.
- `min_stage_id` é opcional, mas, quando informado, a etapa deve pertencer ao `funnel_id` da condição.
- A criação de checklist, itens, associações e condições deve ocorrer em uma única transação.

Resposta de sucesso: `201 Created`, contendo o checklist com seus itens, funis e condições.

Erros esperados:

- `403` quando o usuário não puder gerenciar checklists.
- `422` para payload inválido ou relações inconsistentes.
- `422` com `INSTANCE_REQUIRED` quando um master não selecionar uma instância.

### Listagem Administrativa

`GET /api/checklists`

Retorna os checklists da instância atual, incluindo ativos e inativos, com paginação. A resposta deve incluir os itens e os funis associados.

### Consulta

`GET /api/checklists/{checklist}`

Retorna um checklist específico somente quando ele pertence à instância atual.

### Consulta de Checklists Aplicáveis

`GET /api/checklists/available?funnel_id=1&product_id=10&stage_id=4`

Retorna somente checklists ativos que atendem às condições para o contexto informado.

Validações:

- `funnel_id` é obrigatório.
- `product_id` deve pertencer à instância e ao funil informado, quando enviado.
- `stage_id` deve pertencer ao funil informado.
- A consulta deve respeitar o escopo da instância atual.

### Consulta de Checklists de um Negócio

`GET /api/businesses/{business}/checklists`

Retorna os checklists ativos aplicáveis ao negócio, incluindo o estado atual de conclusão de cada item:

```json
{
  "id": 1,
  "title": "Checklist de averbação",
  "items": [
    {
      "id": 1,
      "label": "Documento de identidade",
      "position": 1,
      "required": true,
      "done": false,
      "completed_at": null,
      "completed_by": null
    }
  ]
}
```

O acesso deve respeitar as regras atuais de autorização do negócio. Checklists de outra instância ou que não atendam às condições do negócio não devem ser retornados.

Quando `done` for `true`, `completed_at` deve vir do registro de conclusão e `completed_by` deve ser o usuário do último histórico `checklist_item_completed` correspondente. Quando `done` for `false`, ambos devem ser `null`.

### Atualização da Conclusão de um Item

`PATCH /api/businesses/{business}/checklists/{checklist}/items/{item}/completion`

O usuário do negócio, o supervisor autorizado, o admin ou o master pode enviar:

```json
{
  "done": true
}
```

Regras:

- `done` é obrigatório e booleano.
- A combinação negócio/checklist/item deve ser válida e pertencer à instância atual.
- O checklist deve ser aplicável ao negócio para que o item possa ser alterado.
- A operação atualiza ou cria a conclusão única do item para o negócio.
- Quando o estado realmente mudar, a operação deve criar um registro em `histories`, usando `object_type = checklist_item_completion`, `object_id` igual ao ID da conclusão, `user_id` igual ao usuário autenticado e `action` com um dos valores `checklist_item_completed` ou `checklist_item_uncompleted`.
- Se o estado enviado for igual ao estado atual, a operação deve ser idempotente, retornar sucesso e não criar um novo histórico.
- `done: true` deve preencher `completed_at`; `done: false` deve limpar `completed_at`.
- A operação deve ser transacional.

### Criação Automática de Conclusões

A criação de um negócio e a alteração de sua etapa devem verificar os checklists aplicáveis ao produto, funil e etapa atuais.

- Checklist sem `min_stage_id` para o produto aplica-se a todas as etapas do funil.
- Checklist sem condições aplica-se a todos os produtos e etapas dos funis associados.
- Para cada item aplicável sem registro em `checklist_item_completions`, criar uma linha com `done = false`.
- Não alterar uma conclusão existente, mesmo que ela esteja marcada como concluída.
- Não criar registro em `histories` durante essa preparação automática.
- A operação deve ser idempotente.

### Atualização

`PATCH /api/checklists/{checklist}`

Usuários master ou admin podem atualizar título, descrição, status, itens, funis e condições. A atualização dos dados aninhados substitui integralmente a configuração enviada e deve ser transacional. Ela não pode alterar as conclusões já registradas por negócio.

O checklist não pode ficar sem funis associados. Ao substituir os itens, as posições devem ser validadas novamente.

### Remoção

`DELETE /api/checklists/{checklist}`

Usuários master ou admin podem remover um checklist. A remoção deve usar soft delete, preservando o histórico estrutural e impedindo que o checklist apareça nas consultas.

### Resposta

O formato mínimo de um checklist é:

```json
{
  "id": 1,
  "instance_id": 1,
  "title": "Checklist de averbação",
  "description": "Documentos necessários para averbação",
  "active": true,
  "funnels": [
    {"id": 1, "name": "Consignado"}
  ],
  "items": [
    {
      "id": 1,
      "label": "Documento de identidade",
      "position": 1,
      "required": true
    }
  ],
  "conditions": [
    {
      "id": 1,
      "funnel_id": 1,
      "product_ids": [10],
      "min_stage_id": 4
    }
  ],
  "created_at": "2026-09-28T12:00:00Z",
  "updated_at": "2026-09-28T12:00:00Z"
}
```

## Persistência

Criar as seguintes estruturas:

### `checklists`

- `id`
- `instance_id`, com foreign key
- `title`
- `description`, nullable
- `active`, default `true`
- `softDeletes`
- timestamps
- unique composto entre `instance_id` e `title`

### `checklist_items`

- `id`
- `checklist_id`, com cascade on delete
- `label`
- `required`, default `false`
- `position`
- timestamps
- unique composto entre `checklist_id` e `position`

### `checklist_item_completions`

- `id`
- `checklist_item_id`, com foreign key
- `business_id`, com foreign key
- `done`, default `false`
- `completed_at`, nullable
- timestamps
- unique composto entre `checklist_item_id` e `business_id`

Não deve existir `user_id` em `checklist_item_completions`. O usuário responsável pela ação deve ser registrado somente em `histories`.

### `histories`

Reutilizar a estrutura existente de histórico:

- `instance_id`
- `object_id` igual ao ID de `checklist_item_completions`
- `object_type = checklist_item_completion`
- `action` igual a `checklist_item_completed` ou `checklist_item_uncompleted`
- `user_id` igual ao usuário que executou a ação
- `created_at` como momento da ação

### `checklist_funnel`

- `checklist_id`
- `funnel_id`
- timestamps
- unique composto entre `checklist_id` e `funnel_id`

### `checklist_conditions`

- `id`
- `checklist_id`, com cascade on delete
- `funnel_id`, com foreign key
- `min_stage_id`, nullable, com foreign key
- timestamps

### `checklist_condition_product`

- `condition_id`, com cascade on delete
- `product_id`, com foreign key
- unique composto entre `condition_id` e `product_id`

As exclusões e consultas devem respeitar soft deletes de checklists, funis, produtos e etapas.

## Criterios de Aceite

- [ ] Usuário master ou admin consegue criar um checklist válido.
- [ ] Usuário seller não consegue criar, editar ou remover checklists.
- [ ] O checklist é criado atomicamente com seus itens, funis e condições.
- [ ] Funis de outra instância são rejeitados com `422`.
- [ ] Produtos que não pertencem à instância ou ao funil da condição são rejeitados com `422`.
- [ ] Etapas que não pertencem ao funil da condição são rejeitadas com `422`.
- [ ] Um checklist sem condições aplica-se a todos os produtos e etapas dos funis associados.
- [ ] Uma condição com produtos restringe o checklist aos produtos informados.
- [ ] Uma condição com etapa mínima aplica-se à etapa informada e às etapas posteriores pela ordem de `position`.
- [ ] Várias condições são avaliadas com lógica `OR`.
- [ ] Itens são persistidos na ordem informada.
- [ ] Posições duplicadas dentro do checklist são rejeitadas.
- [ ] Um checklist não pode ser criado sem ao menos um funil.
- [ ] Um checklist não pode ser atualizado para ficar sem funis.
- [ ] Checklists inativos não aparecem na consulta de checklists aplicáveis.
- [ ] Checklists de outra instância não podem ser consultados, atualizados ou removidos.
- [ ] A criação retorna `201` com checklist, itens, funis e condições.
- [ ] Falha em qualquer validação não deixa registros parciais no banco.
- [ ] O endpoint de checklists aplicáveis retorna somente checklists compatíveis com funil, produto e etapa.
- [x] Ao criar um negócio, são criadas conclusões pendentes para os itens dos checklists aplicáveis.
- [x] Ao alterar a etapa de um negócio, são criadas conclusões pendentes para os novos checklists aplicáveis.
- [x] Um checklist de produto sem etapa mínima aplica-se a todas as etapas do funil.
- [x] A preparação automática não duplica conclusões nem cria histórico de usuário.
- [ ] A conclusão de um item é compartilhada entre os usuários autorizados a acessar o mesmo negócio.
- [ ] Usuário do negócio, supervisor autorizado, admin e master podem marcar ou desmarcar um item.
- [ ] Usuário sem acesso ao negócio não pode alterar a conclusão do item.
- [ ] Marcar ou desmarcar um item atualiza `checklist_item_completions` sem criar duplicidade.
- [ ] Cada mudança de estado gera um registro em `histories` com usuário e data.
- [ ] Repetir a atualização com o mesmo estado não cria histórico duplicado.
- [ ] O estado `done` não é persistido em `checklist_items`.

## Plano de Testes

- Testes de migration para foreign keys, índices, unicidade, defaults e soft deletes.
- Testes de criação com payload completo, sem itens e sem condições.
- Testes de autorização para master, admin e seller.
- Testes de isolamento entre instâncias para todos os endpoints.
- Testes de validação de funil, produto, etapa e combinações entre eles.
- Testes de transação garantindo rollback quando item, condição ou relação for inválida.
- Testes da regra sem condições, que deve aplicar o checklist a todo o funil.
- Testes de etapa mínima usando `stages.position`.
- Testes de múltiplas condições com lógica `OR`.
- Testes de listagem incluindo checklists inativos para usuários gestores.
- Testes de consulta aplicável excluindo checklists inativos.
- Testes de atualização substituindo itens, funis e condições.
- Testes de remoção lógica e ausência nas consultas aplicáveis.
- Testes de consulta de checklist no contexto de um negócio com `done`, `completed_at` e usuário responsável.
- Testes de autorização para usuário do negócio, supervisor, admin, master e usuário sem acesso.
- Testes de marcação e desmarcação idempotentes usando `checklist_item_completions`.
- Testes de criação de negócio e mudança de etapa criando conclusões pendentes.
- Testes de checklist por produto sem etapa mínima aplicável em todas as etapas.
- Testes de idempotência da preparação automática e ausência de histórico nessa operação.
- Testes de histórico para confirmar usuário, ação, objeto e timestamp.

## Notas de Implementacao

- Criar models `Checklist` e `ChecklistItem` e suas relações com `Instance`, `Funnel`, `Product` e `Stage`.
- Criar migrations normalizadas para checklists, itens, associações e condições.
- Criar `ChecklistController`, requests de criação/atualização e resources.
- Usar `InstanceContext` para resolver a instância atual e impedir acesso cross-tenant.
- Usar `DB::transaction` na criação e atualização da configuração aninhada.
- Usar `DB::transaction` ao atualizar uma conclusão e registrar seu histórico.
- Executar a preparação automática dentro da transação de criação/atualização do negócio.
- Centralizar a seleção de checklists aplicáveis e a criação idempotente das conclusões em um serviço reutilizável.
- Manter `done` fora de `checklist_items`; ele pertence a `checklist_item_completions`.
- Reutilizar `histories` para auditar o usuário e o momento de cada marcação ou desmarcação.
- Validar relações com `Rule::exists(...)->where(...)` e verificações adicionais para garantir que produto e etapa pertencem ao funil correto.
- Implementar a consulta de checklists aplicáveis comparando `stages.position`.
- Registrar as rotas estáticas de `available` antes da rota parametrizada `{checklist}`.
- Manter a primeira versão restrita a itens `checkbox`; novos tipos devem exigir nova alteração de contrato e testes.
