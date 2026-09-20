# Spec Driven Development no Projeto

## Status

Implemented

## Contexto

O projeto ja possui testes automatizados, mas ainda nao tinha um processo padronizado para descrever mudancas de comportamento antes da implementacao. Isso dificulta alinhar escopo, contrato de API e criterios de aceite antes do codigo.

## Objetivos

- [x] Criar um guia de Spec Driven Development para o projeto.
- [x] Disponibilizar um template de spec reutilizavel.
- [x] Adicionar um comando automatizado para validar a estrutura das specs.
- [x] Integrar a validacao de specs ao fluxo de testes do Composer.

## Fora de Escopo

- Bloquear commits localmente com hooks.
- Migrar testes existentes para uma nova nomenclatura.
- Criar specs retroativas para todas as features ja implementadas.

## Contrato de API

Nao altera contrato de API. A mudanca adiciona apenas documentacao, template e automacao de desenvolvimento.

## Criterios de Aceite

- [x] O projeto deve conter um guia de SDD em `docs/spec-driven-development.md`.
- [x] O diretorio `specs/` deve conter um template com secoes obrigatorias.
- [x] `composer spec:check` deve validar specs em `specs/*.md`.
- [x] `composer spec:test` deve executar a validacao de specs antes da suite Laravel.

## Plano de Testes

- Rodar `php -l scripts/spec-check.php`.
- Rodar `composer validate --no-check-publish`.
- Rodar `composer spec:check`.
- Rodar `composer spec:test`.

## Notas de Implementacao

O verificador ignora `specs/README.md` e `specs/TEMPLATE.md`, exige as secoes padrao e verifica se a secao de criterios de aceite contem pelo menos um item de checklist.
