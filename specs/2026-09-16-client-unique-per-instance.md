# Cliente Unico por Instancia

## Status

Implemented

## Contexto

Hoje um cliente e identificado por `clients.registration` com unicidade global no banco. Isso faz com que o mesmo CPF/CNPJ seja compartilhado entre instancias diferentes, mesmo quando as instancias representam empresas independentes.

O novo comportamento esperado e que o cliente seja unico apenas dentro da instancia. O mesmo CPF/CNPJ pode existir mais de uma vez na tabela `clients`, desde que cada registro pertencente a uma instancia diferente. Dentro da mesma instancia, o CPF/CNPJ continua sendo unico e deve ser reaproveitado no cadastro de novos negocios.

## Objetivos

- [x] Adicionar escopo de instancia ao cadastro e resolucao de clientes.
- [x] Permitir que duas instancias diferentes tenham clientes com o mesmo CPF/CNPJ.
- [x] Impedir que a mesma instancia tenha dois clientes ativos com o mesmo CPF/CNPJ.
- [x] Reaproveitar o cliente existente da instancia quando um novo negocio for cadastrado com o mesmo CPF/CNPJ.
- [x] Garantir que negocios nao possam ser vinculados a clientes de outra instancia.

## Fora de Escopo

- Mesclar clientes duplicados ja existentes manualmente.
- Alterar regras de validacao de CPF/CNPJ alem da normalizacao numerica existente.
- Criar deduplicacao entre instancias.
- Alterar o modelo de telefones para compartilhar telefones entre clientes de instancias diferentes.

## Contrato de API

### `POST /api/clients/resolve`

O endpoint continua recebendo os dados atuais de cliente, incluindo `registration`, e passa a resolver o cliente no escopo da instancia autenticada.

Quando o usuario autenticado pertence a uma instancia:

- a API normaliza `registration` removendo caracteres nao numericos;
- busca um cliente com o mesmo `registration` e `instance_id` da instancia autenticada;
- se encontrar, atualiza os dados enviados e retorna o cliente existente;
- se nao encontrar, cria um novo cliente com `instance_id` da instancia autenticada;
- nao consulta, retorna nem atualiza clientes de outras instancias com o mesmo `registration`.

Para usuarios `master` sem instancia autenticada, a requisicao deve informar a instancia alvo de forma explicita quando o fluxo permitir operacao cross-tenant. Sem uma instancia resolvida, a API deve retornar erro de validacao em vez de criar cliente sem `instance_id`.

### `POST /api/businesses`

Ao cadastrar um negocio, o cliente vinculado deve pertencer a mesma `instance_id` do negocio.

Se o fluxo de cadastro de negocio receber dados de cliente/CPF antes de criar o negocio, a resolucao deve usar a mesma regra de `POST /api/clients/resolve`: reaproveitar o cliente existente apenas dentro da instancia do negocio e criar um novo cliente quando o CPF/CNPJ existir somente em outra instancia.

Um `client_id` de outra instancia deve retornar `422` com erro de validacao para `client_id` ou `relationships`.

### Banco de Dados

`clients` deve passar a ter `instance_id`.

A unicidade deve ser composta por instancia e documento normalizado:

```text
unique(instance_id, registration)
```

O indice unico global atual de `registration` deve ser removido.

## Criterios de Aceite

- [x] Dado um CPF ja cadastrado na instancia A, quando `POST /api/clients/resolve` for chamado na instancia A com o mesmo CPF, entao a API retorna o mesmo `client.id`.
- [x] Dado um CPF ja cadastrado na instancia A, quando `POST /api/clients/resolve` for chamado na instancia B com o mesmo CPF, entao a API cria e retorna outro `client.id`.
- [x] Dado dois clientes em instancias diferentes com o mesmo CPF, ambos permanecem salvos com `registration` normalizado igual e `instance_id` diferente.
- [x] Dado um negocio criado na instancia A com CPF de cliente ja existente na instancia A, o negocio e vinculado ao cliente existente.
- [x] Dado um negocio criado na instancia B com o mesmo CPF existente somente na instancia A, a API cria/reaproveita um cliente da instancia B e nao vincula o negocio ao cliente da instancia A.
- [x] Dado um `client_id` pertencente a outra instancia, `POST /api/businesses` retorna `422` e nao cria o negocio.
- [x] `GET /api/clients`, `GET /api/clients/{client}`, `PATCH /api/clients/{client}` e `DELETE /api/clients/{client}` continuam expondo somente clientes da instancia autenticada.
- [x] A migracao remove a unicidade global de `clients.registration` e adiciona unicidade composta por `instance_id` e `registration`.

## Plano de Testes

- Adicionar teste de feature para `POST /api/clients/resolve` reaproveitando cliente dentro da mesma instancia.
- Adicionar teste de feature para `POST /api/clients/resolve` criando clientes diferentes para instancias diferentes com o mesmo CPF.
- Adicionar teste de feature para impedir `POST /api/businesses` com `client_id` de outra instancia.
- Adicionar teste de feature para o fluxo de novo negocio reaproveitar o cliente da propria instancia quando o CPF/CNPJ ja existir.
- Adicionar cobertura de migracao/modelo garantindo `instance_id` fillable/cast/relacionamento quando aplicavel.
- Rodar `composer spec:test`.

## Notas de Implementacao

- Criar migracao para adicionar `instance_id` em `clients`, popular registros existentes a partir dos negocios vinculados quando houver uma instancia inferivel e ajustar indices.
- Avaliar registros historicos sem negocio vinculado antes de tornar `clients.instance_id` obrigatorio. Se existirem clientes sem instancia inferivel, a migracao deve ter uma estrategia explicita: atribuir via dado disponivel, manter temporariamente nullable ou falhar com instrucao operacional.
- Atualizar `Client` para pertencer a `Instance` e incluir `instance_id` nos campos preenchiveis.
- Atualizar `StoreClientRequest` e `UpdateClientRequest` para usar `Rule::unique('clients', 'registration')->where('instance_id', $instanceId)` em vez de unicidade global.
- Atualizar `ClientController::resolve` para usar `firstOrCreate`/`updateOrCreate` com `instance_id` e `registration`.
- Atualizar `StoreBusinessRequest` e `UpdateBusinessRequest` para validar que `client_id` pertence a instancia do negocio.
- Revisar `Client::scopeAccessibleByInstance` para usar `clients.instance_id` diretamente, mantendo compatibilidade com clientes legados apenas se necessario durante migracao.
