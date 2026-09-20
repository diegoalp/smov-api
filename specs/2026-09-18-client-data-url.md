# Busca de cliente por CPF/CNPJ e instancia

## Status

Implemented

## Contexto

Criar uma rota somente-leitura para buscar dados de um cliente ja cadastrado por CPF/CNPJ dentro da instancia resolvida da requisicao.

Hoje `POST /api/clients/resolve` busca ou cria um cliente. O novo fluxo precisa consultar um cliente existente antes do cadastro de um negocio, reaproveitando os dados retornados sem criar ou atualizar registros quando o cliente nao existir.

## Objetivos

- [x] Retornar os dados de um cliente existente pelo `registration` dentro da instancia autenticada ou instancia selecionada por usuario `master`.
- [x] Garantir que a busca nao crie, atualize ou exponha clientes de outras instancias.

## Fora de Escopo

- Cadastrar cliente se nao houver.
- Atualizar dados do cliente encontrado.
- Alterar regras de validacao de CPF/CNPJ alem da normalizacao numerica existente.

## Contrato de API

### `GET /api/clients/search`

Endpoint autenticado por Sanctum.

Query params:

- `registration` obrigatorio, string, CPF ou CNPJ com ou sem mascara.
- `instance_id` opcional para usuarios `master` sem instancia autenticada.

Quando o usuario autenticado pertence a uma instancia:

- a API normaliza `registration` removendo caracteres nao numericos;
- busca um cliente com o mesmo `registration` e `instance_id` da instancia autenticada;
- se encontrar, retorna o cliente existente;
- se nao encontrar, retorna `404`;
- ignora qualquer `instance_id` enviado na requisicao e usa a instancia do usuario autenticado.

Para usuarios `master` sem instancia autenticada:

- a requisicao deve informar `instance_id`;
- a API valida se a instancia existe;
- se a instancia nao for informada ou nao existir, retorna `422`;
- a busca usa o `instance_id` informado e nao retorna clientes de outras instancias.

Resposta de sucesso:

- `200` com `ClientResource`, incluindo `id`, `instance_id`, `fullname`, `type`, `registration`, dados cadastrais e `phones`.

Erros esperados:

- `401` quando a requisicao nao estiver autenticada;
- `422` quando `registration` estiver ausente, invalido ou quando um usuario `master` sem instancia autenticada nao informar uma instancia valida;
- `404` quando nenhum cliente com o `registration` normalizado existir na instancia resolvida.

## Criterios de Aceite

- [x] Dado um CPF/CNPJ ja cadastrado na instancia do usuario autenticado, quando `GET /api/clients/search?registration=...` for chamado, entao a API retorna `200` com o `client.id`, `instance_id`, dados cadastrais e telefones do cliente.
- [x] Dado um CPF/CNPJ existente somente em outra instancia, quando um usuario de uma instancia diferente chamar `GET /api/clients/search?registration=...`, entao a API retorna `404`.
- [x] Dado um CPF/CNPJ inexistente na instancia resolvida, quando a busca for chamada, entao a API retorna `404` e nao cria cliente.
- [x] Dado um usuario autenticado com instancia, quando a busca receber `instance_id` de outra instancia, entao a API ignora o parametro e busca apenas na instancia do usuario autenticado.
- [x] Dado um usuario `master` sem instancia autenticada, quando a busca for chamada sem `instance_id` valido, entao a API retorna `422`.
- [x] Dado um usuario `master` sem instancia autenticada e com `instance_id` valido, quando o CPF/CNPJ existir nessa instancia, entao a API retorna o cliente dessa instancia.

## Plano de Testes

- Adicionar teste de feature para `GET /api/clients/search` retornando cliente existente na instancia autenticada.
- Adicionar teste de feature garantindo que a busca nao retorna cliente de outra instancia com o mesmo `registration`.
- Adicionar teste de feature garantindo `404` para cliente inexistente e que nenhum registro e criado.
- Adicionar teste de feature garantindo que usuario com instancia nao consegue trocar o escopo via `instance_id`.
- Adicionar teste de feature para usuario `master` exigindo `instance_id` valido.
- Adicionar teste de feature para usuario `master` buscando cliente dentro da instancia informada.
- Rodar `composer spec:test`.

## Notas de Implementacao

- Adicionar a rota antes de `Route::apiResource('clients', ClientController::class)->except('store')` para evitar conflito com `clients/{client}`.
- Reutilizar `InstanceContext::id($request)` para resolver a instancia e `Client::accessibleByInstance($instanceId)` para manter o isolamento multi-tenant.
- Reutilizar `ClientResource` para manter o formato consistente com `GET /api/clients/{client}`.
- A busca deve ser somente-leitura: nao chamar `ClientResolver::resolve`, `firstOrCreate`, `updateOrCreate` ou qualquer atualizacao de telefones/dados cadastrais.
