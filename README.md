# LTM API

API Laravel para gestao de CRM, instancias, negocios, atividades, documentos e fluxos relacionados.

## Desenvolvimento

Instale dependencias e prepare o ambiente:

```bash
composer setup
```

Rode a suite de testes:

```bash
composer test
```

## Spec Driven Development

Mudancas de comportamento devem comecar por uma spec em `specs/`. Use `specs/TEMPLATE.md` para criar o contrato da feature antes da implementacao.

Comandos principais:

```bash
composer spec:check
composer spec:test
```

Leia o guia completo em `docs/spec-driven-development.md`.

## Estrutura

- `app/`: codigo da aplicacao Laravel.
- `routes/api.php`: rotas da API.
- `database/migrations/`: schema e evolucao do banco.
- `tests/Feature/`: testes de comportamento da API.
- `tests/Unit/`: testes unitarios.
- `specs/`: especificacoes de produto e API.

## Qualidade

Antes de entregar uma mudanca de comportamento, rode:

```bash
composer spec:test
```

Esse comando valida as specs e executa os testes automatizados.
