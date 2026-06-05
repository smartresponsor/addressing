# Addressing W09 RC proof fix

W09 fixes the remaining RC proof blockers found after W08.

## Fixed

- Deptrac `classNameRegex` collectors now use the supported `value` key with explicit regex delimiters.
- PHPStan Doctrine object manager loader no longer pulls `Doctrine\ORM\EntityManagerInterface` from the compiled Symfony container.
- PHPStan console loader forces `APP_ENV=test` before booting the kernel.
- PHPStan container XML path points to the test container.
- RC proof PowerShell script now fails hard on non-zero native command exit codes.

## Still local

Run `composer update --lock` if `composer validate --strict` reports lock drift.
