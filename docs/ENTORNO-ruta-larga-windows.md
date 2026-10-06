# Aviso de entorno — la ruta del proyecto trunca nombres de archivo

**Síntoma:** tras un `composer install`, la suite de pruebas revienta con

```
PHP Fatal error: Uncaught PHPUnit\Event\UnknownSubscriberException:
Subscriber "PHPUnit\Event\Test\MockObjectForAbstractClassCreatedSubscriber"
does not exist or is not an interface
```

y el error va cambiando de clase a medida que se reparan los archivos uno a uno.

---

## Qué ocurre de verdad

No es un problema de versiones ni de caché de Laravel. Al extraer los ZIP de
Composer en esta ruta, **Windows trunca los nombres de archivo largos**:

```
MockObjectForIntersectionOfInterfacesCreatedSubscriber.php   (58 caracteres)
MockObjectForIntersectionOfInterfacesCreatedSu               (46, lo que queda)
```

Trece archivos quedan así en un `composer install` limpio: once de PHPUnit y
dos plantillas Blade del renderizador de excepciones de Laravel. Las clases
dejan de existir, el autoload no las encuentra y PHPUnit aborta antes de
ejecutar una sola prueba.

**Lo engañoso es que no da ningún error al instalar.** Composer dice que todo
fue bien.

La ruta base del proyecto son 120 caracteres:

```
C:\tareas universitarios\TAREAS U\Tareas Univ\Ciclo 5\Taller de programacion Web\botica_san_juan\botica-san-juan-backend
```

Sumando `vendor\phpunit\phpunit\src\Event\Events\Test\TestDouble\` y el nombre
del archivo se pasa del límite efectivo de la API que usa el extractor, aunque
`LongPathsEnabled` esté a 1 en el registro.

---

## La solución: montar el proyecto en una unidad corta

```powershell
# Una sola vez por sesión de Windows
subst X: "C:\tareas universitarios\TAREAS U\Tareas Univ\Ciclo 5\Taller de programacion Web\botica_san_juan\botica-san-juan-backend"

cd X:\
composer install
php artisan test
```

Con `X:\` como base, la ruta más larga baja de ~230 a ~110 caracteres y la
extracción se completa entera. Verificado: **0 archivos truncados y 107 pruebas
en verde**.

Para soltar la unidad: `subst X: /D`

> El `subst` NO mueve nada: es la misma carpeta vista por otro nombre. Git,
> el IDE y el resto siguen funcionando sobre la ruta original.

---

## Qué NO funciona, y por qué conviene saberlo

| Intento | Resultado |
|---|---|
| Renombrar los `.ph` a `.php` | Arregla los de extensión truncada, pero no los cortados a mitad del nombre (`...CreatedSu`), que son irrecuperables sin el original. |
| `composer dump-autoload` | El mapa de clases se construye sobre lo que hay; si falta el archivo, no aparece. Hay que renombrar ANTES de regenerar. |
| `php artisan optimize:clear` | No tiene nada que ver: el fallo es de autoload de Composer, no de caché de Laravel. |
| `composer install --prefer-source` | Clona por git y sí escribe los nombres completos, pero tarda muchísimo (90 repositorios) y en las pruebas dejó la instalación incompleta. |
| `composer reinstall phpunit/phpunit --prefer-source` | Las banderas van ANTES del paquete. Con ese orden, Composer las ignora y clona la rama principal: deja PHPUnit 13 de desarrollo donde el lock pide 11.5.42. |

---

## Solución definitiva

Mover el repositorio a una ruta corta, por ejemplo `C:\dev\botica_san_juan`.
Mientras tanto, el `subst` resuelve el problema sin tocar nada.
