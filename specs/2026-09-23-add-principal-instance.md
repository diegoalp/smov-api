# Instância principal sem expiração

## Status

Implementado

## Contexto

O sistema já diferencia usuários pelo tipo `master`, mas ainda não diferencia instâncias principais que devem permanecer ativas indefinidamente. Precisamos identificar esse tipo de instância para que o frontend possa preparar comportamentos específicos no futuro e para que a instância não seja bloqueada por expiração.

Nesta spec, “usuário master” significa um usuário com a permissão global `UserType::Master`; “instância principal” significa uma instância cujo campo `is_principal` é `true`. São conceitos distintos.

## Objetivos

- [x] Adicionar o campo booleano `is_principal` à entidade `instances`.
- [x] Expor `is_principal` no contrato de leitura e escrita de instâncias.
- [x] Garantir que uma instância principal não tenha data de expiração e não seja bloqueada pelo middleware de expiração.
- [x] Garantir que somente usuários master possam criar, promover ou rebaixar uma instância principal.

## Fora de Escopo

- Layout ou visual diferenciado no frontend.
- Funcionalidades de negócio específicas para instâncias principais.
- Alterações no modelo de permissões de usuários.

## Contrato de API

Os endpoints existentes de instâncias serão utilizados.

### Criação

`POST /api/instances`

O usuário master poderá enviar:

```json
{
  "name": "Instância principal",
  "is_principal": true
}
```

Quando `is_principal` for `true`, `expiration_date` deve ser omitido e será persistido como `null`. Para instâncias comuns, `is_principal` é opcional e assume `false`; as regras atuais de `expiration_date` permanecem válidas.

Usuários autenticados que não sejam master não podem enviar `is_principal: true`; a API deve retornar `422` por campo proibido, sem criar ou alterar a instância. Usuários sem autorização para acessar o endpoint continuam recebendo `403`.

### Atualização

`PATCH /api/instances/{instance}`

Somente um usuário master pode alterar `is_principal`:

- ao tornar uma instância principal (`is_principal: true`), `expiration_date` deve ser removido ou enviado como `null`;
- ao deixar de ser principal (`is_principal: false`), `expiration_date` deve ser enviada com uma data válida, ou a API deve rejeitar a operação com `422`;
- uma instância principal não pode permanecer com `is_principal: true` e uma data de expiração preenchida.

Usuários não master não podem alterar `is_principal` nem tornar instâncias principais; a API deve retornar `422` por campo proibido.

### Resposta

As respostas de `POST /api/instances`, `GET /api/instances`, `GET /api/instances/{instance}` e `PATCH /api/instances/{instance}` devem incluir:

```json
{
"is_principal": true,
"expiration_date": null
}
```

### Banco de Dados

A tabela `instances` deve receber `is_principal` como booleano, com default `false` e sem permitir `null`. Instâncias existentes devem permanecer comuns (`is_principal = false`) e conservar suas datas de expiração atuais.

A regra de integridade do domínio é:

```text
is_principal = true => expiration_date IS NULL
```


## Criterios de Aceite

- [x] Dado um usuário master, quando criar uma instância com `is_principal: true`, a instância é persistida com `is_principal = true` e `expiration_date = null`.
- [x] Dado um usuário autenticado não master, quando tentar criar ou alterar uma instância com `is_principal: true`, a API retorna `422` e nenhuma alteração é persistida.
- [x] Quando uma instância principal for retornada pela API, a resposta conterá `is_principal: true` e `expiration_date: null`.
- [x] Uma instância principal não será considerada expirada e não será bloqueada pelo middleware de instância expirada.
- [x] Instâncias comuns continuam sujeitas às regras atuais de expiração.
- [x] Ao rebaixar uma instância principal, uma data de expiração válida passa a ser obrigatória.
- [x] Instâncias existentes e novas instâncias comuns terão `is_principal = false` por padrão.


## Plano de Testes

- Teste de migration verificando o default `false`, a ausência de `null` e a preservação das instâncias existentes.
- Teste de criação de instância principal por usuário master, verificando `is_principal` e `expiration_date`.
- Teste de rejeição de `is_principal` para usuários admin e demais usuários não master.
- Teste de promoção e rebaixamento por usuário master, incluindo rejeição de combinação inválida entre `is_principal` e `expiration_date`.
- Teste do `InstanceResource` nos endpoints de criação, listagem, consulta e atualização.
- Teste do modelo/middleware confirmando que instâncias principais não expiram nem retornam `INSTANCE_EXPIRED`.
- Teste de regressão confirmando que instâncias comuns continuam expirando e sendo bloqueadas como atualmente.


## Notas de Implementacao

- Criar migration para adicionar `is_principal` na tabela `instances`, com default `false` e valor não nulo.
- Adicionar `is_principal` ao preenchimento/cast do model e ao `InstanceResource`.
- Atualizar `StoreInstanceRequest` e `UpdateInstanceRequest` para validar autorização e a relação entre `is_principal` e `expiration_date`.
- Atualizar a regra de expiração da instância para tratar a instância principal como permanentemente ativa.
- Preservar a compatibilidade de instâncias existentes: todas serão consideradas comuns após a migration.
- Evitar que o campo seja alterado por mass assignment por usuários não autorizados.
- Registrar explicitamente os códigos HTTP escolhidos para autorização e validação na implementação e nos testes.
