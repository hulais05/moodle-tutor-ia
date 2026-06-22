# Recomendación de solución según escala y presupuesto

> No hay una única "IA para Moodle" correcta: depende del tamaño de la institución, el
> presupuesto y la sensibilidad de los datos. Esta guía resume el criterio.

## Las tres opciones

| | **Local (Ollama)** | **Nube económica** | **Nube premium** |
|---|---|---|---|
| Ejemplo | llama3 / mistral | OpenAI gpt-4o-mini, Gemini Flash | OpenAI o3, Claude Opus |
| Costo | $0 (hardware propio) | Bajo (por uso) | Alto (por uso) |
| Privacidad | Máxima (no sale nada) | Datos salen a un tercero | Datos salen a un tercero |
| Calidad de respuesta | Buena | Muy buena | Excelente |
| Mantenimiento | Requiere servidor con GPU/CPU | Mínimo | Mínimo |
| Internet | No necesita | Necesita | Necesita |

## Cuándo conviene cada una

### Local (Ollama) — lo de este proyecto
- Institución con **pocos a medianos** volúmenes y un servidor disponible.
- **Datos sensibles** que no pueden salir (salud, menores, datos personales → ARCA/RGPD).
- Presupuesto operativo ≈ 0; se invierte una vez en hardware.
- **Riesgo:** la calidad depende del modelo y del hardware; respuestas más lentas.

### Nube económica
- Institución con **muchos** alumnos y picos de uso.
- Sin servidor propio ni ganas de mantenerlo.
- Datos no críticos. Costo bajo y predecible por volumen.

### Nube premium
- Cuando la **calidad** de la respuesta es el diferencial comercial (p. ej. un producto
  educativo que se vende "con tutor de IA de primer nivel").
- Presupuesto holgado.

## Regla práctica

> **Empezar local para validar** (costo cero, sin riesgo), y **migrar a nube** solo cuando
> el volumen o la exigencia de calidad lo justifiquen. La arquitectura de Moodle permite
> cambiar el proveedor **sin tocar el resto** — es solo dar de alta otro provider.

## Híbrido (avanzado)

Moodle permite **varios providers** y ordenarlos. Se puede usar local como predeterminado y
la nube como *fallback* para tareas que el modelo local no resuelve bien. Así se optimiza
costo y calidad a la vez.
