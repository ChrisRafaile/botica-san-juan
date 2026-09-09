# Evaluación de pnpm como gestor de dependencias

Documento de decisión técnica. Evalúa si conviene migrar el Frontend de npm a
pnpm, con mediciones tomadas sobre el proyecto real y no sobre casos genéricos.

**Decisión vigente:** el APF1 se entrega con npm. La migración a pnpm queda
planificada para el APF2, condicionada al criterio de la sección 6.

---

## 1. Punto de partida

El repositorio contenía `package-lock.json` (npm) y `pnpm-lock.yaml` (pnpm)
conviviendo, con el segundo desactualizado. La integración continua declaraba
pnpm pero solicitaba su caché antes de habilitar Corepack, por lo que el runner
nunca encontraba el ejecutable y **el job fallaba en todas sus ejecuciones**.

El conflicto se resolvió unificando el proyecto en npm y retirando el lockfile
huérfano. Mantener dos gestores en paralelo produce árboles de dependencias
distintos entre equipos y entornos, y es la causa de fallos difíciles de
reproducir.

## 2. Dimensión real del proyecto

| Métrica | Valor |
|---|---:|
| Dependencias de producción | 11 |
| Dependencias de desarrollo | 27 |
| Paquetes resueltos | 275 |
| Archivos en `node_modules` | 34 771 |
| Tamaño en disco | 0,36 GB |
| Paquetes en el espacio de trabajo | 1 |

## 3. Mediciones de instalación

Tomadas sobre este proyecto, en la misma máquina y con la misma conexión.

| Escenario | Tiempo |
|---|---:|
| `npm ci` con caché tibia | 7,7 s |
| `pnpm install` sin almacén previo | 12,0 s |
| `pnpm install` con almacén tibio | 2,9 s |

pnpm resulta **2,6 veces más rápido** en instalación tibia, con un ahorro
absoluto de 4,8 segundos. En la primera instalación es más lento, porque debe
poblar su almacén global.

## 4. Compatibilidad verificada

`pnpm install` resolvió las 275 dependencias y generó su lockfile sin conflictos.
No se detectaron paquetes que dependan del aplanamiento de `node_modules`, que es
el motivo habitual de incompatibilidad con pnpm por su estructura estricta
basada en enlaces simbólicos.

Las dependencias del proyecto son de uso extendido y con soporte pleno en pnpm:
Vue 3, Vite, Pinia, Vue Router, TypeScript, Axios, Leaflet, SheetJS y Vitest.

> Nota sobre las pruebas locales: al ejecutar `pnpm build` en el entorno de
> desarrollo Windows, los binarios de `node_modules/.bin` no se resolvieron.
> **El mismo fallo se reproduce con npm**, por lo que corresponde a una
> particularidad del PATH de esa máquina y no a una incompatibilidad de pnpm.
> La integración continua compila correctamente con npm: 2 233 módulos
> transformados.

## 5. Ventajas y costos

**A favor de pnpm**

- Instalación más rápida cuando el almacén está poblado.
- Ahorro de disco creciente: los paquetes se guardan una vez y se enlazan, en
  lugar de duplicarse por proyecto.
- `node_modules` estricto: impide importar paquetes no declarados en
  `package.json`, lo que evita dependencias fantasma.
- Gestión nativa de espacios de trabajo, útil si el repositorio evoluciona a
  monorepo.

**En contra, en el estado actual**

- El ahorro absoluto es de segundos: el proyecto tiene un solo paquete.
- El beneficio de disco se materializa con varios proyectos compartiendo
  almacén, no con uno.
- Introduce un requisito adicional en el entorno de cualquier persona que
  trabaje el proyecto.
- Migrar a dos días de una entrega comprometida aporta riesgo sin beneficio
  proporcional.

## 6. Criterio de decisión para el APF2

La migración se justifica cuando se cumpla al menos una condición:

1. El repositorio incorpore un segundo paquete Node y pase a ser monorepo, por
   ejemplo al empaquetar la aplicación de escritorio con Tauri.
2. Las dependencias superen aproximadamente los 600 paquetes resueltos.
3. Los tiempos de instalación en integración continua se vuelvan un cuello de
   botella medible.

Si ninguna se cumple, permanecer en npm es la decisión correcta: es el gestor
que acompaña a Node, no requiere instalación adicional y su rendimiento actual
es suficiente.

## 7. Procedimiento de migración

Estimación: **15 a 20 minutos**, sin afectar el código de la aplicación. Solo
cambian el gestor y la configuración del pipeline.

```bash
# 1. Rama dedicada
git checkout -b chore/migracion-pnpm

# 2. Estado limpio
cd botica-san-juan-frontend
rm -rf node_modules package-lock.json

# 3. Generar el lockfile de pnpm
pnpm install

# 4. Validar que la compilación y las pruebas siguen correctas
pnpm build
pnpm test

# 5. Versionar el nuevo lockfile
git add pnpm-lock.yaml
git rm --cached package-lock.json
```

Ajuste del pipeline en `.github/workflows/ci-cd.yml`. El orden es determinante:
**pnpm debe instalarse antes de que `setup-node` solicite su caché**, que fue
exactamente el error que mantenía la integración continua en rojo.

```yaml
      - name: Instalar pnpm
        uses: pnpm/action-setup@v4
        with:
          version: 10

      - name: Configurar Node
        uses: actions/setup-node@v4
        with:
          node-version: '22'
          cache: pnpm
          cache-dependency-path: botica-san-juan-frontend/pnpm-lock.yaml

      - name: Instalar dependencias
        run: pnpm install --frozen-lockfile

      - name: Verificar tipos y compilar
        run: pnpm build
```

Conviene además fijar el gestor en `package.json`, de modo que Corepack impida
usar otro por error:

```json
"packageManager": "pnpm@10.0.0"
```

Finalmente, retirar `pnpm-lock.yaml` y `yarn.lock` del `.gitignore` raíz, donde
se añadieron para evitar la mezcla de gestores, y excluir en su lugar
`package-lock.json`.

## 8. Verificación posterior

- La integración continua debe quedar en verde en los tres jobs.
- `pnpm build` debe generar `dist/` con el mismo número de módulos transformados.
- Ninguna importación debe romperse por dependencias fantasma. Si alguna falla,
  la corrección es declararla explícitamente en `package.json`, que es
  precisamente el problema que pnpm expone.

---

*Elaboración propia. Mediciones tomadas el 9 de septiembre de 2026 sobre el
repositorio del proyecto.*
