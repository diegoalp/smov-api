# Persist the business value field

## Status

Accepted

## Contexto

The frontend sends is_business_value, but validation discards it and the model
and database have no matching attribute. Reloading loses both UI indicators.

## Objetivos

- [ ] Persist and return the boolean flag across create, update, show and list.

## Fora de Escopo

Automatic backfill of existing business amounts or inference from field labels.

## Contrato de API

Authenticated POST /api/custom-fields and PATCH /api/custom-fields/:id accept
optional boolean is_business_value. True requires type currency; violations
return 422. GET list/show and mutation responses include a JSON boolean.
Omitting the flag preserves it on PATCH unless type changes away from currency.
InstanceCrudController continues enforcing tenant isolation with instance_id.

## Criterios de Aceite

- [ ] True and false survive subsequent GET requests.
- [ ] Unrelated updates preserve the flag; a non-currency type clears it.
- [ ] Non-currency activation is rejected and tenants remain isolated.

## Plano de Testes

CustomFieldBusinessValueTest verifies HTTP persistence, database values,
validation, type changes and tenant isolation using in-memory SQLite.
Run composer spec:test and the frontend production build.

## Notas de Implementacao

Add a false-default boolean column in an additive migration. Add its fillable
attribute, boolean cast and validation. Previously discarded choices cannot be
recovered and must be saved once after applying the migration.
