# ¿Qué cambia y por qué?

<!-- Qué problema resuelve, no qué archivos tocaste: eso ya lo dice el diff.
     Si es una decisión de diseño, explica qué alternativa descartaste. -->

Cierra #

## Tipo de cambio

- [ ] `feat` — funcionalidad nueva
- [ ] `fix` — corrección de un fallo
- [ ] `refactor` — cambio interno sin alterar el comportamiento
- [ ] `docs` — documentación
- [ ] `chore` — dependencias, configuración, tareas de mantenimiento

## Historia de usuario

<!-- HU-XX, o "ninguna" si es deuda técnica o mantenimiento. -->

## Cómo se probó

<!-- Qué ejecutaste y qué devolvió. Si hay comandos de prueba, pégalos.

     php artisan pos:probar
     php artisan conteo:probar
     php artisan igv:probar
     pnpm exec vue-tsc --noEmit
-->

## Comprobaciones antes de pedir revisión

- [ ] El proyecto compila (`pnpm build`) y pasa el chequeo de tipos
- [ ] No hay credenciales, volcados de base ni datos reales de la botica
- [ ] Las migraciones tienen su `down()` y se probaron de ida y vuelta
- [ ] Lo que toca dinero o stock ocurre dentro de una transacción
- [ ] Si cambia la interfaz: revisado en modo claro y oscuro
- [ ] Si cambia el color: contraste mínimo 4.5:1 en texto normal

## Capturas

<!-- Sólo si cambia algo visible. Antes y después ayuda más que sólo después. -->

## Riesgos y qué vigilar al desplegar

<!-- Qué podría romperse, y cómo revertirlo si pasa. -->
