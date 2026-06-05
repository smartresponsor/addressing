# Addressing W10 RC proof fix

W10 removes the hardcoded PHPStan Symfony `containerXmlPath` because the proof script clears cache and the debug test container XML is not guaranteed to exist before PHPStan runs. DI proof remains covered by `bin/console lint:container --no-debug`; PHPStan keeps the console application loader.

Changes:

- `phpstan.neon`: removed `symfony.containerXmlPath`; kept `symfony.consoleApplicationLoader`.
- `tools/addressing-w10/check-addressing-w10-rc-proof.ps1`: no longer deletes Symfony runtime cache before PHPStan; clears only `var/phpstan`; exits on every non-zero native command.
- `tools/addressing-w10/fix-addressing-w10-composer-lock.ps1`: lock drift helper.
