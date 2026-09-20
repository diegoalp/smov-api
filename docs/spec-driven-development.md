# Spec Driven Development

Este projeto usa Spec Driven Development para transformar uma mudanca em contrato antes de virar codigo. A ideia e simples: nenhuma feature relevante entra sem uma spec curta, testavel e rastreavel.

## Fluxo

1. Crie uma spec em `specs/` a partir de `specs/TEMPLATE.md`.
2. Preencha o problema, o contrato de API, os criterios de aceite e o plano de testes.
3. Valide a spec com `composer spec:check`.
4. Implemente somente o que a spec cobre.
5. Escreva ou atualize testes automatizados que provem os criterios de aceite.
6. Rode `composer spec:test` antes de abrir PR ou entregar a mudanca.

## Quando criar uma spec

Crie uma spec para:

- novos endpoints ou alteracoes de contrato de API;
- regras de negocio novas ou alteradas;
- migracoes que mudam comportamento visivel;
- integracoes externas;
- fluxos que afetam autenticacao, autorizacao, instancias ou dados multi-tenant.

Mudancas pequenas de manutencao, refactors internos sem mudanca de comportamento e ajustes cosmeticos podem referenciar uma spec existente ou explicar no PR por que nao precisam de spec.

## Nomes de arquivo

Use nomes estaveis e ordenaveis:

```text
specs/YYYY-MM-DD-nome-da-feature.md
```

## Status da spec

Use um destes valores no campo `Status`:

- `Draft`: ainda esta sendo discutida.
- `Accepted`: pronta para implementacao.
- `Implemented`: codigo e testes entregues.
- `Superseded`: substituida por outra spec.

## Criterios de aceite

Cada criterio deve ser verificavel por teste ou revisao objetiva. Prefira frases que possam falhar claramente.

Bom:

```text
- [ ] Ao criar um negocio sem `stage_id`, a API retorna 422 com `error.code = VALIDATION_ERROR`.
```

Fraco:

```text
- [ ] A criacao de negocio deve funcionar bem.
```

## Contrato de API

Para endpoints, documente metodo, caminho, autenticacao, payloads, respostas de sucesso, erros e efeitos colaterais. Quando a feature nao muda API, escreva `Nao altera contrato de API` e explique o contrato interno afetado.

## Validacao automatizada

`composer spec:check` verifica se cada spec em `specs/*.md` contem as secoes obrigatorias e pelo menos um criterio de aceite.

`composer spec:test` executa a validacao das specs e a suite de testes Laravel.
