# Cómo trabajamos en Tribio

Guía para el equipo. Objetivo: que varias personas trabajen al mismo tiempo (plantillas, funcionalidades, correcciones) sin pisarse el código, sin cruzar migraciones y sin romper producción.

---

## Las 8 reglas de oro

1. **Nadie trabaja directo en `main` ni en `develop`.** Todo cambio va en una rama propia y entra por Pull Request (PR).
2. **Una tarea = un issue = una rama = una PR.** PRs pequeñas: idealmente se fusionan en 1–3 días.
3. **Tu `.env` apunta a TU base de datos local.** Nunca a la de producción. Nunca.
4. **Migraciones: solo se crean nuevas.** Una migración que ya está en `develop` o `main` no se edita, no se borra, no se renombra (el CI lo bloquea).
5. **Cada plantilla vive en su carpeta.** Lo que es de todos (checkout, layouts, CSS/JS globales, rutas) es *zona compartida* y necesita la aprobación del responsable.
6. **La tienda de Maetek (`templates/minimal-light`) está congelada.** No se toca sin autorización escrita del responsable.
7. **Solo se fusiona con el CI en verde y 1 aprobación.**
8. **Actualiza tu rama con `develop` todos los días** (así los conflictos son pequeños).

---

## Ramas

| Rama | Qué es | Quién escribe |
|---|---|---|
| `main` | Producción. Lo que ven los clientes. | Solo el responsable, con una PR desde `develop` |
| `develop` | Versión de pruebas (staging). Aquí se junta el trabajo de todos. | Solo vía PR |
| `tipo/NN-descripcion` | Tu tarea | Tú |

Nombres de rama (NN = número del issue):
- `plantilla/14-vibrant-fresh` — diseño o plantilla nueva
- `feat/21-cupones` — funcionalidad nueva
- `fix/22-precio-usd-carrito` — corrección

---

## El día a día

```bash
# 1. Empezar una tarea (siempre desde develop actualizado)
git checkout develop
git pull
git checkout -b feat/21-cupones

# 2. Trabajar y guardar en pasos pequeños
git status                       # revisa qué vas a subir: nunca .env ni archivos con claves
git add ruta/del/archivo.php
git commit -m "feat: cupones de descuento en el checkout"

# 3. Subir tu rama
git push -u origin feat/21-cupones
#    → en GitHub: "Compare & pull request", base: develop

# 4. Cada mañana: traer lo nuevo de develop a tu rama
git fetch origin
git merge origin/develop
php artisan migrate              # por si alguien agregó migraciones (en TU base local)
```

Si `git merge` avisa un conflicto: abre los archivos marcados, deja la versión correcta (a veces las dos partes), `git add` y `git commit`. Si el conflicto está en zona compartida y no estás seguro, pregunta antes.

---

## Pull Requests

- Base: **`develop`**. Llena la plantilla (checklist, migraciones, capturas).
- La revisa **otra persona del equipo**. Si toca la zona compartida, GitHub pide además al responsable (archivo `.github/CODEOWNERS`).
- El **CI** corre solo: tests, compilación del diseño y migraciones desde cero en MySQL. Si falla, se corrige en la misma rama.
- Se fusiona con **"Squash and merge"** y se borra la rama.
- Cambios de diseño: **capturas en computadora y en celular** (la mayoría de dueños de tienda usan el celular).

---

## Tu entorno local (una sola vez)

Necesitas PHP 8.2, Composer, Node 22 y MySQL (Laragon, XAMPP o Docker).

```bash
git clone https://github.com/TonyUA2023/tribio_ecomerce.git
cd tribio_ecomerce
cp .env.example .env             # ya apunta a 127.0.0.1 / tribio_final
composer install
npm ci
php artisan key:generate
# crea en tu MySQL una base vacía llamada tribio_final
php artisan migrate --seed       # tablas + tiendas y productos de ejemplo
php artisan storage:link
composer run dev                 # servidor + cola + Vite
```

Los usuarios de ejemplo están en `database/seeders/` (dueño de tienda de prueba, admin, etc.).

**Prohibido:**
- Poner en tu `.env` los datos de la base de producción. Un `php artisan migrate:fresh` con esa configuración **borra la tienda de todos los clientes**.
- Copiar la base de producción a tu computadora: tiene datos personales de compradores (Ley 29733). Usa los seeders.
- Subir `.env`, tokens, contraseñas o scripts con credenciales (como `swap.php`). El repositorio es visible para más personas de las que crees.

---

## Migraciones (para que no se crucen)

