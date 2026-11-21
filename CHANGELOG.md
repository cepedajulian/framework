# Historial de cambios

## 1.0.2 – Compatibilidad de ApiFree

- Reenvío automático de la sesión PHP activa a la misma instancia de la aplicación mediante HTTPS, sin reenviarla a hosts externos.
- Lista de hosts adicionales explícitamente permitidos para entornos multisitio (`sessionHosts`).
- Desbloqueo y reapertura de sesión durante peticiones HTTP internas.
- Soporte de body sin alterar para strings y arrays en modo `legacy`, con modos adicionales `json` y `form`.
- Respuesta disponible como JSON decodificado (`response`) y sin modificar (`rawBody`).
- Recuperación de estado (`status`) y cabeceras (`responseHeaders`), con reproducción limitada y segura de estado y cabeceras (`forwardResponse`).
- Se mantienen constructor y método `exec()` con los mismos tres parámetros y en idéntico orden.
- Tests de regresión sin servicios de red: `php tests/apifree_compat.php`.

**Limitaciones:** sesión PHP nativa y mismo almacenamiento de sesiones; destinos HTTPS; `ApiFree` continúa lanzando excepciones por errores de transporte, y la opción de reenviar el estado HTTP puede deshabilitarse con `forwardResponse = false`.
