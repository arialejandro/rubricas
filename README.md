# Rúbrica

Evaluación trimestral de primaria (NEM) para maestras: campos formativos, proyectos transversales
con instrumentos y criterios por nivel de logro, y exportación a Excel.

## Modelo de evaluación

```
Grupo (turno) → Trimestre 1–3 → 4 campos formativos
   Lenguajes · Saberes y Pensamiento Científico · Ética, Naturaleza y Sociedades · De lo Humano y lo Comunitario
   └─ aspectos del campo (texto libre, con %; 3 a 6, suman 100%) — cambian cada trimestre
        · tipo "De los proyectos" → promedio de los productos evaluados en ese campo
        · tipo "Captura directa"  → 0–10 por alumno (Examen, Tareas…)
   └─ materias adicionales (Artes, Inglés en Lenguajes; Educación Física en De lo Humano)
Proyecto (trimestre, campo donde se plantea, 1–4 PDA)
   └─ productos: cada uno se evalúa en SU campo (transversalidad) con un instrumento
        └─ criterios a observar con el descriptor de cada nivel (de la rúbrica)
```

**Niveles de logro** (un criterio se califica eligiendo nivel): Logrado **10** · Satisfactorio **9** ·
En proceso **8 o 7** · Requiere apoyo **6**. Colores de verde (mejor) a rojo (peor). Un promedio se
clasifica al nivel más cercano (≥9.5 Logrado, ≥8.5 Satisfactorio, ≥6.5 En proceso).

**Cálculo de un campo** (`App\Support\TermBook`):
1. Producto = promedio de sus criterios.
2. Aspecto "proyectos" = promedio de los productos de ese campo (de cualquier proyecto).
3. Base = Σ aspecto × %. Con pendientes se reparte entre lo que ya hay y se marca *provisional*.
4. Final = promedio en partes iguales de la base y las materias adicionales del campo.

**Vacío ≠ 0**: una celda vacía es *pendiente*. Pendientes por campo, aspecto, criterio, alumno y grupo.

## Uso

- Hasta 2 grupos por maestra (matutino y vespertino); selector de turno y de trimestre siempre a la mano.
- Centro de trabajo por grupo: escuela, CCT, zona escolar, grado, grupo, ciclo.
- Lista de alumnos desde Excel (`N.L.` + `Nombre`, o apellidos separados). Hay plantilla; no duplica.
- Captura tipo punto de venta: tarjetas → lista con buscador → teclado (0–10 o niveles con el descriptor
  de la rúbrica). Un tap guarda y salta al siguiente alumno.
- Modo claro / oscuro.

## Excel (por trimestre)

*Resumen* (final por campo + promedio y nivel) · una hoja por **campo** (aspectos, base, materias, final y
la matriz de niveles: el valor cae en la columna de su nivel) · una hoja por **proyecto** (encabezado con
campo y PDA; producto → criterio → 4 columnas de nivel) · *Instrumentos* (descriptores) · *Pendientes*.

## Desarrollo local

Requiere PHP 8.3+, Composer, MySQL 5.7+/MariaDB y Node (solo para recompilar estilos).

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed   # crea la maestra demo con datos NEM: demo@rubrica.test / demo1234
php -S 127.0.0.1:8130 -t public server.php
```

Tests (usan la BD `rubrica_test`): `php artisan test`

## Producción

`public/build` va en el repo, así que el servidor **no necesita Node**:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

El document root del dominio debe apuntar a `public/`. Con `RUBRICA_REGISTRATION=false` se cierra el registro público.

### Plesk

1. **PHP 8.3+** en *Sitios web y dominios → Configuración de PHP*.
2. **Base de datos**: *Bases de datos → Agregar base de datos* (MySQL/MariaDB). Anota nombre, usuario y contraseña.
3. **Código**: *Laravel Toolkit* (o la extensión *Git*) apuntando a `https://github.com/arialejandro/rubricas.git`,
   rama `main`. El Toolkit ajusta solo el document root a `public/`; con Git, cámbialo a mano en
   *Configuración de alojamiento → Raíz del documento* → `httpdocs/public`.
4. **`.env`**: copia `.env.example` a `.env` y llena `APP_URL` y `DB_*` (`DB_HOST=localhost` en Plesk).
5. **Comandos** (Laravel Toolkit → Artisan / Composer, o SSH):
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan key:generate
   php artisan migrate --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
   ⛔ **Nunca `--seed` en el servidor** (crea la cuenta demo con contraseña conocida).
6. **Cuenta de la maestra** (no hay correo; la contraseña se genera y se muestra una vez):
   ```bash
   php artisan maestra:cuenta correo@escuela.mx --nombre="Nombre de la maestra"
   ```
   El mismo comando sin `--nombre` le pone **contraseña nueva** a una cuenta existente.
7. **SSL**: *SSL/TLS → Let's Encrypt*. **Respaldo** diario: *Copias de seguridad → Programar*.

**Actualizar** después de un cambio: *Laravel Toolkit → Deploy* (o `git pull`), luego
`composer install --no-dev`, `php artisan migrate --force` y los tres `:cache`.