1. Créalas siempre con `php artisan make:migration nombre_descriptivo`.
2. **Nunca edites una migración ya fusionada.** Si algo quedó mal, crea otra que lo corrija.
3. Siempre con `down()`.
4. **Aditivas y compatibles:** columnas nuevas `nullable` o con `default`. Nada de renombrar o borrar columnas en la misma PR que cambia el código que las usa: primero se agrega lo nuevo, en otra PR se retira lo viejo.
5. **El código debe funcionar aunque la migración todavía no haya corrido** (en producción el código y la migración no llegan en el mismo segundo). Patrón usado en el proyecto: `Schema::hasTable(...)` o un servicio `...Schema::ready()`; ver `App\Services\Marketing\MarketingSchema`.
6. En la PR escribe **qué tablas toca**. Si dos personas necesitan la misma tabla a la vez, se coordina en el issue: se fusiona una primero y la otra actualiza su rama y ajusta.
7. **Solo el responsable** corre migraciones en staging y producción (o el deploy automático, cuando se active).
8. Los tests corren en SQLite en memoria; la migración `2026_09_11_182936_update_role_column_in_users_table` es solo MySQL y los tests la saltan (ver `migrateFreshUsing()` en cualquier test).

---

## Plantillas y diseño (para que el diseño no se cruce)

- **Plantilla nueva:** copia `resources/views/templates/soft-market/` a `resources/views/templates/{tu-plantilla}/` y regístrala en `config/storefront.php` con `'status' => 'development'`. Solo pasa a `available` después de la revisión.
- **Tu carpeta es tuya.** No edites la carpeta de plantilla de otra persona; si necesitas algo de ella, pídeselo o conviértelo en componente compartido (PR aparte).
- **Reutiliza, no copies**, los componentes comunes: `components.checkout.drawer`, `components.checkout.customer-modal`, `components.storefront.reviews`, `components.storefront.rating-badge` y, antes de `</head>`, en una línea sin sangría: `@include('components.marketing.head')`.
- **CSS propio de tu plantilla** dentro de tu plantilla (con clases o variables con prefijo propio), no en `resources/css/app.css`, que es de todos.
- **Tailwind:** una clase nueva con valor arbitrario (p. ej. `bg-[#7DA268]`) no aparece hasta correr `npm run build`.
- **Maetek (`minimal-light`) y `clientes_custom/`** son tiendas reales de clientes: congeladas.
- **Dashboard:** sin pestañas, pensado primero para celular, barra de guardar fija arriba, colores suaves (`.glass-card`, `.badge-*`). Ver la nota *Dashboard-UI-Design-System* del vault.

---

## Zona compartida

Afecta a todas las tiendas a la vez. Cualquier cambio aquí: PR pequeña y separada, con explicación, y aprobación del responsable (lo exige `CODEOWNERS`).

- Checkout y pagos: `StoreController`, `PendingCheckout`, `Order`, `app/Services/Checkout`, `app/Services/Payments`, `resources/views/components/checkout/`
- `database/migrations/`, `routes/`, `config/`, `bootstrap/`
- `resources/views/layouts/`, `resources/views/components/storefront/`, `resources/views/components/marketing/`
- `resources/css/`, `resources/js/`
- `Dockerfile`, `docker-entrypoint.sh`, `.github/`

---

## Documentación (vault `tribio_brain`)

Es otro repositorio (`tribio_brain`). Cuando termines una funcionalidad, actualiza su nota y agrega tu entrada al final de `log.md` (solo al final, nunca editar entradas viejas). El vault tiene `log.md` configurado para que dos personas que agregan entradas a la vez no generen conflicto.

---

## Si trabajas con Claude Code u otra IA

- **Una sesión = una rama.** Nunca dos sesiones (ni dos personas) modificando la misma carpeta sin hacer commit: los cambios se mezclan y nadie sabe qué es de quién. Para varias sesiones en la misma computadora usa `git worktree` (o la opción *worktree* de Claude Code).
- La IA lee `CLAUDE.md` y el vault; tú **revisas el diff** antes de hacer commit.
- La IA no fusiona PRs, no hace `push` a `develop`/`main` y no corre migraciones en producción.

---

## Despliegue

- `develop` → **staging** (copia de Tribio con su propia base de datos de ejemplo). Aquí el dueño revisa los diseños antes de que salgan.
- `main` → **producción**. El responsable abre la PR `develop → main` (por ejemplo, una vez por semana) cuando staging está bien.
- Orden en producción: **copia de seguridad de la base → migraciones → despliegue**.

---

## Configuración inicial de GitHub (una sola vez, la hace el responsable)

1. Crear `develop`: `git checkout main && git pull && git checkout -b develop && git push -u origin develop`.
   En GitHub: *Settings → General → Default branch* = `develop`.
2. *Settings → Collaborators* → invitar a cada programador (rol **Write**).
3. *Settings → Rules → Rulesets → New branch ruleset* para `main` y `develop`:
   - Require a pull request before merging → 1 aprobación, *Require review from Code Owners*
   - Require status checks to pass → `Tests` y `Migraciones`
   - Block force pushes y restrict deletions
4. *Settings → General → Pull Requests*: dejar solo **Allow squash merging** y activar **Automatically delete head branches**.
5. *Issues*: crear etiquetas por área (`plantilla`, `checkout`, `dashboard`, `móvil`, `migración`, `zona-compartida`) y un tablero en *Projects* con columnas **Por hacer / En curso / En revisión / Listo**.
6. En `.github/CODEOWNERS`, reemplazar `@TonyUA2023` por el usuario de quien revisará la zona compartida.
