# Rúbrica

App para que maestras registren proyectos, los evalúen con rúbricas ponderadas y descarguen todo en Excel.

## Cómo funciona

- **Maestra → Grupo (máx. 2: matutino y vespertino) → Alumnos / Proyectos → Aspectos → Calificaciones.**
  Cada maestra solo ve sus grupos. El selector de turno de la barra superior cambia de grupo.
- Cada grupo guarda su **centro de trabajo**: escuela, CCT, zona escolar, grado, grupo y ciclo.
- **Lista de alumnos desde Excel**: columnas `N.L.` y `Nombre` (o apellidos en columnas separadas). Hay plantilla.
  Volver a subirla no duplica: solo actualiza números de lista. Nombres en MAYÚSCULAS se pasan a nombre propio.
- **Captura tipo punto de venta**: proyectos y aspectos en tarjetas; al tocar una calificación se abre un teclado
  0–10 que guarda con un tap y salta al siguiente alumno. Decimales con la tecla `0,0`.
- **Buscador** por apellido, nombre o número de lista (sin importar acentos). Enter abre el primer resultado.
- Modo claro / oscuro.
- Cada proyecto tiene uno o varios **aspectos** con un **peso %** (deben sumar 100%).
- Cada aspecto se califica de **0 a 10**. Aporta `calificación ÷ 10 × peso`. Ej.: un 8 en un aspecto de 20% = 16%.
  La suma es el % final; ÷ 10 = calificación final (0–10).
- **Vacío ≠ 0**: una celda vacía es *pendiente*; un 0 es una calificación.
- Controles contra olvidos: contador de pendientes por grupo, proyecto, aspecto y alumno; filtro "solo pendientes";
  nota final en gris mientras esté incompleta; Excel con celdas amarillas y hoja **Pendientes**.
- **Modo captura**: un aspecto a la vez, lista de alumnos con teclado numérico; Enter salta al siguiente. Se guarda solo.
- Alumnos dados de **baja** conservan sus calificaciones y dejan de contar como pendientes.

## Excel

- Por grupo: hoja *Resumen* (final de cada proyecto + promedio), hoja *Pendientes* y una hoja por proyecto.
- Las notas finales son **fórmulas**: si se corrige una calificación dentro de Excel, el total se recalcula.

## Desarrollo local

Requiere PHP 8.3+, Composer, MySQL 5.7+/MariaDB y Node (solo para recompilar estilos).

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed   # crea la maestra demo: demo@rubrica.test / demo1234
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
