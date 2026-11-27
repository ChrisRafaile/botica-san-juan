# Avatar jerárquico y OpenAPI 0.9.0 · QA en navegador

Medido el 2026-11-24 en local con Chrome (`playwright-core`), cuentas demo.

| Comprobación | Cliente | Admin |
|---|---|---|
| Nivel 1: foto subida, tras recargar (256 px, WebP desde la base) | Sí | Sí |
| Nivel 2: Google, `no-referrer` y petición sin `Referer` | Sí | Sí |
| Google responde 403: cae a iniciales | Sí | Sí |
| Nivel 3: SVG con iniciales | Sí | Sí |
| «Quitar foto» vuelve al nivel correspondiente | Sí | Sí |
| Errores de consola | 0 | 0 |
| Desborde horizontal a 360 px | No | No |

Scalar (`/docs/v1`) y Stoplight (`/docs/api`): muestran **0.9.0**, sin `VR002` y
sin errores de consola.

**Límite:** el nivel 2 se simuló interceptando la imagen y la respuesta de
`/api/user`; no se inició sesión con una cuenta real de Google. El mismo día se
verificó en producción con una sesión real: `../informe-final/22-avatar-google-oauth-prod.png`
y `23-avatar-persistente-prod.png`.

**Reproducir:** API en 8083, portal en 5173, `setInputFiles` en el perfil,
recargar y leer `data-nivel` en `[data-testid=avatar-usuario]`.
