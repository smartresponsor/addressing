# Addressing DBAL test/bootstrap transition

The component test/bootstrap support no longer relies on native `PDO` helpers as the primary contract. Shared test setup now uses Doctrine DBAL `Connection`, and runtime smoke checks read schema state through the same DBAL-first bootstrap surface used by the component runtime.

This keeps test scaffolding aligned with the Doctrine-first/Symfony-first migration and avoids reintroducing a silent parallel PDO authority through support helpers.
